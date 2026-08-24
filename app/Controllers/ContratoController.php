<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ContratoService;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\SambaAnexoService;

class ContratoController extends Controller
{
    private ContratoService $service;

    public function __construct()
    {
        $this->service = new ContratoService();
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $fornecedorId = (int)($_POST['fornecedor_id'] ?? 0);
        $dados = $_POST;
        $dados['criado_por'] = (int)$_SESSION['usuario']['id'];

        $resultado = $this->service->criar($dados);

        AuditService::registrar('Fornecedores', 'Criar contrato', $resultado['message']);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/fornecedores/ver?id=' . $fornecedorId));
        exit;
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $contrato = $this->service->buscar($id);
        $resultado = $this->service->atualizar($id, $_POST);

        AuditService::registrar('Fornecedores', 'Editar contrato', "Contrato #{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/fornecedores/ver?id=' . ($contrato['fornecedor_id'] ?? 0)));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $contrato = $this->service->buscar($id);
        $fornecedorId = $contrato['fornecedor_id'] ?? 0;

        $resultado = $this->service->excluir($id);

        AuditService::registrar('Fornecedores', 'Excluir contrato', "Contrato #{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/fornecedores/ver?id=' . $fornecedorId));
        exit;
    }

    public function anexoUpload(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $contrato = $this->service->buscar($id);
        $resultado = $this->service->definirAnexoUpload($id, $_FILES['arquivo'] ?? []);

        AuditService::registrar('Fornecedores', 'Anexo do contrato (upload)', "Contrato #{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/fornecedores/ver?id=' . ($contrato['fornecedor_id'] ?? 0)));
        exit;
    }

    public function anexoSamba(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $compartilhamentoId = (int)($_POST['compartilhamento_id'] ?? 0);
        $subcaminho = (string)($_POST['subcaminho'] ?? '');
        $nomeArquivo = (string)($_POST['nome_arquivo'] ?? '');

        $contrato = $this->service->buscar($id);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();

        $sambaService = new SambaAnexoService();

        if (!$sambaService->podeAcessarCompartilhamento($compartilhamentoId, $usuarioId, $ehAdmin)) {
            NotificationService::error('Você não tem acesso a esse compartilhamento.');
            header('Location: ' . url('/fornecedores/ver?id=' . ($contrato['fornecedor_id'] ?? 0)));
            exit;
        }

        $compartilhamento = $sambaService->buscarCompartilhamento($compartilhamentoId);
        $subcaminhoValidado = $sambaService->validarSubcaminho($subcaminho);
        $nomeArquivoValidado = $sambaService->validarSubcaminho($nomeArquivo);

        if (!$compartilhamento || $subcaminhoValidado === null || $nomeArquivoValidado === null || $nomeArquivoValidado === '') {
            NotificationService::error('Arquivo inválido.');
            header('Location: ' . url('/fornecedores/ver?id=' . ($contrato['fornecedor_id'] ?? 0)));
            exit;
        }

        $caminhoCompleto = $sambaService->caminhoParaAnexo($compartilhamento, $subcaminhoValidado, $nomeArquivoValidado);
        $resultado = $this->service->definirAnexoSamba($id, $caminhoCompleto, basename($nomeArquivoValidado));

        AuditService::registrar('Fornecedores', 'Anexo do contrato (Samba)', "Contrato #{$id}: {$resultado['message']} ({$caminhoCompleto})");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/fornecedores/ver?id=' . ($contrato['fornecedor_id'] ?? 0)));
        exit;
    }

    /** Serve o anexo -- ramifica por origem, mas a permissão de quem pode ver é sempre a do CONTRATO (nunca a do Samba de novo). */
    public function anexoBaixar(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $id = (int)($_GET['id'] ?? 0);
        $contrato = $this->service->buscar($id);

        if (!$contrato || !$contrato['anexo_origem']) {
            http_response_code(404);
            return;
        }

        if ($contrato['anexo_origem'] === 'upload') {
            $caminho = ContratoService::caminhoCompletoUpload($contrato['anexo_caminho']);
            if (!is_file($caminho)) {
                http_response_code(404);
                return;
            }
            header('Content-Type: ' . (mime_content_type($caminho) ?: 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . basename($contrato['anexo_nome_original']) . '"');
            header('Content-Length: ' . filesize($caminho));
            readfile($caminho);
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($contrato['anexo_nome_original']) . '"');
        header('Cache-Control: no-cache');
        (new SambaAnexoService())->servirArquivo($contrato['anexo_caminho']);
    }

    /** JSON -- compartilhamentos que o usuário logado pode escolher no seletor de anexo. */
    public function sambaCompartilhamentosApi(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $compartilhamentos = (new SambaAnexoService())->compartilhamentosVisiveis($usuarioId, PermissionService::ehAdmin());

        echo json_encode(['success' => true, 'compartilhamentos' => $compartilhamentos]);
    }

    /** JSON -- navega dentro de um compartilhamento pra escolher o arquivo. */
    public function sambaListarApi(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');
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
}
