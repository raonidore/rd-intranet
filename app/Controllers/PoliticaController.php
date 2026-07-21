<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoCatalogoService;
use App\Services\NotificationService;
use App\Services\PoliticaService;

/** "Regras de Segurança" -- políticas locais de máquina entregues pelo agente Windows, sem depender de Microsoft Entra/Intune. */
class PoliticaController extends Controller
{
    private PoliticaService $service;

    public function __construct()
    {
        $this->service = new PoliticaService();
    }

    /** Tela de catálogo (app/Views/ativos/politicas.php) -- aplicar/remover uma regra em várias máquinas de uma vez. */
    public function index(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $catalogoService = new AtivoCatalogoService();

        $this->view('ativos/politicas', [
            'catalogo' => PoliticaService::CATALOGO,
            'maquinas' => $this->service->maquinasElegiveis(),
            'wallpaperInfo' => $this->service->wallpaperInfo(),
            'setores' => $catalogoService->listarSetores(),
            'recursosSetor' => $this->service->listarRecursosSetor(),
            'tiposRecurso' => PoliticaService::TIPOS_RECURSO_SETOR,
            'pacotes' => $this->service->listarPacotesSoftware(),
            'loginScriptInfo' => $this->service->loginScriptInfo(),
        ]);
    }

    public function loginScriptUpload(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $arquivo = $_FILES['arquivo'] ?? null;

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Falha no upload do arquivo.');
        } else {
            $this->service->salvarLoginScript($arquivo['tmp_name'], $arquivo['name']);
        }

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    public function loginScriptRemover(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $this->service->removerLoginScript();

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    /** Instala (envia o arquivo + registra a tarefa de logon) nas máquinas marcadas. */
    public function loginScriptInstalarEmLote(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');
        AuthMiddleware::checkModulo('ativos_novo');

        $ativoIds = array_map('intval', $_POST['ativos'] ?? []);
        $solicitadoPor = $_SESSION['usuario']['nome'] ?? null;

        if (empty($ativoIds)) {
            NotificationService::error('Selecione ao menos uma máquina.');
            header('Location: ' . url('/ativos/politicas'));
            exit;
        }

        $resultado = $this->service->instalarLoginScriptEmLote($ativoIds, $solicitadoPor);

        if ($resultado['success'] ?? false) {
            NotificationService::success("Script de login instalado em {$resultado['enviados']} máquina(s) -- confira o histórico de solicitações de cada uma.");
        } else {
            NotificationService::error($resultado['message'] ?? 'Falha ao instalar o script de login.');
        }

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    /** Desregistra a tarefa de logon (e apaga o .ps1) nas máquinas marcadas. */
    public function loginScriptRemoverEmLote(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');
        AuthMiddleware::checkModulo('ativos_novo');

        $ativoIds = array_map('intval', $_POST['ativos'] ?? []);
        $solicitadoPor = $_SESSION['usuario']['nome'] ?? null;

        if (empty($ativoIds)) {
            NotificationService::error('Selecione ao menos uma máquina.');
            header('Location: ' . url('/ativos/politicas'));
            exit;
        }

        $resultado = $this->service->removerLoginScriptEmLote($ativoIds, $solicitadoPor);

        NotificationService::success("Script de login removido de {$resultado['enviados']} máquina(s).");

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    public function pacoteUpload(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $arquivo = $_FILES['arquivo'] ?? null;
        $nome = trim((string)($_POST['nome'] ?? ''));
        $argumentos = trim((string)($_POST['argumentos'] ?? ''));
        $solicitadoPor = $_SESSION['usuario']['nome'] ?? null;

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Falha no upload do arquivo.');
        } else {
            $resultado = $this->service->criarPacoteSoftware($nome, $arquivo['tmp_name'], $arquivo['name'], $argumentos, $solicitadoPor);
            if (!($resultado['success'] ?? false)) {
                NotificationService::error($resultado['message'] ?? 'Falha ao adicionar o pacote.');
            }
        }

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    public function pacoteExcluir(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $this->service->excluirPacoteSoftware((int)($_POST['id'] ?? 0));

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    /** Instala UM pacote em N máquinas selecionadas -- exige ativos_novo também, igual toda ação que toca o agente/comandos. */
    public function pacoteInstalarEmLote(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');
        AuthMiddleware::checkModulo('ativos_novo');

        $pacoteId = (int)($_POST['pacote_id'] ?? 0);
        $ativoIds = array_map('intval', $_POST['ativos'] ?? []);
        $solicitadoPor = $_SESSION['usuario']['nome'] ?? null;

        if (empty($ativoIds)) {
            NotificationService::error('Selecione ao menos uma máquina.');
            header('Location: ' . url('/ativos/politicas'));
            exit;
        }

        $resultado = $this->service->instalarPacoteEmLote($pacoteId, $ativoIds, $solicitadoPor);

        if ($resultado['success'] ?? false) {
            NotificationService::success(
                "Instalação solicitada em {$resultado['enviados']} máquina(s) -- confira o resultado no histórico de solicitações de cada uma em alguns minutos."
            );
        } else {
            NotificationService::error($resultado['message'] ?? 'Falha ao instalar o pacote.');
        }

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    public function recursoNovo(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $resultado = $this->service->criarRecursoSetor(
            (int)($_POST['setor_id'] ?? 0),
            (string)($_POST['tipo'] ?? ''),
            trim((string)($_POST['nome_exibicao'] ?? '')),
            trim((string)($_POST['letra_unidade'] ?? '')) ?: null,
            trim((string)($_POST['caminho_unc'] ?? ''))
        );

        if (!($resultado['success'] ?? false)) {
            NotificationService::error($resultado['message'] ?? 'Falha ao adicionar o recurso.');
        }

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    public function recursoExcluir(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $this->service->excluirRecursoSetor((int)($_POST['id'] ?? 0));

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    public function wallpaperUpload(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $arquivo = $_FILES['arquivo'] ?? null;

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Falha no upload do arquivo.');
        } else {
            $this->service->salvarWallpaper($arquivo['tmp_name'], $arquivo['name']);
        }

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    public function wallpaperRemover(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $this->service->removerWallpaper();

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    /** Aplica ou remove UMA regra em N máquinas selecionadas (fogo-e-esquece, ver histórico de cada ativo depois). */
    public function aplicarEmLote(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');

        $regraId = (string)($_POST['regra_id'] ?? '');
        $ativoIds = array_map('intval', $_POST['ativos'] ?? []);
        $ativar = ($_POST['acao'] ?? '') === 'aplicar';
        $solicitadoPor = $_SESSION['usuario']['nome'] ?? null;

        if (empty($ativoIds)) {
            NotificationService::error('Selecione ao menos uma máquina.');
            header('Location: ' . url('/ativos/politicas'));
            exit;
        }

        $resultado = $this->service->aplicarEmLote($regraId, $ativoIds, $ativar, $solicitadoPor);

        if ($resultado['success'] ?? false) {
            NotificationService::success(
                ($ativar ? 'Aplicação' : 'Remoção') . " solicitada em {$resultado['enviados']} máquina(s) -- confira o resultado no histórico de solicitações de cada uma em alguns segundos."
            );
        } else {
            NotificationService::error($resultado['message'] ?? 'Falha ao aplicar a regra.');
        }

        header('Location: ' . url('/ativos/politicas'));
        exit;
    }

    /** POST vindo da seção "Regras de Segurança" da tela de detalhe do ativo -- salva o diff e dispara o script combinado. */
    public function salvarMaquina(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');
        header('Content-Type: application/json');

        $ativoId = (int)($_POST['ativo_id'] ?? 0);
        $regrasMarcadas = array_map('strval', $_POST['regras'] ?? []);
        $solicitadoPor = $_SESSION['usuario']['nome'] ?? null;

        echo json_encode($this->service->salvarEstadoMaquina($ativoId, $regrasMarcadas, $solicitadoPor));
    }

    /** Polling da tela de detalhe do ativo -- confirma o resultado da solicitação e devolve o estado atualizado por regra. */
    public function statusMaquina(): void
    {
        AuthMiddleware::checkModulo('ativos_politicas');
        header('Content-Type: application/json');

        $solicitacaoId = (int)($_GET['solicitacao_id'] ?? 0);
        $ativoId = (int)($_GET['ativo_id'] ?? 0);

        echo json_encode($this->service->confirmarResultado($solicitacaoId, $ativoId));
    }
}
