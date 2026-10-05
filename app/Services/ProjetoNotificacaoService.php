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

        // Sem e-mail configurado o aviso fica só dentro do sistema (badge no card
        // da tarefa e no menu Projetos -- ProjetoComentarioService::totalNaoLidas()).
        $email = new EmailService();
        if (!$email->configurado()) {
            return;
        }

        $autor = '';
        if (!empty($comentario['usuario_id'])) {
            $stmt = $this->pdo->prepare('SELECT nome FROM usuarios WHERE id = ?');
            $stmt->execute([$comentario['usuario_id']]);
            $autor = (string)$stmt->fetchColumn();
        } elseif (!empty($comentario['participante_externo_id'])) {
            $stmt = $this->pdo->prepare('SELECT nome FROM projetos_participantes_externos WHERE id = ?');
            $stmt->execute([$comentario['participante_externo_id']]);
            $autor = (string)$stmt->fetchColumn();
        }

        $urlBase = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $assunto = 'Nova mensagem: ' . $tarefa['titulo'];
        $corpo = '<p><strong>' . htmlspecialchars($autor ?: 'Alguém') . '</strong> escreveu na tarefa <strong>' . htmlspecialchars($tarefa['titulo']) . '</strong>'
            . ' (' . htmlspecialchars($tarefa['projeto_titulo'] ?? '') . '):</p>'
            . '<blockquote style="border-left:3px solid #ccc;margin:0;padding:4px 12px;color:#333">' . nl2br(htmlspecialchars($comentario['conteudo'])) . '</blockquote>';

        foreach ((new ProjetoTarefaService())->pessoas((int)$comentario['tarefa_id']) as $pessoa) {
            // não avisa quem acabou de comentar
            if ($pessoa['tipo'] === 'interno' && (int)$pessoa['id'] === (int)($comentario['usuario_id'] ?? 0)) {
                continue;
            }
            if ($pessoa['tipo'] === 'externo' && (int)$pessoa['id'] === (int)($comentario['participante_externo_id'] ?? 0)) {
                continue;
            }
            if (empty($pessoa['email'])) {
                continue;
            }

            if ($pessoa['tipo'] === 'interno') {
                $link = $urlBase . url('/projetos/ver?id=' . $tarefa['projeto_id'] . '&tarefa=' . $tarefa['id']);
            } else {
                // Externo entra pelo portal (link de acesso de uso único, mesmo do convite).
                $link = (new ProjetoParticipanteTokenService())->emitirLink((int)$pessoa['id'], $urlBase);
            }

            $email->enviar(
                $pessoa['email'],
                $assunto,
                $corpo . ($link ? '<p><a href="' . htmlspecialchars($link) . '">Abrir a tarefa e responder</a></p>' : '')
            );
        }
    }
}
