<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ChamadoSetorService;
use App\Services\ChamadoSetorSolicitanteService;
use App\Services\NotificationService;
use App\Services\UnidadeService;
use App\Services\UserService;

class ChamadoSetorController extends Controller
{
    private ChamadoSetorService $service;

    public function __construct()
    {
        $this->service = new ChamadoSetorService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $setores = $this->service->listar();

        $usuariosAtivos = array_values(array_filter(
            (new UserService())->listar(),
            fn (array $u) => (bool)$u['ativo']
        ));

        $usuariosPorSetor = [];
        foreach ($setores as $setor) {
            $usuariosPorSetor[$setor['id']] = $this->service->idsUsuariosDoSetor((int)$setor['id']);
        }

        $solicitantes = new ChamadoSetorSolicitanteService();

        $this->view('chamados/setores', [
            'aba' => ($_GET['aba'] ?? '') === 'solicitantes' ? 'solicitantes' : 'equipes',
            'setores' => $setores,
            'usuariosAtivos' => $usuariosAtivos,
            'usuariosPorSetor' => $usuariosPorSetor,
            'setoresSolicitantes' => $solicitantes->listar(),
            'setorSolicitanteObrigatorio' => $solicitantes->obrigatorio(),
            'unidades' => (new UnidadeService())->listarAtivas(),
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $nome = trim($_POST['nome'] ?? '');
        $resultado = $this->service->criar($nome);

        AuditService::registrar('Chamados', 'Criar setor', "Setor \"{$nome}\": {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $resultado = $this->service->atualizar($id, $nome, isset($_POST['ativo']));

        AuditService::registrar('Chamados', 'Atualizar setor', "Setor #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Chamados', 'Excluir setor', "Setor #{$id} removido.");

        $this->notificarEVoltar($resultado);
    }

    public function salvarUsuarios(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $setorId = (int)($_POST['setor_id'] ?? 0);
        $usuarios = $_POST['usuarios'] ?? [];
        $resultado = $this->service->salvarUsuariosDoSetor($setorId, is_array($usuarios) ? $usuarios : []);

        AuditService::registrar('Chamados', 'Usuários do setor', "Setor #{$setorId}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    // --- Setores do solicitante (setores DA EMPRESA, aba "Setores do solicitante") ---

    public function criarSolicitante(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $nome = trim($_POST['nome'] ?? '');
        $resultado = (new ChamadoSetorSolicitanteService())->criar($nome, (int)($_POST['unidade_id'] ?? 0) ?: null);

        AuditService::registrar('Chamados', 'Criar setor do solicitante', "Setor \"{$nome}\": {$resultado['message']}");

        $this->notificarEVoltar($resultado, 'solicitantes');
    }

    public function atualizarSolicitante(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = (new ChamadoSetorSolicitanteService())->atualizar(
            $id,
            (string)($_POST['nome'] ?? ''),
            (int)($_POST['unidade_id'] ?? 0) ?: null,
            isset($_POST['ativo'])
        );

        AuditService::registrar('Chamados', 'Atualizar setor do solicitante', "Setor do solicitante #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado, 'solicitantes');
    }

    public function excluirSolicitante(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = (new ChamadoSetorSolicitanteService())->excluir($id);

        AuditService::registrar('Chamados', 'Excluir setor do solicitante', "Setor do solicitante #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado, 'solicitantes');
    }

    public function salvarObrigatorioSolicitante(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $obrigatorio = isset($_POST['obrigatorio']);
        (new ChamadoSetorSolicitanteService())->salvarObrigatorio($obrigatorio);

        AuditService::registrar('Chamados', 'Setor do solicitante obrigatório', $obrigatorio ? 'Ligado' : 'Desligado');

        $this->notificarEVoltar(['success' => true, 'message' => $obrigatorio
            ? 'Setor do solicitante agora é obrigatório na abertura pelo painel.'
            : 'Setor do solicitante agora é opcional.'], 'solicitantes');
    }

    /** Equipes cadastradas por engano (setores da empresa) viram setores do solicitante. */
    public function converter(): void
    {
        AuthMiddleware::checkModulo('chamados_setores');

        $ids = $_POST['setores'] ?? [];
        $resultado = (new ChamadoSetorSolicitanteService())->converterEquipes(
            is_array($ids) ? $ids : [],
            (int)($_POST['destino_id'] ?? 0),
            isset($_POST['mover_todos'])
        );

        $this->notificarEVoltar($resultado, $resultado['success'] ? 'solicitantes' : 'equipes');
    }

    private function notificarEVoltar(array $resultado, string $aba = 'equipes'): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/setores' . ($aba === 'solicitantes' ? '?aba=solicitantes' : '')));
        exit;
    }
}
