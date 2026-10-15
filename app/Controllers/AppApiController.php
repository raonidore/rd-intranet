<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AppSessaoService;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\ChamadoAnexoService;
use App\Services\ChamadoCategoriaService;
use App\Services\ChamadoService;
use App\Services\ChamadoSetorService;
use App\Services\ChamadoSetorSolicitanteService;
use App\Services\ChamadoSubcategoriaService;
use App\Services\PermissionService;
use App\Services\UnidadeService;
use App\Services\UserService;

/**
 * API do app Android (/api/app/*) -- módulo Chamados para a equipe de TI.
 * Sem sessão web: o app manda o token do login no cabeçalho X-RD-Token;
 * a sessão PHP é fechada logo no início e $_SESSION vira só memória da
 * requisição (AppSessaoService monta o usuário nela). As regras de negócio
 * são as mesmas das telas web (ChamadoService), inclusive permissões.
 */
class AppApiController extends Controller
{
    /** Versão do contrato da API -- o app avisa quando o servidor é mais velho do que ele precisa. */
    public const VERSAO_API = 1;

    private AppSessaoService $sessoes;

    public function __construct()
    {
        // Nada de cookie/sessão do navegador: cada requisição vale pelo token.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        $this->sessoes = new AppSessaoService();
    }

    // ------------------------------------------------------------ público

    /** "Testar conexão" da tela inicial: confirma que o endereço é um RD.Intranet e de qual empresa. */
    public function info(): void
    {
        $ativos = new AtivoService();

        $this->json([
            'success' => true,
            'produto' => 'RD.Intranet',
            'api' => self::VERSAO_API,
            'empresa' => $ativos->nomeEmpresa(),
            'tem_logo' => $ativos->logoEmpresaConfigurada(),
        ]);
    }

    /** Logo da empresa (a mesma do menu do painel) -- pública, mostrada na tela de login. */
    public function logo(): void
    {
        $ativos = new AtivoService();
        if (!$ativos->logoEmpresaConfigurada()) {
            http_response_code(404);
            exit;
        }

        $caminho = AtivoService::caminhoLogoEmpresa();
        header('Content-Type: ' . $ativos->logoEmpresaMime());
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
        exit;
    }

    public function login(): void
    {
        $dados = $this->corpo();
        $resultado = $this->sessoes->login(
            (string)($dados['login'] ?? ''),
            (string)($dados['senha'] ?? ''),
            (string)($dados['dispositivo'] ?? ''),
            (string)($dados['versao_app'] ?? '')
        );

        if (!$resultado['success']) {
            http_response_code(401);
            $this->json($resultado);
        }

        $this->json($resultado + $this->dadosDaConta());
    }

    // ------------------------------------------------------------ conta

    public function eu(): void
    {
        $this->exigirUsuario();
        $this->json(['success' => true] + $this->dadosDaConta());
    }

    public function logout(): void
    {
        $token = $this->token();
        if ($token !== '') {
            $this->sessoes->logout($token);
        }

        $this->json(['success' => true, 'message' => 'Desconectado.']);
    }

    // ------------------------------------------------------------ chamados

    /** Contadores das abas + gatilho de alerta (maior id na fila / aguardando resposta). */
    public function resumo(): void
    {
        $usuario = $this->exigirUsuario();
        $service = new ChamadoService();
        $id = (int)$usuario['id'];
        $permissoes = $this->sessoes->permissoes();

        $this->json([
            'success' => true,
            'fila' => $permissoes['fila'] ? $service->contarFila($this->setoresVisiveis($id)) : 0,
            'meus' => $permissoes['atendimentos'] ? count($service->listarDoUsuario($id)) : 0,
            'aguardando_resposta' => $permissoes['atendimentos'] ? $service->contarAguardandoResposta($id) : 0,
            'ultimo_aguardando_id' => $permissoes['atendimentos'] ? $service->ultimoIdAguardandoResposta($id) : 0,
            'ultimo_fila_id' => $permissoes['fila'] ? $this->ultimoIdFila($this->setoresVisiveis($id)) : 0,
        ]);
    }

