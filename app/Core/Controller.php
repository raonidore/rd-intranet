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

    /** Traduz os códigos nativos de erro de upload do PHP (UPLOAD_ERR_*) pra algo acionável. */
    protected static function mensagemErroUpload(int $err): string
    {
        return match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo maior que o limite configurado no servidor (upload_max_filesize/post_max_size do php.ini). Aumente esses valores e reinicie o Apache.',
            UPLOAD_ERR_PARTIAL => 'O upload foi interrompido no meio (conexão caiu). Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta uma pasta temporária no servidor pra receber o upload.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar o arquivo temporário no servidor (disco cheio ou sem permissão).',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por uma extensão do PHP.',
            default => "Erro no upload (código {$err}).",
        };
    }
}
