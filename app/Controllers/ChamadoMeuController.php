<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\ChamadoAnexoService;
use App\Services\ChamadoService;
use App\Services\NotificationService;

/**
 * "Meus Chamados" (Chamados > Meus Chamados + card do Dashboard) --
 * versão pro usuário logado acompanhar só o que ELE abriu pelo painel
 * (usuario_abertura_id), sem os controles de atendente (mudar status,
 * nota interna, assumir). Mesmo espírito do Portal do Solicitante
 * (PortalChamadoController), mas pra quem já tem login no sistema --
 * aqui a "posse" é validada contra usuario_abertura_id em vez de
 * solicitante_id/token.
 */
class ChamadoMeuController extends Controller
{
    private const MODULOS = ['chamados_atendimentos', 'chamados_abrir'];

    /** @return array|null null e já redireciona se o chamado não existir ou não for desse usuário */
    private function exigirDono(int $id, int $usuarioId): ?array
    {
        $chamado = (new ChamadoService())->buscar($id);

        if (!$chamado || (int)($chamado['usuario_abertura_id'] ?? 0) !== $usuarioId) {
            NotificationService::error('Chamado não encontrado.');
            header('Location: ' . url('/chamados/meus'));
            exit;
        }

        return $chamado;
    }

    public function index(): void
    {
        AuthMiddleware::checkQualquerModulo(self::MODULOS);

        $this->view('chamados/meus', [
            'chamados' => (new ChamadoService())->listarAbertosPeloUsuario((int)$_SESSION['usuario']['id']),
        ]);
    }

    public function ver(): void
    {
        AuthMiddleware::checkQualquerModulo(self::MODULOS);

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $id = (int)($_GET['id'] ?? 0);
        $service = new ChamadoService();
        $chamado = $this->exigirDono($id, $usuarioId);

        $this->view('chamados/meus_ver', [
            'chamado' => $chamado,
            'comentarios' => $service->comentarios($id, false), // nunca mostra nota interna
            'anexos' => (new ChamadoAnexoService())->listarPorChamado($id),
            'somenteLeitura' => in_array($chamado['status'], ['resolvido', 'fechado'], true),
        ]);
    }

    public function responder(): void
    {
        AuthMiddleware::checkQualquerModulo(self::MODULOS);

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $id = (int)($_POST['id'] ?? 0);
        $this->exigirDono($id, $usuarioId);

        // Tipo sempre 'publica' aqui -- quem abre chamado pra si não tem
        // opção de nota interna (isso é ferramenta de atendente).
        $resultado = (new ChamadoService())->responder($id, $_POST['conteudo'] ?? '', 'publica', $usuarioId);

        if (!$resultado['success']) {
            NotificationService::error($resultado['message']);
        } elseif (($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $resultadoAnexo = (new ChamadoAnexoService())->salvar($id, $_FILES['arquivo'], $usuarioId, $resultado['id'] ?? null);
            if (!$resultadoAnexo['success']) {
                NotificationService::error($resultadoAnexo['message']);
            }
        }

        header('Location: ' . url('/chamados/meus/ver?id=' . $id));
        exit;
    }

    public function anexo(): void
    {
        AuthMiddleware::checkQualquerModulo(self::MODULOS);

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $id = (int)($_POST['id'] ?? 0);
        $this->exigirDono($id, $usuarioId);

        $resultado = (new ChamadoAnexoService())->salvar($id, $_FILES['arquivo'] ?? [], $usuarioId);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);

        header('Location: ' . url('/chamados/meus/ver?id=' . $id));
        exit;
    }

    public function anexoBaixar(): void
    {
        AuthMiddleware::checkQualquerModulo(self::MODULOS);

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $anexoId = (int)($_GET['id'] ?? 0);
        $anexoService = new ChamadoAnexoService();
        $anexo = $anexoService->buscar($anexoId);

        if (!$anexo) {
            http_response_code(404);
            return;
        }

        // Posse validada pelo chamado dono do anexo, não pelo anexo em si
        // (anexo não tem usuário "dono" de verdade -- é de quem enviou).
        $this->exigirDono((int)$anexo['chamado_id'], $usuarioId);

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
