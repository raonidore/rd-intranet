<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\IptablesService;
use App\Services\IptablesTemplateService;
use App\Services\NotificationService;

class IptablesController extends Controller
{
    private IptablesService $service;

    public function __construct()
    {
        $this->service = new IptablesService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $regras = [];
        foreach ($this->service->listar() as $r) {
            $r['explicacao'] = $this->service->explicar($r);
            $regras[] = $r;
        }

        $this->view('infrastructure/iptables', [
            'regras' => $regras,
            'politicas' => [
                'INPUT' => $this->service->politicaAtual('INPUT'),
                'FORWARD' => $this->service->politicaAtual('FORWARD'),
                'OUTPUT' => $this->service->politicaAtual('OUTPUT'),
            ],
            'ultimoApplyEm' => $this->service->ultimoApplyEm(),
            'ultimoErro' => $this->service->ultimoErroApply(),
            'sshPortas' => $this->service->portasSshAtuais(),
            'painelPortas' => $this->service->portasPainelAtuais(),
            'panicoAtivo' => $this->service->panicoAtivo(),
            'sombreadas' => $this->service->detectarSombreadas(),
        ]);
    }

    public function aoVivo(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $estado = $this->service->estadoAoVivo();
        $contadores = $this->service->contadores();

        foreach ($estado['regras'] as &$r) {
            $c = $contadores[$r['tabela']][$r['cadeia']]['regras'][$r['indice'] - 1] ?? null;
            $r['pkts'] = $c['pkts'] ?? 0;
            $r['bytes'] = $c['bytes'] ?? 0;
        }
        unset($r);

        $this->view('infrastructure/iptables_ao_vivo', [
            'estado' => $estado,
            'topHits' => $this->service->topRegrasPorHits(),
            'rankingIps' => $this->service->rankingIpsBloqueados(),
        ]);
    }

    public function contadores(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        echo json_encode($this->service->contadores());
    }

    public function logsRegra(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        $id = (int)($_GET['id'] ?? 0);

        echo json_encode(['itens' => $this->service->logsRegra($id)]);
    }

    public function status(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');
        header('Content-Type: application/json');

        echo json_encode($this->service->statusRollback());
    }

    public function confirmar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');
        header('Content-Type: application/json');

        $resultado = $this->service->confirmar();

        if ($resultado['success']) {
            AuditService::registrar('Firewall', 'Confirmar', 'Alteração de firewall confirmada e persistida.');
        }