    /** lista = fila | meus | equipe | encerrados | abertos_por_mim; q = busca livre. */
    public function chamados(): void
    {
        $usuario = $this->exigirUsuario();
        $service = new ChamadoService();
        $id = (int)$usuario['id'];
        $lista = (string)($_GET['lista'] ?? 'meus');
        $q = trim((string)($_GET['q'] ?? ''));

        $linhas = match ($lista) {
            'fila' => $this->exigirModulo('chamados_fila') ?? $service->listarFila($this->setoresVisiveis($id)),
            'equipe' => $this->exigirModulo('chamados_equipe') ?? $service->listarDaEquipe($this->setoresVisiveis($id), $id),
            'encerrados' => $this->exigirModulo('chamados_atendimentos') ?? (PermissionService::temAcesso('chamados_equipe')
                ? $service->listarEncerradosDaEquipe($this->setoresVisiveis($id), $id, 100, ['q' => $q])
                : $service->listarEncerradosDoUsuario($id, 100, ['q' => $q])),
            'abertos_por_mim' => array_slice($service->listarAbertosPeloUsuario($id), 0, 100),
            default => $this->exigirModulo('chamados_atendimentos') ?? $service->listarDoUsuario($id),
        };

        if ($q !== '' && $lista !== 'encerrados') {
            $termo = mb_strtolower($q);
            $linhas = array_values(array_filter($linhas, fn (array $c) => str_contains(mb_strtolower(implode(' ', [
                $c['numero_controle'] ?? '', $c['titulo'], $c['solicitante_nome'] ?? '', $c['ativo_codigo'] ?? '', $c['ativo_nome'] ?? '', $c['categoria_nome'] ?? '',
            ])), $termo)));
        }

        $this->json(['success' => true, 'lista' => $lista, 'chamados' => array_map(fn (array $c) => $this->resumoChamado($c), $linhas)]);
    }

    public function ver(): void
    {
        $usuario = $this->exigirUsuario();
        $service = new ChamadoService();
        $chamadoId = (int)($_GET['id'] ?? 0);
        $chamado = $service->buscar($chamadoId);

        // Quem só abre chamado (sem Atendimentos) vê os que ele mesmo abriu, sem notas internas.
        $atendente = PermissionService::temAcesso('chamados_atendimentos');
        if (!$chamado || (!$atendente && (int)($chamado['usuario_abertura_id'] ?? 0) !== (int)$usuario['id'])) {
            $this->erro(404, 'Chamado não encontrado.');
        }

        $encerrado = in_array($chamado['status'], ['resolvido', 'fechado'], true);
        $ehMeu = (int)($chamado['usuario_id'] ?? 0) === (int)$usuario['id'];
        $anexos = (new ChamadoAnexoService())->listarPorChamado($chamadoId);
        $anexosPorComentario = [];
        foreach ($anexos as $a) {
            $anexosPorComentario[(int)($a['comentario_id'] ?? 0)][] = $this->anexo($a);
        }

        $this->json([
            'success' => true,
            'chamado' => $this->resumoChamado($chamado) + [
                'descricao' => $chamado['descricao'],
                'setor' => $chamado['setor_nome'] ?? '',
                'setor_solicitante' => $chamado['setor_solicitante_nome'] ?? '',
                'unidade' => $chamado['unidade_nome'] ?? '',
                'solicitante_email' => $chamado['solicitante_email'] ?? '',
                'solicitante_telefone' => $chamado['solicitante_telefone'] ?? '',
                'canal' => $chamado['canal_abertura'] ?? '',
                'primeira_resposta_em' => $chamado['primeira_resposta_em'] ?? null,
                'resolvido_em' => $chamado['resolvido_em'] ?? null,
                'fechado_em' => $chamado['fechado_em'] ?? null,
            ],
            'mensagens' => array_map(fn (array $m) => [
                'id' => (int)$m['id'],
                'tipo' => $m['tipo'],
                'autor' => $m['usuario_id'] === null ? ($chamado['solicitante_nome'] ?? 'Solicitante') : ($m['usuario_nome'] ?? 'Equipe'),
                'da_equipe' => $m['usuario_id'] !== null,
                'sou_eu' => (int)($m['usuario_id'] ?? 0) === (int)$usuario['id'],
                'texto' => $m['conteudo'],
                'quando' => $m['criado_em'],
                'anexos' => $anexosPorComentario[(int)$m['id']] ?? [],
            ], $service->comentarios($chamadoId, $atendente)),
            'anexos_avulsos' => $anexosPorComentario[0] ?? [],
            'historico' => $atendente ? array_map(fn (array $h) => [
                'campo' => $h['campo'],
                'de' => $this->valorHistorico($h['campo'], $h['valor_anterior']),
                'para' => $this->valorHistorico($h['campo'], $h['valor_novo']),
                'por' => $h['usuario_nome'] ?? 'Sistema',
                'quando' => $h['criado_em'] ?? null,
            ], $service->historico($chamadoId)) : [],
            'acoes' => [
                'assumir' => $chamado['status'] === 'fila' && PermissionService::temAcesso('chamados_fila'),
                'pegar_para_mim' => !$encerrado && !$ehMeu && $chamado['status'] !== 'fila' && PermissionService::temAcesso('chamados_transferir'),
                'transferir' => !$encerrado && PermissionService::temAcesso('chamados_transferir'),
                'responder' => $atendente && !$encerrado,
                'nota_interna' => $atendente && !$encerrado,
                'status' => $atendente,
                'anexar' => $atendente && !$encerrado,
            ],
            'status_possiveis' => $atendente ? $this->statusPossiveis($chamado['status']) : [],
        ]);
    }

