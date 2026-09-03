<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppAtendimentoService;
use App\Services\WhatsAppContatoService;
use App\Services\WhatsAppPermissaoService;

/**
 * Tela de Contatos (CRM leve) -- lista quem já falou com a empresa
 * (whatsapp_contatos, preenchida automaticamente por
 * WhatsAppContatoService::buscarOuCriarPorNumero()), com ações pra ver
 * o histórico completo, reabrir o atendimento direto (sem bot) e
 * excluir. Mesmo módulo de permissão de Atendimentos/Fila
 * ('whatsapp_atendimentos') -- não existe hoje um conceito de "contato
 * pertence a tal setor" pra filtrar por usuário, então não restringe
 * por linha.
 */
class WhatsAppContatoController extends Controller
{
    private const POR_PAGINA = 20;

    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $busca = trim((string)($_GET['busca'] ?? ''));
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));

        $resultado = (new WhatsAppContatoService())->listar($busca, $pagina, self::POR_PAGINA);

        $this->view('whatsapp/contatos', [
            'contatos' => $resultado['itens'],
            'total' => $resultado['total'],
            'pagina' => $pagina,
            'porPagina' => self::POR_PAGINA,
            'busca' => $busca,
        ]);
    }

    public function historico(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $id = (int)($_GET['id'] ?? 0);
        $contatoService = new WhatsAppContatoService();
        $contato = $contatoService->buscarPorId($id);

        if (!$contato) {
            NotificationService::error('Contato não encontrado.');
            header('Location: ' . url('/whatsapp/contatos'));
            exit;
        }

        $atendimentoService = new WhatsAppAtendimentoService();
        $podeVerNps = (new WhatsAppPermissaoService())->usuarioPodeVerNps($_SESSION['usuario']);

        $atendimentos = $contatoService->historicoAtendimentos($id);
        foreach ($atendimentos as &$atendimento) {
            $atendimento['mensagens'] = $atendimentoService->mensagens((int)$atendimento['id'], 0, !$podeVerNps);
        }
        unset($atendimento);

        $this->view('whatsapp/contato_historico', [
            'contato' => $contato,
            'atendimentos' => $atendimentos,
        ]);
    }

    public function reabrir(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = (new WhatsAppAtendimentoService())->reabrirOuAbrirParaContato($id, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('WhatsApp', 'Reabrir atendimento', "Contato #{$id}: {$resultado['message']}");

        if ($resultado['success']) {
            header('Location: ' . url('/whatsapp/atendimentos/ver?id=' . $resultado['atendimento_id']));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/whatsapp/contatos'));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = (new WhatsAppContatoService())->excluir($id);

        AuditService::registrar('WhatsApp', 'Excluir contato', "Contato #{$id}: {$resultado['message']}");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/whatsapp/contatos'));
        exit;
    }
}
