<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\ChamadoEstatisticaService;

class ChamadoEstatisticaController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('chamados_estatisticas');

        $estatistica = new ChamadoEstatisticaService();

        $aba = $_GET['aba'] ?? 'geral';
        $periodoGeral = in_array($_GET['periodo_geral'] ?? '', ChamadoEstatisticaService::PERIODOS_GERAL, true)
            ? $_GET['periodo_geral'] : 'mes';
        $periodoRanking = in_array($_GET['periodo_ranking'] ?? '', ChamadoEstatisticaService::PERIODOS_RANKING, true)
            ? $_GET['periodo_ranking'] : 'geral';

        $this->view('chamados/estatisticas', [
            'aba' => $aba,
            'periodoGeral' => $periodoGeral,
            'periodoRanking' => $periodoRanking,
            'geral' => $estatistica->geral($periodoGeral),
            'ranking' => $estatistica->ranking($periodoRanking),
            'tempoReal' => $estatistica->tempoReal(),
        ]);
    }

    /** Atualização periódica do painel "tela cheia" (aba Em tempo real), sem recarregar a página. */
    public function tempoRealApi(): void
    {
        AuthMiddleware::checkModulo('chamados_estatisticas');
        header('Content-Type: application/json');

        echo json_encode(['success' => true, 'tempoReal' => (new ChamadoEstatisticaService())->tempoReal()]);
    }
}
