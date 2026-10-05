<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ModuloCatalogo;
use App\Services\NotificationService;

class SistemaModulosController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('sistema/modulos', [
            'grupos' => ModuloCatalogo::agrupados(),
            'gruposTogleaveis' => ModuloCatalogo::GRUPOS_TOGGLEAVEIS,
            'gruposHabilitados' => ModuloCatalogo::gruposHabilitados(),
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkAdmin();

        $antes = ModuloCatalogo::gruposHabilitados();
        ModuloCatalogo::salvarGruposHabilitados($_POST['grupos'] ?? []);
        $depois = ModuloCatalogo::gruposHabilitados();

        // Registra O QUE mudou: um grupo desligado some até pro admin, e sem
        // isso não dava pra saber quando/quem desligou (caso do Backup no enzilab).
        $ligados = array_diff($depois, $antes);
        $desligados = array_diff($antes, $depois);
        $mudancas = array_filter([
            $ligados ? 'Ligado: ' . implode(', ', $ligados) : '',
            $desligados ? 'Desligado: ' . implode(', ', $desligados) : '',
        ]);
        AuditService::registrar('Sistema', 'Módulos', $mudancas ? implode('. ', $mudancas) . '.' : 'Grupos de módulos salvos sem alteração.');
        NotificationService::success('Módulos atualizados.');

        header('Location: ' . url('/administracao/modulos'));
        exit;
    }
}
