<?php

namespace App\Services;

use App\Repositories\WifiRepository;
use App\Services\Rede\RedeDrivers;

/**
 * Núcleo do Captive Portal: quem pode entrar, com quais regras, e o que
 * acontece em cada passo (login, cadastro, liberação, sessão, e-mails).
 * As regras vêm do perfil (wifi_perfis) com as exceções da pessoa por cima
 * (wifi_pessoas.regras) -- nada fixo aqui. Quem libera o aparelho de fato é
 * o driver do equipamento (Rede\RedeDrivers::controladorPortal()).
 */
class WifiPortalService
{
    /** Chaves de regra que uma pessoa pode sobrepor ao perfil. */
    public const REGRAS_SOBREPONIVEIS = ['validade_dias', 'sessao_minutos', 'banda_down_kbps', 'banda_up_kbps', 'cota_mb', 'max_aparelhos', 'lembrar_dias', 'horarios', 'filtro'];

    private WifiRepository $repo;
    private WifiConfigService $config;

    public function __construct()
    {
        $this->repo = new WifiRepository();
        $this->config = new WifiConfigService();
    }

    public function repositorio(): WifiRepository
    {
        return $this->repo;
    }

    public static function normalizarMac(?string $mac): ?string
    {
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string)$mac));

        return strlen($hex) === 12 ? implode(':', str_split($hex, 2)) : null;
    }

    // ================================================================ regras

    /** Regras efetivas: perfil + exceções da pessoa (horarios/filtro como array). */
    public function regras(array $perfil, ?array $pessoa = null): array
    {
        $r = [];
        foreach (self::REGRAS_SOBREPONIVEIS as $k) {
            $r[$k] = $perfil[$k] ?? null;
        }
        $excecoes = $pessoa ? (json_decode((string)($pessoa['regras'] ?? ''), true) ?: []) : [];
        foreach ($excecoes as $k => $v) {
            if (in_array($k, self::REGRAS_SOBREPONIVEIS, true) && $v !== null && $v !== '') {
                $r[$k] = $v;
            }
        }
        foreach (['horarios', 'filtro'] as $k) {
            if (!is_array($r[$k])) {
                $r[$k] = json_decode((string)($r[$k] ?? ''), true) ?: null;
            }
        }
        $r['filtro'] ??= [];

        return $r;
    }

    /** null = pode usar agora; string = motivo. */
    public function foraDoHorario(?array $horarios): ?string
    {
        if (!$horarios || empty($horarios['dias'])) {
            return null;
        }
        $dia = (int)date('N'); // 1=seg ... 7=dom
        $hora = date('H:i');
        $dentro = in_array($dia, array_map('intval', $horarios['dias']), true)
            && (empty($horarios['inicio']) || $hora >= $horarios['inicio'])
            && (empty($horarios['fim']) || $hora <= $horarios['fim']);
        if ($dentro) {
            return null;
        }
        $nomes = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sáb', 7 => 'dom'];

        return 'Acesso permitido só ' . implode(', ', array_map(fn ($d) => $nomes[(int)$d] ?? $d, $horarios['dias']))
            . (!empty($horarios['inicio']) ? " das {$horarios['inicio']} às {$horarios['fim']}" : '') . '.';
    }

    private function emailNoDominio(string $email, ?string $dominios): bool
    {
        $lista = array_filter(array_map(fn ($d) => strtolower(trim(ltrim($d, '@'))), preg_split('/[\s,;]+/', (string)$dominios)));
        if (!$lista) {
            return true;
        }

        return in_array(strtolower(substr(strrchr($email, '@') ?: '', 1)), $lista, true);
    }

    // ================================================================ proteção

    public function bloqueadoPorTentativas(string $chave): bool
    {
        return $this->repo->falhasRecentes($chave, max(1, $this->config->int('tentativas_janela_min'))) >= max(1, $this->config->int('tentativas_max'));
    }

    // ================================================================ portal: login

    /**
     * @param array $ctx ['mac','ip','ap','ssid','url']
     * @return array{success: bool, message?: string, etapa?: string, pessoa_id?: int}
     *   etapa: 'termos' | 'trocar_senha' | 'aparelhos' | 'conectado'
     */
    public function login(string $email, string $senha, array $ctx, bool $lembrar): array
    {
        $email = mb_strtolower(trim($email));
        $chave = 'login:' . $email;
        $chaveMac = 'mac:' . ($ctx['mac'] ?? '');
        if ($this->bloqueadoPorTentativas($chave) || $this->bloqueadoPorTentativas($chaveMac)) {
            return ['success' => false, 'message' => 'Muitas tentativas erradas. Aguarde ' . $this->config->int('tentativas_janela_min') . ' minutos e tente de novo.'];
        }
        $pessoa = $email !== '' ? $this->repo->pessoaPorEmail($email) : null;
        if (!$pessoa || empty($pessoa['senha_hash']) || !password_verify($senha, $pessoa['senha_hash'])) {
            $this->repo->registrarTentativa($chave, false);
            $this->repo->registrarTentativa($chaveMac, false);

            return ['success' => false, 'message' => 'E-mail ou senha incorretos.'];
        }
        $this->repo->registrarTentativa($chave, true);

        return $this->continuarAcesso($pessoa, $ctx, $lembrar);
    }

    /** Confere situação, termos, senha provisória e aparelhos; se tudo certo, libera. */
    public function continuarAcesso(array $pessoa, array $ctx, bool $lembrar = false): array
    {
        $pessoa = $this->repo->pessoa((int)$pessoa['id']);
        $perfil = $this->repo->perfil((int)$pessoa['perfil_id']);
        if (!$perfil || !$perfil['ativo']) {
            return ['success' => false, 'message' => 'Este tipo de acesso está desativado no momento.'];
        }
        $situacao = $this->situacaoBloqueante($pessoa);
        if ($situacao) {
            return ['success' => false, 'message' => $situacao];
        }
        $regras = $this->regras($perfil, $pessoa);
        if ($motivo = $this->foraDoHorario($regras['horarios'])) {
            return ['success' => false, 'message' => $motivo];
        }
        $termo = $this->repo->termoAtual((int)$perfil['id']);
        if ($termo && !$this->repo->aceitouTermo((int)$pessoa['id'], (int)$termo['id'])) {
            return ['success' => true, 'etapa' => 'termos', 'pessoa_id' => (int)$pessoa['id']];
        }
        if (!empty($pessoa['trocar_senha'])) {
            return ['success' => true, 'etapa' => 'trocar_senha', 'pessoa_id' => (int)$pessoa['id']];
        }
        $mac = $ctx['mac'] ?? null;
        if (!$mac) {
            return ['success' => false, 'message' => 'Não identificamos o aparelho. Desconecte e conecte de novo no Wi-Fi.'];
        }
        $aparelho = $this->repo->aparelhoPorMac($mac);
        if ($aparelho && (int)$aparelho['pessoa_id'] === (int)$pessoa['id'] && $aparelho['bloqueado']) {
            return ['success' => false, 'message' => 'Este aparelho foi bloqueado. Procure a TI.'];
        }
        $ativas = array_filter($this->repo->sessoesAtivas((int)$pessoa['id']), fn ($s) => $s['mac'] !== $mac);
        if (count($ativas) >= max(1, (int)$regras['max_aparelhos'])) {
            return ['success' => true, 'etapa' => 'aparelhos', 'pessoa_id' => (int)$pessoa['id']];
        }

        return $this->liberarAparelho($pessoa, $perfil, $regras, $ctx, $lembrar);
    }

    private function situacaoBloqueante(array $pessoa): ?string
    {
        return match ($pessoa['status']) {
            'pendente' => 'Seu cadastro está aguardando liberação. Você recebe um e-mail assim que for liberado.',
            'recusado' => 'Seu cadastro não foi liberado' . ($pessoa['motivo'] ? ': ' . $pessoa['motivo'] : '.'),
            'bloqueado' => 'Seu acesso está bloqueado' . ($pessoa['motivo'] ? ': ' . $pessoa['motivo'] : '.') . ' Procure a TI.',
            'expirado' => 'Seu acesso venceu. Procure quem te recebeu ou a TI para renovar.',
            'anonimizado' => 'Cadastro inexistente.',
            default => (!empty($pessoa['valido_ate']) && strtotime($pessoa['valido_ate']) < time()) ? 'Seu acesso venceu. Procure quem te recebeu ou a TI para renovar.' : null,
        };
    }

    /** Libera o aparelho no equipamento, abre a sessão e atualiza o filtro. */
    public function liberarAparelho(array $pessoa, array $perfil, array $regras, array $ctx, bool $lembrar): array
    {
        $controlador = RedeDrivers::controladorPortal();
        if (!$controlador) {
            return ['success' => false, 'message' => 'O Wi-Fi não está conectado ao sistema. Avise a TI.'];
        }
        $minutos = max(1, (int)$regras['sessao_minutos']);
        // Nunca libera além da validade da conta.
        if (!empty($pessoa['valido_ate'])) {
            $minutos = max(1, min($minutos, (int)floor((strtotime($pessoa['valido_ate']) - time()) / 60)));
        }
        $r = $controlador->autorizar($ctx['mac'], $minutos, $regras['banda_down_kbps'] ? (int)$regras['banda_down_kbps'] : null,
            $regras['banda_up_kbps'] ? (int)$regras['banda_up_kbps'] : null, $regras['cota_mb'] ? (int)$regras['cota_mb'] : null, $ctx['ap'] ?? null);
        if (!$r['success']) {
            return ['success' => false, 'message' => 'Não foi possível liberar o acesso agora. Tente de novo em instantes. (' . $r['message'] . ')'];
        }
        $existente = $this->repo->sessaoAtivaPorMac($ctx['mac']);
        if ($existente) {
            $this->repo->encerrarSessao((int)$existente['id'], 'novo login');
        }
        $this->repo->abrirSessao([
            'pessoa_id' => (int)$pessoa['id'], 'perfil_id' => (int)$perfil['id'], 'mac' => $ctx['mac'], 'ip' => $ctx['ip'] ?? null,
            'ap_mac' => $ctx['ap'] ?? null, 'ssid' => $ctx['ssid'] ?? null, 'fim_previsto' => date('Y-m-d H:i:s', time() + $minutos * 60),
        ]);
        $lembrarDias = (int)$regras['lembrar_dias'];
        $this->repo->registrarAparelho((int)$pessoa['id'], $ctx['mac'], $lembrar && $lembrarDias > 0 ? date('Y-m-d H:i:s', time() + $lembrarDias * 86400) : null);
        $this->repo->atualizarPessoa((int)$pessoa['id'], ['ultimo_acesso' => date('Y-m-d H:i:s')]);
        $this->sincronizarFiltro();

        return ['success' => true, 'etapa' => 'conectado', 'pessoa_id' => (int)$pessoa['id'], 'minutos' => $minutos];
    }

    /** Aparelho lembrado (colaborador marcou "lembrar") entra sem digitar nada. */
    public function acessoPorAparelhoLembrado(array $ctx): ?array
    {
        $mac = $ctx['mac'] ?? null;
        $ap = $mac ? $this->repo->aparelhoPorMac($mac) : null;
        if (!$ap || $ap['bloqueado'] || empty($ap['lembrado_ate']) || strtotime($ap['lembrado_ate']) < time() || $ap['pessoa_status'] !== 'ativo') {
            return null;
        }
        $pessoa = $this->repo->pessoa((int)$ap['pessoa_id']);
        $r = $this->continuarAcesso($pessoa, $ctx, true);

        return ($r['etapa'] ?? '') === 'conectado' ? $r : null;
    }

    public function aceitarTermos(int $pessoaId, array $ctx): array
    {
        $pessoa = $this->repo->pessoa($pessoaId);
        $termo = $pessoa ? $this->repo->termoAtual((int)$pessoa['perfil_id']) : null;
        if (!$pessoa || !$termo) {
            return ['success' => false, 'message' => 'Cadastro não encontrado.'];
        }
        $this->repo->registrarAceite($pessoaId, $termo, $ctx['ip'] ?? null, $ctx['mac'] ?? null);

        return $this->continuarAcesso($pessoa, $ctx, (bool)($ctx['lembrar'] ?? false));
    }

    public function trocarSenha(int $pessoaId, string $senha, string $confirmacao, array $ctx): array
    {
        if ($senha !== $confirmacao) {
            return ['success' => false, 'message' => 'As senhas não conferem.'];
        }
        $erro = $this->validarSenha($senha);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }
        $this->repo->atualizarPessoa($pessoaId, ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'trocar_senha' => 0]);

        return $this->continuarAcesso($this->repo->pessoa($pessoaId), $ctx, (bool)($ctx['lembrar'] ?? false));
    }

    public function validarSenha(string $senha): ?string
    {
        if (mb_strlen($senha) < 8) {
            return 'A senha precisa ter pelo menos 8 caracteres.';
        }
        if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
            return 'Use letras e números na senha.';
        }

        return null;
    }

    /** Desconecta um aparelho da própria pessoa (tela de limite de aparelhos). */
    public function desconectarMeuAparelho(int $pessoaId, int $sessaoId): array
    {
        $s = $this->repo->sessao($sessaoId);
        if (!$s || (int)$s['pessoa_id'] !== $pessoaId) {
            return ['success' => false, 'message' => 'Sessão não encontrada.'];
        }

        return $this->derrubarSessao($sessaoId, 'desconectado pela pessoa');
    }

    // ================================================================ portal: cadastro

    /**
     * Autocadastro pelo portal. Perfil 'cadastro' (visitante) sem liberação já
     * entra; os demais ficam pendentes ou recebem a senha por e-mail.
     */
    public function cadastrar(int $perfilId, array $dados, array $ctx): array
    {
        $perfil = $this->repo->perfil($perfilId);
        if (!$perfil || !$perfil['ativo'] || !$perfil['autocadastro']) {
            return ['success' => false, 'message' => 'Este tipo de cadastro não está disponível.'];
        }
        if ($this->bloqueadoPorTentativas('cadastro:' . ($ctx['mac'] ?? $ctx['ip'] ?? ''))) {
            return ['success' => false, 'message' => 'Muitos cadastros seguidos deste aparelho. Aguarde alguns minutos.'];
        }
        $nome = trim((string)($dados['nome'] ?? ''));
        $email = mb_strtolower(trim((string)($dados['email'] ?? '')));
        $telefone = preg_replace('/\D/', '', (string)($dados['telefone'] ?? ''));
        if (mb_strlen($nome) < 3) {
            return ['success' => false, 'message' => 'Informe seu nome completo.'];
        }
        $senhaPorEmail = $perfil['tipo_acesso'] === 'senha';
        if ($senhaPorEmail && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Informe um e-mail válido -- é por ele que você recebe a senha.'];
        }
        if (!$senhaPorEmail && $email === '' && $telefone === '') {
            return ['success' => false, 'message' => 'Informe um e-mail ou celular.'];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'E-mail inválido.'];
        }
        if ($email !== '' && !$this->emailNoDominio($email, $perfil['dominios_email'])) {
            return ['success' => false, 'message' => 'Este tipo de cadastro só aceita e-mails da empresa (' . trim(str_replace(["\r", "\n"], ' ', (string)$perfil['dominios_email'])) . ').'];
        }
        $campos = json_decode((string)$perfil['campos'], true) ?: [];
        $extra = [];
        $colunas = ['telefone' => $telefone ?: null, 'documento' => null, 'empresa' => null, 'cnpj' => null, 'setor' => null, 'anfitriao' => null];
        foreach ($campos as $c) {
            $valor = trim((string)($dados[$c['campo']] ?? ''));
            if (!empty($c['obrigatorio']) && $valor === '') {
                return ['success' => false, 'message' => "Preencha o campo \"{$c['rotulo']}\"."];
            }
            if (array_key_exists($c['campo'], $colunas)) {
                if ($c['campo'] !== 'telefone') {
                    $colunas[$c['campo']] = $valor ?: null;
                }
            } elseif ($valor !== '') {
                $extra[$c['rotulo']] = $valor;
            }
        }
        if (empty($dados['aceite'])) {
            return ['success' => false, 'message' => 'Para usar o Wi-Fi é preciso aceitar os termos.'];
        }

        // Já cadastrado neste perfil? Visitante volta a entrar; os demais são orientados.
        $existente = $email !== '' ? $this->repo->pessoaPorEmail($email, $perfilId) : ($telefone ? $this->repo->pessoaPorContato($perfilId, $telefone) : null);
        if ($existente && $senhaPorEmail) {
            return ['success' => false, 'message' => 'Este e-mail já tem cadastro. Use "Entrar" ou "Esqueci a senha".'];
        }
        $this->repo->registrarTentativa('cadastro:' . ($ctx['mac'] ?? $ctx['ip'] ?? ''), false);

        $validade = $perfil['validade_dias'] ? date('Y-m-d H:i:s', strtotime('+' . (int)$perfil['validade_dias'] . ' days')) : null;
        if (!$senhaPorEmail && $perfil['validade_dias'] && (int)$perfil['validade_dias'] === 1) {
            $validade = date('Y-m-d 23:59:59'); // visitante: vale até o fim do dia
        }
        if ($existente) {
            $pessoaId = (int)$existente['id'];
            if (in_array($existente['status'], ['bloqueado', 'recusado'], true)) {
                return ['success' => false, 'message' => $this->situacaoBloqueante($existente)];
            }
            $this->repo->atualizarPessoa($pessoaId, ['nome' => $nome, 'status' => $perfil['exige_liberacao'] ? $existente['status'] : 'ativo', 'valido_ate' => $validade]);
        } else {
            $pessoaId = $this->repo->criarPessoa([
                'perfil_id' => $perfilId, 'nome' => $nome, 'email' => $email ?: null, 'origem' => 'portal',
                'dados_extra' => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                'status' => $perfil['exige_liberacao'] ? 'pendente' : 'ativo', 'valido_ate' => $perfil['exige_liberacao'] ? null : $validade,
            ] + $colunas);
        }
        $termo = $this->repo->termoAtual($perfilId);
        if ($termo && !$this->repo->aceitouTermo($pessoaId, (int)$termo['id'])) {
            $this->repo->registrarAceite($pessoaId, $termo, $ctx['ip'] ?? null, $ctx['mac'] ?? null);
        }
        AuditService::registrar('Wi-Fi', 'Cadastro no portal', "{$perfil['nome']}: {$nome}" . ($email ? " ({$email})" : '') . ($perfil['exige_liberacao'] ? ' -- aguardando liberação.' : '.'));

        if ($perfil['exige_liberacao']) {
            $pessoa = $this->repo->pessoa($pessoaId);
            if ($email) {
                $this->enviarEmail('recebido', $pessoa);
            }
            $this->avisarLiberadores($pessoa);

            return ['success' => true, 'etapa' => 'aguardando', 'pessoa_id' => $pessoaId];
        }
        if ($senhaPorEmail) {
            // Perfil com senha e sem liberação: senha vai por e-mail, entra depois.
            $this->gerarEEnviarSenha($pessoaId);

            return ['success' => true, 'etapa' => 'senha_enviada', 'pessoa_id' => $pessoaId];
        }

        return $this->continuarAcesso($this->repo->pessoa($pessoaId), $ctx);
    }

    // ================================================================ esqueci / redefinir

    public function solicitarRedefinicao(string $email): void
    {
        $pessoa = $this->repo->pessoaPorEmail($email);
        // Resposta é sempre a mesma pra quem pede (não revela se o e-mail existe).
        if (!$pessoa || empty($pessoa['email']) || $pessoa['status'] !== 'ativo' || $this->bloqueadoPorTentativas('redefinir:' . $pessoa['id'])) {
            return;
        }
        $this->repo->registrarTentativa('redefinir:' . $pessoa['id'], false);
        $token = bin2hex(random_bytes(24));
        $this->repo->criarToken((int)$pessoa['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600));
        $this->enviarEmail('redefinir', $pessoa, ['link' => $this->config->urlPortal() . '/guest/redefinir?t=' . $token]);
    }

    public function tokenRedefinicao(string $token): ?array
    {
        return $token !== '' ? $this->repo->tokenValido(hash('sha256', $token)) : null;
    }

    public function redefinirSenha(string $token, string $senha, string $confirmacao): array
    {
        $t = $this->tokenRedefinicao($token);
        if (!$t) {
            return ['success' => false, 'message' => 'Link inválido ou vencido. Peça um novo em "Esqueci a senha".'];
        }
        if ($senha !== $confirmacao) {
            return ['success' => false, 'message' => 'As senhas não conferem.'];
        }
        if ($erro = $this->validarSenha($senha)) {
            return ['success' => false, 'message' => $erro];
        }
        $this->repo->atualizarPessoa((int)$t['pessoa_id'], ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'trocar_senha' => 0]);
        $this->repo->usarToken((int)$t['id']);

        return ['success' => true, 'message' => 'Senha alterada. Agora é só entrar.'];
    }

    // ================================================================ administração

    /** Cadastro feito pela TI/RH: já nasce liberado e recebe a senha por e-mail. */
    public function cadastrarPeloAdmin(array $d, int $usuarioId): array
    {
        $perfil = $this->repo->perfil((int)($d['perfil_id'] ?? 0));
        $nome = trim((string)($d['nome'] ?? ''));
        $email = mb_strtolower(trim((string)($d['email'] ?? '')));
        if (!$perfil || $nome === '') {
            return ['success' => false, 'message' => 'Informe nome e perfil.'];
        }
        if ($perfil['tipo_acesso'] === 'senha' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Informe um e-mail válido: é por ele que a pessoa recebe a senha.'];
        }
        if ($email !== '' && $this->repo->pessoaPorEmail($email, (int)$perfil['id'])) {
            return ['success' => false, 'message' => 'Já existe um cadastro com este e-mail neste perfil.'];
        }
        $validade = !empty($d['valido_ate']) ? $d['valido_ate'] . ' 23:59:59' : ($perfil['validade_dias'] ? date('Y-m-d H:i:s', strtotime('+' . (int)$perfil['validade_dias'] . ' days')) : null);
        $id = $this->repo->criarPessoa([
            'perfil_id' => (int)$perfil['id'], 'nome' => $nome, 'email' => $email ?: null,
            'telefone' => preg_replace('/\D/', '', (string)($d['telefone'] ?? '')) ?: null,
            'empresa' => trim((string)($d['empresa'] ?? '')) ?: null, 'cnpj' => trim((string)($d['cnpj'] ?? '')) ?: null,
            'setor' => trim((string)($d['setor'] ?? '')) ?: null, 'anfitriao' => trim((string)($d['anfitriao'] ?? '')) ?: null,
            'documento' => trim((string)($d['documento'] ?? '')) ?: null,
            'status' => 'ativo', 'valido_ate' => $validade, 'origem' => 'admin', 'liberado_por' => $usuarioId, 'liberado_em' => date('Y-m-d H:i:s'),
        ]);
        AuditService::registrar('Wi-Fi', 'Cadastrar pessoa', "{$perfil['nome']}: {$nome}" . ($email ? " ({$email})" : '') . '.');
        if ($perfil['tipo_acesso'] === 'senha') {
            $envio = $this->gerarEEnviarSenha($id);
            if (!$envio['success']) {
                return ['success' => true, 'id' => $id, 'message' => "Cadastro criado, mas o e-mail com a senha falhou: {$envio['message']}"];
            }
        }

        return ['success' => true, 'id' => $id, 'message' => 'Cadastro criado' . ($perfil['tipo_acesso'] === 'senha' ? ' e senha enviada por e-mail.' : '.')];
    }

    public function liberar(int $id, int $usuarioId): array
    {
        $p = $this->repo->pessoa($id);
        if (!$p || !in_array($p['status'], ['pendente', 'recusado', 'expirado', 'bloqueado'], true)) {
            return ['success' => false, 'message' => 'Cadastro não encontrado ou já liberado.'];
        }
        $perfil = $this->repo->perfil((int)$p['perfil_id']);
        $regras = $this->regras($perfil, $p);
        $validade = $regras['validade_dias'] ? date('Y-m-d H:i:s', strtotime('+' . (int)$regras['validade_dias'] . ' days')) : null;
        $this->repo->atualizarPessoa($id, ['status' => 'ativo', 'motivo' => null, 'valido_ate' => $validade, 'liberado_por' => $usuarioId,
            'liberado_em' => date('Y-m-d H:i:s'), 'aviso_vencimento_em' => null]);
        AuditService::registrar('Wi-Fi', 'Liberar acesso', "{$p['perfil_nome']}: {$p['nome']}" . ($p['email'] ? " ({$p['email']})" : '') . '.');
        if ($perfil['tipo_acesso'] === 'senha' && empty($p['senha_hash'])) {
            $envio = $this->gerarEEnviarSenha($id);

            return ['success' => true, 'message' => 'Acesso liberado.' . ($envio['success'] ? ' Login e senha enviados por e-mail.' : ' Atenção: o e-mail falhou -- ' . $envio['message'])];
        }
        if ($p['email']) {
            $this->enviarEmail('liberado', $this->repo->pessoa($id));
        }

        return ['success' => true, 'message' => 'Acesso liberado.'];
    }

    public function recusar(int $id, string $motivo): array
    {
        $p = $this->repo->pessoa($id);
        if (!$p) {
            return ['success' => false, 'message' => 'Cadastro não encontrado.'];
        }
        $this->repo->atualizarPessoa($id, ['status' => 'recusado', 'motivo' => trim($motivo) ?: null]);
        AuditService::registrar('Wi-Fi', 'Recusar cadastro', "{$p['perfil_nome']}: {$p['nome']}" . ($motivo ? " -- motivo: {$motivo}" : '') . '.');
        if ($p['email']) {
            $this->enviarEmail('recusado', $this->repo->pessoa($id));
        }

        return ['success' => true, 'message' => 'Cadastro recusado.'];
    }

    public function bloquear(int $id, string $motivo): array
    {
        $p = $this->repo->pessoa($id);
        if (!$p) {
            return ['success' => false, 'message' => 'Cadastro não encontrado.'];
        }
        $this->repo->atualizarPessoa($id, ['status' => 'bloqueado', 'motivo' => trim($motivo) ?: null]);
        $this->repo->esquecerAparelhos($id);
        foreach ($this->repo->sessoesAtivas($id) as $s) {
            $this->derrubarSessao((int)$s['id'], 'bloqueado');
        }
        AuditService::registrar('Wi-Fi', 'Bloquear acesso', "{$p['perfil_nome']}: {$p['nome']}" . ($motivo ? " -- motivo: {$motivo}" : '') . '.');

        return ['success' => true, 'message' => 'Acesso bloqueado e aparelhos desconectados.'];
    }

    public function derrubarSessao(int $sessaoId, string $motivo): array
    {
        $s = $this->repo->sessao($sessaoId);
        if (!$s || $s['fim']) {
            return ['success' => false, 'message' => 'Sessão já encerrada.'];
        }
        $controlador = RedeDrivers::controladorPortal();
        $r = $controlador && $s['mac'] ? $controlador->desautorizar($s['mac']) : ['success' => false, 'message' => 'controlador indisponível'];
        $this->repo->encerrarSessao($sessaoId, $motivo);
        $this->sincronizarFiltro();
        AuditService::registrar('Wi-Fi', 'Derrubar sessão', "Sessão {$sessaoId} ({$s['mac']}) encerrada: {$motivo}.");

        return ['success' => true, 'message' => $r['success'] ? 'Aparelho desconectado.' : 'Sessão encerrada no sistema (o equipamento não respondeu: ' . $r['message'] . ').'];
    }

    public function redefinirSenhaPeloAdmin(int $id): array
    {
        $p = $this->repo->pessoa($id);
        if (!$p || !$p['email']) {
            return ['success' => false, 'message' => 'Cadastro sem e-mail.'];
        }
        $r = $this->gerarEEnviarSenha($id);
        AuditService::registrar('Wi-Fi', 'Redefinir senha', "Nova senha provisória enviada para {$p['email']}.");

        return $r['success'] ? ['success' => true, 'message' => 'Nova senha provisória enviada por e-mail.'] : $r;
    }

    /** Gera senha provisória (troca obrigatória no 1º acesso) e manda por e-mail. */
    private function gerarEEnviarSenha(int $pessoaId): array
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $senha = '';
        for ($i = 0; $i < 10; $i++) {
            $senha .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $senha .= random_int(10, 99) . '#';
        $this->repo->atualizarPessoa($pessoaId, ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'trocar_senha' => 1]);

        return $this->enviarEmail('liberado', $this->repo->pessoa($pessoaId), ['senha' => $senha]);
    }

    public function salvarExcecoes(int $id, array $regras): array
    {
        $limpo = [];
        foreach (self::REGRAS_SOBREPONIVEIS as $k) {
            if (!array_key_exists($k, $regras) || $regras[$k] === '' || $regras[$k] === null) {
                continue;
            }
            $limpo[$k] = in_array($k, ['horarios', 'filtro'], true) ? $regras[$k] : (int)$regras[$k];
        }
        $this->repo->atualizarPessoa($id, ['regras' => $limpo ? json_encode($limpo, JSON_UNESCAPED_UNICODE) : null]);
        AuditService::registrar('Wi-Fi', 'Exceções da pessoa', "Pessoa {$id}: " . ($limpo ? json_encode($limpo, JSON_UNESCAPED_UNICODE) : 'sem exceções') . '.');
        $this->sincronizarFiltro();

        return ['success' => true, 'message' => 'Exceções salvas.'];
    }

    // ================================================================ e-mails

    private function avisarLiberadores(array $pessoa): void
    {
        $destinos = $this->repo->emailsLiberadores();
        if (!$destinos) {
            return;
        }
        $this->enviarEmail('novo_pendente', $pessoa, ['para' => $destinos]);
    }

    /** @return array{success: bool, message: string} */
    public function enviarEmail(string $tipo, array $pessoa, array $extra = []): array
    {
        $email = new EmailService();
        if (!$email->configurado()) {
            return ['success' => false, 'message' => 'o envio de e-mail não está configurado (Sistema > E-mail)'];
        }
        $titulo = $this->config->get('portal_titulo');
        $nome = htmlspecialchars(explode(' ', $pessoa['nome'])[0]);
        $portal = htmlspecialchars($this->config->urlPortal() . '/guest/');
        $validade = !empty($pessoa['valido_ate']) ? ' até <strong>' . date('d/m/Y H:i', strtotime($pessoa['valido_ate'])) . '</strong>' : '';
        [$assunto, $corpo] = match ($tipo) {
            'recebido' => ["{$titulo}: cadastro recebido", "<p>Olá, {$nome}.</p><p>Recebemos seu cadastro para o Wi-Fi. Assim que alguém da equipe liberar, você recebe outro e-mail com o acesso.</p>"],
            'liberado' => isset($extra['senha'])
                ? ["{$titulo}: seu acesso ao Wi-Fi", "<p>Olá, {$nome}.</p><p>Seu acesso ao Wi-Fi foi liberado{$validade}.</p>"
                    . '<table style="border-collapse:collapse;margin:12px 0"><tr><td style="padding:4px 12px 4px 0;color:#666">Login</td><td><strong>' . htmlspecialchars($pessoa['email']) . '</strong></td></tr>'
                    . '<tr><td style="padding:4px 12px 4px 0;color:#666">Senha provisória</td><td><strong style="font-family:monospace;font-size:15px">' . htmlspecialchars($extra['senha']) . '</strong></td></tr></table>'
                    . '<p>Conecte no Wi-Fi, entre com esses dados e crie sua senha definitiva no primeiro acesso.</p>']
                : ["{$titulo}: acesso liberado", "<p>Olá, {$nome}.</p><p>Seu acesso ao Wi-Fi foi liberado{$validade}. Conecte no Wi-Fi e entre com seu e-mail e senha.</p>"],
            'recusado' => ["{$titulo}: cadastro não liberado", "<p>Olá, {$nome}.</p><p>Seu cadastro para o Wi-Fi não foi liberado" . ($pessoa['motivo'] ? ': ' . htmlspecialchars($pessoa['motivo']) : '.') . '</p>'],
            'redefinir' => ["{$titulo}: redefinir senha", "<p>Olá, {$nome}.</p><p>Para criar uma nova senha do Wi-Fi, abra o link abaixo (vale por 1 hora):</p><p><a href=\"" . htmlspecialchars($extra['link']) . '">' . htmlspecialchars($extra['link']) . '</a></p><p>Se não foi você que pediu, ignore este e-mail.</p>'],
            'vencendo' => ["{$titulo}: seu acesso vence em breve", "<p>Olá, {$nome}.</p><p>Seu acesso ao Wi-Fi vale{$validade}. Se precisar de mais tempo, fale com quem te recebeu ou com a TI.</p>"],
            'novo_pendente' => ["{$titulo}: cadastro aguardando liberação", '<p>Um novo cadastro está aguardando liberação:</p><p><strong>' . htmlspecialchars($pessoa['nome']) . '</strong> -- ' . htmlspecialchars($pessoa['perfil_nome'] ?? '')
                . ($pessoa['empresa'] ? '<br>Empresa: ' . htmlspecialchars($pessoa['empresa']) : '') . ($pessoa['email'] ? '<br>E-mail: ' . htmlspecialchars($pessoa['email']) : '')
                . ($pessoa['anfitriao'] ? '<br>Quem recebe: ' . htmlspecialchars($pessoa['anfitriao']) : '') . '</p><p>Libere ou recuse em <strong>Wi-Fi &gt; Liberações</strong> no RD.Intranet.</p>'],
            default => ['', ''],
        };
        if ($assunto === '') {
            return ['success' => false, 'message' => 'tipo de e-mail desconhecido'];
        }
        $rodape = '<hr style="border:0;border-top:1px solid #ddd;margin:20px 0"><p style="color:#888;font-size:12px">Mensagem automática do portal Wi-Fi.'
            . ($this->config->get('encarregado_email') ? ' Dúvidas sobre seus dados: ' . htmlspecialchars($this->config->get('encarregado_email')) . '.' : '') . '</p>';

        return $email->enviar($extra['para'] ?? $pessoa['email'], $assunto, $corpo . $rodape);
    }

    // ================================================================ manutenção (cron)

    /** Rodada periódica: consumo, sessões vencidas, contas vencidas, avisos, LGPD. */
    public function manutencao(): array
    {
        $resumo = ['consumo' => 0, 'sessoes_encerradas' => 0, 'contas_vencidas' => 0, 'avisos' => 0, 'anonimizadas' => 0];
        $controlador = RedeDrivers::controladorPortal();
        $convidados = $controlador ? $controlador->convidados() : [];
        foreach ($this->repo->sessoesAtivas() as $s) {
            $g = $convidados[$s['mac']] ?? null;
            if ($g) {
                $this->repo->atualizarConsumo((int)$s['id'], $g['bytes_down'], $g['bytes_up'], $g['ip']);
                $resumo['consumo']++;
            }
            $venceu = $s['fim_previsto'] && strtotime($s['fim_previsto']) < time();
            if ($venceu || ($g && !$g['autorizado'] && strtotime($s['inicio']) < time() - 120)) {
                $this->repo->encerrarSessao((int)$s['id'], $venceu ? 'tempo esgotado' : 'encerrada no equipamento');
                $resumo['sessoes_encerradas']++;
            }
        }
        foreach ($this->repo->pessoasVencidas() as $p) {
            $this->repo->atualizarPessoa((int)$p['id'], ['status' => 'expirado']);
            foreach ($this->repo->sessoesAtivas((int)$p['id']) as $s) {
                $this->derrubarSessao((int)$s['id'], 'conta vencida');
            }
            $resumo['contas_vencidas']++;
        }
        foreach ($this->repo->pessoasAVencer(3) as $p) {
            $p = $this->repo->pessoa((int)$p['id']);
            if ((int)($this->repo->perfil((int)$p['perfil_id'])['validade_dias'] ?? 0) > 3 && $this->enviarEmail('vencendo', $p)['success']) {
                $resumo['avisos']++;
            }
            $this->repo->atualizarPessoa((int)$p['id'], ['aviso_vencimento_em' => date('Y-m-d H:i:s')]);
        }
        $retencao = max(1, $this->config->int('retencao_dias'));
        $resumo['anonimizadas'] = $this->repo->anonimizarSessoesAntigas($retencao);
        foreach ($this->repo->pessoasParaAnonimizar($retencao) as $id) {
            $this->repo->anonimizarPessoa((int)$id);
            $resumo['anonimizadas']++;
        }
        $this->repo->limparTentativas();
        if ($resumo['sessoes_encerradas'] || $resumo['contas_vencidas']) {
            $this->sincronizarFiltro();
        }

        return $resumo;
    }

    private function sincronizarFiltro(): void
    {
        try {
            (new WifiFiltroService())->sincronizar();
        } catch (\Throwable $e) {
            error_log('Wi-Fi: falha ao sincronizar o filtro -- ' . $e->getMessage());
        }
    }

    // ================================================================ LGPD

    /** Tudo o que existe sobre a pessoa (direito de acesso do titular). */
    public function exportarTitular(int $id): ?array
    {
        $p = $this->repo->pessoa($id);
        if (!$p) {
            return null;
        }
        unset($p['senha_hash']);

        return [
            'gerado_em' => date('c'),
            'cadastro' => $p,
            'aparelhos' => $this->repo->aparelhos($id),
            'aceites_de_termos' => $this->repo->aceitesDaPessoa($id),
            'registros_de_conexao' => $this->repo->historicoSessoes(['pessoa_id' => $id], 100000),
        ];
    }

    public function anonimizarTitular(int $id): array
    {
        $p = $this->repo->pessoa($id);
        if (!$p) {
            return ['success' => false, 'message' => 'Cadastro não encontrado.'];
        }
        foreach ($this->repo->sessoesAtivas($id) as $s) {
            $this->derrubarSessao((int)$s['id'], 'exclusão a pedido do titular');
        }
        $this->repo->reduzirPessoa($id);
        AuditService::registrar('Wi-Fi', 'LGPD - pedido do titular', "Cadastro {$id} ({$p['perfil_nome']}): dados não obrigatórios removidos e acesso encerrado; nome/e-mail anonimizados ao fim do prazo dos registros de conexão.");

        return ['success' => true, 'message' => 'Acesso encerrado e dados não obrigatórios removidos. Nome e e-mail ficam vinculados aos registros de conexão só até o fim do prazo legal (' . $this->config->int('retencao_dias') . ' dias) e depois são anonimizados automaticamente.'];
    }
}
