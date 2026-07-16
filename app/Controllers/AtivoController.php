<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoCatalogoService;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\CronService;
use App\Services\EtiquetaService;
use App\Services\NotificationService;

class AtivoController extends Controller
{
    private AtivoService $service;
    private AtivoCatalogoService $catalogoService;

    public function __construct()
    {
        $this->service = new AtivoService();
        $this->catalogoService = new AtivoCatalogoService();
    }

    public function dashboard(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $this->view('ativos/dashboard', array_merge($this->service->dashboard(), [
            'comunidadePadrao' => $this->service->comunidadePadrao(),
            'coletaSnmpAtiva' => $this->coletaSnmpAtiva(),
            'chaveAgente' => $this->service->chaveAgente(),
            'historicoChaves' => $this->service->historicoChavesAgente(),
            'intervaloComunicacao' => $this->service->intervaloComunicacao(),
            'heartbeatIntervalo' => $this->service->heartbeatIntervaloSegundos(),
            'versaoAgenteExe' => $this->service->versaoAgenteExe(),
            'agenteExeDisponivel' => $this->service->agenteExeDisponivel(),
            'dotnetRuntimeDisponivel' => $this->service->dotnetRuntimeDisponivel(),
            'dotnetRuntimeLabel' => $this->service->dotnetRuntimeLabel(),
        ]));
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');

        $filtros = [
            'tipo' => $_GET['tipo'] ?? '',
            'status' => $_GET['status'] ?? '',
            'busca' => trim($_GET['busca'] ?? ''),
        ];

        $this->view('ativos/lista', [
            'ativos' => $this->service->listar($filtros),
            'filtros' => $filtros,
            'versaoAgenteExeAtual' => $this->service->versaoAgenteExe(),
        ]);
    }

