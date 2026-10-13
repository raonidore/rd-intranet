<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtualizacaoService;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\Rede\RedeDrivers;
use App\Services\WifiConfigService;
use App\Services\WifiFiltroService;
use App\Services\WifiHttpsService;
use App\Services\WifiPerfilService;
use App\Services\WifiPortalService;
use App\Services\WifiRedeService;

/** Administração do portal Wi-Fi (TI/RH): conectados, liberações, cadastros, perfis, relatórios/LGPD e configurações. */
class WifiController extends Controller
{
    private WifiPortalService $wifi;
    private WifiConfigService $config;

    public function __construct()
    {
        $this->wifi = new WifiPortalService();
        $this->config = new WifiConfigService();
    }

    private function usuarioId(): int
    {
        return (int)($_SESSION['usuario']['id'] ?? 0);
    }

    private function voltar(string $rota, array $r): void
    {
        if (!preg_match('#^/wifi[\w/?=&.-]*$#', $rota)) {
            $rota = '/wifi';
        }
        $r['success'] ? NotificationService::success($r['message']) : NotificationService::error($r['message']);
        header('Location: ' . url($rota));
        exit;
    }

    private function exigirAlgum(array $modulos): void
    {
        foreach ($modulos as $m) {
            if (PermissionService::temAcesso($m)) {
                return;
            }
        }
        AuthMiddleware::checkModulo($modulos[0]);
    }

    // ================================================================ painel

    public function painel(): void
    {
        AuthMiddleware::checkModulo('wifi_painel');
        $repo = $this->wifi->repositorio();
        $this->view('wifi/painel', [
            'sessoes' => $repo->sessoesAtivas(),
            'contagem' => $repo->contarPorStatus(),
            'controlador' => RedeDrivers::controladorPortal(),
            'portalAtivo' => $this->config->bool('portal_ativo'),
            'resumoHoje' => $repo->resumoUso(date('Y-m-d'), date('Y-m-d')),
        ]);
    }

    public function derrubar(): void
    {
        AuthMiddleware::checkModulo('wifi_liberar');
        $this->voltar($_POST['voltar'] ?? '/wifi', $this->wifi->derrubarSessao((int)($_POST['sessao_id'] ?? 0), 'derrubada por ' . ($_SESSION['usuario']['nome'] ?? 'administrador')));
    }

    public function atualizarConsumo(): void
    {
        AuthMiddleware::checkModulo('wifi_painel');
        $r = $this->wifi->manutencao();
        $this->voltar('/wifi', ['success' => true, 'message' => "Atualizado: consumo de {$r['consumo']} sessão(ões), {$r['sessoes_encerradas']} encerrada(s)."]);
    }

    // ================================================================ liberações

    public function liberacoes(): void
    {
        AuthMiddleware::checkModulo('wifi_liberar');
        $this->view('wifi/liberacoes', [
            'pendentes' => $this->wifi->repositorio()->listarPessoas(['status' => 'pendente']),
        ]);
    }

    public function liberar(): void
    {
        AuthMiddleware::checkModulo('wifi_liberar');
        $this->voltar($_POST['voltar'] ?? '/wifi/liberacoes', $this->wifi->liberar((int)($_POST['id'] ?? 0), $this->usuarioId()));
    }

    public function recusar(): void
    {
        AuthMiddleware::checkModulo('wifi_liberar');
        $this->voltar($_POST['voltar'] ?? '/wifi/liberacoes', $this->wifi->recusar((int)($_POST['id'] ?? 0), (string)($_POST['motivo'] ?? '')));
    }

    public function bloquear(): void
    {
        AuthMiddleware::checkModulo('wifi_liberar');
        $this->voltar($_POST['voltar'] ?? '/wifi/pessoas', $this->wifi->bloquear((int)($_POST['id'] ?? 0), (string)($_POST['motivo'] ?? '')));
    }

    // ================================================================ cadastros

