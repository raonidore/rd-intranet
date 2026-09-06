<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Centraliza "quem avisar de quê" pro módulo Projetos, em vez de
 * espalhar a lógica de canal pelos controllers/services. `usuarios`
 * não tem campo de telefone no sistema -- avisos internos são só por
 * e-mail; participante externo tem telefone próprio no cadastro, daí
 * também recebe por WhatsApp quando preenchido (EmailService::enviar()
 * e WhatsAppMensagemService::enviar() já existem prontos, isso aqui só
 * decide quando chamar cada um).
 */
class ProjetoNotificacaoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function notificarAtribuicaoInterna(int $tarefaId, int $usuarioId): void
    {
        $tarefa = (new ProjetoTarefaService())->buscar($tarefaId);
        if (!$tarefa) {
            return;
        }

        $stmt = $this->pdo->prepare('SELECT nome, email FROM usuarios WHERE id = ?');
        $stmt->execute([$usuarioId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || empty($usuario['email'])) {
            return;
        }

        $email = new EmailService();
        if (!$email->configurado()) {
            return;
        }

        $email->enviar(
            $usuario['email'],
            'Você foi atribuído a uma tarefa: ' . $tarefa['titulo'],
            '<p>Olá, ' . htmlspecialchars($usuario['nome']) . '!</p>'
            . '<p>Você foi atribuído à tarefa <strong>' . htmlspecialchars($tarefa['titulo']) . '</strong>, do projeto <strong>' . htmlspecialchars($tarefa['projeto_titulo']) . '</strong>.</p>'
            . '<p><a href="' . htmlspecialchars(url('/projetos/ver?id=' . $tarefa['projeto_id'])) . '">Ver o projeto</a></p>'
        );
    }

    public function notificarAtribuicaoExterna(int $tarefaId, int $participanteExternoId): void
    {
        $tarefa = (new ProjetoTarefaService())->buscar($tarefaId);
        $participante = (new ProjetoParticipanteExternoService())->buscarPorId($participanteExternoId);
        if (!$tarefa || !$participante) {
            return;
        }

        $urlBase = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        if (!empty($participante['email'])) {
            $link = (new ProjetoParticipanteTokenService())->emitirLink($participanteExternoId, $urlBase);
            if ($link !== null) {
                (new EmailService())->enviar(
                    $participante['email'],
                    'Você foi incluído num projeto: ' . $tarefa['projeto_titulo'],
                    '<p>Olá, ' . htmlspecialchars($participante['nome']) . '!</p>'
                    . '<p>Você foi incluído na tarefa <strong>' . htmlspecialchars($tarefa['titulo']) . '</strong>, do projeto <strong>' . htmlspecialchars($tarefa['projeto_titulo']) . '</strong>.</p>'
                    . '<p><a href="' . htmlspecialchars($link) . '">Acompanhar</a></p>'
                );
            }
        }

        if (!empty($participante['telefone'])) {
            (new WhatsAppMensagemService())->enviar(
                $participante['telefone'],
                "Olá, {$participante['nome']}! Você foi incluído na tarefa \"{$tarefa['titulo']}\" do projeto \"{$tarefa['projeto_titulo']}\"."
            );
        }
    }

    public function notificarPrazoVencendo(int $tarefaId): void
    {
        $tarefa = (new ProjetoTarefaService())->buscar($tarefaId);
        if (!$tarefa) {
            return;
        }

        foreach ((new ProjetoTarefaService())->pessoas($tarefaId) as $pessoa) {
            if ($pessoa['tipo'] === 'interno') {
                if (empty($pessoa['email'])) {
                    continue;
                }
                $email = new EmailService();
                if (!$email->configurado()) {
                    continue;
                }
                $email->enviar(
                    $pessoa['email'],
                    'Prazo vencendo amanhã: ' . $tarefa['titulo'],
                    '<p>A tarefa <strong>' . htmlspecialchars($tarefa['titulo']) . '</strong> (' . htmlspecialchars($tarefa['projeto_titulo']) . ') vence amanhã.</p>'
                    . '<p><a href="' . htmlspecialchars(url('/projetos/ver?id=' . $tarefa['projeto_id'])) . '">Ver o projeto</a></p>'
                );
            }
        }
    }

    public function notificarComentario(int $comentarioId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projetos_comentarios WHERE id = ?');
        $stmt->execute([$comentarioId]);
        $comentario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comentario || $comentario['tarefa_id'] === null) {
            return;
        }

        $tarefa = (new ProjetoTarefaService())->buscar((int)$comentario['tarefa_id']);
        if (!$tarefa) {
            return;
        }

        foreach ((new ProjetoTarefaService())->pessoas((int)$comentario['tarefa_id']) as $pessoa) {
            // não avisa quem acabou de comentar
            if ($pessoa['tipo'] === 'interno' && (int)$pessoa['id'] === (int)($comentario['usuario_id'] ?? 0)) {
                continue;
            }
            if ($pessoa['tipo'] === 'externo' && (int)$pessoa['id'] === (int)($comentario['participante_externo_id'] ?? 0)) {
                continue;
            }

            if ($pessoa['tipo'] === 'interno' && !empty($pessoa['email'])) {
                $email = new EmailService();
                if ($email->configurado()) {
                    $email->enviar(
                        $pessoa['email'],
                        'Novo comentário: ' . $tarefa['titulo'],
                        '<p>Novo comentário na tarefa <strong>' . htmlspecialchars($tarefa['titulo']) . '</strong>:</p>'
                        . '<p>' . nl2br(htmlspecialchars($comentario['conteudo'])) . '</p>'
                    );
                }
            }
        }
    }
}
