<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\DocumentoCategoriaService;
use App\Services\DocumentoPermissaoService;
use App\Services\DocumentoService;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\SambaAnexoService;

class DocumentoController extends Controller
{
    private DocumentoService $service;
    private DocumentoCategoriaService $categoriaService;
    private DocumentoPermissaoService $permissaoService;

    public function __construct()
    {
        $this->service = new DocumentoService();
        $this->categoriaService = new DocumentoCategoriaService();
        $this->permissaoService = new DocumentoPermissaoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();
        $visiveis = $this->permissaoService->categoriasVisiveis($usuarioId, $ehAdmin);

        $categorias = array_values(array_filter(
            $this->categoriaService->listarAtivas(),
            fn($c) => in_array((int)$c['id'], $visiveis, true)
        ));

        $this->view('documentos/index', ['categorias' => $categorias]);
    }

    public function categoria(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');

        $categoriaId = (int)($_GET['id'] ?? 0);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();
        $permissao = $this->permissaoService->efetiva($categoriaId, $usuarioId, $ehAdmin);

        $categoria = $this->categoriaService->buscar($categoriaId);

        if (!$categoria || !$permissao['visualizar']) {
            header('Location: ' . url('/documentos'));
            exit;
        }

        $this->view('documentos/categoria', [
            'categoria' => $categoria,
            'documentos' => $this->service->listarPorCategoria($categoriaId),
            'permissao' => $permissao,
        ]);
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');

        $id = (int)($_GET['id'] ?? 0);
        $documento = $this->service->buscar($id);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();

        if (!$documento) {
            header('Location: ' . url('/documentos'));
            exit;
        }

        $permissao = $this->permissaoService->efetiva((int)$documento['categoria_id'], $usuarioId, $ehAdmin);

        if (!$permissao['visualizar']) {
            header('Location: ' . url('/documentos'));
            exit;
        }

        $this->view('documentos/ver', [
            'documento' => $documento,
            'versoes' => $this->service->versoes($id),
            'permissao' => $permissao,
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');

        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();

        if (!$this->permissaoService->podeEditar($categoriaId, $usuarioId, $ehAdmin)) {
            NotificationService::error('Você não tem permissão para criar documentos nessa categoria.');
            header('Location: ' . url('/documentos'));
            exit;
        }

        $dados = $_POST;
        $dados['usuario_id'] = $usuarioId;
        $resultado = $this->service->criar($dados);

        AuditService::registrar('Documentos', 'Criar documento', $resultado['message']);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url($resultado['success'] ? '/documentos/ver?id=' . $resultado['id'] : '/documentos/categoria?id=' . $categoriaId));
        exit;
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');

        $id = (int)($_POST['id'] ?? 0);
        [$documento, $permissao] = $this->documentoComPermissao($id);

        if (!$documento || !$permissao['editar']) {
            NotificationService::error('Você não tem permissão para editar esse documento.');
            header('Location: ' . url('/documentos'));
            exit;
        }

        $dados = $_POST;
        $dados['usuario_id'] = (int)$_SESSION['usuario']['id'];
        $resultado = $this->service->atualizar($id, $dados);

        AuditService::registrar('Documentos', 'Editar documento', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/documentos/ver?id=' . $id));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');

        $id = (int)($_POST['id'] ?? 0);
        [$documento, $permissao] = $this->documentoComPermissao($id);

        if (!$documento || !$permissao['excluir']) {
            NotificationService::error('Você não tem permissão para excluir esse documento.');
            header('Location: ' . url('/documentos'));
            exit;
        }

        $categoriaId = $documento['categoria_id'];
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Documentos', 'Excluir documento', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/documentos/categoria?id=' . $categoriaId));
        exit;
    }

    public function anexoUpload(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');

        $id = (int)($_POST['id'] ?? 0);
        [$documento, $permissao] = $this->documentoComPermissao($id);

        if (!$documento || !$permissao['editar']) {
            NotificationService::error('Você não tem permissão para editar esse documento.');
            header('Location: ' . url('/documentos'));
            exit;
        }

        $resultado = $this->service->definirAnexoUpload($id, $_FILES['arquivo'] ?? [], (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Documentos', 'Anexo do documento (upload)', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/documentos/ver?id=' . $id));
        exit;
    }

    public function anexoSamba(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');

        $id = (int)($_POST['id'] ?? 0);
        [$documento, $permissao] = $this->documentoComPermissao($id);

        if (!$documento || !$permissao['editar']) {
            NotificationService::error('Você não tem permissão para editar esse documento.');
            header('Location: ' . url('/documentos'));
            exit;
        }

        $compartilhamentoId = (int)($_POST['compartilhamento_id'] ?? 0);
        $subcaminho = (string)($_POST['subcaminho'] ?? '');
        $nomeArquivo = (string)($_POST['nome_arquivo'] ?? '');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();
        $sambaService = new SambaAnexoService();

        if (!$sambaService->podeAcessarCompartilhamento($compartilhamentoId, $usuarioId, $ehAdmin)) {
            NotificationService::error('Você não tem acesso a esse compartilhamento.');
            header('Location: ' . url('/documentos/ver?id=' . $id));
            exit;
        }

        $compartilhamento = $sambaService->buscarCompartilhamento($compartilhamentoId);
        $subcaminhoValidado = $sambaService->validarSubcaminho($subcaminho);
        $nomeArquivoValidado = $sambaService->validarSubcaminho($nomeArquivo);

        if (!$compartilhamento || $subcaminhoValidado === null || $nomeArquivoValidado === null || $nomeArquivoValidado === '') {
            NotificationService::error('Arquivo inválido.');
            header('Location: ' . url('/documentos/ver?id=' . $id));
            exit;
        }

        $caminhoCompleto = $sambaService->caminhoParaAnexo($compartilhamento, $subcaminhoValidado, $nomeArquivoValidado);
        $resultado = $this->service->definirAnexoSamba($id, $caminhoCompleto, basename($nomeArquivoValidado), $usuarioId);

        AuditService::registrar('Documentos', 'Anexo do documento (Samba)', "#{$id}: {$resultado['message']} ({$caminhoCompleto})");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/documentos/ver?id=' . $id));
        exit;
    }

    /** Serve o anexo -- ramifica por origem, permissão de quem pode ver é sempre a do DOCUMENTO (categoria), nunca a do Samba de novo. */
    public function anexoBaixar(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');

        $id = (int)($_GET['id'] ?? 0);
        [$documento, $permissao] = $this->documentoComPermissao($id);

        if (!$documento || !$permissao['visualizar'] || !$documento['anexo_origem']) {
            http_response_code(404);
            return;
        }

        if ($documento['anexo_origem'] === 'upload') {
            $caminho = DocumentoService::caminhoCompletoUpload($documento['anexo_caminho']);
            if (!is_file($caminho)) {
                http_response_code(404);
                return;
            }
            header('Content-Type: ' . (mime_content_type($caminho) ?: 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . basename($documento['anexo_nome_original']) . '"');
            header('Content-Length: ' . filesize($caminho));
            readfile($caminho);
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($documento['anexo_nome_original']) . '"');
        header('Cache-Control: no-cache');
        (new SambaAnexoService())->servirArquivo($documento['anexo_caminho']);
    }

    public function sambaCompartilhamentosApi(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $compartilhamentos = (new SambaAnexoService())->compartilhamentosVisiveis($usuarioId, PermissionService::ehAdmin());

        echo json_encode(['success' => true, 'compartilhamentos' => $compartilhamentos]);
    }

    public function sambaListarApi(): void
    {
        AuthMiddleware::checkModulo('documentos_acessar');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();
        $compartilhamentoId = (int)($_GET['compartilhamento_id'] ?? 0);
        $subcaminho = (string)($_GET['subcaminho'] ?? '');

        $sambaService = new SambaAnexoService();

        if (!$sambaService->podeAcessarCompartilhamento($compartilhamentoId, $usuarioId, $ehAdmin)) {
            echo json_encode(['success' => false, 'message' => 'Sem acesso.']);
            return;
        }

        $compartilhamento = $sambaService->buscarCompartilhamento($compartilhamentoId);
        $subcaminhoValidado = $sambaService->validarSubcaminho($subcaminho);

        if (!$compartilhamento || $subcaminhoValidado === null) {
            echo json_encode(['success' => false, 'message' => 'Caminho inválido.']);
            return;
        }

        echo json_encode([
            'success' => true,
            'itens' => $sambaService->listarItens($compartilhamento, $subcaminhoValidado),
        ]);
    }

    /** @return array{0: ?array, 1: array{visualizar:bool, editar:bool, excluir:bool}} */
    private function documentoComPermissao(int $id): array
    {
        $documento = $this->service->buscar($id);
        if (!$documento) {
            return [null, ['visualizar' => false, 'editar' => false, 'excluir' => false]];
        }

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();
        $permissao = $this->permissaoService->efetiva((int)$documento['categoria_id'], $usuarioId, $ehAdmin);

        return [$documento, $permissao];
    }
}
