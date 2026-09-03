<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\ChamadoExternoCategoriaService;
use App\Services\ChamadoExternoEstatisticaService;
use App\Services\ChamadoExternoService;
use App\Services\FornecedorService;
use App\Services\NotificationService;
use App\Services\NumeroControleService;
use App\Services\PermissionService;
use App\Services\SambaAnexoService;

class ChamadoExternoController extends Controller
{
    private ChamadoExternoService $service;

    public function __construct()
    {
        $this->service = new ChamadoExternoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $filtros = array_filter([
            'status' => $_GET['status'] ?? null,
            'fornecedor_id' => $_GET['fornecedor_id'] ?? null,
            'categoria_id' => $_GET['categoria_id'] ?? null,
        ]);

        $this->view('chamados_externos/index', [
            'chamados' => $this->service->listar($filtros),
            'fornecedores' => (new FornecedorService())->listarAtivos(),
            'categorias' => (new ChamadoExternoCategoriaService())->listarAtivas(),
            'filtros' => $filtros,
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $this->view('chamados_externos/form', [
            'chamado' => null,
            'fornecedores' => (new FornecedorService())->listarAtivos(),
            'categorias' => (new ChamadoExternoCategoriaService())->listarAtivas(),
            'ativoPreSelecionado' => !empty($_GET['ativo_id']) ? (new AtivoService())->buscar((int)$_GET['ativo_id']) : null,
            'proximoNumero' => NumeroControleService::previewProximo(Database::connection(), 'chamados_externos', 'aberto_em', 'CE'),
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $dados = $_POST;
        $dados['usuario_id'] = (int)$_SESSION['usuario']['id'];
        $resultado = $this->service->criar($dados);

        AuditService::registrar('Chamados Externos', 'Criar chamado', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/chamados-externos/ver?id=' . $resultado['id']));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/novo'));
        exit;
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $id = (int)($_GET['id'] ?? 0);
        $chamado = $this->service->buscar($id);

        if (!$chamado) {
            header('Location: ' . url('/chamados-externos'));
            exit;
        }

        $this->view('chamados_externos/ver', [
            'chamado' => $chamado,
            'timeline' => $this->service->timeline($id),
            'anexos' => $this->service->anexos($id),
            'fornecedores' => (new FornecedorService())->listarAtivos(),
            'categorias' => (new ChamadoExternoCategoriaService())->listarAtivas(),
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, $_POST);

        AuditService::registrar('Chamados Externos', 'Editar chamado', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/ver?id=' . $id));
        exit;
    }

    public function mudarStatus(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $resultado = $this->service->mudarStatus($id, $status, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Chamados Externos', 'Mudar status', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/ver?id=' . $id));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Chamados Externos', 'Excluir chamado', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos'));
        exit;
    }

    public function comentar(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->comentar($id, (string)($_POST['conteudo'] ?? ''), (int)$_SESSION['usuario']['id']);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/ver?id=' . $id));
        exit;
    }

    public function anexoUpload(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->anexarUpload($id, $_FILES['arquivo'] ?? [], (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Chamados Externos', 'Anexo (upload)', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/ver?id=' . $id));
        exit;
    }

    public function anexoSamba(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $compartilhamentoId = (int)($_POST['compartilhamento_id'] ?? 0);
        $subcaminho = (string)($_POST['subcaminho'] ?? '');
        $nomeArquivo = (string)($_POST['nome_arquivo'] ?? '');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin();
        $sambaService = new SambaAnexoService();

        if (!$sambaService->podeAcessarCompartilhamento($compartilhamentoId, $usuarioId, $ehAdmin)) {
            NotificationService::error('Você não tem acesso a esse compartilhamento.');
            header('Location: ' . url('/chamados-externos/ver?id=' . $id));
            exit;
        }

        $compartilhamento = $sambaService->buscarCompartilhamento($compartilhamentoId);
        $subcaminhoValidado = $sambaService->validarSubcaminho($subcaminho);
        $nomeArquivoValidado = $sambaService->validarSubcaminho($nomeArquivo);

        if (!$compartilhamento || $subcaminhoValidado === null || $nomeArquivoValidado === null || $nomeArquivoValidado === '') {
            NotificationService::error('Arquivo inválido.');
            header('Location: ' . url('/chamados-externos/ver?id=' . $id));
            exit;
        }

        $caminhoCompleto = $sambaService->caminhoParaAnexo($compartilhamento, $subcaminhoValidado, $nomeArquivoValidado);
        $resultado = $this->service->anexarSamba($id, $caminhoCompleto, basename($nomeArquivoValidado), $usuarioId);

        AuditService::registrar('Chamados Externos', 'Anexo (Samba)', "#{$id}: {$resultado['message']} ({$caminhoCompleto})");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/ver?id=' . $id));
        exit;
    }

    public function anexoExcluir(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $anexoId = (int)($_POST['anexo_id'] ?? 0);
        $chamadoId = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluirAnexo($anexoId, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Chamados Externos', 'Excluir anexo', "#{$chamadoId}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/ver?id=' . $chamadoId));
        exit;
    }

    public function anexoRenomear(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $anexoId = (int)($_POST['anexo_id'] ?? 0);
        $chamadoId = (int)($_POST['id'] ?? 0);
        $novoNome = (string)($_POST['novo_nome'] ?? '');
        $resultado = $this->service->renomearAnexo($anexoId, $novoNome, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Chamados Externos', 'Renomear anexo', "#{$chamadoId}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/ver?id=' . $chamadoId));
        exit;
    }

