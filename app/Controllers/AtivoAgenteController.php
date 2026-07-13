<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;

/**
 * Endpoints usados pelo agente Windows -- NÃO passam por sessão/login,
 * a autenticação é por chave de API compartilhada (mesmo modelo do
 * "deploy key" do OCS Inventory/GLPI), enviada no header
 * X-RD-Agente-Chave. O download do script em si (baixarScript) é a
 * única ação aqui que exige sessão -- é o admin, pelo navegador, que
 * baixa o instalador pra distribuir.
 */
class AtivoAgenteController extends Controller
{
    private AtivoService $service;

    public function __construct()
    {
        $this->service = new AtivoService();
    }

    public function checkin(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if ($chaveEnviada === '' || !hash_equals($this->service->chaveAgente(), $chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Corpo JSON inválido.']);
            return;
        }

        echo json_encode($this->service->checkinAgente($payload));
    }

    public function baixarScript(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $template = file_get_contents(__DIR__ . '/../../scripts/agente/rd-intranet-agent.ps1');

        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $servidorUrl = $esquema . '://' . $host . url('');

        $conteudo = str_replace(
            ['__SERVER_URL__', '__API_KEY__', '__INTERVALO_MINUTOS__'],
            [$servidorUrl, $this->service->chaveAgente(), (string)$this->service->intervaloComunicacao()],
            $template
        );

        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="rd-intranet-agent.ps1"');
        echo $conteudo;
    }
}
