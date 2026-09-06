<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\ProjetoAreaService;
use App\Services\ProjetoComentarioService;
use App\Services\ProjetoFaseService;
use App\Services\ProjetoPainelTvTokenService;
use App\Services\ProjetoService;
use App\Services\ProjetoTarefaService;

/**
 * Modo TV -- link de exibição de longa duração pra uma área,
 * pensado pra ficar aberto o dia inteiro numa TV/monitor, sem
 * ninguém logado nela. `pagina()`/`dados()` são públicas de
 * propósito (sem AuthMiddleware), protegidas só pelo token --
 * mesma filosofia do Portal do Solicitante.
 */
class ProjetoPainelTvController extends Controller
{
    public function gerarLink(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $areaId = (int)($_POST['area_id'] ?? 0);
        $token = (new ProjetoPainelTvTokenService())->gerar($areaId, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Projetos', 'Gerar link do Modo TV', "área #{$areaId}");

        NotificationService::success('Link gerado: ' . url('/projetos/tv?token=' . $token));
        header('Location: ' . url('/projetos/areas'));
        exit;
    }

    public function revogar(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        (new ProjetoPainelTvTokenService())->revogar($id);

        AuditService::registrar('Projetos', 'Revogar link do Modo TV', "#{$id}");

        NotificationService::success('Link revogado.');
        header('Location: ' . url('/projetos/areas'));
        exit;
    }

    /** Página cheia, sem sidebar/topbar -- ver app/Views/layouts/tv.php. */
    public function pagina(): void
    {
        $token = (string)($_GET['token'] ?? '');
        $validado = (new ProjetoPainelTvTokenService())->validarToken($token);

        if (!$validado) {
            http_response_code(404);
            echo 'Link inválido ou revogado.';
            return;
        }

        $area = (new ProjetoAreaService())->buscar((int)$validado['area_id']);

        $this->view('projetos/tv', ['area' => $area, 'token' => $token]);
    }

    /** JSON que alimenta o auto-refresh (60s) + rotação (15s no cliente) do Modo TV. */
    public function dados(): void
    {
        header('Content-Type: application/json');

        $token = (string)($_GET['token'] ?? '');
        $validado = (new ProjetoPainelTvTokenService())->validarToken($token);

        if (!$validado) {
            echo json_encode(['success' => false, 'message' => 'Link inválido ou revogado.']);
            return;
        }

        $areaId = (int)$validado['area_id'];
        $projetoService = new ProjetoService();
        $faseService = new ProjetoFaseService();
        $tarefaService = new ProjetoTarefaService();
        $comentarioService = new ProjetoComentarioService();

        $projetos = array_map(function (array $projeto) use ($faseService, $tarefaService, $comentarioService) {
            $fases = array_map(function (array $fase) use ($tarefaService) {
                $fase['progresso'] = $tarefaService->progressoPorFase((int)$fase['id']);
                return $fase;
            }, $faseService->listar((int)$projeto['id']));

            $timeline = array_slice(array_reverse($comentarioService->timeline((int)$projeto['id'])), 0, 3);

            return [
                'id' => (int)$projeto['id'],
                'titulo' => $projeto['titulo'],
                'cliente' => $projeto['cliente'],
                'fases' => $fases,
                'resumo' => $tarefaService->resumo((int)$projeto['id']),
                'timeline' => $timeline,
            ];
        }, $projetoService->ativosPorArea($areaId));

        echo json_encode(['success' => true, 'projetos' => $projetos]);
    }
}