        echo json_encode($resultado);
    }

    public function reverterAgora(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');
        header('Content-Type: application/json');

        $resultado = $this->service->reverterAgora();

        AuditService::registrar('Firewall', 'Reverter manualmente', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function panicoAtivar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');
        header('Content-Type: application/json');

        $resultado = $this->service->ativarPanico();

        AuditService::registrar('Firewall', 'Ativar modo pânico', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function panicoDesativar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');
        header('Content-Type: application/json');

        $resultado = $this->service->desativarPanico();

        AuditService::registrar('Firewall', 'Desativar modo pânico', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function avaliarRisco(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');
        header('Content-Type: application/json');

        echo json_encode(['risco' => $this->service->avaliarRisco($this->dadosDoPost())]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $this->view('infrastructure/iptables_regra_form', [
            'regra' => null,
            'interfaces' => $this->service->interfacesValidas(),
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $nome = trim($_POST['nome'] ?? '');
        $resultado = $this->service->criar($this->dadosDoPost());

        if ($resultado['success']) {
            AuditService::registrar('Firewall', 'Criar regra', "Regra \"{$nome}\" criada (aguardando confirmação).");
        }
        $this->notificarEVoltar($resultado);
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $id = (int)($_GET['id'] ?? 0);
        $regra = $this->service->buscar($id);

        if (!$regra) {
            header('Location: ' . url('/infraestrutura/iptables'));
            exit;
        }

        $this->view('infrastructure/iptables_regra_form', [
            'regra' => $regra,
            'interfaces' => $this->service->interfacesValidas(),
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, $this->dadosDoPost());

        if ($resultado['success']) {
            AuditService::registrar('Firewall', 'Editar regra', "Regra #{$id} atualizada (aguardando confirmação).");
        }
        $this->notificarEVoltar($resultado);
    }

    public function excluirForm(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $id = (int)($_GET['id'] ?? 0);
        $regra = $this->service->buscar($id);

        if (!$regra) {
            header('Location: ' . url('/infraestrutura/iptables'));
            exit;
        }

        $this->view('infrastructure/iptables_regra_excluir', ['regra' => $regra]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $id = (int)($_POST['id'] ?? 0);
        $regra = $this->service->buscar($id);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Firewall', 'Excluir regra', "Regra #{$id} (\"" . ($regra['nome'] ?? '?') . "\") excluída (aguardando confirmação).");
        $this->notificarEVoltar($resultado);
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $id = (int)($_GET['id'] ?? 0);
        $resultado = $this->service->alternarAtivo($id, true);

        AuditService::registrar('Firewall', 'Ativar regra', "Regra #{$id} ativada.");
        $this->notificarEVoltar($resultado);
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $id = (int)($_GET['id'] ?? 0);
        $resultado = $this->service->alternarAtivo($id, false);

        AuditService::registrar('Firewall', 'Desativar regra', "Regra #{$id} desativada.");
        $this->notificarEVoltar($resultado);
    }

    public function mover(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $id = (int)($_GET['id'] ?? 0);
        $delta = ($_GET['direcao'] ?? '') === 'cima' ? -15 : 15;
        $resultado = $this->service->mover($id, $delta);

        $this->notificarEVoltar($resultado);
    }

    public function politicaSalvar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $resultado = $this->service->definirPoliticas([
            'INPUT' => $_POST['INPUT'] ?? '',
            'FORWARD' => $_POST['FORWARD'] ?? '',
            'OUTPUT' => $_POST['OUTPUT'] ?? '',
        ]);

        if ($resultado['success']) {
            AuditService::registrar('Firewall', 'Alterar política padrão', 'Políticas de chain atualizadas (aguardando confirmação).');
        }
        $this->notificarEVoltar($resultado);
    }

    public function exportar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $regras = array_map(function (array $r): array {
            unset($r['id'], $r['criado_em'], $r['atualizado_em']);
            return $r;
        }, $this->service->listar());

        AuditService::registrar('Firewall', 'Exportar regras', count($regras) . ' regra(s) exportada(s).');

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="firewall-regras-' . date('Y-m-d_His') . '.json"');
        echo json_encode($regras, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function importarForm(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $this->view('infrastructure/iptables_importar');
    }

    public function importar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        if (empty($_FILES['arquivo']['tmp_name']) || !is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
            NotificationService::error('Nenhum arquivo enviado.');
            header('Location: ' . url('/infraestrutura/iptables/importar'));
            exit;
        }

        $regras = json_decode((string)file_get_contents($_FILES['arquivo']['tmp_name']), true);

        if (!is_array($regras)) {
            NotificationService::error('Arquivo inválido: não é um JSON de regras reconhecível.');
            header('Location: ' . url('/infraestrutura/iptables/importar'));
            exit;
        }

        $resultado = $this->service->importarRegras($regras);

        if ($resultado['success']) {
            AuditService::registrar('Firewall', 'Importar regras', $resultado['message']);
        }
        $this->notificarEVoltar($resultado);
    }

    public function templates(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $this->view('infrastructure/iptables_templates', [
            'catalogo' => (new IptablesTemplateService())->catalogo(),
        ]);
    }

    public function templateForm(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $chave = $_GET['chave'] ?? '';
        $catalogo = (new IptablesTemplateService())->catalogo();

        if (!isset($catalogo[$chave])) {
            header('Location: ' . url('/infraestrutura/iptables/templates'));
            exit;
        }

        $this->view('infrastructure/iptables_template_form', [
            'chave' => $chave,
            'template' => $catalogo[$chave],
            'interfaces' => $this->service->interfacesValidas(),
            'sshPortas' => $this->service->portasSshAtuais(),
        ]);
    }

    public function templateAplicar(): void
    {
        AuthMiddleware::checkModulo('infra_iptables');

        $chave = $_POST['chave'] ?? '';
        $resultado = $this->service->aplicarTemplate($chave, $_POST);

        if ($resultado['success']) {
            AuditService::registrar('Firewall', 'Aplicar template', "Template \"{$chave}\" aplicado (aguardando confirmação).");
        }
        $this->notificarEVoltar($resultado);
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/infraestrutura/iptables'));
        exit;
    }

    private function dadosDoPost(): array
    {
        return [
            'nome' => trim($_POST['nome'] ?? ''),
            'descricao' => trim($_POST['descricao'] ?? ''),
            'tabela' => $_POST['tabela'] ?? 'filter',
            'cadeia' => $_POST['cadeia'] ?? 'INPUT',
            'acao' => $_POST['acao'] ?? 'ACCEPT',
            'protocolo' => $_POST['protocolo'] ?? 'tcp',
            'porta_destino' => trim($_POST['porta_destino'] ?? ''),
            'porta_origem' => trim($_POST['porta_origem'] ?? ''),
            'ip_origem' => trim($_POST['ip_origem'] ?? ''),
            'ip_destino' => trim($_POST['ip_destino'] ?? ''),
            'interface_entrada' => trim($_POST['interface_entrada'] ?? ''),
            'interface_saida' => trim($_POST['interface_saida'] ?? ''),
            'nat_destino' => trim($_POST['nat_destino'] ?? ''),
            'extra' => trim($_POST['extra'] ?? ''),
            'ordem' => (int)($_POST['ordem'] ?? 100),
            'ativo' => isset($_POST['ativo']),
            'registrar_log' => isset($_POST['registrar_log']),
        ];
    }
}
