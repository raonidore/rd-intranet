<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\KbService;
use App\Services\NotificationService;
use App\Services\PermissionService;

class KbController extends Controller
{
    private KbService $service;

    public function __construct()
    {
        $this->service = new KbService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_visualizar');

        $busca = trim($_GET['q'] ?? '');
        $podeCriar = PermissionService::temAcesso('base_conhecimento_criar');

        $edicao = null;
        if ($podeCriar && !empty($_GET['editar'])) {
            $edicao = $this->service->buscarPorId((int)$_GET['editar']);
        }

        $meus = $this->service->listarMeus($busca);
        foreach ($meus as &$artigo) {
            $artigo['imagens'] = $this->service->listarImagens((int)$artigo['id']);
        }
        unset($artigo);

        $this->view('base_conhecimento/index', [
            'meus' => $meus,
            'publicos' => $this->service->listarPublicos($busca),
            'categorias' => $this->service->listarCategorias(),
            'subcategorias' => $this->service->listarSubcategorias(),
            'centralConfigurada' => $this->service->centralConfigurada(),
            'busca' => $busca,
            'podeCriar' => $podeCriar,
            'linguagensComando' => KbService::LINGUAGENS_COMANDO,
            'edicao' => $edicao,
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_criar');

        $resultado = $this->service->criar($_POST, $this->reagruparArquivos($_FILES['imagens'] ?? []));

        if ($resultado['success']) {
            AuditService::registrar('Base de Conhecimento', 'Criar artigo', $resultado['message']);
        }
        $this->notificarEVoltar($resultado);
    }

    /**
     * PHP entrega um input tipo "imagens[]" como arrays paralelos
     * ($_FILES['imagens']['name'][0], ['tmp_name'][0]...) -- reagrupa num
     * array de arquivos individuais (mesmo formato de um $_FILES normal),
     * mais fácil de percorrer no service.
     */
    private function reagruparArquivos(array $bruto): array
    {
        if (empty($bruto['name']) || !is_array($bruto['name'])) {
            return [];
        }

        $arquivos = [];
        foreach ($bruto['name'] as $indice => $nome) {
            if ($nome === '') {
                continue;
            }
            $arquivos[] = [
                'name' => $nome,
                'tmp_name' => $bruto['tmp_name'][$indice],
                'error' => $bruto['error'][$indice],
                'size' => $bruto['size'][$indice],
            ];
        }

        return $arquivos;
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_criar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, $_POST, $this->reagruparArquivos($_FILES['imagens'] ?? []));

        if ($resultado['success']) {
            AuditService::registrar('Base de Conhecimento', 'Editar artigo', "Artigo #{$id} atualizado.");
        }
        $this->notificarEVoltar($resultado);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_criar');

        $this->service->excluir((int)($_POST['id'] ?? 0));
        AuditService::registrar('Base de Conhecimento', 'Excluir artigo', "Artigo #{$_POST['id']} excluído.");

        $this->notificarEVoltar(['success' => true, 'message' => 'Artigo excluído.']);
    }

    public function imagem(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_visualizar');

        $imagem = $this->service->caminhoImagem((int)($_GET['id'] ?? 0));
        if ($imagem === null) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . (mime_content_type($imagem['caminho']) ?: 'application/octet-stream'));
        header('Cache-Control: private, max-age=300');
        readfile($imagem['caminho']);
    }

    public function categoriaCriar(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_criar');

        $resultado = $this->service->criarCategoria($_POST['nome'] ?? '');
        $this->notificarEVoltar($resultado);
    }

    public function categoriaExcluir(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_criar');

        $this->service->excluirCategoria((int)($_POST['id'] ?? 0));
        $this->notificarEVoltar(['success' => true, 'message' => 'Categoria excluída.']);
    }

    public function subcategoriaCriar(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_criar');

        $resultado = $this->service->criarSubcategoria((int)($_POST['categoria_id'] ?? 0), $_POST['nome'] ?? '');
        $this->notificarEVoltar($resultado);
    }

    public function subcategoriaExcluir(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_criar');

        $this->service->excluirSubcategoria((int)($_POST['id'] ?? 0));
        $this->notificarEVoltar(['success' => true, 'message' => 'Subcategoria excluída.']);
    }

    public function sincronizar(): void
    {
        AuthMiddleware::checkModulo('base_conhecimento_visualizar');
        header('Content-Type: application/json');

        echo json_encode($this->service->sincronizar());
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/base-conhecimento'));
        exit;
    }
}
