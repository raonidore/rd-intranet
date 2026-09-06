<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\EmailService;
use App\Services\ProjetoAnexoService;
use App\Services\ProjetoComentarioService;
use App\Services\ProjetoNotificacaoService;
use App\Services\ProjetoParticipanteExternoService;
use App\Services\ProjetoParticipanteTokenService;
use App\Services\ProjetoTarefaService;
use App\Services\NotificationService;
use PDO;
use App\Core\Database;

/**
 * Portal do participante externo de Projetos -- páginas públicas
 * (sem AuthMiddleware) pra consultor/ponto focal do cliente ver as
 * tarefas em que foi incluído e comentar sem login interno. Mesmo
 * esquema do Portal do Solicitante de Chamados: link mágico, sessão
 * própria em $_SESSION['projeto_participante_id'].
 */
class ProjetoPortalController extends Controller
{
    private function exigirParticipante(): ?array
    {
        $id = $_SESSION['projeto_participante_id'] ?? null;
        if (!$id) {
            header('Location: ' . url('/projetos/portal/login'));
            exit;
        }

        return (new ProjetoParticipanteExternoService())->buscarPorId((int)$id);
    }

    public function loginForm(): void
    {
        $this->view('projetos_portal/login', [
            'emailDisponivel' => (new EmailService())->configurado(),
        ]);
    }

    public function login(): void
    {
        if ((new EmailService())->configurado()) {
            $urlBase = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
            (new ProjetoParticipanteTokenService())->solicitar($_POST['email'] ?? '', $urlBase);
        }

        NotificationService::success('Se o e-mail informado tiver tarefas atribuídas, você vai receber um link de acesso em instantes.');

        header('Location: ' . url('/projetos/portal/login'));
        exit;
    }

    public function acessar(): void
    {
        $tokenService = new ProjetoParticipanteTokenService();
        $registro = $tokenService->validarToken($_GET['token'] ?? '');

        if (!$registro) {
            NotificationService::error('Link inválido ou expirado. Solicite um novo acesso.');
            header('Location: ' . url('/projetos/portal/login'));
            exit;
        }

        $tokenService->marcarUsado((int)$registro['id']);
        $_SESSION['projeto_participante_id'] = (int)$registro['participante_externo_id'];

        header('Location: ' . url('/projetos/portal'));
        exit;
    }

    public function sair(): void
    {
        unset($_SESSION['projeto_participante_id']);
        header('Location: ' . url('/projetos/portal/login'));
        exit;
    }

    public function index(): void
    {
        $participante = $this->exigirParticipante();

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT t.*, p.titulo AS projeto_titulo
             FROM projetos_tarefas_externos e
             JOIN projetos_tarefas t ON t.id = e.tarefa_id
             JOIN projetos p ON p.id = t.projeto_id
             WHERE e.participante_externo_id = ?
             ORDER BY t.coluna != "concluido" DESC, t.prazo IS NULL, t.prazo ASC'
        );
        $stmt->execute([$participante['id']]);

        $this->view('projetos_portal/lista', [
            'participante' => $participante,
            'tarefas' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function tarefa(): void
    {
        $participante = $this->exigirParticipante();

        $id = (int)($_GET['id'] ?? 0);
        $tarefaService = new ProjetoTarefaService();
        $tarefa = $tarefaService->buscar($id);

        if (!$tarefa || !$this->participanteNaTarefa($id, (int)$participante['id'])) {
            header('Location: ' . url('/projetos/portal'));
            exit;
        }

        $this->view('projetos_portal/tarefa', [
            'participante' => $participante,
            'tarefa' => $tarefa,
            'timeline' => array_filter(
                (new ProjetoComentarioService())->timeline((int)$tarefa['projeto_id']),
                fn (array $c) => (int)($c['tarefa_id'] ?? 0) === $id
            ),
            'anexos' => array_filter(
                (new ProjetoAnexoService())->porProjeto((int)$tarefa['projeto_id']),
                fn (array $a) => (int)($a['tarefa_id'] ?? 0) === $id
            ),
        ]);
    }

    public function comentar(): void
    {
        $participante = $this->exigirParticipante();

        $tarefaId = (int)($_POST['tarefa_id'] ?? 0);
        if (!$this->participanteNaTarefa($tarefaId, (int)$participante['id'])) {
            header('Location: ' . url('/projetos/portal'));
            exit;
        }

        $tarefa = (new ProjetoTarefaService())->buscar($tarefaId);
        $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
        $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

        $resultado = (new ProjetoComentarioService())->comentar(
            (int)$tarefa['projeto_id'],
            $tarefaId,
            (string)($_POST['conteudo'] ?? ''),
            null,
            (int)$participante['id'],
            $latitude,
            $longitude
        );

        if ($resultado['success'] && !empty($_FILES['arquivo']['name'])) {
            (new ProjetoAnexoService())->anexarUploadComComentario((int)$tarefa['projeto_id'], $tarefaId, (int)$resultado['id'], $_FILES['arquivo'], null, (int)$participante['id']);
        }

        if ($resultado['success']) {
            (new ProjetoNotificacaoService())->notificarComentario((int)$resultado['id']);
        }

        header('Location: ' . url('/projetos/portal/tarefa?id=' . $tarefaId));
        exit;
    }

    private function participanteNaTarefa(int $tarefaId, int $participanteId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM projetos_tarefas_externos WHERE tarefa_id = ? AND participante_externo_id = ?'
        );
        $stmt->execute([$tarefaId, $participanteId]);

        return (int)$stmt->fetchColumn() > 0;
    }
}
