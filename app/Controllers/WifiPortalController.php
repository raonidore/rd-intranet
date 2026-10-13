<?php

namespace App\Controllers;

use App\Services\WifiConfigService;
use App\Services\WifiPortalService;

/**
 * Portal público do Wi-Fi (/guest). Não usa o Router nem a sessão do painel:
 * tem sessão própria (RDWIFI) e é aberto por quem ainda não tem internet --
 * por isso nada aqui carrega CSS/JS de fora do servidor.
 *
 * Entrada: o equipamento (UniFi) manda o aparelho para
 *   http://<ip>/guest/s/<site>/?id=<mac>&ap=<mac_ap>&ssid=..&url=..
 * Guardamos esse contexto; se o HTTPS estiver ativo, ele segue assinado
 * para https://<nome>/guest/?c=... (cookie não atravessa de host).
 */
class WifiPortalController
{
    private WifiPortalService $wifi;
    private WifiConfigService $config;

    public function __construct()
    {
        $this->wifi = new WifiPortalService();
        $this->config = new WifiConfigService();
    }

    public function dispatch(string $rota): void
    {
        header('Cache-Control: no-store');
        header('X-Frame-Options: DENY');
        $_SESSION['wifi'] ??= [];
        if (empty($_SESSION['wifi']['csrf'])) {
            $_SESSION['wifi']['csrf'] = bin2hex(random_bytes(16));
        }
        $post = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($post && !hash_equals($_SESSION['wifi']['csrf'], (string)($_POST['_csrf'] ?? ''))) {
            $this->render('erro', ['mensagem' => 'A página expirou. Volte e tente de novo.']);

            return;
        }
        if (preg_match('#^s/[^/]+/?$#', $rota)) {
            $this->entrada();

            return;
        }
        if (!$this->config->bool('portal_ativo') && !in_array($rota, ['redefinir', 'privacidade'], true)) {
            $this->render('erro', ['mensagem' => 'O portal do Wi-Fi está desativado no momento.']);

            return;
        }
        if (isset($_GET['c'])) {
            $this->receberContexto((string)$_GET['c']);

            return;
        }

        match ($rota) {
            '' => $this->inicio(),
            'entrar' => $post ? $this->entrar() : $this->ir(''),
            'cadastro' => $post ? $this->salvarCadastro() : $this->cadastro(),
            'termos' => $post ? $this->aceitarTermos() : $this->termos(),
            'trocar-senha' => $post ? $this->salvarTrocaSenha() : $this->etapa('trocar_senha'),
            'aparelhos' => $post ? $this->desconectarAparelho() : $this->aparelhos(),
            'aguardando' => $this->render('aguardando'),
            'senha-enviada' => $this->render('senha_enviada'),
            'conectado' => $this->conectado(),
            'esqueci' => $post ? $this->enviarEsqueci() : $this->render('esqueci'),
            'redefinir' => $post ? $this->salvarRedefinicao() : $this->redefinir(),
            'privacidade' => $this->privacidade(),
            default => $this->naoEncontrado(),
        };
    }

    // ================================================================ contexto do aparelho

    private function entrada(): void
    {
        $ctx = [
            'mac' => WifiPortalService::normalizarMac($_GET['id'] ?? null),
            'ap' => WifiPortalService::normalizarMac($_GET['ap'] ?? null),
            'ssid' => mb_substr((string)($_GET['ssid'] ?? ''), 0, 64),
            'url' => $this->urlSegura((string)($_GET['url'] ?? '')),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            't' => time(),
        ];
        $dominio = $this->config->get('https_dominio');
        if ($this->config->bool('https_ativo') && $dominio !== '' && strcasecmp($_SERVER['HTTP_HOST'] ?? '', $dominio) !== 0) {
            header('Location: https://' . $dominio . '/guest/?c=' . $this->assinar($ctx));
            exit;
        }
        $_SESSION['wifi'] = ['csrf' => $_SESSION['wifi']['csrf'], 'ctx' => $ctx];
        $this->ir('');
    }

    private function receberContexto(string $c): void
    {
        $ctx = $this->verificar($c);
        if (!$ctx) {
            $this->render('erro', ['mensagem' => 'Link do Wi-Fi inválido ou antigo. Desconecte e conecte de novo na rede.']);

            return;
        }
        $_SESSION['wifi'] = ['csrf' => $_SESSION['wifi']['csrf'], 'ctx' => $ctx];
        $this->ir('');
    }

    private function assinar(array $ctx): string
    {
        $dados = rtrim(strtr(base64_encode(json_encode($ctx)), '+/', '-_'), '=');

        return $dados . '.' . hash_hmac('sha256', $dados, $this->config->chavePortal());
    }

    private function verificar(string $c): ?array
    {
        [$dados, $assinatura] = array_pad(explode('.', $c, 2), 2, '');
        if (!hash_equals(hash_hmac('sha256', $dados, $this->config->chavePortal()), $assinatura)) {
            return null;
        }
        $ctx = json_decode((string)base64_decode(strtr($dados, '-_', '+/')), true);

        return is_array($ctx) && (time() - (int)($ctx['t'] ?? 0)) < 1800 ? $ctx : null;
    }