    public function assumir(): void
    {
        $usuario = $this->exigirUsuario();
        $this->exigirModulo('chamados_fila');
        $id = (int)($this->corpo()['id'] ?? 0);

        $resultado = (new ChamadoService())->assumir($id, (int)$usuario['id']);
        AuditService::registrar('Chamados', 'Assumir chamado', "Chamado #{$id} (app): {$resultado['message']}");

        $this->resultado($resultado);
    }

    /** Resposta pública ou nota interna, com anexos opcionais (base64). */
    public function responder(): void
    {
        $usuario = $this->exigirUsuario();
        $this->exigirModulo('chamados_atendimentos');
        $dados = $this->corpo();
        $id = (int)($dados['id'] ?? 0);
        $tipo = ($dados['tipo'] ?? '') === 'interna' ? 'interna' : 'publica';
        $anexos = is_array($dados['anexos'] ?? null) ? $dados['anexos'] : [];
        $texto = (string)($dados['texto'] ?? '');

        // Só foto, sem texto: a mensagem diz o que foi enviado (o comentário é obrigatório no serviço).
        if (trim($texto) === '' && $anexos) {
            $texto = count($anexos) === 1 ? '📎 Anexo enviado pelo app.' : '📎 ' . count($anexos) . ' anexos enviados pelo app.';
        }

        $resultado = (new ChamadoService())->responder($id, $texto, $tipo, (int)$usuario['id']);
        if (!$resultado['success']) {
            $this->resultado($resultado);
        }

        $erros = $this->salvarAnexos($id, $anexos, (int)$usuario['id'], $resultado['id'] ?? null);
        $this->json(['success' => true, 'message' => $erros ? 'Mensagem enviada, mas: ' . implode(' ', $erros) : 'Enviado.']);
    }

    public function status(): void
    {
        $usuario = $this->exigirUsuario();
        $this->exigirModulo('chamados_atendimentos');
        $dados = $this->corpo();
        $id = (int)($dados['id'] ?? 0);

        // Resolver com uma mensagem final: a resposta vai antes, como no painel.
        $mensagem = trim((string)($dados['mensagem'] ?? ''));
        if ($mensagem !== '') {
            $resposta = (new ChamadoService())->responder($id, $mensagem, 'publica', (int)$usuario['id']);
            if (!$resposta['success']) {
                $this->resultado($resposta);
            }
        }

        $resultado = (new ChamadoService())->mudarStatus($id, (string)($dados['status'] ?? ''), (int)$usuario['id']);
        AuditService::registrar('Chamados', 'Mudar status', "Chamado #{$id} (app): {$resultado['message']}");

        $this->resultado($resultado);
    }