    public function verForm(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');

        $id = (int)($_GET['id'] ?? 0);
        $ativo = $this->service->buscar($id);

        if (!$ativo) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $this->view('ativos/ver', [
            'ativo' => $ativo,
            'programas' => $this->service->listarProgramas($id),
            'alertas' => $this->service->listarAlertas($id),
            'comandos' => $this->service->historicoComandos($id),
            'historicoComandosExecucao' => $this->service->historicoSolicitacoesExecucao($id),
            'redes' => $this->service->listarRedes($id),
            'volumes' => $this->service->listarVolumes($id),
            'portas' => $this->service->listarPortas($id),
            'portasRede' => $this->service->listarPortasRede($id),
            'memoria' => $this->service->listarMemoria($id),
            'atualizacoesWindows' => $this->service->listarAtualizacoesWindows($id),
            'estaLigada' => AtivoService::estaLigada($ativo),
            'uptime' => AtivoService::uptimeTexto($ativo),
            'minutosDesdeCheckin' => AtivoService::minutosDesdeUltimoCheckin($ativo),
            'segundosDesdeHeartbeat' => AtivoService::segundosDesdeUltimoHeartbeat($ativo),
            'intervaloComunicacao' => $this->service->intervaloComunicacao(),
            'elevacaoConfigurada' => $this->service->credenciaisElevacaoConfiguradas($id),
            'elevacaoUsuarioAtual' => $this->service->usuarioElevacaoAtual($id),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $this->view('ativos/form', [
            'ativo' => null,
            'tipoSelecionado' => $_GET['tipo'] ?? 'computador',
            'setores' => $this->catalogoService->listarSetores(),
            'localizacoes' => $this->catalogoService->listarLocalizacoes(),
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = $this->service->criar($_POST);

        header('Location: ' . url($id ? '/ativos/ver?id=' . $id : '/ativos/novo'));
        exit;
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_GET['id'] ?? 0);
        $ativo = $this->service->buscar($id);

        if (!$ativo) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $this->view('ativos/form', [
            'ativo' => $ativo,
            'tipoSelecionado' => $ativo['tipo'],
            'setores' => $this->catalogoService->listarSetores(),
            'localizacoes' => $this->catalogoService->listarLocalizacoes(),
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_POST['id'] ?? 0);

        $this->service->editar($id, $_POST);

        header('Location: ' . url('/ativos/ver?id=' . $id));
        exit;
    }

    public function excluirForm(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_GET['id'] ?? 0);
        $ativo = $this->service->buscar($id);

        if (!$ativo) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $this->view('ativos/excluir', ['ativo' => $ativo]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_POST['id'] ?? 0);

        $this->service->excluir($id);

        header('Location: ' . url('/ativos'));
        exit;
    }

    public function etiqueta(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');

        $id = (int)($_GET['id'] ?? 0);
        $ativo = $this->service->buscar($id);

        if (!$ativo) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $this->renderEtiquetas([$ativo]);
    }

    /** ZPL pronto pra este ativo, buscado pelo navegador e mandado direto pro agente Windows local (impressora Zebra). */
    public function etiquetaZpl(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $ativo = $this->service->buscar($id);

        if (!$ativo) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Ativo não encontrado.']);
            return;
        }

        $etiquetaService = new EtiquetaService();
        $config = $etiquetaService->configuracao();

        echo json_encode(['success' => true, 'zpl' => $etiquetaService->gerarZpl($config, $ativo)]);
    }

    public function etiquetasLote(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');

        $ids = array_map('intval', $_GET['ids'] ?? []);
        $ids = array_filter($ids);

        if (empty($ids)) {
            header('Location: ' . url('/ativos'));
            exit;
        }

        $ativos = $this->service->buscarPorIds($ids);

        $this->renderEtiquetas($ativos);
    }

    /**
     * Monta cada etiqueta em HTML respeitando a MESMA configuração de
     * Ativos > Configurações de Etiqueta (tamanho, campos, fontes) --
     * antes essa tela tinha um layout próprio, fixo, que não batia com o
     * que a pré-visualização (nem a impressão na Zebra) mostravam.
     */
    private function renderEtiquetas(array $ativos): void
    {
        $etiquetaService = new EtiquetaService();
        $config = $etiquetaService->configuracao();

        $blocosHtml = [];
        foreach ($ativos as $ativo) {
            $qrBase64 = in_array('qrcode', $config['campos'], true)
                ? $this->service->gerarEtiquetaQrCodeBase64((int)$ativo['id'])
                : null;

            $blocosHtml[] = $etiquetaService->gerarPreviewHtml($config, $ativo, $qrBase64);
        }

        $this->view('ativos/etiqueta', [
            'ativos' => $ativos,
            'blocosHtml' => $blocosHtml,
            'config' => $config,
        ]);
    }

    public function coletarSnmp(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->coletarSnmp($id);

        echo json_encode($resultado);
    }

    public function salvarConfigSnmp(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $this->service->salvarComunidadePadrao(trim($_POST['comunidade'] ?? 'public'));

        AuditService::registrar('Ativos', 'Config. SNMP', 'Community padrão de SNMP atualizada.');

        header('Location: ' . url('/ativos'));
        exit;
    }

    public function ativarColetaSnmp(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');
        header('Content-Type: application/json');

        if ($this->coletaSnmpAtiva()) {
            echo json_encode(['success' => true, 'message' => 'Coleta já estava ativa.']);
            return;
        }

        $resultado = (new CronService())->criar([
            'nome' => $this->service->nomeJobCronSnmp(),
            'descricao' => 'Coleta dados via SNMP dos ativos com essa opção habilitada (Ativos de TI).',
            'expressao' => '*/30 * * * *',
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd ativos:coletar-snmp',
            'ativo' => true,
        ]);

        AuditService::registrar('Ativos', 'Ativar coleta SNMP', $resultado['message']);

        echo json_encode($resultado);
    }

    private function coletaSnmpAtiva(): bool
    {
        foreach ((new CronService())->listar() as $job) {
            if ($job['nome'] === $this->service->nomeJobCronSnmp()) {
                return true;
            }
        }

        return false;
    }

    public function salvarIntervaloComunicacao(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $this->service->salvarIntervaloComunicacao((int)($_POST['minutos'] ?? 15));

        header('Location: ' . url('/ativos'));
        exit;
    }

    public function salvarIntervaloHeartbeat(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $this->service->salvarHeartbeatIntervaloSegundos((int)($_POST['segundos'] ?? 1));

        header('Location: ' . url('/ativos'));
        exit;
    }

    public function solicitarCheckin(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);

        echo json_encode($this->service->solicitarCheckin($id));
    }

    public function regenerarChaveAgente(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $geradoPor = $_SESSION['usuario']['nome'] ?? 'Desconhecido';
        $notificarAgentes = ($_POST['notificar_agentes'] ?? '1') === '1';

        $this->service->regenerarChaveAgente($geradoPor, $notificarAgentes);

        NotificationService::success(
            $notificarAgentes
                ? 'Nova chave gerada e sendo enviada automaticamente pros agentes já conectados (chegam a ela em poucos segundos, no próximo heartbeat). A chave anterior continua válida.'
                : 'Nova chave gerada, sem notificar os agentes já conectados -- eles continuam na chave anterior até você decidir notificar ou reinstalar manualmente. Só instalações novas já saem com essa chave.'
        );

        header('Location: ' . url('/ativos'));
        exit;
    }

    public function desativarChaveAgente(): void
    {
        AuthMiddleware::checkModulo('ativos_dashboard');

        $id = (int)($_POST['id'] ?? 0);
        $desativadoPor = $_SESSION['usuario']['nome'] ?? 'Desconhecido';

        $this->service->desativarChaveAgente($id, $desativadoPor);

        header('Location: ' . url('/ativos'));
        exit;
    }

    public function salvarCredenciaisElevacao(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_POST['id'] ?? 0);

        $this->service->salvarCredenciaisElevacao(
            $id,
            $_POST['usuario'] ?? '',
            $_POST['senha'] ?? ''
        );

        header('Location: ' . url('/ativos/ver?id=' . $id));
        exit;
    }