    /** Só devolve o aparelho para http(s) -- nunca javascript: ou afins. */
    private function urlSegura(string $url): string
    {
        return preg_match('#^https?://[^\s"<>]+$#i', $url) ? mb_substr($url, 0, 500) : '';
    }

    private function ctx(): array
    {
        $ctx = $_SESSION['wifi']['ctx'] ?? [];
        // IP pode mudar entre o redirecionamento e o login (DHCP); vale o atual.
        $ctx['ip'] = $_SERVER['REMOTE_ADDR'] ?? ($ctx['ip'] ?? null);
        $ctx['lembrar'] = !empty($_SESSION['wifi']['lembrar']);

        return $ctx;
    }

    private function temAparelho(): bool
    {
        return !empty($_SESSION['wifi']['ctx']['mac']);
    }

    // ================================================================ telas

    private function inicio(): void
    {
        if ($this->temAparelho() && ($r = $this->wifi->acessoPorAparelhoLembrado($this->ctx()))) {
            $this->seguir($r);

            return;
        }
        $perfis = $this->wifi->repositorio()->perfis(true);
        $this->render('inicio', [
            'perfisCadastro' => array_values(array_filter($perfis, fn ($p) => $p['autocadastro'])),
            'temSenha' => (bool)array_filter($perfis, fn ($p) => $p['tipo_acesso'] === 'senha'),
            'semAparelho' => !$this->temAparelho(),
        ]);
    }

    private function entrar(): void
    {
        if (!$this->temAparelho()) {
            $this->render('erro', ['mensagem' => 'Abra esta página conectado ao Wi-Fi para entrar.']);

            return;
        }
        $_SESSION['wifi']['lembrar'] = !empty($_POST['lembrar']);
        $r = $this->wifi->login((string)($_POST['email'] ?? ''), (string)($_POST['senha'] ?? ''), $this->ctx(), $_SESSION['wifi']['lembrar']);
        if (!$r['success']) {
            $perfis = $this->wifi->repositorio()->perfis(true);
            $this->render('inicio', [
                'erro' => $r['message'], 'email' => $_POST['email'] ?? '',
                'perfisCadastro' => array_values(array_filter($perfis, fn ($p) => $p['autocadastro'])),
                'temSenha' => true, 'semAparelho' => false,
            ]);

            return;
        }
        $this->seguir($r);
    }

    /** Leva para a próxima etapa que o serviço indicou. */
    private function seguir(array $r): void
    {
        if (!empty($r['pessoa_id'])) {
            $_SESSION['wifi']['pessoa_id'] = (int)$r['pessoa_id'];
        }
        if (isset($r['minutos'])) {
            $_SESSION['wifi']['minutos'] = (int)$r['minutos'];
        }
        $this->ir(match ($r['etapa'] ?? '') {
            'termos' => 'termos',
            'trocar_senha' => 'trocar-senha',
            'aparelhos' => 'aparelhos',
            'aguardando' => 'aguardando',
            'senha_enviada' => 'senha-enviada',
            'conectado' => 'conectado',
            default => '',
        });
    }

    private function pessoaDaSessao(): ?array
    {
        $id = (int)($_SESSION['wifi']['pessoa_id'] ?? 0);

        return $id ? $this->wifi->repositorio()->pessoa($id) : null;
    }

    private function cadastro(array $extra = []): void
    {
        $perfil = $this->wifi->repositorio()->perfilPorSlug((string)($_GET['perfil'] ?? $_POST['perfil'] ?? ''));
        if (!$perfil || !$perfil['ativo'] || !$perfil['autocadastro']) {
            $this->ir('');

            return;
        }
        $this->render('cadastro', $extra + [
            'perfil' => $perfil,
            'campos' => json_decode((string)$perfil['campos'], true) ?: [],
            'termo' => $this->wifi->repositorio()->termoAtual((int)$perfil['id']),
            'dados' => [],
        ]);
    }

    private function salvarCadastro(): void
    {
        $perfil = $this->wifi->repositorio()->perfilPorSlug((string)($_POST['perfil'] ?? ''));
        if (!$perfil) {
            $this->ir('');

            return;
        }
        if (!$this->temAparelho() && $perfil['tipo_acesso'] === 'cadastro') {
            $this->render('erro', ['mensagem' => 'Abra esta página conectado ao Wi-Fi para se cadastrar.']);

            return;
        }
        $r = $this->wifi->cadastrar((int)$perfil['id'], $_POST, $this->ctx());
        if (!$r['success']) {
            $this->cadastro(['erro' => $r['message'], 'dados' => $_POST]);

            return;
        }
        $this->seguir($r);
    }

    private function termos(): void
    {
        $pessoa = $this->pessoaDaSessao();
        if (!$pessoa) {
            $this->ir('');

            return;
        }
        $this->render('termos', ['termo' => $this->wifi->repositorio()->termoAtual((int)$pessoa['perfil_id']), 'pessoa' => $pessoa]);
    }

