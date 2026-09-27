<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;
use App\Services\AcessoRemotoService;
use App\Services\NotificationService;
use App\Services\SegurancaEventoService;

/**
 * Endpoints usados pelo agente Windows -- NÃO passam por sessão/login,
 * a autenticação é por chave de API compartilhada (mesmo modelo do
 * "deploy key" do OCS Inventory/GLPI), enviada no header
 * X-RD-Agente-Chave. O download do script em si (baixarScript) é a
 * única ação aqui que exige sessão -- é o admin, pelo navegador, que
 * baixa o instalador pra distribuir.
 */
class AtivoAgenteController extends Controller
{
    private AtivoService $service;

    public function __construct()
    {
        $this->service = new AtivoService();
    }

    public function checkin(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Corpo JSON inválido.']);
            return;
        }

        echo json_encode($this->service->checkinAgente($payload, $chaveEnviada));
    }

    /**
     * Ping leve de "estou ligado", chamado pelo agente a cada poucos
     * segundos -- ver AtivoService::registrarHeartbeat(). Propositalmente
     * mais simples que checkin(): só uma UPDATE indexada por machine_guid,
     * pra aguentar ser chamado com muito mais frequência sem pesar no
     * servidor.
     */
    public function heartbeat(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $machineGuid = trim((string)($payload['machine_guid'] ?? ''));

        if ($machineGuid === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'machine_guid é obrigatório.']);
            return;
        }

        echo json_encode($this->service->registrarHeartbeat($machineGuid, $chaveEnviada));
    }

    /**
     * Evento do módulo anti-ransomware, enviado pelo agente no instante da
     * detecção (não espera o próximo checkin). Mesma autenticação por chave
     * dos demais endpoints do agente.
     */
    public function eventoSeguranca(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $ativoId = is_array($payload) ? $this->service->idPorMachineGuid(trim((string)($payload['machine_guid'] ?? ''))) : null;

        if ($ativoId === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ativo não encontrado para este machine_guid.']);
            return;
        }

        echo json_encode((new SegurancaEventoService())->registrarDoAgente($ativoId, $payload));
    }

    /**
     * Lista pública de extensões/notas de ransomware já filtrada (agente
     * 1.0.35+ baixa quando a versão no checkin muda). 404 quando a opção
     * está desligada na Central de Segurança.
     */
    public function assinaturasSeguranca(): void
    {
        header('Content-Type: application/json');

        if (!$this->service->chaveValida($_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $assinaturas = new \App\Services\SegurancaAssinaturaService();
        $dados = $assinaturas->ativo() ? $assinaturas->dados() : null;
        if ($dados === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Lista desligada ou ainda não baixada.']);
            return;
        }

        echo json_encode(['success' => true, 'versao' => $dados['versao'], 'extensoes' => $dados['extensoes'], 'notas' => $dados['notas']]);
    }

    /** Botão "Pedir ajuda" do agente -- vira um cartão em Chamados > Suporte Remoto. */
    public function pedidoSuporte(): void
    {
        header('Content-Type: application/json');

        if (!$this->service->chaveValida($_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $ativoId = is_array($payload) ? $this->service->idPorMachineGuid(trim((string)($payload['machine_guid'] ?? ''))) : null;
        if ($ativoId === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Máquina ainda não cadastrada -- aguarde o primeiro envio de inventário.']);
            return;
        }

        echo json_encode((new \App\Services\SuporteRemotoService())->registrarPedido(
            $ativoId,
            (string)($payload['usuario'] ?? ''),
            (string)($payload['mensagem'] ?? '')
        ));
    }

    /** Serves an existing MeshAgent binary only to a registered agent using an active API key. */
    public function baixarInstaladorMeshAgent(): void
    {
        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';
        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $machineGuid = is_array($payload) ? trim((string)($payload['machine_guid'] ?? '')) : '';
        $arquitetura = is_array($payload) ? trim((string)($payload['arquitetura'] ?? '')) : '';
        if ($machineGuid === '' || $this->service->idPorMachineGuid($machineGuid) === null) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Máquina não cadastrada para esta chave.']);
            return;
        }
        if (!$this->service->temInstalacaoMeshPendente($machineGuid)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Não há solicitação de instalação MeshAgent pendente para este ativo.']);
            return;
        }
        if (!in_array($arquitetura, ['x64', 'arm64'], true)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Arquitetura MeshAgent inválida.']);
            return;
        }

        $caminho = (new AcessoRemotoService())->caminhoMeshAgentePublico($arquitetura);
        $tamanho = $caminho !== null ? filesize($caminho) : false;
        if ($caminho === null || $tamanho === false || $tamanho < 500000 || $tamanho > 40 * 1024 * 1024) {
            http_response_code($caminho === null ? 404 : 422);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $caminho === null
                ? "Instalador MeshAgent {$arquitetura} não enviado em Ativos > Acesso Remoto."
                : 'O instalador MeshAgent armazenado tem tamanho inválido.']);
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . $tamanho);
        header('X-MeshAgent-SHA256: ' . hash_file('sha256', $caminho));
        header('Content-Disposition: attachment; filename="MeshAgent-' . $arquitetura . '.exe"');
        readfile($caminho);
    }

    /** Agente devolve o resultado de uma solicitação (listar arquivos/processos) recebida no heartbeat. */
    public function responderSolicitacao(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $machineGuid = trim((string)($payload['machine_guid'] ?? ''));
        $id = (int)($payload['id'] ?? 0);

        if ($machineGuid === '' || $id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'machine_guid e id são obrigatórios.']);
            return;
        }

        echo json_encode($this->service->responderSolicitacao($machineGuid, $id, $payload));
    }

    /** Agente envia (upload multipart) o conteúdo de um arquivo pra uma solicitação 'baixar_arquivo'. */
    public function responderSolicitacaoArquivo(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $machineGuid = trim((string)($_POST['machine_guid'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);
        $nomeOriginal = trim((string)($_POST['nome'] ?? '')) ?: 'arquivo';
        $arquivo = $_FILES['arquivo'] ?? null;

        if ($machineGuid === '' || $id <= 0 || !$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos ou upload falhou.']);
            return;
        }

        echo json_encode($this->service->responderSolicitacaoComArquivo($machineGuid, $id, $arquivo['tmp_name'], $nomeOriginal));
    }

    /** Agente baixa o anexo de um comando 'enviar_arquivo' pendente. */
    public function baixarAnexoComando(): void
    {
        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $machineGuid = trim((string)($_GET['machine_guid'] ?? ''));
        $comandoId = (int)($_GET['id'] ?? 0);

        $anexo = $this->service->buscarAnexoComando($machineGuid, $comandoId);

        if ($anexo === null) {
            http_response_code(404);
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($anexo['nome']) . '"');
        header('Content-Length: ' . filesize($anexo['caminho']));
        readfile($anexo['caminho']);

        $this->service->limparAnexoComando($comandoId);
    }

    public function baixarScript(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $template = file_get_contents(__DIR__ . '/../../scripts/agente/rd-intranet-agent.ps1');

        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $servidorUrl = $esquema . '://' . $host . url('');

        $conteudo = str_replace(
            ['__SERVER_URL__', '__API_KEY__', '__INTERVALO_MINUTOS__'],
            [$servidorUrl, $this->service->chaveAgente(), (string)$this->service->intervaloComunicacao()],
            $template
        );

        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="rd-intranet-agent.ps1"');
        echo $conteudo;
    }

    /** Download manual do .exe pelo admin, pelo navegador -- pra instalar/distribuir. */
    public function baixarExecutavel(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $caminho = $this->service->caminhoAgenteExePublico();

        if ($caminho === null) {
            http_response_code(404);
            echo 'Nenhuma versão do agente .exe foi enviada ainda.';
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="RdIntranetAgente.exe"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }

    /**
     * Consultado pelo instalador do agente (X-RD-Agente-Chave) logo
     * depois do operador digitar URL+chave, pra listar unidade (obrigatória
     * na tela) e setor/localização (opcionais) antes do primeiro checkin --
     * evita cair sempre na unidade padrão e precisar corrigir depois.
     * Serve de quebra também como teste de "a chave/URL estão certas?".
     */
    public function cadastros(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $unidadeService = new \App\Services\UnidadeService();
        $catalogoService = new \App\Services\AtivoCatalogoService();

        echo json_encode([
            'success' => true,
            'unidades' => array_map(fn (array $u) => ['id' => (int)$u['id'], 'nome' => $u['nome']], $unidadeService->listarAtivas()),
            'setores' => array_map(fn (array $s) => ['id' => (int)$s['id'], 'nome' => $s['nome']], $catalogoService->listarSetores()),
            'localizacoes' => array_map(fn (array $l) => ['id' => (int)$l['id'], 'nome' => $l['nome']], $catalogoService->listarLocalizacoes()),
        ]);
    }

    /** Consultado pelo próprio agente (X-RD-Agente-Chave) pra saber se há versão nova. */
    public function versaoExecutavel(): void
    {
        header('Content-Type: application/json');

        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        echo json_encode([
            'success' => true,
            'disponivel' => $this->service->agenteExeDisponivel(),
            'versao' => $this->service->versaoAgenteExe(),
        ]);
    }

    /** Baixado pelo próprio agente (X-RD-Agente-Chave) pra se autoatualizar. */
    public function downloadAtualizacao(): void
    {
        $chaveEnviada = $_SERVER['HTTP_X_RD_AGENTE_CHAVE'] ?? '';

        if (!$this->service->chaveValida($chaveEnviada)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chave de API inválida.']);
            return;
        }

        $caminho = $this->service->caminhoAgenteExePublico();

        if ($caminho === null) {
            http_response_code(404);
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }

    public function uploadExecutavel(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $arquivo = $_FILES['arquivo'] ?? null;
        $versao = trim($_POST['versao'] ?? '');

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error(self::mensagemErroUpload($arquivo['error'] ?? -1));
        } else {
            $this->service->salvarNovoAgenteExe($arquivo['tmp_name'], $versao);
        }

        $this->redirecionarAposUpload(url('/ativos'));
    }

    /** Alternativa ao upload manual: busca o .exe já compilado direto do repositório git -- útil pra quem roda o sistema em vários servidores. */
    public function baixarAgenteDoGit(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $this->service->atualizarAgenteViaGit();

        header('Location: ' . url('/ativos'));
        exit;
    }

    /** Download manual do instalador do .NET Desktop Runtime pelo admin. */
    public function baixarDotnetRuntime(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $caminho = $this->service->caminhoDotnetRuntimePublico();

        if ($caminho === null) {
            http_response_code(404);
            echo 'Nenhum instalador do .NET Desktop Runtime foi enviado ainda.';
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="windowsdesktop-runtime-win-x64.exe"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }

    public function uploadDotnetRuntime(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $arquivo = $_FILES['arquivo'] ?? null;
        $label = trim($_POST['label'] ?? '');

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error(self::mensagemErroUpload($arquivo['error'] ?? -1));
        } else {
            $this->service->salvarDotnetRuntime($arquivo['tmp_name'], $label);
        }

        $this->redirecionarAposUpload(url('/ativos'));
    }
}
