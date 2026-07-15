<?php

namespace App\Core;

class Controller
{
    protected function view(string $view, array $data = [])
    {
        extract($data);

        require __DIR__ . '/../Views/' . $view . '.php';
    }

    /**
     * Redireciona depois de uma ação (ex: upload) que pode ter sido
     * chamada via XHR (pra dar barra de progresso) OU um form comum.
     * XHR segue redirect (Location) sozinho, silenciosamente, e essa
     * requisição invisível acaba rodando a página de destino e
     * consumindo a flash message (Alert::flash() já limpa a sessão) antes
     * da navegação de verdade acontecer -- resultado: a página real
     * recarrega sem mensagem nenhuma. Pra requisições XHR (identificadas
     * pelo header X-Requested-With que o JS do cliente manda), responde
     * JSON direto, sem Location e sem limpar a flash -- o JS faz a
     * navegação real depois, que aí sim mostra a mensagem certinha.
     */
    protected function redirecionarAposUpload(string $urlRedirecionamento): void
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => ($_SESSION['flash_tipo'] ?? '') !== 'error',
                'message' => $_SESSION['flash_msg'] ?? '',
            ]);
            exit;
        }

        header('Location: ' . $urlRedirecionamento);
        exit;
    }
}
