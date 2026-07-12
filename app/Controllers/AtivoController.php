<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\CronService;

class AtivoController extends Controller
{
    private AtivoService $service;

    public function __construct()
    {
        $this->service = new AtivoService();
    }

    public function dashboard(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $this->view('ativos/dashboard', array_merge($this->service->dashboard(), [
            'comunidadePadrao' => $this->service->comunidadePadrao(),
            'coletaSnmpAtiva' => $this->coletaSnmpAtiva(),
        ]));
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');

        $filtros = [
            'tipo' => $_GET['tipo'] ?? '',
            'status' => $_GET['status'] ?? '',
            'busca' => trim($_GET['busca'] ?? ''),
        ];

        $this->view('ativos/lista', [
            'ativos' => $this->service->listar($filtros),
            'filtros' => $filtros,
        ]);
    }

    public function verForm(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');

        $id = (int)($_GET['id'] ?? 0);
        $ativo = $this->service->buscar($id);

        if (!$ativo) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $this->view('ativos/ver', [
            'ativo' => $ativo,
            'programas' => $this->service->listarProgramas($id),
            'alertas' => $this->service->listarAlertas($id),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $this->view('ativos/form', [
            'ativo' => null,
            'tipoSelecionado' => $_GET['tipo'] ?? 'computador',
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = $this->service->criar($_POST);

        header('Location: ' . url($id ? '/ativos/ver?id=' . $id : '/ativos/novo'));
        exit;
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_GET['id'] ?? 0);
        $ativo = $this->service->buscar($id);

        if (!$ativo) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $this->view('ativos/form', [
            'ativo' => $ativo,
            'tipoSelecionado' => $ativo['tipo'],
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_POST['id'] ?? 0);

        $this->service->editar($id, $_POST);

        header('Location: ' . url('/ativos/ver?id=' . $id));
        exit;
    }

    public function excluirForm(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_GET['id'] ?? 0);
        $ativo = $this->service->buscar($id);

        if (!$ativo) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $this->view('ativos/excluir', ['ativo' => $ativo]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_POST['id'] ?? 0);

        $this->service->excluir($id);

        header('Location: ' . url('/ativos'));
        exit;
    }

    public function etiqueta(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');

        $id = (int)($_GET['id'] ?? 0);
        $ativo = $this->service->buscar($id);

        if (!$ativo) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $this->view('ativos/etiqueta', [
            'ativos' => [$ativo],
            'qrCodes' => [$id => $this->service->gerarEtiquetaQrCodeBase64($id)],
        ]);
    }

    public function etiquetasLote(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');

        $ids = array_map('intval', $_GET['ids'] ?? []);
        $ids = array_filter($ids);

        if (empty($ids)) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $ativos = $this->service->buscarPorIds($ids);

        $qrCodes = [];
        foreach ($ativos as $ativo) {
            $qrCodes[$ativo['id']] = $this->service->gerarEtiquetaQrCodeBase64((int)$ativo['id']);
        }

        $this->view('ativos/etiqueta', [
            'ativos' => $ativos,
            'qrCodes' => $qrCodes,
        ]);
    }

    public function coletarSnmp(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->coletarSnmp($id);

        echo json_encode($resultado);
    }

    public function salvarConfigSnmp(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $this->service->salvarComunidadePadrao(trim($_POST['comunidade'] ?? 'public'));

        AuditService::registrar('Ativos', 'Config. SNMP', 'Community padrão de SNMP atualizada.');

        header('Location: ' . url('/ativos'));
        exit;
    }

    public function ativarColetaSnmp(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');
        header('Content-Type: application/json');

        if ($this->coletaSnmpAtiva()) {
            echo json_encode(['success' => true, 'message' => 'Coleta já estava ativa.']);
            return;
        }

        $resultado = (new CronService())->criar([
            'nome' => $this->service->nomeJobCronSnmp(),
            'descricao' => 'Coleta dados via SNMP dos ativos com essa opção habilitada (Ativos de TI).',
            'expressao' => '*/30 * * * *',
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd ativos:coletar-snmp',
            'ativo' => true,
        ]);

        AuditService::registrar('Ativos', 'Ativar coleta SNMP', $resultado['message']);

        echo json_encode($resultado);
    }

    private function coletaSnmpAtiva(): bool
    {
        foreach ((new CronService())->listar() as $job) {
            if ($job['nome'] === $this->service->nomeJobCronSnmp()) {
                return true;
            }
        }

        return false;
    }
}
