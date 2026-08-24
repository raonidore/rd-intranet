<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ChamadoAvaliacaoService;
use App\Services\ChamadoService;
use App\Services\ChamadoSolicitanteService;
use App\Services\ChamadoSolicitanteTokenService;
use App\Services\EmailService;
use App\Services\NotificationService;

/**
 * Portal do Solicitante -- páginas públicas (sem AuthMiddleware, quem
 * acessa não tem conta de usuário no sistema) pra quem abriu um
 * chamado acompanhar status e responder sem precisar de login interno.
 * Login por link mágico (e-mail), sessão própria em
 * $_SESSION['solicitante_id'] -- nunca confundida com $_SESSION['usuario']
 * (login de equipe).
 */
class PortalChamadoController extends Controller
{
    private function exigirSolicitante(): ?array
    {
        $id = $_SESSION['solicitante_id'] ?? null;
        if (!$id) {
            header('Location: ' . url('/portal/chamados'));
            exit;
        }

        return (new ChamadoSolicitanteService())->buscarPorId((int)$id);
    }

    public function loginForm(): void
    {
        $this->view('portal/login', [
            'emailDisponivel' => (new EmailService())->configurado(),
        ]);
    }

    public function login(): void
    {
        if ((new EmailService())->configurado()) {
            $urlBase = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
            (new ChamadoSolicitanteTokenService())->solicitar($_POST['email'] ?? '', $urlBase);
        }

        NotificationService::success('Se o e-mail informado tiver chamados registrados, você vai receber um link de acesso em instantes.');

        header('Location: ' . url('/portal/chamados'));
        exit;
    }

    public function acessar(): void
    {
        $tokenService = new ChamadoSolicitanteTokenService();
        $registro = $tokenService->validarToken($_GET['token'] ?? '');

        if (!$registro) {
            NotificationService::error('Link inválido ou expirado. Solicite um novo acesso.');
            header('Location: ' . url('/portal/chamados'));
            exit;
        }

        $tokenService->marcarUsado((int)$registro['id']);
        $_SESSION['solicitante_id'] = (int)$registro['solicitante_id'];

        header('Location: ' . url('/portal/chamados/meus'));
        exit;
    }

    public function sair(): void
    {
        unset($_SESSION['solicitante_id']);
        header('Location: ' . url('/portal/chamados'));
        exit;
    }

    public function meus(): void
    {
        $solicitante = $this->exigirSolicitante();

        $this->view('portal/meus', [
            'solicitante' => $solicitante,
            'chamados' => (new ChamadoService())->listarDoSolicitante((int)$solicitante['id']),
        ]);
    }

    public function ver(): void
    {
        $solicitante = $this->exigirSolicitante();

        $id = (int)($_GET['id'] ?? 0);
        $service = new ChamadoService();
        $chamado = $service->buscar($id);

        if (!$chamado || (int)$chamado['solicitante_id'] !== (int)$solicitante['id']) {
            header('Location: ' . url('/portal/chamados/meus'));
            exit;
        }

        $somenteLeitura = in_array($chamado['status'], ['resolvido', 'fechado'], true);

        $this->view('portal/ver', [
            'solicitante' => $solicitante,
            'chamado' => $chamado,
            'comentarios' => $service->comentarios($id, false),
            'somenteLeitura' => $somenteLeitura,
            'avaliacao' => $somenteLeitura ? (new ChamadoAvaliacaoService())->buscar($id) : null,
        ]);
    }

    public function responder(): void
    {
        $solicitante = $this->exigirSolicitante();

        $id = (int)($_POST['id'] ?? 0);
        $service = new ChamadoService();
        $chamado = $service->buscar($id);

        if ($chamado && (int)$chamado['solicitante_id'] === (int)$solicitante['id']) {
            $service->responderComoSolicitante($id, $_POST['conteudo'] ?? '');
        }

        header('Location: ' . url('/portal/chamados/ver?id=' . $id));
        exit;
    }

    public function avaliar(): void
    {
        $solicitante = $this->exigirSolicitante();

        $id = (int)($_POST['id'] ?? 0);
        $chamado = (new ChamadoService())->buscar($id);

        if ($chamado
            && (int)$chamado['solicitante_id'] === (int)$solicitante['id']
            && in_array($chamado['status'], ['resolvido', 'fechado'], true)
        ) {
            $resolvidoRaw = $_POST['resolvido'] ?? '';
            $resolvido = $resolvidoRaw === '' ? null : ($resolvidoRaw === '1');

            $resultado = (new ChamadoAvaliacaoService())->registrar(
                $id,
                (int)$solicitante['id'],
                (int)($_POST['nota'] ?? 0),
                $resolvido,
                trim($_POST['comentario'] ?? '')
            );

            $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/portal/chamados/ver?id=' . $id));
        exit;
    }
}