    public function pessoas(): void
    {
        $this->exigirAlgum(['wifi_pessoas', 'wifi_liberar']);
        $filtro = ['status' => $_GET['status'] ?? '', 'perfil_id' => $_GET['perfil_id'] ?? '', 'busca' => trim((string)($_GET['busca'] ?? ''))];
        $this->view('wifi/pessoas', [
            'pessoas' => $this->wifi->repositorio()->listarPessoas($filtro),
            'perfis' => $this->wifi->repositorio()->perfis(),
            'filtro' => $filtro,
            'contagem' => $this->wifi->repositorio()->contarPorStatus(),
        ]);
    }

    public function pessoaNova(): void
    {
        AuthMiddleware::checkModulo('wifi_pessoas');
        $this->view('wifi/pessoa_nova', ['perfis' => $this->wifi->repositorio()->perfis(true)]);
    }

    public function pessoaCriar(): void
    {
        AuthMiddleware::checkModulo('wifi_pessoas');
        $r = $this->wifi->cadastrarPeloAdmin($_POST, $this->usuarioId());
        $this->voltar($r['success'] ? '/wifi/pessoas/ver?id=' . $r['id'] : '/wifi/pessoas/nova', $r);
    }

    public function pessoaVer(): void
    {
        $this->exigirAlgum(['wifi_pessoas', 'wifi_liberar']);
        $id = (int)($_GET['id'] ?? 0);
        $repo = $this->wifi->repositorio();
        $pessoa = $repo->pessoa($id);
        if (!$pessoa) {
            $this->voltar('/wifi/pessoas', ['success' => false, 'message' => 'Cadastro não encontrado.']);
        }
        $perfil = $repo->perfil((int)$pessoa['perfil_id']);
        $filtroSvc = new WifiFiltroService();
        $this->view('wifi/pessoa_ver', [
            'pessoa' => $pessoa,
            'perfil' => $perfil,
            'perfis' => $repo->perfis(),
            'regras' => $this->wifi->regras($perfil, $pessoa),
            'excecoes' => json_decode((string)($pessoa['regras'] ?? ''), true) ?: [],
            'aparelhos' => $repo->aparelhos($id),
            'sessoes' => $repo->historicoSessoes(['pessoa_id' => $id], 50),
            'aceites' => $repo->aceitesDaPessoa($id),
            'servicos' => $filtroSvc->ativo() ? $filtroSvc->servicosDisponiveis() : [],
        ]);
    }

