<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\ChamadoAnexoService;
use App\Services\ChamadoCategoriaService;
use App\Services\ChamadoService;
use App\Services\ChamadoSetorService;
use App\Services\KbService;
use App\Services\NotificationService;
use App\Services\UnidadeService;

class ChamadoController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $service = new ChamadoService();
        $usuarioId = (int)$_SESSION['usuario']['id'];

        $aba = $_GET['aba'] ?? 'andamento';

        $this->view('chamados/atendimentos', [
            'aba' => $aba,
            'chamados' => $service->listarDoUsuario($usuarioId),
            'encerrados' => $aba === 'encerrados' ? $service->listarEncerradosDoUsuario($usuarioId) : [],
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $this->view('chamados/novo', [
            'categorias' => (new ChamadoCategoriaService())->listarAtivas(),
            'setores' => (new ChamadoSetorService())->listarAtivos(),
            'unidades' => (new UnidadeService())->listarAtivas(),
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $resultado = (new ChamadoService())->abrir($_POST);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/chamados/atendimentos/ver?id=' . $resultado['id']));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados/atendimentos/novo'));
        exit;
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $id = (int)($_GET['id'] ?? 0);
        $service = new ChamadoService();
        $chamado = $service->buscar($id);

        if (!$chamado) {
            NotificationService::error('Chamado não encontrado.');
            header('Location: ' . url('/chamados/atendimentos'));
            exit;
        }

        $this->view('chamados/ver', [
            'chamado' => $chamado,
            'comentarios' => $service->comentarios($id),
            'historico' => $service->historico($id),
            'anexos' => (new ChamadoAnexoService())->listarPorChamado($id),
            'somenteLeitura' => in_array($chamado['status'], ['resolvido', 'fechado'], true),
        ]);
    }

    public function responder(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $tipo = ($_POST['tipo'] ?? '') === 'interna' ? 'interna' : 'publica';

        $resultado = (new ChamadoService())->responder($id, $_POST['conteudo'] ?? '', $tipo, (int)$_SESSION['usuario']['id']);

        if (!$resultado['success']) {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/atendimentos/ver?id=' . $id));
        exit;
    }

    /** Contagem de "aguardando resposta" -- badge do menu e alerta sonoro. */
    public function contadorApi(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $service = new ChamadoService();

        echo json_encode([
            'success' => true,
            'aguardando' => $service->contarAguardandoResposta($usuarioId),
            'ultimoId' => $service->ultimoIdAguardandoResposta($usuarioId),
        ]);
    }

    public function mudarStatus(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        $resultado = (new ChamadoService())->mudarStatus($id, $status, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Chamados', 'Mudar status', "Chamado #{$id}: {$resultado['message']}");

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/atendimentos/ver?id=' . $id));
        exit;
    }

    /** Autocomplete de Ativo (código/nome/nº de série) na abertura do chamado. */
    public function ativosBuscarApi(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');
        header('Content-Type: application/json');

        $termo = trim($_GET['q'] ?? '');
        if (strlen($termo) < 2) {
            echo json_encode(['success' => true, 'ativos' => []]);
            return;
        }

        $ativos = array_slice((new AtivoService())->listar(['busca' => $termo]), 0, 10);

        echo json_encode([
            'success' => true,
            'ativos' => array_map(fn (array $a) => [
                'id' => (int)$a['id'],
                'codigo' => $a['codigo_patrimonio'],
                'nome' => $a['nome'],
                'tipo' => $a['tipo_nome'],
            ], $ativos),
        ]);
    }

    /** Sugestão de artigos da Base de Conhecimento enquanto o chamado é aberto. */
    public function kbSugestoesApi(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');
        header('Content-Type: application/json');

        $termo = trim($_GET['q'] ?? '');
        if (strlen($termo) < 3) {
            echo json_encode(['success' => true, 'artigos' => []]);
            return;
        }

        $artigos = array_slice((new KbService())->listarMeus($termo), 0, 5);

        echo json_encode([
            'success' => true,
            'artigos' => array_map(fn (array $a) => [
                'id' => (int)$a['id'],
                'titulo' => $a['titulo'],
                'categoria' => $a['categoria_nome'],
            ], $artigos),
        ]);
    }

    /** "Criar artigo da Base de Conhecimento" a partir de um chamado resolvido. */
    public function criarArtigoKb(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $chamado = (new ChamadoService())->buscar($id);

        if (!$chamado) {
            NotificationService::error('Chamado não encontrado.');
            header('Location: ' . url('/chamados/atendimentos'));
            exit;
        }

        $resultado = (new KbService())->criar([
            'titulo' => $_POST['titulo'] ?? $chamado['titulo'],
            'problema' => $chamado['descricao'],
            'solucao' => $_POST['solucao'] ?? '',
            'visibilidade' => 'privado',
        ]);

        AuditService::registrar('Chamados', 'Artigo da Base de Conhecimento', "A partir do chamado #{$id}: {$resultado['message']}");

        if ($resultado['success']) {
            NotificationService::success('Artigo criado na Base de Conhecimento.');
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/atendimentos/ver?id=' . $id));
        exit;
    }

    public function anexo(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $chamado = (new ChamadoService())->buscar($id);

        if (!$chamado) {
            NotificationService::error('Chamado não encontrado.');
            header('Location: ' . url('/chamados/atendimentos'));
            exit;
        }

        $resultado = (new ChamadoAnexoService())->salvar($id, $_FILES['arquivo'] ?? [], (int)$_SESSION['usuario']['id']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/atendimentos/ver?id=' . $id));
        exit;
    }

    public function anexoBaixar(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $anexoId = (int)($_GET['id'] ?? 0);
        $anexoService = new ChamadoAnexoService();
        $anexo = $anexoService->buscar($anexoId);

        if (!$anexo) {
            http_response_code(404);
            return;
        }

        $caminho = $anexoService->caminhoCompleto($anexo['caminho_arquivo']);

        if (!is_file($caminho)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . ($anexo['tipo_mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($anexo['nome_original']) . '"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }
}