    public function removerCredenciaisElevacao(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');

        $id = (int)($_POST['id'] ?? 0);

        $this->service->removerCredenciaisElevacao($id);

        header('Location: ' . url('/ativos/ver?id=' . $id));
        exit;
    }

    public function cadastros(): void
    {
        AuthMiddleware::checkModulo('ativos_cadastros');

        $this->view('ativos/cadastros', [
            'setores' => $this->catalogoService->listarSetores(),
            'localizacoes' => $this->catalogoService->listarLocalizacoes(),
        ]);
    }

    public function cadastroNovo(): void
    {
        AuthMiddleware::checkModulo('ativos_cadastros');

        $this->catalogoService->criar(
            $_POST['tipo'] ?? '',
            $_POST['nome'] ?? ''
        );

        header('Location: ' . url('/ativos/cadastros'));
        exit;
    }

    public function cadastroEditar(): void
    {
        AuthMiddleware::checkModulo('ativos_cadastros');

        $this->catalogoService->atualizar(
            (int)($_POST['id'] ?? 0),
            $_POST['nome'] ?? ''
        );

        header('Location: ' . url('/ativos/cadastros'));
        exit;
    }

    public function cadastroExcluir(): void
    {
        AuthMiddleware::checkModulo('ativos_cadastros');

        $this->catalogoService->excluir((int)($_POST['id'] ?? 0));

        header('Location: ' . url('/ativos/cadastros'));
        exit;
    }

    public function enviarComando(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $comando = $_POST['comando'] ?? '';
        $usuario = $_SESSION['usuario']['nome'] ?? null;

        $alvo = null;
        $alvoLabel = null;

        if ($comando === 'desinstalar_programa') {
            $programa = $this->service->buscarPrograma($id, (int)($_POST['programa_id'] ?? 0));
            if (!$programa) {
                echo json_encode(['success' => false, 'message' => 'Programa não encontrado.']);
                return;
            }
            if (empty($programa['uninstall_string'])) {
                echo json_encode(['success' => false, 'message' => 'Não temos o comando de desinstalação deste programa (o instalador não registrou um UninstallString no Windows).']);
                return;
            }
            $alvo = $programa['uninstall_string'];
            $alvoLabel = $programa['nome'];
        } elseif ($comando === 'desinstalar_atualizacao') {
            $atualizacao = $this->service->buscarAtualizacaoWindows($id, (int)($_POST['atualizacao_id'] ?? 0));
            if (!$atualizacao) {
                echo json_encode(['success' => false, 'message' => 'Atualização não encontrada.']);
                return;
            }
            $alvo = $atualizacao['kb'];
            $alvoLabel = $atualizacao['kb'];
        } elseif ($comando === 'executar_arquivo' || $comando === 'encerrar_processo' || $comando === 'renomear_arquivo') {
            // Caminho/PID vem direto do resultado que o próprio agente já
            // mandou (explorador de arquivos/processos) -- não tem um
            // cadastro server-side pra buscar por id, como nos outros.
            // Em renomear_arquivo, alvo_label é o NOME NOVO (só o nome,
            // sem caminho) -- o agente monta o caminho completo de destino.
            $alvo = trim((string)($_POST['alvo'] ?? ''));
            $alvoLabel = trim((string)($_POST['alvo_label'] ?? '')) ?: null;
        }

        $resultado = $this->service->enviarComando($id, $comando, $usuario, $alvo, $alvoLabel);

        echo json_encode($resultado);
    }

