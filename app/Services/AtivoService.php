<?php

namespace App\Services;

use App\Repositories\AtivoRepository;

class AtivoService
{
    private AtivoRepository $repository;
    private LinuxService $linux;

    public const TIPOS = [
        'computador' => ['label' => 'Computador', 'prefixo' => 'PC', 'icone' => 'bi-pc-display'],
        'servidor' => ['label' => 'Servidor', 'prefixo' => 'SRV', 'icone' => 'bi-hdd-rack'],
        'monitor' => ['label' => 'Monitor', 'prefixo' => 'MON', 'icone' => 'bi-display'],
        'impressora' => ['label' => 'Impressora', 'prefixo' => 'IMP', 'icone' => 'bi-printer'],
        'switch' => ['label' => 'Switch', 'prefixo' => 'SW', 'icone' => 'bi-hdd-network'],
    ];

    public const STATUS = [
        'ativo' => 'Em uso',
        'manutencao' => 'Em manutenção',
        'estoque' => 'Em estoque',
        'baixado' => 'Baixado',
    ];

    /**
     * Campos extras por tipo, guardados na coluna `detalhes` (JSON).
     * Só os campos aqui listados são aceitos na montagem do JSON --
     * evita gravar lixo arbitrário vindo do POST.
     */
    public const CAMPOS_DETALHES = [
        'computador' => [
            'sistema_operacional' => 'Sistema operacional',
            'processador' => 'Processador',
            'memoria_ram' => 'Memória RAM',
            'memoria_usada' => 'Memória em uso',
            'tipo_memoria' => 'Tipo de memória',
            'armazenamento' => 'Armazenamento',
            'placa_mae' => 'Placa-mãe',
            'placa_video' => 'Placa de vídeo',
            'placa_som' => 'Placa de som',
            'usuario_logado' => 'Usuário',
            'ligado_desde' => 'Ligado desde',
            'snmp_sys_descr' => 'Descrição (SNMP)',
            'snmp_uptime' => 'Uptime (SNMP)',
        ],
        'servidor' => [
            'sistema_operacional' => 'Sistema operacional',
            'processador' => 'Processador',
            'memoria_ram' => 'Memória RAM',
            'memoria_usada' => 'Memória em uso',
            'tipo_memoria' => 'Tipo de memória',
            'armazenamento' => 'Armazenamento',
            'placa_video' => 'Placa de vídeo',
            'placa_som' => 'Placa de som',
            'funcao' => 'Função',
            'virtualizado' => 'Virtualizado',
            'ligado_desde' => 'Ligado desde',
            'snmp_sys_descr' => 'Descrição (SNMP)',
            'snmp_uptime' => 'Uptime (SNMP)',
        ],
        'monitor' => [
            'tamanho_polegadas' => 'Tamanho (polegadas)',
            'resolucao' => 'Resolução',
            'entrada_video' => 'Entrada de vídeo',
        ],
        'impressora' => [
            'tipo_conexao' => 'Tipo de conexão',
            'contador_paginas' => 'Contador de páginas',
            'nivel_toner' => 'Nível de toner',
            'snmp_uptime' => 'Uptime (SNMP)',
        ],
        'switch' => [
            'numero_portas' => 'Número de portas',
            'gerenciavel' => 'Gerenciável',
            'firmware' => 'Firmware',
            'snmp_sys_descr' => 'Descrição (SNMP)',
            'snmp_uptime' => 'Uptime (SNMP)',
        ],
    ];

    /**
     * Tipos que fazem sentido pra coleta SNMP (dispositivos de rede) --
     * monitor fica de fora, não é um dispositivo endereçável na rede.
     */
    public const TIPOS_COM_SNMP = ['computador', 'servidor', 'impressora', 'switch'];

    private const NOME_JOB_CRON_SNMP = 'Coleta SNMP de Ativos de TI';

    public function __construct()
    {
        $this->repository = new AtivoRepository();
        $this->linux = new LinuxService();
    }

    public function listar(array $filtros = []): array
    {
        return $this->repository->listar($filtros);
    }

    public function buscar(int $id): ?array
    {
        $ativo = $this->repository->buscarPorId($id);

        if ($ativo) {
            $ativo['detalhes'] = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        }

        return $ativo;
    }

