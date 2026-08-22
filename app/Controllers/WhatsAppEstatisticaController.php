<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppConfigService;
use App\Services\WhatsAppEstatisticaService;
use App\Services\WhatsAppNpsService;

class WhatsAppEstatisticaController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_estatisticas');

        $estatistica = new WhatsAppEstatisticaService();
        $nps = new WhatsAppNpsService();
        $config = new WhatsAppConfigService();

        $aba = $_GET['aba'] ?? 'geral';
        $periodoGeral = in_array($_GET['periodo_geral'] ?? '', WhatsAppEstatisticaService::PERIODOS_GERAL, true)
            ? $_GET['periodo_geral'] : 'mes';
        $periodoRanking = in_array($_GET['periodo_ranking'] ?? '', WhatsAppEstatisticaService::PERIODOS_RANKING, true)
            ? $_GET['periodo_ranking'] : 'geral';

        $this->view('whatsapp/estatisticas', [
            'aba' => $aba,
            'periodoGeral' => $periodoGeral,
            'periodoRanking' => $periodoRanking,
            'geral' => $estatistica->geral($periodoGeral),
            'ranking' => $estatistica->ranking($periodoRanking),
            'tempoReal' => $estatistica->tempoReal(),
            'resumoNps' => $nps->resumo(),
            'npsMensagemEspera' => $config->npsMensagemEspera(),
            'npsPerguntaAtendente' => $config->npsPerguntaAtendente(),
            'npsPerguntaResolucao' => $config->npsPerguntaResolucao(),
            'npsAgradecimento' => $config->npsAgradecimento(),
            'npsTimeoutMinutos' => $config->npsTimeoutMinutos(),
        ]);
    }

    public function salvarNps(): void
    {
        AuthMiddleware::checkModulo('whatsapp_estatisticas');

        $resultado = (new WhatsAppConfigService())->salvarNps(
            $_POST['mensagem_espera'] ?? '',
            $_POST['pergunta_atendente'] ?? '',
            $_POST['pergunta_resolucao'] ?? '',
            $_POST['agradecimento'] ?? '',
            (int)($_POST['timeout_minutos'] ?? 0)
        );

        AuditService::registrar('WhatsApp', 'NPS - mensagens', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/whatsapp/estatisticas?aba=nps'));
        exit;
    }
}
