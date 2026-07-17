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

        ModuloCatalogo::salvarGruposHabilitados($_POST['grupos'] ?? []);

        AuditService::registrar('Sistema', 'Módulos', 'Grupos de módulos habilitados atualizados.');
        NotificationService::success('Módulos atualizados.');

        header('Location: ' . url('/administracao/modulos'));
        exit;
    }
}