    public function dashboard(): array
    {
        return [
            'total' => $this->repository->contarTotal(),
            'por_tipo' => $this->repository->contarPorTipo(),
            'por_status' => $this->repository->contarPorStatus(),
            'recentes' => $this->repository->recentes(),
        ];
    }

    public function criar(array $post): ?int
    {
        $tipo = $post['tipo'] ?? '';

        if (!isset(self::TIPOS[$tipo])) {
            NotificationService::error('Tipo de ativo inválido.');
            return null;
        }

        $dados = [
            'tipo' => $tipo,
            'codigo_patrimonio' => $this->proximoCodigo($tipo),
            'nome' => trim($post['nome'] ?? ''),
            'marca' => trim($post['marca'] ?? '') ?: null,
            'modelo' => trim($post['modelo'] ?? '') ?: null,
            'numero_serie' => trim($post['numero_serie'] ?? '') ?: null,
            'setor_id' => !empty($post['setor_id']) ? (int)$post['setor_id'] : null,
            'localizacao_id' => !empty($post['localizacao_id']) ? (int)$post['localizacao_id'] : null,
            'responsavel' => trim($post['responsavel'] ?? '') ?: null,
            'status' => isset(self::STATUS[$post['status'] ?? '']) ? $post['status'] : 'ativo',
            'ip' => trim($post['ip'] ?? '') ?: null,
            'snmp_habilitado' => isset($post['snmp_habilitado']) ? 1 : 0,
            'snmp_community' => trim($post['snmp_community'] ?? '') ?: null,
            'observacoes' => trim($post['observacoes'] ?? '') ?: null,
            'detalhes' => json_encode($this->extrairDetalhes($tipo, $post), JSON_UNESCAPED_UNICODE),
        ];

        if ($dados['nome'] === '') {
            NotificationService::error('Informe um nome/identificação para o ativo.');
            return null;
        }

        $id = $this->repository->criar($dados);

        AuditService::registrar('Ativos', 'Criar Ativo', 'Ativo ' . $dados['codigo_patrimonio'] . ' (' . $dados['nome'] . ') cadastrado.');

        NotificationService::success('Ativo cadastrado com sucesso. Código: ' . $dados['codigo_patrimonio']);

        return $id;
    }

