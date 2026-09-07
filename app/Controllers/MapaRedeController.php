<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\MapaRedeService;
use App\Services\NotificationService;

class MapaRedeController extends Controller
{
    private MapaRedeService $service;

    public function __construct()
    {
        $this->service = new MapaRedeService();
    }

    private function usuarioId(): ?int
    {
        return isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_rede_mapa');

        $this->view('infrastructure/mapa_rede_lista', [
            'mapas' => $this->service->listar(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('infra_rede_mapa');

        $this->view('infrastructure/mapa_rede_novo', []);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('infra_rede_mapa');

        $id = $this->service->criar(
            trim($_POST['nome'] ?? ''),
            trim($_POST['descricao'] ?? ''),
            $this->usuarioId()
        );

        NotificationService::success('Mapa criado.');
        header('Location: ' . url('/infraestrutura/rede/mapa/ver?id=' . $id));
        exit;
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('infra_rede_mapa');

        $id = (int)($_GET['id'] ?? 0);
        $mapa = $this->service->buscar($id);

        if (!$mapa) {
            NotificationService::error('Mapa não encontrado.');
            header('Location: ' . url('/infraestrutura/rede/mapa'));
            exit;
        }

        $this->view('infrastructure/mapa_rede_editor', [
            'mapa' => $mapa,
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkModulo('infra_rede_mapa');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $dados = json_decode($_POST['dados'] ?? '', true);

        if (!is_array($dados)) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
            return;
        }

        $ok = $this->service->salvarDados($id, $dados);

        echo json_encode(['success' => $ok, 'message' => $ok ? '' : 'Falha ao salvar (formato inválido).']);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('infra_rede_mapa');

        $this->service->excluir((int)($_POST['id'] ?? 0));

        NotificationService::success('Mapa excluído.');
        header('Location: ' . url('/infraestrutura/rede/mapa'));
        exit;
    }

    public function renomear(): void
    {
        AuthMiddleware::checkModulo('infra_rede_mapa');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $ok = $this->service->renomear($id, trim($_POST['nome'] ?? ''), trim($_POST['descricao'] ?? ''));

        echo json_encode(['success' => $ok]);
    }

    public function listarNomes(): void
    {
        AuthMiddleware::checkModulo('infra_rede_mapa');
        header('Content-Type: application/json');

        $mapas = array_map(fn (array $m) => ['id' => $m['id'], 'nome' => $m['nome']], $this->service->listar());

        echo json_encode($mapas);
    }

    /** Recebido do IP Scanner: cria (se novo) e importa os hosts pro mapa. */
    public function importar(): void
    {
        AuthMiddleware::checkModulo('infra_rede_mapa');
        header('Content-Type: application/json');

        $mapaId = (int)($_POST['mapa_id'] ?? 0);
        $nomeNovo = trim($_POST['nome_novo'] ?? '');
        $hosts = json_decode($_POST['hosts'] ?? '', true);

        if (!is_array($hosts)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum dispositivo pra importar.']);
            return;
        }

        if ($mapaId <= 0) {
            if ($nomeNovo === '') {
                echo json_encode(['success' => false, 'message' => 'Informe um nome pro novo mapa.']);
                return;
            }
            $mapaId = $this->service->criar($nomeNovo, null, $this->usuarioId());
        }

        $resultado = $this->service->importarDoScan($mapaId, $hosts);
        $resultado['mapa_id'] = $mapaId;

        echo json_encode($resultado);
    }
}
