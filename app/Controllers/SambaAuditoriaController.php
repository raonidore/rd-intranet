<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;
use App\Services\NotificationService;
use App\Services\SambaAuditoriaService;

class SambaAuditoriaController extends Controller
{
    private SambaAuditoriaService $service;
    private AtivoService $ativoService;

    public function __construct()
    {
        $this->service = new SambaAuditoriaService();
        $this->ativoService = new AtivoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('samba_auditoria');

        $ativa = $this->service->ativa();

        $filtros = [
            'usuario' => trim($_GET['usuario'] ?? ''),
            'compartilhamento' => trim($_GET['compartilhamento'] ?? ''),
            'acao' => trim($_GET['acao'] ?? ''),
            'busca' => trim($_GET['busca'] ?? ''),
        ];

        $registros = $ativa ? $this->service->listar($filtros) : [];
        $registros = $this->enriquecerComAcaoArquivo($registros);
        $registros = $this->enriquecerComAtivo($registros);

        $this->view('samba/auditoria', [
            'ativa' => $ativa,
            'filtros' => $filtros,
            'registros' => $registros,
            'compartilhamentos' => $ativa ? $this->service->compartilhamentosNoLog() : [],
            'retencao' => $ativa ? $this->service->retencao() : null,
        ]);
    }

    /**
     * Acrescenta, em cada registro, o "rel" (caminho relativo que as
     * rotas de Samba > Arquivos entendem) e a classificação de extensão
     * -- só quando dá pra saber onde o arquivo está DE VERDADE agora:
     *   - "renomeado": o caminho atual é o destino, não a origem.
     *   - "excluido": a origem não existe mais lá (foi pra dentro da
     *     Lixeira via recycle) -- sem botão de ação, pra não sugerir que
     *     o arquivo ainda está no caminho mostrado.
     *   - "pasta_criada": não tem view/edit (é diretório), só um link
     *     pra abrir a pasta em Samba > Arquivos.
     *   - "offload_write_recv" sem caminho (texto explicativo no lugar):
     *     relFromAbsoluto() já devolve null nesse caso, sem tratamento
     *     especial aqui.
     */
    private function enriquecerComAcaoArquivo(array $registros): array
    {
        foreach ($registros as &$r) {
            $r['rel'] = null;

            if ($r['acao'] === 'excluido') {
                continue;
            }

            if ($r['acao'] === 'pasta_criada') {
                $r['rel'] = SambaArquivosController::relFromAbsoluto($r['arquivo']);
                continue;
            }

            $caminhoAtual = ($r['acao'] === 'renomeado' && !empty($r['arquivo_destino']))
                ? $r['arquivo_destino']
                : $r['arquivo'];

            $rel = SambaArquivosController::relFromAbsoluto($caminhoAtual);
            if ($rel === null) {
                continue;
            }

            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));

            $r['rel'] = $rel;
            $r['ext'] = $ext;
            $r += SambaArquivosController::classificarExtensao($ext);
        }
        unset($r);

        return $registros;
    }

    /** Cruza a "máquina" de cada linha do log com um Ativo já cadastrado (mesmo hostname), pra mostrar o código de patrimônio junto. */
    private function enriquecerComAtivo(array $registros): array
    {
        $nomes = array_column($registros, 'maquina');
        $ativosPorNome = $this->ativoService->buscarPorNomesMaquina($nomes);

        foreach ($registros as &$r) {
            $ativo = $ativosPorNome[mb_strtoupper(trim($r['maquina']))] ?? null;
            $r['ativo_id'] = $ativo['id'] ?? null;
            $r['ativo_codigo'] = $ativo['codigo_patrimonio'] ?? null;
        }
        unset($r);

        return $registros;
    }

    public function salvarRetencao(): void
    {
        AuthMiddleware::checkModulo('samba_auditoria');

        $dias = (int) ($_POST['dias'] ?? 0);
        $resultado = $this->service->salvarRetencaoDias($dias);
        $resultado['success'] ? NotificationService::success('Retenção do log de auditoria atualizada.') : NotificationService::error($resultado['message']);

        header('Location: ' . url('/samba/auditoria'));
        exit;
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('samba_auditoria');

        $resultado = $this->service->ativar();
        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);

        header('Location: ' . url('/samba/auditoria'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('samba_auditoria');

        $resultado = $this->service->desativar();
        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);

        header('Location: ' . url('/samba/auditoria'));
        exit;
    }
}
