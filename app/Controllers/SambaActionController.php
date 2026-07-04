<?php

namespace App\Controllers;

use App\Actions\Samba\ImportarCompartilhamentoAction;
use App\Actions\Samba\MoverPastaParaLixeiraAction;
use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\ActionExecutorService;

class SambaActionController extends Controller
{
    private ActionExecutorService $executor;

    public function __construct()
    {
        $this->executor = new ActionExecutorService();
    }

    public function importarCompartilhamento(): void
    {
        AuthMiddleware::check();

        $payload = [
            'nome' => $_POST['nome'] ?? '',
            'grupo' => $_POST['grupo'] ?? '',
            'caminho' => $_POST['caminho'] ?? '',
        ];

        $this->executor->execute(
            new ImportarCompartilhamentoAction(),
            $payload
        );

        header('Location: ' . url('/samba/diagnostico'));
        exit;
    }

    public function moverPastaParaLixeira(): void
    {
        AuthMiddleware::check();

        $payload = [
            'nome' => $_POST['nome'] ?? '',
        ];

        $this->executor->execute(
            new MoverPastaParaLixeiraAction(),
            $payload
        );

        header('Location: ' . url('/samba/diagnostico'));
        exit;
    }
}