    public function transferir(): void
    {
        $usuario = $this->exigirUsuario();
        $this->exigirModulo('chamados_transferir');
        $dados = $this->corpo();
        $id = (int)($dados['id'] ?? 0);
        $destino = (int)($dados['usuario_id'] ?? 0) ?: (int)$usuario['id'];

        $resultado = (new ChamadoService())->transferir($id, $destino, (int)$usuario['id'], (string)($dados['motivo'] ?? ''));
        AuditService::registrar('Chamados', 'Transferir chamado', "Chamado #{$id} (app): {$resultado['message']}");

        $this->resultado($resultado);
    }

    public function anexar(): void
    {
        $usuario = $this->exigirUsuario();
        $this->exigirModulo('chamados_atendimentos');
        $dados = $this->corpo();
        $id = (int)($dados['id'] ?? 0);
        if (!(new ChamadoService())->buscar($id)) {
            $this->erro(404, 'Chamado não encontrado.');
        }

        $erros = $this->salvarAnexos($id, is_array($dados['anexos'] ?? null) ? $dados['anexos'] : [], (int)$usuario['id'], null);
        $this->resultado($erros ? ['success' => false, 'message' => implode(' ', $erros)] : ['success' => true, 'message' => 'Anexo enviado.']);
    }

    public function baixarAnexo(): void
    {
        $usuario = $this->exigirUsuario();
        $service = new ChamadoAnexoService();
        $anexo = $service->buscar((int)($_GET['id'] ?? 0));
        $chamado = $anexo ? (new ChamadoService())->buscar((int)$anexo['chamado_id']) : null;

        $permitido = $chamado && (PermissionService::temAcesso('chamados_atendimentos') || (int)($chamado['usuario_abertura_id'] ?? 0) === (int)$usuario['id']);
        $caminho = $anexo ? $service->caminhoCompleto($anexo['caminho_arquivo']) : '';
        if (!$permitido || !is_file($caminho)) {
            $this->erro(404, 'Anexo não encontrado.');
        }

        header('Content-Type: ' . ($anexo['tipo_mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($anexo['nome_original']) . '"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
        exit;
    }

    public function atendentes(): void
    {
        $this->exigirUsuario();
        $this->exigirModulo('chamados_transferir');

        $this->json(['success' => true, 'atendentes' => array_map(
            fn (array $a) => ['id' => (int)$a['id'], 'nome' => $a['nome']],
            (new ChamadoService())->atendentesDisponiveis()
        )]);
    }

    /** Tudo que a tela "Novo chamado" precisa, numa chamada só (mesmos campos de Atendimentos > Novo). */
    public function formulario(): void
    {
        $usuario = $this->exigirUsuario();
        $this->exigirAbrir();
        $setoresSolicitantes = new ChamadoSetorSolicitanteService();

        $this->json([
            'success' => true,
            'categorias' => array_map(fn (array $c) => [
                'id' => (int)$c['id'],
                'nome' => $c['nome'],
                'exige_subcategoria' => !empty($c['exige_subcategoria']),
            ], (new ChamadoCategoriaService())->listarAtivas()),
            'subcategorias' => (object)(new ChamadoSubcategoriaService())->listarAtivasAgrupadas(),
            'setores' => array_map(fn (array $s) => ['id' => (int)$s['id'], 'nome' => $s['nome']], (new ChamadoSetorService())->listarAtivos()),
            'unidades' => array_map(fn (array $u) => ['id' => (int)$u['id'], 'nome' => $u['nome']], (new UnidadeService())->listarAtivas()),
            'setores_solicitantes' => array_map(fn (array $s) => [
                'id' => (int)$s['id'],
                'nome' => $s['nome'],
                'unidade_id' => $s['unidade_id'] !== null ? (int)$s['unidade_id'] : null,
            ], $setoresSolicitantes->listarAtivos()),
            'setor_solicitante_obrigatorio' => $setoresSolicitantes->obrigatorio(),
            'prioridades' => array_map(fn ($chave, $rotulo) => ['id' => $chave, 'nome' => $rotulo], array_keys(ChamadoService::PRIORIDADES), ChamadoService::PRIORIDADES),
            'eu' => ['nome' => $usuario['nome'], 'email' => $usuario['email'] ?? ''],
        ]);
    }

    public function buscarAtivos(): void
    {
        $this->exigirUsuario();
        $this->exigirAbrir();
        $termo = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($termo) < 2) {
            $this->json(['success' => true, 'ativos' => []]);
        }

        $ativos = array_slice((new AtivoService())->listar(['busca' => mb_substr($termo, 0, 60)]), 0, 15);
        $this->json(['success' => true, 'ativos' => array_map(fn (array $a) => [
            'id' => (int)$a['id'],
            'codigo' => $a['codigo_patrimonio'],
            'nome' => $a['nome'],
            'tipo' => $a['tipo_nome'] ?? '',
            'unidade_id' => isset($a['unidade_id']) ? (int)$a['unidade_id'] : null,
        ], $ativos)]);
    }

    public function buscarUsuarios(): void
    {
        $this->exigirUsuario();
        $this->exigirAbrir();
        $termo = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($termo) < 2) {
            $this->json(['success' => true, 'usuarios' => []]);
        }

        $this->json(['success' => true, 'usuarios' => array_map(fn (array $u) => [
            'id' => (int)$u['id'],
            'nome' => $u['nome'],
            'email' => $u['email'] ?? '',
        ], array_slice((new UserService())->buscarPorTermo($termo), 0, 15))]);
    }

    public function abrir(): void
    {
        $usuario = $this->exigirUsuario();
        $this->exigirAbrir();
        $dados = $this->corpo();

        $post = [
            'titulo' => mb_substr(trim((string)($dados['titulo'] ?? '')), 0, 200),
            'descricao' => trim((string)($dados['descricao'] ?? '')),
            'categoria_id' => (int)($dados['categoria_id'] ?? 0),
            'subcategoria_id' => (int)($dados['subcategoria_id'] ?? 0) ?: null,
            'setor_id' => (int)($dados['setor_id'] ?? 0) ?: null,
            'unidade_id' => (int)($dados['unidade_id'] ?? 0),
            'setor_solicitante_id' => (int)($dados['setor_solicitante_id'] ?? 0) ?: null,
            'prioridade' => (string)($dados['prioridade'] ?? 'media'),
            'ativo_id' => (int)($dados['ativo_id'] ?? 0) ?: null,
            'solicitante_nome' => trim((string)($dados['solicitante_nome'] ?? '')) ?: $usuario['nome'],
            'solicitante_email' => trim((string)($dados['solicitante_email'] ?? '')),
            'solicitante_telefone' => trim((string)($dados['solicitante_telefone'] ?? '')),
        ];
        if ($post['solicitante_email'] === '' && $post['solicitante_telefone'] === '' && $post['solicitante_nome'] === $usuario['nome']) {
            $post['solicitante_email'] = (string)($usuario['email'] ?? '');
        }
        if ($post['solicitante_email'] !== '' && !filter_var($post['solicitante_email'], FILTER_VALIDATE_EMAIL)) {
            $this->erro(422, 'E-mail do solicitante inválido.');
        }

        $resultado = (new ChamadoService())->abrir($post, 'app', (int)$usuario['id']);
        if (!$resultado['success']) {
            $this->resultado($resultado);
        }

        $erros = $this->salvarAnexos((int)$resultado['id'], is_array($dados['anexos'] ?? null) ? $dados['anexos'] : [], (int)$usuario['id'], null);

        // "Já assumir": atendente que abre o chamado na rua normalmente é quem vai atender.
        if (!empty($dados['assumir']) && PermissionService::temAcesso('chamados_fila')) {
            (new ChamadoService())->assumir((int)$resultado['id'], (int)$usuario['id']);
        }

        $this->json([
            'success' => true,
            'id' => (int)$resultado['id'],
            'message' => $resultado['message'] . ($erros ? ' Mas: ' . implode(' ', $erros) : ''),
        ]);
    }

    // ------------------------------------------------------------ apoio

    private function dadosDaConta(): array
    {
        $u = $_SESSION['usuario'];
        $ativos = new AtivoService();

        return [
            'api' => self::VERSAO_API,
            'empresa' => $ativos->nomeEmpresa(),
            'usuario' => ['id' => (int)$u['id'], 'nome' => $u['nome'], 'login' => $u['login'], 'email' => $u['email'] ?? '', 'admin' => ($u['perfil'] ?? '') === 'admin'],
            'permissoes' => $this->sessoes->permissoes(),
            'status' => ChamadoService::STATUS,
            'prioridades' => ChamadoService::PRIORIDADES,
        ];
    }

    private function resumoChamado(array $c): array
    {
        $agora = time();
        $sla = null;
        if (!in_array($c['status'], ['resolvido', 'fechado'], true)) {
            // O prazo que importa agora: primeira resposta enquanto ninguém respondeu, depois resolução.
            $prazo = ($c['primeira_resposta_em'] ?? null) === null && !empty($c['sla_resposta_prazo']) ? $c['sla_resposta_prazo'] : ($c['sla_resolucao_prazo'] ?? null);
            if ($prazo) {
                $sla = [
                    'tipo' => $prazo === ($c['sla_resposta_prazo'] ?? null) && ($c['primeira_resposta_em'] ?? null) === null ? 'resposta' : 'resolucao',
                    'prazo' => $prazo,
                    'estourado' => strtotime($prazo) < $agora && empty($c['sla_pausado_em']),
                    'pausado' => !empty($c['sla_pausado_em']),
                ];
            }
        }

        return [
            'id' => (int)$c['id'],
            'numero' => $c['numero_controle'] ?: (string)$c['id'],
            'titulo' => $c['titulo'],
            'status' => $c['status'],
            'prioridade' => $c['prioridade'],
            'categoria' => $c['categoria_nome'] ?? '',
            'solicitante' => $c['solicitante_nome'] ?? '',
            'unidade' => $c['unidade_sigla'] ?: ($c['unidade_nome'] ?? ''),
            'atendente' => $c['usuario_nome'] ?? '',
            'atendente_id' => $c['usuario_id'] !== null ? (int)$c['usuario_id'] : null,
            'ativo' => $c['ativo_codigo'] ? trim($c['ativo_codigo'] . ' · ' . $c['ativo_nome']) : '',
            'ativo_id' => $c['ativo_id'] !== null ? (int)$c['ativo_id'] : null,
            'aguardando_resposta' => !empty($c['aguardando_resposta']),
            'aberto_em' => $c['aberto_em'],
            'atualizado_em' => $c['ultima_mensagem_em'] ?? $c['aberto_em'],
            'sla' => $sla,
        ];
    }

    private function anexo(array $a): array
    {
        return [
            'id' => (int)$a['id'],
            'nome' => $a['nome_original'],
            'tipo' => $a['tipo_mime'],
            'tamanho' => (int)$a['tamanho_bytes'],
            'imagem' => str_starts_with((string)$a['tipo_mime'], 'image/'),
        ];
    }

    private function valorHistorico(string $campo, ?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if ($campo === 'status') {
            return ChamadoService::STATUS[$valor] ?? $valor;
        }
        if ($campo === 'usuario_id') {
            static $nomes = null;
            $nomes ??= array_column((new ChamadoService())->atendentesDisponiveis(), 'nome', 'id');
            return $nomes[(int)$valor] ?? ('#' . $valor);
        }

        return $valor;
    }

    /** Para onde o chamado pode ir a partir do status atual (mesmos botões da tela web). */
    private function statusPossiveis(string $atual): array
    {
        $mapa = [
            'fila' => ['em_atendimento', 'resolvido', 'fechado'],
            'em_atendimento' => ['aguardando_cliente', 'resolvido', 'fila', 'fechado'],
            'aguardando_cliente' => ['em_atendimento', 'resolvido', 'fechado'],
            'resolvido' => ['em_atendimento', 'fechado'],
            'fechado' => ['em_atendimento'],
        ];

        return array_map(fn (string $s) => ['id' => $s, 'nome' => ChamadoService::STATUS[$s]], $mapa[$atual] ?? []);
    }

    /** @return string[] erros (vazio = tudo salvo) */
    private function salvarAnexos(int $chamadoId, array $anexos, int $usuarioId, ?int $comentarioId): array
    {
        $erros = [];
        $service = new ChamadoAnexoService();
        foreach (array_slice($anexos, 0, 10) as $anexo) {
            $nome = mb_substr(basename((string)($anexo['nome'] ?? 'anexo')), 0, 150) ?: 'anexo';
            $conteudo = base64_decode((string)($anexo['base64'] ?? ''), true);
            if ($conteudo === false || $conteudo === '') {
                $erros[] = "\"{$nome}\" chegou vazio.";
                continue;
            }
            $resultado = $service->salvarConteudo($chamadoId, $nome, $conteudo, $usuarioId, $comentarioId);
            if (!$resultado['success']) {
                $erros[] = "\"{$nome}\": {$resultado['message']}";
            }
        }

        return $erros;
    }

    /** @return int[]|null */
    private function setoresVisiveis(int $usuarioId): ?array
    {
        return PermissionService::ehAdmin() ? null : (new ChamadoSetorService())->idsSetoresDoUsuario($usuarioId);
    }

    private function ultimoIdFila(?array $setorIds): int
    {
        $fila = (new ChamadoService())->listarFila($setorIds);

        return $fila ? max(array_map(fn (array $c) => (int)$c['id'], $fila)) : 0;
    }

    private function token(): string
    {
        $token = trim((string)($_SERVER['HTTP_X_RD_TOKEN'] ?? ''));

        // Só o download de anexo aceita o token na URL -- é o que deixa a foto aparecer num <img> do app.
        if ($token === '' && str_ends_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/api/app/chamados/anexo')) {
            $token = trim((string)($_GET['token'] ?? ''));
        }

        return $token;
    }

    private function exigirUsuario(): array
    {
        $usuario = $this->token() === '' ? null : $this->sessoes->usuarioDoToken($this->token());
        if ($usuario === null) {
            http_response_code(401);
            $this->json(['success' => false, 'sessao_expirada' => true, 'message' => 'Sua sessão expirou. Entre de novo.']);
        }

        return $usuario;
    }

    /** Devolve null (pra usar com ??) ou encerra com 403. */
    private function exigirModulo(string $modulo): mixed
    {
        if (!PermissionService::temAcesso($modulo)) {
            $this->erro(403, 'Seu usuário não tem acesso a esta parte dos chamados.');
        }

        return null;
    }

    private function exigirAbrir(): void
    {
        if (!$this->sessoes->permissoes()['abrir']) {
            $this->erro(403, 'Seu usuário não pode abrir chamados.');
        }
    }

    private function corpo(): array
    {
        $dados = json_decode((string)file_get_contents('php://input'), true);

        return is_array($dados) ? $dados : [];
    }

    private function resultado(array $resultado): never
    {
        if (!$resultado['success']) {
            http_response_code(422);
        }
        $this->json($resultado);
    }

    private function erro(int $codigo, string $mensagem): never
    {
        http_response_code($codigo);
        $this->json(['success' => false, 'message' => $mensagem]);
    }

    private function json(array $dados): never
    {
        header('Content-Type: application/json; charset=utf-8');
        // Hora do servidor: o app calcula "há 5 min" e o SLA mesmo com o relógio/fuso do celular diferente.
        $dados['agora'] = date('Y-m-d H:i:s');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
