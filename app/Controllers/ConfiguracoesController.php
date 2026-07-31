<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ConfigBackupService;
use App\Services\ConfigRestauracaoService;

/**
 * Sistema > Configurações: backup e restauração total (banco + arquivos
 * críticos do SO) -- disaster recovery. Fora do ModuloCatalogo, mesmo
 * padrão de EmailConfigController: só admin, checkAdmin() em todo método.
 */
class ConfiguracoesController extends Controller
{
    private ConfigBackupService $backupService;
    private ConfigRestauracaoService $restauracaoService;

    public function __construct()
    {
        $this->backupService = new ConfigBackupService();
        $this->restauracaoService = new ConfigRestauracaoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/configuracoes', [
            'historico' => $this->backupService->historico(),
            'agendamento' => $this->backupService->agendamentoAtual(),
            'senhaAgendadaConfigurada' => $this->backupService->senhaAgendadaConfigurada(),
        ]);
    }

    public function gerar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $senha = (string)($_POST['senha'] ?? '');
        $resultado = $this->backupService->gerar($senha, 'manual', $_SESSION['usuario']['id'] ?? null);

        AuditService::registrar('Sistema', 'Gerar backup de configuração', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function gerarStatus(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $execucaoId = (int)($_GET['execucao_id'] ?? 0);

        echo json_encode($this->backupService->status($execucaoId));
    }

    public function download(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_GET['id'] ?? 0);
        $caminho = $this->backupService->caminhoArquivo($id);

        if ($caminho === null) {
            http_response_code(404);
            echo 'Arquivo não encontrado.';
            return;
        }

        AuditService::registrar('Sistema', 'Baixar backup de configuração', basename($caminho));

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($caminho) . '"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }

    public function agendarSalvar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $expressao = (string)($_POST['expressao'] ?? '');
        $senhaAgendada = (string)($_POST['senha_agendada'] ?? '');

        $resultado = $this->backupService->agendar($expressao, $senhaAgendada);

        AuditService::registrar('Sistema', 'Agendar backup de configuração', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function agendarExcluir(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $resultado = $this->backupService->desagendar();

        AuditService::registrar('Sistema', 'Remover agendamento de backup de configuração', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function restaurarUpload(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $arquivo = $_FILES['arquivo'] ?? null;
        $senha = (string)($_POST['senha'] ?? '');
        $confirmacao = trim((string)($_POST['confirmacao'] ?? ''));

        if ($confirmacao !== 'RESTAURAR') {
            echo json_encode(['success' => false, 'message' => 'Digite RESTAURAR no campo de confirmação pra prosseguir.']);
            return;
        }

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            $mensagem = $arquivo ? self::mensagemErroUpload((int)$arquivo['error']) : 'Nenhum arquivo enviado.';
            echo json_encode(['success' => false, 'message' => $mensagem]);
            return;
        }

        $resultado = $this->restauracaoService->iniciar($arquivo['tmp_name'], $senha, $_SESSION['usuario']['id'] ?? null);

        echo json_encode($resultado);
    }

    public function restaurarStatus(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $execucaoId = (string)($_GET['execucao_id'] ?? '');

        echo json_encode($this->restauracaoService->status($execucaoId));
    }

    public function restaurarFinalizar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $execucaoId = (string)($_POST['execucao_id'] ?? '');

        echo json_encode($this->restauracaoService->finalizar($execucaoId));
    }
}
