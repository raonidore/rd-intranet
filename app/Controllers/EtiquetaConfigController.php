<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;
use App\Services\EtiquetaService;

class EtiquetaConfigController extends Controller
{
    private EtiquetaService $service;
    private AtivoService $ativoService;

    public function __construct()
    {
        $this->service = new EtiquetaService();
        $this->ativoService = new AtivoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('ativos_etiqueta_config');

        $config = $this->service->configuracao();
        $ativo = $this->ativoAmostra();

        $this->view('ativos/etiqueta_config', [
            'config' => $config,
            'camposDisponiveis' => EtiquetaService::CAMPOS_DISPONIVEIS,
            'previewHtml' => $this->service->gerarPreviewHtml($config, $ativo),
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkModulo('ativos_etiqueta_config');

        $this->service->salvarConfiguracao($_POST);

        header('Location: ' . url('/ativos/etiqueta-config'));
        exit;
    }

    /** Pré-visualização ao vivo (AJAX) -- usa os valores atuais do formulário, antes de salvar. */
    public function preview(): void
    {
        AuthMiddleware::checkModulo('ativos_etiqueta_config');
        header('Content-Type: application/json');

        $config = [
            'largura_mm' => (float)str_replace(',', '.', $_POST['largura_mm'] ?? '55'),
            'altura_mm' => (float)str_replace(',', '.', $_POST['altura_mm'] ?? '25'),
            'dpi' => (int)($_POST['dpi'] ?? 203),
            'campos' => array_values(array_intersect((array)($_POST['campos'] ?? []), array_keys(EtiquetaService::CAMPOS_DISPONIVEIS))),
        ];

        if ($config['largura_mm'] <= 0) $config['largura_mm'] = 55;
        if ($config['altura_mm'] <= 0) $config['altura_mm'] = 25;

        echo json_encode(['success' => true, 'html' => $this->service->gerarPreviewHtml($config, $this->ativoAmostra())]);
    }

    /** Ativo real mais recente pra pré-visualização ficar parecida com o uso de verdade; se não houver nenhum, um exemplo fixo. */
    private function ativoAmostra(): array
    {
        $ativos = $this->ativoService->listar();

        if (!empty($ativos)) {
            return $ativos[0];
        }

        return [
            'id' => 0,
            'codigo_patrimonio' => 'RD-PC-000001',
            'tipo' => 'computador',
            'nome' => 'EXEMPLO-PC',
            'apelido' => 'Notebook de Exemplo',
            'setor_nome' => 'TI',
            'localizacao_nome' => 'Sala 1',
        ];
    }
}