    public function editar(int $id, array $post): bool
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            NotificationService::error('Ativo não encontrado.');
            return false;
        }

        $dados = [
            'nome' => trim($post['nome'] ?? ''),
            'marca' => trim($post['marca'] ?? '') ?: null,
            'modelo' => trim($post['modelo'] ?? '') ?: null,
            'numero_serie' => trim($post['numero_serie'] ?? '') ?: null,
            'setor_id' => !empty($post['setor_id']) ? (int)$post['setor_id'] : null,
            'localizacao_id' => !empty($post['localizacao_id']) ? (int)$post['localizacao_id'] : null,
            'responsavel' => trim($post['responsavel'] ?? '') ?: null,
            'status' => isset(self::STATUS[$post['status'] ?? '']) ? $post['status'] : $ativo['status'],
            'ip' => trim($post['ip'] ?? '') ?: null,
            'snmp_habilitado' => isset($post['snmp_habilitado']) ? 1 : 0,
            'snmp_community' => trim($post['snmp_community'] ?? '') ?: null,
            'observacoes' => trim($post['observacoes'] ?? '') ?: null,
            'detalhes' => json_encode($this->extrairDetalhes($ativo['tipo'], $post), JSON_UNESCAPED_UNICODE),
        ];

        if ($dados['nome'] === '') {
            NotificationService::error('Informe um nome/identificação para o ativo.');
            return false;
        }

        $this->repository->atualizar($id, $dados);

        AuditService::registrar('Ativos', 'Editar Ativo', 'Ativo ' . $ativo['codigo_patrimonio'] . ' atualizado.');

        NotificationService::success('Ativo atualizado com sucesso.');

        return true;
    }

    public function excluir(int $id): bool
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            NotificationService::error('Ativo não encontrado.');
            return false;
        }

        $this->repository->excluir($id);

        AuditService::registrar('Ativos', 'Excluir Ativo', 'Ativo ' . $ativo['codigo_patrimonio'] . ' (' . $ativo['nome'] . ') removido.');

        NotificationService::success('Ativo removido do cadastro.');

        return true;
    }

    public function buscarPorIds(array $ids): array
    {
        return $this->repository->buscarPorIds($ids);
    }

    public function listarProgramas(int $ativoId): array
    {
        return $this->repository->listarProgramas($ativoId);
    }

    public function listarAlertas(int $ativoId): array
    {
        return $this->repository->listarAlertas($ativoId);
    }

    public function listarRedes(int $ativoId): array
    {
        return $this->repository->listarRedes($ativoId);
    }

    public function listarVolumes(int $ativoId): array
    {
        return $this->repository->listarVolumes($ativoId);
    }

    public function listarMemoria(int $ativoId): array
    {
        return $this->repository->listarMemoria($ativoId);
    }

    public function listarPortas(int $ativoId): array
    {
        return $this->repository->listarPortas($ativoId);
    }

    private function proximoCodigo(string $tipo): string
    {
        $prefixo = self::TIPOS[$tipo]['prefixo'];
        $ultimo = $this->repository->ultimoCodigoPorTipo($tipo);

        $numero = 0;
        if ($ultimo && preg_match('/(\d+)$/', $ultimo, $m)) {
            $numero = (int)$m[1];
        }

        return sprintf('%s-%s-%06d', $this->siglaEmpresa(), $prefixo, $numero + 1);
    }

    public function nomeEmpresa(): string
    {
        return ConfigService::get('empresa_nome', 'RD Tecnologia') ?? 'RD Tecnologia';
    }

    public function siglaEmpresa(): string
    {
        return ConfigService::get('empresa_sigla', 'RD') ?? 'RD';
    }

    public function salvarEmpresa(string $nome, string $sigla): bool
    {
        $nome = trim($nome);
        $sigla = strtoupper(trim($sigla));

        if ($nome === '') {
            NotificationService::error('Informe o nome da empresa.');
            return false;
        }

        if (!preg_match('/^[A-Z]{2,6}$/', $sigla)) {
            NotificationService::error('A sigla deve ter de 2 a 6 letras (sem números ou símbolos).');
            return false;
        }

        ConfigService::set('empresa_nome', $nome);
        ConfigService::set('empresa_sigla', $sigla);

        AuditService::registrar('Administração', 'Dados da Empresa', "Nome: {$nome}, Sigla: {$sigla}.");

        NotificationService::success('Dados da empresa salvos. Novos ativos e etiquetas já usam a sigla atualizada.');

        return true;
    }

    private function extrairDetalhes(string $tipo, array $post): array
    {
        $campos = self::CAMPOS_DETALHES[$tipo] ?? [];
        $detalhes = [];

        foreach (array_keys($campos) as $campo) {
            $valor = trim((string)($post[$campo] ?? ''));
            if ($valor !== '') {
                $detalhes[$campo] = $valor;
            }
        }

        return $detalhes;
    }

    /**
     * Gera o QR code (PNG base64) da etiqueta -- codifica a URL absoluta
     * de detalhe do ativo, pra quem escanear com o celular já cair na
     * ficha completa. Mesmo padrão de VpnWireguardService::gerarQrCodeBase64().
     */
    public function gerarEtiquetaQrCodeBase64(int $id): ?string
    {
        $urlAbsoluta = $this->urlAbsoluta('/ativos/ver?id=' . $id);

        $resultado = $this->linux->executarComEntrada('qrencode -t PNG -o -', $urlAbsoluta);

        if (!$resultado['success'] || $resultado['output'] === '') {
            return null;
        }

        return base64_encode($resultado['output']);
    }

    private function urlAbsoluta(string $path): string
    {
        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $esquema . '://' . $host . url($path);
    }

    public function comunidadePadrao(): string
    {
        return ConfigService::get('ativos_snmp_community_padrao', 'public') ?? 'public';
    }

    public function salvarComunidadePadrao(string $comunidade): void
    {
        ConfigService::set('ativos_snmp_community_padrao', trim($comunidade) !== '' ? trim($comunidade) : 'public');
    }

    public function coletarSnmp(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if (empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Este ativo não tem IP cadastrado.'];
        }

        $community = $ativo['snmp_community'] ?: $this->comunidadePadrao();

        $coletado = (new SnmpService())->coletar($ativo['ip'], $community, $ativo['tipo']);

        if (empty($coletado)) {
            return [
                'success' => false,
                'message' => 'Não foi possível coletar dados via SNMP (dispositivo não respondeu ou SNMP está desabilitado nele).',
            ];
        }

        $detalhesAtuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $detalhesNovos = array_merge($detalhesAtuais, $coletado);

        $this->repository->atualizarDetalhesSnmp($id, json_encode($detalhesNovos, JSON_UNESCAPED_UNICODE));

        AuditService::registrar('Ativos', 'Coleta SNMP', 'Dados coletados via SNMP para ' . $ativo['codigo_patrimonio'] . '.');

        return ['success' => true, 'message' => 'Dados coletados com sucesso via SNMP.'];
    }

    /**
     * Roda a coleta SNMP em todos os ativos com `snmp_habilitado = 1` --
     * chamado pelo cron (rd ativos:coletar-snmp), mesmo padrão de
     * VpnIkev2Service::coletarTrafego().
     */
    public function coletarSnmpTodos(): array
    {
        $ativos = $this->repository->listarComSnmpHabilitado();
        $sucesso = 0;

        foreach ($ativos as $ativo) {
            $resultado = $this->coletarSnmp((int)$ativo['id']);
            if ($resultado['success']) {
                $sucesso++;
            }
        }

        return ['total' => count($ativos), 'sucesso' => $sucesso];
    }

    public function nomeJobCronSnmp(): string
    {
        return self::NOME_JOB_CRON_SNMP;
    }

    /*
     |---------------------------------------------------------
     | Agente Windows (Fase 3) -- checkin autenticado por chave de API,
     | não por sessão. A chave é compartilhada por todo o parque
     | (mesmo modelo do "deploy key" do OCS Inventory/GLPI), guardada
     | via ConfigService igual qualquer outra configuração do app.
     |---------------------------------------------------------
     */

    public function chaveAgente(): string
    {
        $chave = ConfigService::get('ativos_agent_api_key');

        if (!$chave) {
            $chave = bin2hex(random_bytes(32));
            ConfigService::set('ativos_agent_api_key', $chave);
        }

        return $chave;
    }

    public function regenerarChaveAgente(): string
    {
        $chave = bin2hex(random_bytes(32));
        ConfigService::set('ativos_agent_api_key', $chave);

        return $chave;
    }

    public function vincularDispositivoMesh(int $ativoId, ?string $meshDeviceId): bool
    {
        $meshDeviceId = $meshDeviceId === '' ? null : $meshDeviceId;

        if ($meshDeviceId !== null) {
            // Um dispositivo só pode estar vinculado a um ativo por vez.
            $this->repository->limparMeshDeOutros($meshDeviceId, $ativoId);
        }

        $ok = $this->repository->vincularMesh($ativoId, $meshDeviceId);

        if ($ok) {
            AuditService::registrar(
                'Ativos',
                'Acesso Remoto',
                $meshDeviceId ? "Ativo #{$ativoId} vinculado ao dispositivo MeshCentral {$meshDeviceId}." : "Ativo #{$ativoId} desvinculado do MeshCentral."
            );
        }

        return $ok;
    }

    public function desvincularDispositivoMesh(string $meshDeviceId): void
    {
        $this->repository->limparMesh($meshDeviceId);
        AuditService::registrar('Ativos', 'Acesso Remoto', "Dispositivo MeshCentral {$meshDeviceId} desvinculado.");
    }

    /**
     * Recebe o payload do agente Windows e faz upsert em `ativos`,
     * casando por `machine_guid` (não por hostname/nome -- esse pode
     * mudar). Substitui a lista de programas instalados (snapshot atual)
     * e insere os alertas enviados (o agente já manda só os novos desde
     * o último checkin, via um bookmark local dele).
     */
    public function checkinAgente(array $payload): array
    {
        $machineGuid = trim((string)($payload['machine_guid'] ?? ''));

        if ($machineGuid === '') {
            return ['success' => false, 'message' => 'machine_guid é obrigatório.'];
        }

        $tipo = in_array($payload['tipo'] ?? '', ['computador', 'servidor'], true) ? $payload['tipo'] : 'computador';

        $existente = $this->repository->buscarPorMachineGuid($machineGuid);

        $camposBase = [
            'nome' => trim((string)($payload['nome'] ?? '')) ?: ($existente['nome'] ?? 'Ativo sem nome'),
            'marca' => trim((string)($payload['marca'] ?? '')) ?: null,
            'modelo' => trim((string)($payload['modelo'] ?? '')) ?: null,
            'numero_serie' => trim((string)($payload['numero_serie'] ?? '')) ?: null,
            'ip' => trim((string)($payload['ip'] ?? '')) ?: null,
        ];

        $camposTecnicos = $this->extrairDetalhes($tipo, $payload);

        if ($existente) {
            $id = (int)$existente['id'];
            $detalhesAtuais = json_decode($existente['detalhes'] ?? '', true) ?: [];
            $detalhesNovos = array_merge($detalhesAtuais, $camposTecnicos);

            $this->repository->atualizarViaAgente($id, $camposBase, json_encode($detalhesNovos, JSON_UNESCAPED_UNICODE));
        } else {
            $id = $this->repository->criarViaAgente(array_merge($camposBase, [
                'tipo' => $tipo,
                'codigo_patrimonio' => $this->proximoCodigo($tipo),
                'machine_guid' => $machineGuid,
                'detalhes' => json_encode($camposTecnicos, JSON_UNESCAPED_UNICODE),
            ]));
        }

        $this->repository->substituirProgramas($id, array_slice($payload['programas'] ?? [], 0, 500));
        $this->repository->inserirAlertas($id, array_slice($payload['alertas'] ?? [], 0, 200));
        $this->repository->substituirRedes($id, array_slice($payload['redes'] ?? [], 0, 20));
        $this->repository->substituirVolumes($id, array_slice($payload['volumes'] ?? [], 0, 20));
        $this->repository->substituirPortas($id, array_slice($payload['portas'] ?? [], 0, 100));
        $this->repository->substituirMemoria($id, array_slice($payload['memoria_modulos'] ?? [], 0, 32));
        $this->repository->substituirAtualizacoesWindows($id, array_slice($payload['atualizacoes_windows'] ?? [], 0, 500));

        // Comandos remotos pendentes (desligar/reiniciar/desinstalar) --
        // entregues agora, junto com a resposta deste checkin. O agente
        // é quem decide como/quando executar (com aviso pro usuário,
        // quando aplicável).
        $pendentes = $this->repository->comandosPendentes($id);
        if (!empty($pendentes)) {
            $this->repository->marcarComandosEntregues(array_column($pendentes, 'id'));
        }

        return [
            'success' => true,
            'message' => 'Check-in recebido.',
            'ativo_id' => $id,
            'comandos' => array_map(fn($c) => [
                'id' => (int)$c['id'],
                'comando' => $c['comando'],
                'alvo' => $c['alvo'],
            ], $pendentes),
        ];
    }

    public function listarAtualizacoesWindows(int $ativoId): array
    {
        return $this->repository->listarAtualizacoesWindows($ativoId);
    }

    public function buscarPrograma(int $ativoId, int $programaId): ?array
    {
        return $this->repository->buscarPrograma($ativoId, $programaId);
    }

    public function buscarAtualizacaoWindows(int $ativoId, int $atualizacaoId): ?array
    {
        return $this->repository->buscarAtualizacaoWindows($ativoId, $atualizacaoId);
    }

    /*
     |---------------------------------------------------------
     | Comandos remotos (desligar/reiniciar)
     |---------------------------------------------------------
     */

    private const COMANDOS_VALIDOS = ['desligar', 'reiniciar', 'desinstalar_atualizacao', 'desinstalar_programa'];

    public function enviarComando(int $ativoId, string $comando, ?string $solicitadoPor, ?string $alvo = null, ?string $alvoLabel = null): array
    {
        if (!in_array($comando, self::COMANDOS_VALIDOS, true)) {
            return ['success' => false, 'message' => 'Comando inválido.'];
        }

        if (in_array($comando, ['desinstalar_atualizacao', 'desinstalar_programa'], true) && empty($alvo)) {
            return ['success' => false, 'message' => 'Informe o que deve ser desinstalado.'];
        }

        $ativo = $this->repository->buscarPorId($ativoId);
        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if ($ativo['origem'] !== 'agente') {
            return ['success' => false, 'message' => 'Este ativo não tem o agente Windows instalado -- não é possível enviar comandos remotos.'];
        }

        $this->repository->criarComando($ativoId, $comando, $solicitadoPor, $alvo, $alvoLabel);

        $labels = [
            'desligar' => 'Desligamento',
            'reiniciar' => 'Reinício',
            'desinstalar_atualizacao' => 'Desinstalação da atualização ' . $alvoLabel,
            'desinstalar_programa' => 'Desinstalação de ' . $alvoLabel,
        ];
        $label = $labels[$comando];

        AuditService::registrar(
            'Ativos',
            'Comando remoto',
            $label . ' solicitado(a) para ' . $ativo['codigo_patrimonio'] . ' (' . $ativo['nome'] . ').'
        );

        return [
            'success' => true,
            'message' => "{$label} agendado(a). Será entregue ao agente na próxima coleta (em até " . $this->intervaloAproximado() . ").",
        ];
    }

    public function historicoComandos(int $ativoId): array
    {
        return $this->repository->historicoComandos($ativoId);
    }

    /**
     * Intervalo esperado entre checkins, configurável em Ativos >
     * Dashboard. Usado pra (a) calcular a janela de "está ligada" e
     * (b) ser gravado no .ps1 baixado a partir de agora -- agentes já
     * instalados mantêm o intervalo antigo até serem reinstalados.
     */
    public function intervaloComunicacao(): int
    {
        return (int)(ConfigService::get('ativos_intervalo_comunicacao_min', '15') ?? 15);
    }

    public function salvarIntervaloComunicacao(int $minutos): bool
    {
        if ($minutos < 5 || $minutos > 240) {
            NotificationService::error('O intervalo deve ser entre 5 e 240 minutos.');
            return false;
        }

        ConfigService::set('ativos_intervalo_comunicacao_min', (string)$minutos);

        AuditService::registrar('Ativos', 'Config. Comunicação', "Intervalo de comunicação com agentes alterado para {$minutos} min.");

        NotificationService::success('Intervalo salvo. Vale pra novos agentes instalados a partir de agora -- os já instalados mantêm o intervalo com que foram configurados.');

        return true;
    }

    private function intervaloAproximado(): string
    {
        $min = $this->intervaloComunicacao();
        return "{$min}-" . ($min * 2) . ' min';
    }

    /**
     * "Ligada" é uma inferência, não um dado direto: se o último checkin
     * foi recente, assumimos que a máquina está ligada. A janela usa 2x
     * o intervalo configurado, como margem de uma coleta perdida.
     */
    public static function estaLigada(array $ativo): bool
    {
        if (empty($ativo['ultimo_checkin'])) {
            return false;
        }

        $minutos = (int)(ConfigService::get('ativos_intervalo_comunicacao_min', '15') ?? 15);

        return (time() - strtotime($ativo['ultimo_checkin'])) <= $minutos * 2 * 60;
    }

    /**
     * Quantos minutos se passaram desde o último checkin -- pra deixar
     * claro na tela que "Ligada" é uma inferência, não um dado ao vivo.
     */
    public static function minutosDesdeUltimoCheckin(array $ativo): ?int
    {
        if (empty($ativo['ultimo_checkin'])) {
            return null;
        }

        return (int)floor((time() - strtotime($ativo['ultimo_checkin'])) / 60);
    }

    /**
     * "X dias, HH:MM:SS" -- precisão total, não arredondado, pra bater
     * exatamente com o que o usuário pediu.
     */
    public static function uptimeTexto(array $ativo): ?string
    {
        $detalhes = is_array($ativo['detalhes'] ?? null)
            ? $ativo['detalhes']
            : (json_decode($ativo['detalhes'] ?? '', true) ?: []);

        $ligadoDesde = $detalhes['ligado_desde'] ?? null;
        if (!$ligadoDesde) {
            return null;
        }

        $timestamp = strtotime($ligadoDesde);
        if (!$timestamp) {
            return null;
        }

        $segundos = max(0, time() - $timestamp);
        $dias = intdiv($segundos, 86400);
        $horas = intdiv($segundos % 86400, 3600);
        $minutos = intdiv($segundos % 3600, 60);
        $segs = $segundos % 60;

        $hms = sprintf('%02d:%02d:%02d', $horas, $minutos, $segs);

        return $dias > 0
            ? "{$dias} dia" . ($dias > 1 ? 's' : '') . ", {$hms}"
            : $hms;
    }
}
