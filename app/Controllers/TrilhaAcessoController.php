<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AcessoMaquinaService;
use App\Services\AtivoService;
use App\Services\AtualizacaoService;
use App\Services\AuditService;
use App\Services\EntraService;
use App\Services\PdfTabelaService;
use App\Services\PlanilhaXlsxService;

/**
 * Segurança > Trilha de acesso: quem entrou em qual máquina e quando
 * (log do Windows via agente + registro de entradas do Entra), com
 * exportação em PDF/Excel pra evidência de auditoria.
 */
class TrilhaAcessoController extends Controller
{
    private AcessoMaquinaService $service;

    public function __construct()
    {
        $this->service = new AcessoMaquinaService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('seguranca_trilha_acesso');

        // Servidor atualizado por git pull não passa pelo AtualizacaoService -- garante o cron aqui.
        (new AtualizacaoService())->garantirCronTrilhaAcesso();

        $filtros = $this->filtros();

        $this->view('seguranca/trilha_acesso', [
            'filtros' => $filtros,
            'linhas' => $this->service->listar($filtros),
            'totais' => $this->service->contar($filtros),
            'maquinas' => (new AtivoService())->listar(['tipo' => 'computador']),
            'comTrilha' => $this->service->maquinasComTrilha(),
            'entraConfigurado' => (new EntraService())->configurado(),
            'retencaoAnos' => $this->service->retencaoAnos(),
        ]);
    }

    public function exportar(): void
    {
        AuthMiddleware::checkModulo('seguranca_trilha_acesso');

        $filtros = $this->filtros();
        $formato = ($_GET['formato'] ?? '') === 'pdf' ? 'pdf' : 'xlsx';
        $linhas = $this->service->listar($filtros, 50000);
        $tabela = $this->service->tabela($linhas);
        $descricao = $this->descreverFiltros($filtros) . ' · ' . count($linhas) . ' registro(s) · gerado em ' . date('d/m/Y H:i');

        AuditService::registrar('Segurança', 'Exportar trilha de acesso', strtoupper($formato) . " -- {$descricao}.");

        $nomeArquivo = 'trilha-acesso-' . $filtros['de'] . '-a-' . $filtros['ate'] . '.' . $formato;
        if ($formato === 'pdf') {
            $conteudo = (new PdfTabelaService())->gerar('Trilha de acesso às máquinas', $descricao, (new AtivoService())->nomeEmpresa(), $tabela['cabecalho'], $tabela['linhas']);
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $nomeArquivo . '"');
        } else {
            $conteudo = (new PlanilhaXlsxService())->gerar('Trilha de acesso', $tabela['cabecalho'], $tabela['linhas'], [], 'Trilha de acesso às máquinas -- ' . $descricao);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        }
        header('Content-Length: ' . strlen($conteudo));
        echo $conteudo;
        exit;
    }

    private function filtros(): array
    {
        $data = fn($v, $padrao) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? (string)$v : $padrao;

        return [
            'de' => $data($_GET['de'] ?? '', date('Y-m-d', strtotime('-6 days'))),
            'ate' => $data($_GET['ate'] ?? '', date('Y-m-d')),
            'ativo_id' => (int)($_GET['ativo_id'] ?? 0) ?: null,
            'usuario' => trim((string)($_GET['usuario'] ?? '')),
            'evento' => (string)($_GET['evento'] ?? ''),
            'origem' => (string)($_GET['origem'] ?? ''),
        ];
    }

    private function descreverFiltros(array $f): string
    {
        $partes = ['Período ' . date('d/m/Y', strtotime($f['de'])) . ' a ' . date('d/m/Y', strtotime($f['ate']))];
        if ($f['ativo_id']) {
            $ativo = (new AtivoService())->buscar($f['ativo_id']);
            $partes[] = 'Máquina ' . ($ativo['nome'] ?? "#{$f['ativo_id']}");
        }
        if ($f['usuario'] !== '') {
            $partes[] = "Usuário contém \"{$f['usuario']}\"";
        }
        if (isset(AcessoMaquinaService::EVENTOS[$f['evento']])) {
            $partes[] = 'Evento ' . AcessoMaquinaService::EVENTOS[$f['evento']];
        }
        if ($f['origem'] !== '') {
            $partes[] = 'Origem ' . ($f['origem'] === 'entra' ? 'Entra ID' : 'Windows');
        }

        return implode(' · ', $partes);
    }
}