    /** Upload do arquivo (multipart) pra ser entregue ao agente via comando 'enviar_arquivo'. */
    public function enviarArquivo(): void
    {
        AuthMiddleware::checkModulo('ativos_novo');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $destino = trim((string)($_POST['destino'] ?? ''));
        $usuario = $_SESSION['usuario']['nome'] ?? null;
        $arquivo = $_FILES['arquivo'] ?? null;

        if ($destino === '') {
            echo json_encode(['success' => false, 'message' => 'Informe a pasta de destino.']);
            return;
        }

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => self::mensagemErroUpload($arquivo['error'] ?? -1)]);
            return;
        }

        $pasta = __DIR__ . '/../../storage/uploads/ativos_transferencias';
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            echo json_encode(['success' => false, 'message' => 'Falha ao criar pasta de destino no servidor.']);
            return;
        }

        $nomeOriginal = basename($arquivo['name']);
        $nomeSanitizado = preg_replace('/[^A-Za-z0-9._-]/', '_', $nomeOriginal) ?: 'arquivo';
        $caminhoServidor = $pasta . '/enviar_' . uniqid('', true) . '_' . $nomeSanitizado;

        if (!@move_uploaded_file($arquivo['tmp_name'], $caminhoServidor)) {
            echo json_encode(['success' => false, 'message' => 'Falha ao salvar o arquivo no servidor.']);
            return;
        }

        $separador = str_ends_with($destino, '\\') ? '' : '\\';
        $caminhoCompleto = $destino . $separador . $nomeOriginal;

        $resultado = $this->service->enviarComando($id, 'enviar_arquivo', $usuario, $caminhoCompleto, $nomeOriginal, $caminhoServidor);

        if (!$resultado['success']) {
            @unlink($caminhoServidor);
        }

        echo json_encode($resultado);
    }

    public function solicitarListagem(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $parametro = trim((string)($_POST['parametro'] ?? '')) ?: null;
        $elevado = !empty($_POST['elevado']);
        $usuario = $_SESSION['usuario']['nome'] ?? null;

        if (in_array($tipo, ['executar_cmd', 'executar_powershell'], true)) {
            AuthMiddleware::checkModulo('ativos_novo');
        }

        echo json_encode($this->service->solicitarListagem($id, $tipo, $parametro, $usuario, $elevado));
    }

    public function baixarSolicitacaoArquivo(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');

        $id = (int)($_GET['id'] ?? 0);
        $ativoId = (int)($_GET['ativo_id'] ?? 0);

        $arquivo = $this->service->baixarResultadoArquivo($id, $ativoId);

        if ($arquivo === null) {
            http_response_code(404);
            echo 'Arquivo não encontrado (pode já ter expirado -- baixe de novo pelo explorador).';
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($arquivo['nome']) . '"');
        header('Content-Length: ' . filesize($arquivo['caminho']));
        readfile($arquivo['caminho']);

        $this->service->limparArquivoResultado($id);
    }

    public function resultadoSolicitacao(): void
    {
        AuthMiddleware::checkModulo('ativos_lista');
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $ativoId = (int)($_GET['ativo_id'] ?? 0);

        echo json_encode($this->service->resultadoSolicitacao($id, $ativoId));
    }
}