    private function aceitarTermos(): void
    {
        $pessoa = $this->pessoaDaSessao();
        if (!$pessoa || empty($_POST['aceite'])) {
            $this->ir($pessoa ? 'termos' : '');

            return;
        }
        $this->resultado($this->wifi->aceitarTermos((int)$pessoa['id'], $this->ctx()));
    }

    private function etapa(string $tela): void
    {
        $this->pessoaDaSessao() ? $this->render($tela) : $this->ir('');
    }

    private function salvarTrocaSenha(): void
    {
        $pessoa = $this->pessoaDaSessao();
        if (!$pessoa || empty($pessoa['trocar_senha'])) {
            $this->ir('');

            return;
        }
        $r = $this->wifi->trocarSenha((int)$pessoa['id'], (string)($_POST['senha'] ?? ''), (string)($_POST['confirmacao'] ?? ''), $this->ctx());
        if (!$r['success'] && $this->pessoaDaSessao()['trocar_senha']) {
            $this->render('trocar_senha', ['erro' => $r['message']]);

            return;
        }
        $this->resultado($r);
    }

    private function aparelhos(?string $erro = null): void
    {
        $pessoa = $this->pessoaDaSessao();
        if (!$pessoa) {
            $this->ir('');

            return;
        }
        $perfil = $this->wifi->repositorio()->perfil((int)$pessoa['perfil_id']);
        $this->render('aparelhos', [
            'sessoes' => $this->wifi->repositorio()->sessoesAtivas((int)$pessoa['id']),
            'max' => (int)$this->wifi->regras($perfil, $pessoa)['max_aparelhos'],
            'erro' => $erro,
        ]);
    }

    private function desconectarAparelho(): void
    {
        $pessoa = $this->pessoaDaSessao();
        if (!$pessoa) {
            $this->ir('');

            return;
        }
        $r = $this->wifi->desconectarMeuAparelho((int)$pessoa['id'], (int)($_POST['sessao_id'] ?? 0));
        if (!$r['success']) {
            $this->aparelhos($r['message']);

            return;
        }
        $this->resultado($this->wifi->continuarAcesso($pessoa, $this->ctx(), !empty($_SESSION['wifi']['lembrar'])));
    }

    private function resultado(array $r): void
    {
        $r['success'] ? $this->seguir($r) : $this->render('erro', ['mensagem' => $r['message'], 'voltar' => true]);
    }

    private function conectado(): void
    {
        $pessoa = $this->pessoaDaSessao();
        $this->render('conectado', [
            'nome' => $pessoa ? explode(' ', $pessoa['nome'])[0] : '',
            'url' => $_SESSION['wifi']['ctx']['url'] ?? '',
            'minutos' => (int)($_SESSION['wifi']['minutos'] ?? 0),
        ]);
    }

    private function enviarEsqueci(): void
    {
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->wifi->solicitarRedefinicao($email);
        }
        $this->render('esqueci', ['enviado' => true]);
    }

    private function redefinir(?string $erro = null): void
    {
        $token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
        $this->render('redefinir', ['token' => $token, 'valido' => (bool)$this->wifi->tokenRedefinicao($token), 'erro' => $erro]);
    }

    private function salvarRedefinicao(): void
    {
        $r = $this->wifi->redefinirSenha((string)($_POST['t'] ?? ''), (string)($_POST['senha'] ?? ''), (string)($_POST['confirmacao'] ?? ''));
        $r['success'] ? $this->render('redefinir', ['ok' => $r['message'], 'valido' => false, 'token' => '']) : $this->redefinir($r['message']);
    }

    private function privacidade(): void
    {
        $this->render('privacidade', [
            'encarregadoNome' => $this->config->get('encarregado_nome'),
            'encarregadoEmail' => $this->config->get('encarregado_email'),
            'retencao' => $this->config->int('retencao_dias'),
            'filtroDias' => $this->config->bool('filtro_ativo') ? $this->config->int('filtro_log_dias') : 0,
        ]);
    }

    private function naoEncontrado(): void
    {
        http_response_code(404);
        $this->render('erro', ['mensagem' => 'Página não encontrada.', 'voltar' => true]);
    }

    // ================================================================ saída

    private function ir(string $rota): void
    {
        header('Location: /guest/' . $rota);
        exit;
    }

    private function render(string $tela, array $dados = []): void
    {
        $titulo = $this->config->get('portal_titulo');
        $cor = preg_match('/^#[0-9a-fA-F]{6}$/', $this->config->get('portal_cor')) ? $this->config->get('portal_cor') : '#0d7b83';
        $boasVindas = $this->config->get('portal_boas_vindas');
        $csrf = $_SESSION['wifi']['csrf'];
        extract($dados);
        ob_start();
        require __DIR__ . '/../Views/wifi_portal/' . $tela . '.php';
        $conteudo = ob_get_clean();
        require __DIR__ . '/../Views/wifi_portal/_layout.php';
    }
}