    /** Serve o anexo -- permissão de quem pode ver é o gate do módulo, igual Contratos/Documentos (nunca revalida o Samba de novo). `modo=inline` é usado pelo pop-up de visualização (PDF/imagem); sem isso, força download. */
    public function anexoBaixar(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');

        $anexoId = (int)($_GET['anexo_id'] ?? 0);
        $anexo = $this->service->buscarAnexo($anexoId);

        if (!$anexo) {
            http_response_code(404);
            return;
        }

        $inline = ($_GET['modo'] ?? '') === 'inline';
        $disposicao = $inline ? 'inline' : 'attachment';

        if ($anexo['anexo_origem'] === 'upload') {
            $caminho = ChamadoExternoService::caminhoCompletoUpload($anexo['anexo_caminho']);
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
            ? ChamadoExternoService::mimetypeParaVisualizar(pathinfo($anexo['anexo_nome_original'], PATHINFO_EXTENSION))
            : 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: ' . $disposicao . '; filename="' . rawurlencode($anexo['anexo_nome_original']) . '"');
        header('Cache-Control: no-cache');
        (new SambaAnexoService())->servirArquivo($anexo['anexo_caminho']);
    }

    public function sambaCompartilhamentosApi(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $compartilhamentos = (new SambaAnexoService())->compartilhamentosVisiveis($usuarioId, PermissionService::ehAdmin());

        echo json_encode(['success' => true, 'compartilhamentos' => $compartilhamentos]);
    }

    public function sambaListarApi(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');
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

    public function estatisticas(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_estatisticas');

        $service = new ChamadoExternoEstatisticaService();

        $this->view('chamados_externos/estatisticas', [
            'resumo' => $service->resumo(),
            'porFornecedor' => $service->porFornecedor(),
            'porCategoria' => $service->porCategoria(),
            'porMes' => $service->porMes(),
        ]);
    }

    /** JSON leve pro autocomplete de Ativo no formulario -- reaproveita AtivoService::listar(). */
    public function ativosApi(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_atendimentos');
        header('Content-Type: application/json');

        $termo = (string)($_GET['termo'] ?? '');
        $ativos = (new AtivoService())->listar(['busca' => $termo]);

        $resultado = array_map(fn($a) => [
            'id' => $a['id'],
            'label' => trim(($a['codigo_patrimonio'] ?? '') . ' - ' . ($a['nome'] ?? '')),
        ], array_slice($ativos, 0, 20));

        echo json_encode(['success' => true, 'ativos' => $resultado]);
    }
}
