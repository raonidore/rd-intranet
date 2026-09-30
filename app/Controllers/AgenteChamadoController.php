<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AgenteChamadoService;
use App\Services\AtivoService;

/**
 * API do módulo Chamados do agente Windows (/api/agente/chamados/*).
 * Sem sessão web: toda chamada exige a chave de instalação
 * (X-RD-Agente-Chave) e uma máquina já inventariada (X-RD-Agente-Maquina,
 * o machine_guid). "Meus chamados", ver e responder exigem também o
 * token do login feito no agente (X-RD-Agente-Sessao).
 */
class AgenteChamadoController extends Controller
{
    private AgenteChamadoService $service;

    public function __construct()
    {
        $this->service = new AgenteChamadoService();
    }

    public function formulario(): void
    {
        $ativoId = $this->exigirMaquina();
        $usuario = $this->usuarioOpcional();

        $this->json(['success' => true, 'usuario' => $usuario ? $this->service->dadosUsuario($usuario) : null] + $this->service->formulario($ativoId));
    }

    public function ativos(): void
    {
        $this->exigirMaquina();

        $this->json(['success' => true, 'ativos' => $this->service->buscarAtivos((string)($_GET['q'] ?? ''))]);
    }

    public function login(): void
    {
        $ativoId = $this->exigirMaquina();
        $dados = $this->corpo();

        $resultado = $this->service->login(
            (string)($dados['login'] ?? ''),
            (string)($dados['senha'] ?? ''),
            $ativoId,
            (string)($dados['usuario_windows'] ?? '')
        );

        if (!$resultado['success']) {
            http_response_code(401);
        }
        $this->json($resultado);
    }

    public function logout(): void
    {
        $this->exigirMaquina();
        $token = $this->token();
        if ($token !== '') {
            $this->service->logout($token);
        }

        $this->json(['success' => true, 'message' => 'Desconectado.']);
    }

    public function abrir(): void
    {
        $ativoId = $this->exigirMaquina();
        $usuario = $this->usuarioOpcional();

        // Token enviado mas inválido/expirado: não abre como anônimo sem avisar.
        if ($usuario === null && $this->token() !== '') {
            $this->sessaoExpirada();
        }

        $resultado = $this->service->abrir($this->corpo(), $ativoId, $usuario);
        if (!$resultado['success']) {
            http_response_code(422);
        }
        $this->json($resultado);
    }

    public function meus(): void
    {
        $this->exigirMaquina();
        $usuario = $this->exigirUsuario();

        $this->json(['success' => true, 'chamados' => $this->service->meus((int)$usuario['id'])]);
    }

    public function ver(): void
    {
        $this->exigirMaquina();
        $usuario = $this->exigirUsuario();

        $chamado = $this->service->ver((int)($_GET['id'] ?? 0), (int)$usuario['id']);
        if ($chamado === null) {
            http_response_code(404);
            $this->json(['success' => false, 'message' => 'Chamado não encontrado.']);
        }

        $this->json(['success' => true, 'chamado' => $chamado]);
    }

    public function responder(): void
    {
        $this->exigirMaquina();
        $usuario = $this->exigirUsuario();
        $dados = $this->corpo();

        $resultado = $this->service->responder((int)($dados['id'] ?? 0), (int)$usuario['id'], (string)($dados['texto'] ?? ''));
        if (!$resultado['success']) {
            http_response_code(422);
        }
        $this->json($resultado);
    }

    // ------------------------------------------------------------ apoio

    private function exigirMaquina(): int
    {
        $ativoService = new AtivoService();

        if (!$ativoService->chaveValida($_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '')) {
            http_response_code(401);
            $this->json(['success' => false, 'message' => 'Chave de API inválida.']);
        }

        $ativoId = $ativoService->idPorMachineGuid(trim((string)($_SERVER['HTTP_X_RD_AGENTE_MAQUINA'] ?? '')));
        if ($ativoId === null) {
            http_response_code(400);
            $this->json(['success' => false, 'message' => 'Máquina ainda não cadastrada -- aguarde o primeiro envio de inventário.']);
        }

        return $ativoId;
    }

    private function token(): string
    {
        return trim((string)($_SERVER['HTTP_X_RD_AGENTE_SESSAO'] ?? ''));
    }

    private function usuarioOpcional(): ?array
    {
        $token = $this->token();

        return $token === '' ? null : $this->service->usuarioDoToken($token);
    }

    private function exigirUsuario(): array
    {
        $usuario = $this->usuarioOpcional();
        if ($usuario === null) {
            $this->sessaoExpirada();
        }

        return $usuario;
    }

    private function sessaoExpirada(): never
    {
        http_response_code(401);
        $this->json(['success' => false, 'sessao_expirada' => true, 'message' => 'Sua sessão expirou. Entre de novo com seu usuário do RD Intranet.']);
    }

    private function corpo(): array
    {
        $dados = json_decode((string)file_get_contents('php://input'), true);

        return is_array($dados) ? $dados : [];
    }

    private function json(array $dados): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