    public function pessoaEditar(): void
    {
        AuthMiddleware::checkModulo('wifi_pessoas');
        $id = (int)($_POST['id'] ?? 0);
        $p = $this->wifi->repositorio()->pessoa($id);
        if (!$p) {
            $this->voltar('/wifi/pessoas', ['success' => false, 'message' => 'Cadastro não encontrado.']);
        }
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->voltar('/wifi/pessoas/ver?id=' . $id, ['success' => false, 'message' => 'E-mail inválido.']);
        }
        $campos = [
            'nome' => trim((string)($_POST['nome'] ?? '')) ?: $p['nome'],
            'email' => $email ?: null,
            'telefone' => preg_replace('/\D/', '', (string)($_POST['telefone'] ?? '')) ?: null,
            'empresa' => trim((string)($_POST['empresa'] ?? '')) ?: null,
            'cnpj' => trim((string)($_POST['cnpj'] ?? '')) ?: null,
            'documento' => trim((string)($_POST['documento'] ?? '')) ?: null,
            'setor' => trim((string)($_POST['setor'] ?? '')) ?: null,
            'anfitriao' => trim((string)($_POST['anfitriao'] ?? '')) ?: null,
            'valido_ate' => !empty($_POST['valido_ate']) ? $_POST['valido_ate'] . ' 23:59:59' : null,
            'aviso_vencimento_em' => null,
        ];
        if (!empty($_POST['perfil_id']) && $this->wifi->repositorio()->perfil((int)$_POST['perfil_id'])) {
            $campos['perfil_id'] = (int)$_POST['perfil_id'];
        }
        if ($p['status'] === 'expirado' && $campos['valido_ate'] && strtotime($campos['valido_ate']) > time()) {
            $campos['status'] = 'ativo';
        }
        $this->wifi->repositorio()->atualizarPessoa($id, $campos);
        AuditService::registrar('Wi-Fi', 'Editar cadastro', "{$campos['nome']}" . ($campos['valido_ate'] ? ' -- válido até ' . date('d/m/Y', strtotime($campos['valido_ate'])) : ' -- sem vencimento') . '.');
        $this->voltar('/wifi/pessoas/ver?id=' . $id, ['success' => true, 'message' => 'Cadastro atualizado.']);
    }

    public function pessoaExcecoes(): void
    {
        AuthMiddleware::checkModulo('wifi_pessoas');
        $id = (int)($_POST['id'] ?? 0);
        $e = [];
        foreach (['validade_dias', 'cota_mb', 'max_aparelhos', 'lembrar_dias'] as $k) {
            $e[$k] = $_POST[$k] ?? '';
        }
        $e['sessao_minutos'] = ($_POST['sessao_horas'] ?? '') === '' ? '' : (int)$_POST['sessao_horas'] * 60;
        $e['banda_down_kbps'] = ($_POST['banda_down_mbps'] ?? '') === '' ? '' : (int)round((float)str_replace(',', '.', $_POST['banda_down_mbps']) * 1000);
        $e['banda_up_kbps'] = ($_POST['banda_up_mbps'] ?? '') === '' ? '' : (int)round((float)str_replace(',', '.', $_POST['banda_up_mbps']) * 1000);
        if (!empty($_POST['filtro_proprio'])) {
            $e['filtro'] = WifiPerfilService::filtroDoPost($_POST);
        }
        $this->voltar('/wifi/pessoas/ver?id=' . $id, $this->wifi->salvarExcecoes($id, $e));
    }

    public function pessoaSenha(): void
    {
        AuthMiddleware::checkModulo('wifi_pessoas');
        $id = (int)($_POST['id'] ?? 0);
        $this->voltar('/wifi/pessoas/ver?id=' . $id, $this->wifi->redefinirSenhaPeloAdmin($id));
    }

    public function aparelho(): void
    {
        AuthMiddleware::checkModulo('wifi_liberar');
        $repo = $this->wifi->repositorio();
        $ap = $repo->aparelho((int)($_POST['aparelho_id'] ?? 0));
        if (!$ap) {
            $this->voltar('/wifi/pessoas', ['success' => false, 'message' => 'Aparelho não encontrado.']);
        }
        $bloquear = ($_POST['acao'] ?? '') === 'bloquear';
        $repo->bloquearAparelho((int)$ap['id'], $bloquear);
        if ($bloquear && ($s = $repo->sessaoAtivaPorMac($ap['mac']))) {
            $this->wifi->derrubarSessao((int)$s['id'], 'aparelho bloqueado');
        }
        AuditService::registrar('Wi-Fi', $bloquear ? 'Bloquear aparelho' : 'Desbloquear aparelho', "Aparelho {$ap['mac']} (cadastro {$ap['pessoa_id']}).");
        $this->voltar('/wifi/pessoas/ver?id=' . $ap['pessoa_id'], ['success' => true, 'message' => $bloquear ? 'Aparelho bloqueado.' : 'Aparelho desbloqueado.']);
    }

    // ================================================================ LGPD (titular)

    public function pessoaExportar(): void
    {
        AuthMiddleware::checkModulo('wifi_relatorios');
        $id = (int)($_GET['id'] ?? 0);
        $dados = $this->wifi->exportarTitular($id);
        if (!$dados) {
            $this->voltar('/wifi/pessoas', ['success' => false, 'message' => 'Cadastro não encontrado.']);
        }
        AuditService::registrar('Wi-Fi', 'LGPD - exportar dados do titular', "Cadastro {$id} exportado.");
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="wifi-titular-' . $id . '-' . date('Ymd') . '.json"');
        echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function pessoaAnonimizar(): void
    {
        AuthMiddleware::checkModulo('wifi_relatorios');
        $this->voltar('/wifi/pessoas', $this->wifi->anonimizarTitular((int)($_POST['id'] ?? 0)));
    }

    // ================================================================ perfis

    public function perfis(): void
    {
        AuthMiddleware::checkModulo('wifi_regras');
        $repo = $this->wifi->repositorio();
        $perfis = $repo->perfis();
        foreach ($perfis as &$p) {
            $p['pessoas'] = $repo->contarPessoasDoPerfil((int)$p['id']);
        }
        $this->view('wifi/perfis', ['perfis' => $perfis]);
    }

    public function perfilForm(): void
    {
        AuthMiddleware::checkModulo('wifi_regras');
        $id = (int)($_GET['id'] ?? 0);
        $repo = $this->wifi->repositorio();
        $perfil = $id ? $repo->perfil($id) : null;
        $filtroSvc = new WifiFiltroService();
        $this->view('wifi/perfil_form', [
            'perfil' => $perfil,
            'termo' => $perfil ? $repo->termoAtual($id) : null,
            'servicos' => $filtroSvc->servicosDisponiveis(),
            'filtroAtivo' => $filtroSvc->ativo(),
        ]);
    }

    public function perfilSalvar(): void
    {
        AuthMiddleware::checkModulo('wifi_regras');
        $id = (int)($_POST['id'] ?? 0) ?: null;
        $r = (new WifiPerfilService())->salvar($id, $_POST, $this->usuarioId());
        $this->voltar($r['success'] ? '/wifi/perfis' : '/wifi/perfis/editar' . ($id ? '?id=' . $id : ''), $r);
    }

    public function perfilExcluir(): void
    {
        AuthMiddleware::checkModulo('wifi_regras');
        $this->voltar('/wifi/perfis', (new WifiPerfilService())->excluir((int)($_POST['id'] ?? 0)));
    }

    // ================================================================ relatórios

    public function relatorios(): void
    {
        AuthMiddleware::checkModulo('wifi_relatorios');
        $de = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['de'] ?? '')) ? $_GET['de'] : date('Y-m-d', strtotime('-30 days'));
        $ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['ate'] ?? '')) ? $_GET['ate'] : date('Y-m-d');
        $repo = $this->wifi->repositorio();
        $filtro = ['de' => $de, 'ate' => $ate, 'perfil_id' => $_GET['perfil_id'] ?? ''];
        if (($_GET['formato'] ?? '') === 'csv') {
            AuditService::registrar('Wi-Fi', 'Exportar registros de conexão', "Período {$de} a {$ate}.");
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="wifi-conexoes-' . $de . '-a-' . $ate . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Início', 'Fim', 'Perfil', 'Nome', 'E-mail', 'MAC', 'IP', 'SSID', 'Download (MB)', 'Upload (MB)', 'Encerramento'], ';');
            foreach ($repo->historicoSessoes($filtro, 100000) as $s) {
                fputcsv($out, [$s['inicio'], $s['fim'], $s['perfil_nome'], $s['anonimizado'] ? '(anonimizado)' : $s['pessoa_nome'], $s['pessoa_email'], $s['mac'], $s['ip'], $s['ssid'],
                    round($s['bytes_down'] / 1048576, 1), round($s['bytes_up'] / 1048576, 1), $s['motivo_fim']], ';');
            }
            exit;
        }
        $this->view('wifi/relatorios', [
            'filtro' => $filtro,
            'perfis' => $repo->perfis(),
            'resumo' => $repo->resumoUso($de, $ate),
            'sessoes' => $repo->historicoSessoes($filtro, 300),
            'retencao' => $this->config->int('retencao_dias'),
        ]);
    }

    public function sites(): void
    {
        AuthMiddleware::checkModulo('wifi_relatorios');
        $ip = filter_var($_GET['ip'] ?? '', FILTER_VALIDATE_IP) ?: null;
        $busca = trim((string)($_GET['busca'] ?? '')) ?: null;
        $filtroSvc = new WifiFiltroService();
        $erro = null;
        $lista = [];
        if (!$filtroSvc->ativo()) {
            $erro = 'O filtro de sites não está ativo (Wi-Fi > Configurações > Filtro).';
        } else {
            try {
                $lista = $filtroSvc->sitesVisitados($ip, 300, $busca);
            } catch (\Throwable $e) {
                $erro = $e->getMessage();
            }
        }
        AuditService::registrar('Wi-Fi', 'Consultar sites visitados', $ip ? "IP {$ip}." : ($busca ? "Busca \"{$busca}\"." : 'Últimas consultas.'));
        $this->view('wifi/sites', ['lista' => $lista, 'ip' => $ip, 'busca' => $busca, 'erro' => $erro, 'dias' => $this->config->int('filtro_log_dias')]);
    }

    // ================================================================ configurações

    public function configuracoes(): void
    {
        AuthMiddleware::checkModulo('wifi_configuracoes');
        $filtroSvc = new WifiFiltroService();
        $this->view('wifi/configuracoes', [
            'c' => $this->config,
            'filtroStatus' => $filtroSvc->status(),
            'servicos' => $filtroSvc->ativo() ? $filtroSvc->servicosDisponiveis() : [],
            'controladores' => RedeDrivers::CONTROLADORES,
            'temToken' => $this->config->segredo('cpanel_token_cifrado') !== null,
            'httpsAndamento' => ($_GET['aba'] ?? '') === 'https' ? (new WifiHttpsService())->concluirSeEmitido() : null,
            'aba' => $_GET['aba'] ?? 'portal',
        ]);
    }

    public function configuracoesSalvar(): void
    {
        AuthMiddleware::checkModulo('wifi_configuracoes');
        $aba = (string)($_POST['aba'] ?? 'portal');
        $c = $this->config;
        $msg = 'Configurações salvas.';
        switch ($aba) {
            case 'portal':
                $ligar = !empty($_POST['portal_ativo']);
                $c->set('portal_titulo', mb_substr(trim((string)($_POST['portal_titulo'] ?? '')), 0, 60) ?: 'Wi-Fi');
                $c->set('portal_boas_vindas', mb_substr(trim((string)($_POST['portal_boas_vindas'] ?? '')), 0, 300));
                $c->set('portal_cor', preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['portal_cor'] ?? '')) ? $_POST['portal_cor'] : '#0d7b83');
                $ip = trim((string)($_POST['ip_portal'] ?? ''));
                $c->set('ip_portal', filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '');
                if (isset(RedeDrivers::CONTROLADORES[$_POST['controlador'] ?? ''])) {
                    $c->set('controlador', $_POST['controlador']);
                }
                $c->set('tentativas_max', (string)max(3, (int)($_POST['tentativas_max'] ?? 5)));
                $c->set('tentativas_janela_min', (string)max(5, (int)($_POST['tentativas_janela_min'] ?? 15)));
                if ($ligar !== $c->bool('portal_ativo')) {
                    $r = (new WifiHttpsService())->publicarPortal($ligar);
                    if (!$r['success']) {
                        $this->voltar('/wifi/configuracoes?aba=portal', $r);
                    }
                    $c->set('portal_ativo', $ligar ? '1' : '0');
                    if ($ligar) {
                        (new AtualizacaoService())->garantirCronWifi();
                    }
                    $msg = $ligar ? 'Portal ligado e publicado em /guest.' : 'Portal desligado.';
                }
                break;
            case 'lgpd':
                $c->set('encarregado_nome', mb_substr(trim((string)($_POST['encarregado_nome'] ?? '')), 0, 100));
                $email = trim((string)($_POST['encarregado_email'] ?? ''));
                $c->set('encarregado_email', filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '');
                $c->set('retencao_dias', (string)max(180, (int)($_POST['retencao_dias'] ?? 365)));
                $c->set('filtro_log_dias', (string)max(1, min(90, (int)($_POST['filtro_log_dias'] ?? 7))));
                if ((int)($_POST['retencao_dias'] ?? 365) < 180) {
                    $msg = 'Salvo. Retenção mínima de 180 dias aplicada (o Marco Civil pede ao menos 6 meses para registros de acesso).';
                }
                break;
            case 'filtro':
                $c->set('filtro_ativo', !empty($_POST['filtro_ativo']) ? '1' : '0');
                $c->set('filtro_global', json_encode(WifiPerfilService::filtroDoPost($_POST), JSON_UNESCAPED_UNICODE));
                break;
            case 'https':
                $dominio = mb_strtolower(trim((string)($_POST['https_dominio'] ?? '')));
                if ($dominio !== '' && !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $dominio)) {
                    $this->voltar('/wifi/configuracoes?aba=https', ['success' => false, 'message' => 'Nome inválido.']);
                }
                if ($dominio !== $c->get('https_dominio')) {
                    $c->set('https_ativo', '0');
                }
                $c->set('https_dominio', $dominio);
                $c->set('cpanel_url', trim((string)($_POST['cpanel_url'] ?? '')) ?: 'https://cpanel.rd.inf.br');
                $c->set('cpanel_usuario', trim((string)($_POST['cpanel_usuario'] ?? '')) ?: 'rd');
                $c->set('cpanel_zona', mb_strtolower(trim((string)($_POST['cpanel_zona'] ?? ''))) ?: 'rd.inf.br');
                if (trim((string)($_POST['cpanel_token'] ?? '')) !== '') {
                    $c->salvarSegredo('cpanel_token_cifrado', strtoupper(trim($_POST['cpanel_token'])));
                }
                break;
        }
        AuditService::registrar('Wi-Fi', 'Configurações', "Aba \"{$aba}\" salva.");
        if (in_array($aba, ['filtro', 'lgpd', 'https'], true)) {
            try {
                (new WifiFiltroService())->sincronizar();
            } catch (\Throwable $e) {
                $msg .= ' Aviso: o filtro não sincronizou agora (' . $e->getMessage() . ').';
            }
        }
        $this->voltar('/wifi/configuracoes?aba=' . urlencode($aba), ['success' => true, 'message' => $msg]);
    }

    public function filtroInstalar(): void
    {
        AuthMiddleware::checkModulo('wifi_configuracoes');
        set_time_limit(300);
        $r = (new WifiFiltroService())->instalar();
        if ($r['success']) {
            try {
                (new WifiFiltroService())->sincronizar();
            } catch (\Throwable $e) {
                $r['message'] .= ' (sincronização: ' . $e->getMessage() . ')';
            }
        }
        $this->voltar('/wifi/configuracoes?aba=filtro', $r);
    }

    public function cpanelTestar(): void
    {
        AuthMiddleware::checkModulo('wifi_configuracoes');
        $this->voltar('/wifi/configuracoes?aba=https', (new WifiHttpsService())->testarCpanel());
    }

    public function httpsEmitir(): void
    {
        AuthMiddleware::checkModulo('wifi_configuracoes');
        $email = trim((string)($_POST['email'] ?? '')) ?: (string)($_SESSION['usuario']['email'] ?? '');
        $this->voltar('/wifi/configuracoes?aba=https', (new WifiHttpsService())->emitir($email));
    }

    public function httpsDesligar(): void
    {
        AuthMiddleware::checkModulo('wifi_configuracoes');
        $this->config->set('https_ativo', '0');
        AuditService::registrar('Wi-Fi', 'HTTPS do portal', 'Portal voltou a abrir por HTTP (IP).');
        $this->voltar('/wifi/configuracoes?aba=https', ['success' => true, 'message' => 'O portal volta a abrir pelo IP (HTTP). O certificado continua instalado.']);
    }

    // ================================================================ assistente de rede

    public function rede(): void
    {
        AuthMiddleware::checkModulo('wifi_configuracoes');
        $this->view('wifi/rede', [
            'diag' => (new WifiRedeService())->diagnostico(),
            'filtroAtivo' => (new WifiFiltroService())->ativo(),
            'resultado' => $_SESSION['wifi_rede_resultado'] ?? null,
        ]);
        unset($_SESSION['wifi_rede_resultado']);
    }

    public function redeAplicar(): void
    {
        AuthMiddleware::checkModulo('wifi_configuracoes');
        set_time_limit(300);
        $r = (new WifiRedeService())->aplicar($_POST);
        $_SESSION['wifi_rede_resultado'] = $r;
        $this->voltar('/wifi/rede', $r);
    }
}
