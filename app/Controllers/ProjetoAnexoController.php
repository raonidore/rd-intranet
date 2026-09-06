<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\ProjetoAnexoService;
use App\Services\ProjetoService;
use App\Services\SambaAnexoService;

class ProjetoAnexoController extends Controller
{
    private ProjetoAnexoService $service;
    private ProjetoService $projetoService;

    public function __construct()
    {
        $this->service = new ProjetoAnexoService();
        $this->projetoService = new ProjetoService();
    }

    /** Garante que quem está mexendo no anexo realmente enxerga o projeto dono dele -- senão sai pra fora. */
    private function garantirAcesso(int $projetoId): void
    {
        $projeto = $this->projetoService->buscar($projetoId);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin() || PermissionService::temAcesso('projetos_gerenciar');

        if (!$projeto || !$this->projetoService->ehVisivelPara($projeto, $usuarioId, $ehAdmin)) {
            NotificationService::error('Você não tem acesso a esse projeto.');
            header('Location: ' . url('/projetos'));
            exit;
        }
    }

    public function upload(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $tarefaId = !empty($_POST['tarefa_id']) ? (int)$_POST['tarefa_id'] : null;
        $resultado = $this->service->anexarUpload($projetoId, $tarefaId, $_FILES['arquivo'] ?? [], (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Projetos', 'Anexo (upload)', "projeto #{$projetoId}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    public function samba(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $tarefaId = !empty($_POST['tarefa_id']) ? (int)$_POST['tarefa_id'] : null;
        $compartilhamentoId = (int)($_POST['compartilhamento_id'] ?? 0);
        $subcaminho = (string)($_POST['subcaminho'] ?? '');
        $nomeArquivo = (string)($_POST['nome_arquivo'] ?? '');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();
        $sambaService = new SambaAnexoService();

        if (!$sambaService->podeAcessarCompartilhamento($compartilhamentoId, $usuarioId, $ehAdmin)) {
            NotificationService::error('Você não tem acesso a esse compartilhamento.');
            header('Location: ' . url('/projetos/ver?id=' . $projetoId));
            exit;
        }

        $compartilhamento = $sambaService->buscarCompartilhamento($compartilhamentoId);
        $subcaminhoValidado = $sambaService->validarSubcaminho($subcaminho);
        $nomeArquivoValidado = $sambaService->validarSubcaminho($nomeArquivo);

        if (!$compartilhamento || $subcaminhoValidado === null || $nomeArquivoValidado === null || $nomeArquivoValidado === '') {
            NotificationService::error('Arquivo inválido.');
            header('Location: ' . url('/projetos/ver?id=' . $projetoId));
            exit;
        }

        $caminhoCompleto = $sambaService->caminhoParaAnexo($compartilhamento, $subcaminhoValidado, $nomeArquivoValidado);
        $resultado = $this->service->anexarSamba($projetoId, $tarefaId, $caminhoCompleto, basename($nomeArquivoValidado), $usuarioId);

        AuditService::registrar('Projetos', 'Anexo (Samba)', "projeto #{$projetoId}: {$resultado['message']} ({$caminhoCompleto})");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $anexoId = (int)($_POST['anexo_id'] ?? 0);
        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $resultado = $this->service->excluirAnexo($anexoId, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Projetos', 'Excluir anexo', "projeto #{$projetoId}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    public function renomear(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $anexoId = (int)($_POST['anexo_id'] ?? 0);
        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $novoNome = (string)($_POST['novo_nome'] ?? '');
        $resultado = $this->service->renomearAnexo($anexoId, $novoNome, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Projetos', 'Renomear anexo', "projeto #{$projetoId}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    /** `modo=inline` é usado pelo pop-up de visualização (PDF/imagem); sem isso, força download. */
    public function baixar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $anexoId = (int)($_GET['anexo_id'] ?? 0);
        $anexo = $this->service->buscarAnexo($anexoId);

        if (!$anexo) {
            http_response_code(404);
            return;
        }

        $projeto = $this->projetoService->buscar((int)$anexo['projeto_id']);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin() || PermissionService::temAcesso('projetos_gerenciar');
        if (!$projeto || !$this->projetoService->ehVisivelPara($projeto, $usuarioId, $ehAdmin)) {
            http_response_code(403);
            return;
        }

        $inline = ($_GET['modo'] ?? '') === 'inline';
        $disposicao = $inline ? 'inline' : 'attachment';

        if ($anexo['anexo_origem'] === 'upload') {
            $caminho = ProjetoAnexoService::caminhoCompletoUpload($anexo['anexo_caminho']);
            if (!is_file($caminho)) {
                http_response_code(404);
                return;
            }
            header('Content-Type: ' . (mime_content_type($caminho) ?: 'application/octet-stream'));
            header('Content-Disposition: ' . $disposicao . '; filename="' . basename($anexo['anexo_nome_original']) . '"');
            header('Content-Length: ' . filesize($caminho));
            readfile($caminho);
            return;
        }

        $contentType = $inline
            ? ProjetoAnexoService::mimetypeParaVisualizar(pathinfo($anexo['anexo_nome_original'], PATHINFO_EXTENSION))
            : 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: ' . $disposicao . '; filename="' . rawurlencode($anexo['anexo_nome_original']) . '"');
        header('Cache-Control: no-cache');
        (new SambaAnexoService())->servirArquivo($anexo['anexo_caminho']);
    }
}
