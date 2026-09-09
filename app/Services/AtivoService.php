<?php

namespace App\Services;

use App\Repositories\AtivoRepository;

class AtivoService
{
    private AtivoRepository $repository;
    private LinuxService $linux;

    public const STATUS = [
        'ativo' => 'Em uso',
        'manutencao' => 'Em manutenção',
        'estoque' => 'Em estoque',
        'baixado' => 'Baixado',
    ];

    /**
     * Colunas disponíveis em Ativos > Lista -- controla o menu de "quais
     * colunas mostrar" (visibilidade é só client-side/localStorage, não
     * precisa de round-trip; ordenação usa 'ordenar', que é validado contra
     * AtivoRepository::ORDENACAO_PERMITIDA antes de virar SQL). 'condicao'
     * é o status Ligado/Desligado ao vivo -- calculado, não tem coluna própria,
     * mas ordena pelo timestamp cru do último heartbeat (mesma ordem prática).
     * 'ip' e 'so' são as duas colunas novas pedidas,
     * por isso nascem desmarcadas por padrão (não muda a visão de quem já
     * usa a tela hoje até a pessoa optar por ligar).
     */
    public const COLUNAS_LISTA = [
        'codigo' => ['label' => 'Código', 'ordenar' => 'codigo_patrimonio', 'padrao' => true],
        'nome' => ['label' => 'Nome', 'ordenar' => 'nome', 'padrao' => true],
        'apelido' => ['label' => 'Apelido', 'ordenar' => 'apelido', 'padrao' => true],
        'tipo' => ['label' => 'Tipo', 'ordenar' => 'tipo', 'padrao' => true],
        'status' => ['label' => 'Status', 'ordenar' => 'status', 'padrao' => true],
        'condicao' => ['label' => 'Condição', 'ordenar' => 'condicao', 'padrao' => true],
        'setor' => ['label' => 'Setor', 'ordenar' => 'setor_nome', 'padrao' => true],
        'localizacao' => ['label' => 'Localização', 'ordenar' => 'localizacao_nome', 'padrao' => true],
        'unidade' => ['label' => 'Unidade', 'ordenar' => 'unidade_nome', 'padrao' => true],
        'responsavel' => ['label' => 'Responsável', 'ordenar' => 'responsavel', 'padrao' => false],
        'versao_agente' => ['label' => 'Versão do Agente', 'ordenar' => 'agente_versao', 'padrao' => true],
        'ip' => ['label' => 'IP Principal', 'ordenar' => 'ip', 'padrao' => false],
        'so' => ['label' => 'S.O.', 'ordenar' => 'sistema_operacional', 'padrao' => false],
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
            'windows_ativado' => 'Windows ativado',
            'rdp_habilitado' => 'RDP habilitado',
            'descricao_computador' => 'Descrição do computador',
            'nome_computador' => 'Nome do computador',
            'grupo_trabalho' => 'Grupo de trabalho',
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
            'windows_ativado' => 'Windows ativado',
            'rdp_habilitado' => 'RDP habilitado',
            'descricao_computador' => 'Descrição do computador',
            'nome_computador' => 'Nome do computador',
            'grupo_trabalho' => 'Grupo de trabalho',
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
        // 'dvr_canais' (array) fica FORA daqui de propósito -- mesmo motivo de
        // 'unifi_clientes'/'unifi_wan_config': o loop genérico da Visão Geral só
        // sabe exibir texto simples. Lido direto de $detalhes numa aba própria
        // ("Canais"), condicionada a tipo_slug === 'dvr_nvr' em ver.php.
        'dvr_nvr' => [
            'canais' => 'Número de canais (manual)',
            'capacidade_hd' => 'Capacidade do HD (manual)',
            'resolucao_gravacao' => 'Resolução de gravação',
            'dvr_modelo' => 'Modelo (Intelbras)',
            'dvr_serial' => 'Número de série (Intelbras)',
            'dvr_firmware' => 'Versão de firmware (Intelbras)',
            'dvr_hardware' => 'Versão de hardware (Intelbras)',
            'dvr_nome_dispositivo' => 'Nome no dispositivo',
            'dvr_status_disco' => 'Status do HD',
            'snmp_sys_descr' => 'Descrição (SNMP)',
            'snmp_uptime' => 'Uptime (SNMP)',
        ],
        'switch' => [
            'numero_portas' => 'Número de portas',
            'gerenciavel' => 'Gerenciável',
            'firmware' => 'Firmware',
            'snmp_sys_descr' => 'Descrição (SNMP)',
            'snmp_uptime' => 'Uptime (SNMP)',
            // omada_* -- coletados via Omada Controller (TP-Link), não SNMP.
            // Ficam ao lado dos campos manuais/SNMP acima, sem tirar nada:
            // um switch TP-Link pode ter os dois preenchidos ao mesmo tempo.
            'omada_model' => 'Modelo (Omada)',
            'omada_firmware' => 'Versão de firmware (Omada)',
            'omada_status' => 'Status no Controller',
            'omada_uptime' => 'Uptime (Omada)',
        ],
        // 'unifi_radios', 'unifi_clientes' (arrays) e 'unifi_adotado_em'
        // (formatado com data_br() só na view, nunca no coletor -- data_br()
        // só é carregado no bootstrap web, não no `rd` CLI que roda o cron)
        // ficam FORA daqui de propósito -- o loop genérico da Visão Geral em
        // ver.php só sabe exibir texto simples já pronto. Lidos direto de
        // $detalhes numa aba própria ("Wi-Fi"), condicionada a
        // tipo_slug === 'ponto_acesso'.
        'ponto_acesso' => [
            'unifi_model' => 'Modelo (UniFi)',
            'unifi_firmware' => 'Versão de firmware',
            'unifi_status' => 'Status no Controller',
            'unifi_uptime' => 'Uptime',
            'unifi_cpu_pct' => 'Uso de CPU',
            'unifi_mem_pct' => 'Uso de memória',
            'unifi_temperatura_c' => 'Temperatura',
            'unifi_clientes_total' => 'Clientes conectados',
        ],
        'roteador' => [
            'unifi_model' => 'Modelo (UniFi)',
            'unifi_firmware' => 'Versão de firmware',
            'unifi_status' => 'Status no Controller',
            'unifi_uptime' => 'Uptime',
            'unifi_cpu_pct' => 'Uso de CPU',
            'unifi_mem_pct' => 'Uso de memória',
            'unifi_temperatura_c' => 'Temperatura',
            'unifi_clientes_total' => 'Clientes conectados (rede toda)',
            'unifi_wan_status' => 'Status do WAN',
            'unifi_wan_ip_publico' => 'IP público (WAN)',
            'unifi_velocidade' => 'Última velocidade medida (Speedtest)',
        ],
    ];

    private const NOME_JOB_CRON_SNMP = 'Coleta SNMP de Ativos de TI';
    private const NOME_JOB_CRON_UNIFI = 'Coleta UniFi de Ativos de TI';
    private const NOME_JOB_CRON_OMADA = 'Coleta Omada de Ativos de TI';
    private const NOME_JOB_CRON_INTELBRAS_DVR = 'Coleta DVR/NVR Intelbras de Ativos de TI';

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
            'duplicatas' => $this->repository->duplicatasPorNome(),
        ];
    }

    /** Card de resumo do dashboard principal -- só computadores, contagem total + quantos estão ligados agora (heartbeat). */
    public function resumoDashboard(): array
    {
        $computadores = $this->repository->computadoresParaResumo();

        $ligados = 0;
        foreach ($computadores as $c) {
            if (self::estaLigada($c)) {
                $ligados++;
            }
        }

        return [
            'total' => count($computadores),
            'ligados' => $ligados,
        ];
    }

    public function criar(array $post): ?int
    {
        $tipo = (new AtivoTipoService())->buscar((int)($post['tipo_id'] ?? 0));

        if (!$tipo) {
            NotificationService::error('Tipo de ativo inválido.');
            return null;
        }

        $unidade = (new UnidadeService())->buscar((int)($post['unidade_id'] ?? 0));

        if (!$unidade) {
            NotificationService::error('Unidade inválida.');
            return null;
        }

        $dados = [
            'tipo_id' => (int)$tipo['id'],
            'unidade_id' => (int)$unidade['id'],
            'codigo_patrimonio' => $this->proximoCodigo($tipo, $unidade),
            'nome' => trim($post['nome'] ?? ''),
            'apelido' => trim($post['apelido'] ?? '') ?: null,
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
            'detalhes' => json_encode($this->extrairDetalhes($tipo['slug'] ?? '', $post), JSON_UNESCAPED_UNICODE),
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

        $unidadeId = !empty($post['unidade_id']) ? (int)$post['unidade_id'] : (int)$ativo['unidade_id'];
        if (!(new UnidadeService())->buscar($unidadeId)) {
            NotificationService::error('Unidade inválida.');
            return false;
        }

        $dados = [
            'nome' => trim($post['nome'] ?? ''),
            'apelido' => trim($post['apelido'] ?? '') ?: null,
            'marca' => trim($post['marca'] ?? '') ?: null,
            'modelo' => trim($post['modelo'] ?? '') ?: null,
            'numero_serie' => trim($post['numero_serie'] ?? '') ?: null,
            'setor_id' => !empty($post['setor_id']) ? (int)$post['setor_id'] : null,
            'localizacao_id' => !empty($post['localizacao_id']) ? (int)$post['localizacao_id'] : null,
            'unidade_id' => $unidadeId,
            'responsavel' => trim($post['responsavel'] ?? '') ?: null,
            'status' => isset(self::STATUS[$post['status'] ?? '']) ? $post['status'] : $ativo['status'],
            'ip' => trim($post['ip'] ?? '') ?: null,
            'snmp_habilitado' => isset($post['snmp_habilitado']) ? 1 : 0,
            'snmp_community' => trim($post['snmp_community'] ?? '') ?: null,
            'observacoes' => trim($post['observacoes'] ?? '') ?: null,
            'detalhes' => json_encode($this->extrairDetalhes($ativo['tipo_slug'] ?? '', $post), JSON_UNESCAPED_UNICODE),
            'machine_guid' => $ativo['machine_guid'] ?? null,
        ];

        if ($dados['nome'] === '') {
            NotificationService::error('Informe um nome/identificação para o ativo.');
            return false;
        }

        // Só um ativo de agente tem machine_guid pra editar, e só faz
        // sentido corrigir manualmente em dois casos: reformatação (a
        // máquina virou um ativo novo) ou colisão (duas máquinas com
        // identificador de hardware genérico acabaram com o mesmo
        // machine_guid -- ver CollectorService no agente). Confere
        // duplicidade aqui pra dar um erro claro em vez de estourar a
        // constraint UNIQUE do banco sem explicação.
        if ($ativo['origem'] === 'agente' && isset($post['machine_guid'])) {
            $novoGuid = trim($post['machine_guid']);

            if ($novoGuid === '') {
                NotificationService::error('O identificador da máquina não pode ficar em branco.');
                return false;
            }

            if ($novoGuid !== $ativo['machine_guid']) {
                $conflito = $this->repository->buscarPorMachineGuid($novoGuid);
                if ($conflito && (int)$conflito['id'] !== $id) {
                    NotificationService::error("Esse identificador já pertence ao ativo {$conflito['codigo_patrimonio']} ({$conflito['nome']}) -- não dá pra usar o mesmo em dois ativos.");
                    return false;
                }
            }

            $dados['machine_guid'] = $novoGuid;
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

    /** @return array<int, array> ativo_id => lista de volumes locais com uso >= 90% -- pro alerta piscando na Lista de Ativos. */
    public function volumesCriticos(): array
    {
        $porAtivo = [];

        foreach ($this->repository->volumesCriticos() as $v) {
            $totalGb = (float)$v['total_gb'];
            $usadoGb = (float)$v['usado_gb'];

            $porAtivo[(int)$v['ativo_id']][] = [
                'unidade' => $v['unidade'],
                'total_gb' => $totalGb,
                'usado_gb' => $usadoGb,
                'pct' => $totalGb > 0 ? round(($usadoGb / $totalGb) * 100, 1) : 0,
            ];
        }

        return $porAtivo;
    }

    /** Buckets fixos de RAM (GB) pro gráfico do Panorama da Frota -- valor fora da lista cai em "Outro". */
    private const BUCKETS_RAM_GB = [2, 4, 6, 8, 12, 16, 24, 32, 64];

    /** Passos fixos de CPU (GHz, arredondado pra baixo) pro gráfico do Panorama da Frota. */
    private const BUCKETS_CPU_GHZ = [2.0, 2.5, 3.0, 3.5, 4.0];

    /**
     * Números consolidados do parque (RAM, CPU, SO, disco crítico) pro painel
     * "Panorama da Frota" -- `memoria_ram`/`processador`/`sistema_operacional`
     * são texto livre vindo do agente (nunca foram pensados como
     * numérico/enum), então bucketiza por regex. Carregado sob demanda (rota
     * própria), não no load da lista inteira.
     */
    public function relatorioFrota(): array
    {
        $ramPorBucket = [];
        $cpuPorBucket = [];
        $soPorBucket = [];
        $total = 0;

        $tipoComputador = (new AtivoTipoService())->buscarPorSlug('computador');

        foreach ($this->repository->detalhesPorTipo((int)$tipoComputador['id']) as $ativo) {
            $total++;
            $detalhes = json_decode($ativo['detalhes'] ?? '', true) ?: [];

            $bucketRam = $this->bucketRam($detalhes['memoria_ram'] ?? '');
            $ramPorBucket[$bucketRam] = ($ramPorBucket[$bucketRam] ?? 0) + 1;

            $bucketCpu = $this->bucketCpu($detalhes['processador'] ?? '');
            $cpuPorBucket[$bucketCpu] = ($cpuPorBucket[$bucketCpu] ?? 0) + 1;

            $bucketSo = $this->bucketSistemaOperacional($detalhes['sistema_operacional'] ?? '');
            $soPorBucket[$bucketSo] = ($soPorBucket[$bucketSo] ?? 0) + 1;
        }

        return [
            'total' => $total,
            'ram' => $this->serieOrdenada($ramPorBucket, array_merge(
                array_map(static fn($gb) => $gb . ' GB', self::BUCKETS_RAM_GB),
                ['Outro']
            )),
            'cpu' => $this->serieOrdenada($cpuPorBucket, array_merge(
                array_map(static fn($ghz) => number_format($ghz, 1) . '+ GHz', self::BUCKETS_CPU_GHZ),
                ['Não informado']
            )),
            'so' => $this->serieOrdenada($soPorBucket, ['Windows 11', 'Windows 10', 'Windows 8.1', 'Windows 8', 'Windows 7', 'Windows Server', 'Outro']),
            'disco' => $this->serieOrdenada($this->discoPorTipo(), ['SSD', 'HD', 'Desconhecido', 'Não coletado']),
            'discosCriticos' => count($this->volumesCriticos()),
        ];
    }

    /** Volumes locais bucketizados por SSD/HD -- "Não coletado" é NULL (agente ainda não atualizado ou WMI sem MSFT_PhysicalDisk), diferente de "Desconhecido" (WMI respondeu mas não soube dizer o tipo). */
    private function discoPorTipo(): array
    {
        $porTipo = [];

        foreach ($this->repository->volumesLocaisTodos() as $v) {
            $tipo = match ($v['tipo_disco'] ?? null) {
                'SSD' => 'SSD',
                'HDD' => 'HD',
                'Desconhecido' => 'Desconhecido',
                default => 'Não coletado',
            };

            $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + 1;
        }

        return $porTipo;
    }

    private function bucketRam(string $texto): string
    {
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*GB/i', $texto, $m)) {
            return 'Outro';
        }

        $gb = (float)str_replace(',', '.', $m[1]);

        foreach (self::BUCKETS_RAM_GB as $bucket) {
            if (abs($gb - $bucket) < 0.5) {
                return $bucket . ' GB';
            }
        }

        return 'Outro';
    }

    private function bucketCpu(string $texto): string
    {
        if (!preg_match('/([\d.]+)\s*GHz/i', $texto, $m)) {
            return 'Não informado';
        }

        $ghz = (float)$m[1];

        $passos = self::BUCKETS_CPU_GHZ;
        rsort($passos);

        foreach ($passos as $passo) {
            if ($ghz >= $passo) {
                return number_format($passo, 1) . '+ GHz';
            }
        }

        return 'Não informado';
    }

    private function bucketSistemaOperacional(string $texto): string
    {
        // Ordem importa: "Windows Server" tem que ser checado antes do
        // genérico "Windows \d+", senão "Windows Server 2022" cairia em "Outro".
        if (preg_match('/Windows\s+Server/i', $texto)) {
            return 'Windows Server';
        }
        if (preg_match('/Windows\s*11/i', $texto)) {
            return 'Windows 11';
        }
        if (preg_match('/Windows\s*10/i', $texto)) {
            return 'Windows 10';
        }
        if (preg_match('/Windows\s*8\.1/i', $texto)) {
            return 'Windows 8.1';
        }
        if (preg_match('/Windows\s*8/i', $texto)) {
            return 'Windows 8';
        }
        if (preg_match('/Windows\s*7/i', $texto)) {
            return 'Windows 7';
        }

        return 'Outro';
    }

    /** @return array{labels: array<int,string>, dados: array<int,int>} Só entram labels com pelo menos 1 ocorrência, na ordem fixa dada -- pronto pro Chart.js. */
    private function serieOrdenada(array $porBucket, array $ordemFixa): array
    {
        $labels = [];
        $dados = [];

        foreach ($ordemFixa as $label) {
            if (!empty($porBucket[$label])) {
                $labels[] = $label;
                $dados[] = $porBucket[$label];
            }
        }

        return ['labels' => $labels, 'dados' => $dados];
    }

    public function listarMemoria(int $ativoId): array
    {
        return $this->repository->listarMemoria($ativoId);
    }

    public function listarPlacasVideo(int $ativoId): array
    {
        return $this->repository->listarPlacasVideo($ativoId);
    }

    public function listarControladoras(int $ativoId): array
    {
        return $this->repository->listarControladoras($ativoId);
    }

    public function listarBateria(int $ativoId): array
    {
        return $this->repository->listarBateria($ativoId);
    }

    public function listarPortas(int $ativoId): array
    {
        return $this->repository->listarPortas($ativoId);
    }

    public function listarPortasRede(int $ativoId): array
    {
        return $this->repository->listarPortasRede($ativoId);
    }

    /** @param array $tipo linha de ativos_tipos; @param array $unidade linha de unidades */
    private function proximoCodigo(array $tipo, array $unidade): string
    {
        $numero = $this->repository->proximoNumeroContador((int)$tipo['id'], (int)$unidade['id']);

        return sprintf(
            '%s-%s-%s-%0' . $this->codigoDigitos() . 'd',
            $this->siglaEmpresa(),
            $unidade['sigla'],
            $tipo['sigla'],
            $numero
        );
    }

    /** Quantidade de dígitos do número sequencial do código de patrimônio (ex: 4 dígitos -> RD-PC-0001). Só vale pra códigos novos -- não reescreve os já existentes. */
    public function codigoDigitos(): int
    {
        return max(1, min(10, (int)(ConfigService::get('ativos_codigo_digitos', '6') ?? 6)));
    }

    public function salvarCodigoDigitos(int $digitos): bool
    {
        if ($digitos < 1 || $digitos > 10) {
            NotificationService::error('Quantidade de dígitos inválida (use um valor entre 1 e 10).');
            return false;
        }

        ConfigService::set('ativos_codigo_digitos', (string)$digitos);
        AuditService::registrar('Ativos', 'Configuração de Etiqueta', "Código de patrimônio passa a usar {$digitos} dígitos.");

        return true;
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

    private const EXTENSOES_LOGO_VALIDAS = ['jpg', 'jpeg', 'png'];
    private const MIME_LOGO = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    private const LOGO_LARGURA_MAX = 320;
    private const LOGO_ALTURA_MAX = 120;

    public static function caminhoLogoEmpresa(): string
    {
        return __DIR__ . '/../../storage/uploads/empresa/logo';
    }

    public function logoEmpresaConfigurada(): bool
    {
        return is_file(self::caminhoLogoEmpresa());
    }

    public function logoEmpresaMime(): string
    {
        $extensao = ConfigService::get('empresa_logo_ext', 'png') ?: 'png';

        return self::MIME_LOGO[$extensao] ?? 'image/png';
    }

    public function salvarLogoEmpresa(string $caminhoTemporario, string $nomeOriginal): bool
    {
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if (!in_array($extensao, self::EXTENSOES_LOGO_VALIDAS, true)) {
            NotificationService::error('A logo precisa ser .jpg, .jpeg ou .png.');
            return false;
        }

        // O redimensionamento pro padrão 320x120 já acontece no navegador
        // via canvas -- esse limite aqui é só uma trava contra alguém
        // mandar um arquivo gigante direto pro endpoint, sem passar por lá.
        if (filesize($caminhoTemporario) > 3 * 1024 * 1024) {
            NotificationService::error('Arquivo muito grande (máximo 3MB).');
            return false;
        }

        $destino = self::caminhoLogoEmpresa();
        $pasta = dirname($destino);
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Não foi possível preparar a pasta de armazenamento no servidor.');
            return false;
        }

        $extensaoFinal = $extensao;

        // Reforço no servidor (o canvas do navegador pode ser pulado
        // enviando direto pro endpoint): com GD disponível, revalida que é
        // uma imagem de verdade (não só a extensão) e redimensiona de novo
        // pro mesmo padrão, sempre virando PNG.
        if (extension_loaded('gd')) {
            if (!$this->redimensionarLogoEmpresa($caminhoTemporario, $destino)) {
                NotificationService::error('Arquivo não é uma imagem válida.');
                return false;
            }
            $extensaoFinal = 'png';
        } elseif (!@copy($caminhoTemporario, $destino)) {
            NotificationService::error('Não foi possível salvar a logo no servidor.');
            return false;
        }

        ConfigService::set('empresa_logo_ext', $extensaoFinal);

        AuditService::registrar('Administração', 'Dados da Empresa', 'Logo da empresa enviada/atualizada.');
        NotificationService::success('Logo salva.');

        return true;
    }

    public function removerLogoEmpresa(): void
    {
        @unlink(self::caminhoLogoEmpresa());
        ConfigService::set('empresa_logo_ext', '');

        AuditService::registrar('Administração', 'Dados da Empresa', 'Logo da empresa removida.');
        NotificationService::success('Logo removida.');
    }

    /*
     |---------------------------------------------------------
     | Logo DO SISTEMA (o "RD Intranet" no topo do menu -- imagem própria
     | da instalação, não do cliente que fica logo abaixo). Padrão embutido
     | no repo (public/assets/img/logord.png, versionado no git) -- fica
     | de propósito FORA dele, em storage/uploads (gitignored): se o
     | upload sobrescrevesse o arquivo versionado direto, o próximo "git
     | pull" (Administração > Atualizações) travaria por causa da mudança
     | local não commitada. Sem upload próprio, cai no arquivo padrão.
     |---------------------------------------------------------
     */

    private const LOGO_SISTEMA_LARGURA_MAX = 480;
    private const LOGO_SISTEMA_ALTURA_MAX = 480;

    public static function caminhoLogoSistema(): string
    {
        return __DIR__ . '/../../storage/uploads/sistema/logo';
    }

    public static function caminhoLogoSistemaPadrao(): string
    {
        return __DIR__ . '/../../public/assets/img/logord.png';
    }

    public function logoSistemaConfigurada(): bool
    {
        return is_file(self::caminhoLogoSistema());
    }

    public function logoSistemaMime(): string
    {
        $extensao = ConfigService::get('sistema_logo_ext', 'png') ?: 'png';

        return self::MIME_LOGO[$extensao] ?? 'image/png';
    }

    public function salvarLogoSistema(string $caminhoTemporario, string $nomeOriginal): bool
    {
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if (!in_array($extensao, self::EXTENSOES_LOGO_VALIDAS, true)) {
            NotificationService::error('A logo precisa ser .jpg, .jpeg ou .png.');
            return false;
        }

        if (filesize($caminhoTemporario) > 3 * 1024 * 1024) {
            NotificationService::error('Arquivo muito grande (máximo 3MB).');
            return false;
        }

        $destino = self::caminhoLogoSistema();
        $pasta = dirname($destino);
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Não foi possível preparar a pasta de armazenamento no servidor.');
            return false;
        }

        $extensaoFinal = $extensao;

        if (extension_loaded('gd')) {
            if (!$this->redimensionarImagemLogo($caminhoTemporario, $destino, self::LOGO_SISTEMA_LARGURA_MAX, self::LOGO_SISTEMA_ALTURA_MAX)) {
                NotificationService::error('Arquivo não é uma imagem válida.');
                return false;
            }
            $extensaoFinal = 'png';
        } elseif (!@copy($caminhoTemporario, $destino)) {
            NotificationService::error('Não foi possível salvar a logo no servidor.');
            return false;
        }

        ConfigService::set('sistema_logo_ext', $extensaoFinal);

        AuditService::registrar('Administração', 'Dados da Empresa', 'Logo do sistema (RD Intranet) enviada/atualizada.');
        NotificationService::success('Logo do sistema salva.');

        return true;
    }

    public function removerLogoSistema(): void
    {
        @unlink(self::caminhoLogoSistema());
        ConfigService::set('sistema_logo_ext', '');

        AuditService::registrar('Administração', 'Dados da Empresa', 'Logo do sistema removida -- voltou ao padrão.');
        NotificationService::success('Logo do sistema removida, voltou ao padrão.');
    }

    /** Mesmo padrão 320x120 do canvas do navegador (não amplia, mantém proporção), sempre gravando PNG com transparência preservada. */
    private function redimensionarLogoEmpresa(string $origem, string $destino): bool
    {
        return $this->redimensionarImagemLogo($origem, $destino, self::LOGO_LARGURA_MAX, self::LOGO_ALTURA_MAX);
    }

    private function redimensionarImagemLogo(string $origem, string $destino, int $larguraMax, int $alturaMax): bool
    {
        $info = @getimagesize($origem);
        if ($info === false) {
            return false;
        }

        [$largura, $altura, $tipo] = $info;

        $imagemOrigem = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($origem),
            IMAGETYPE_PNG => @imagecreatefrompng($origem),
            default => false,
        };

        if ($imagemOrigem === false) {
            return false;
        }

        $escala = min(1, $larguraMax / $largura, $alturaMax / $altura);
        $larguraFinal = max(1, (int)round($largura * $escala));
        $alturaFinal = max(1, (int)round($altura * $escala));

        $imagemFinal = imagecreatetruecolor($larguraFinal, $alturaFinal);
        imagealphablending($imagemFinal, false);
        imagesavealpha($imagemFinal, true);
        imagefill($imagemFinal, 0, 0, imagecolorallocatealpha($imagemFinal, 0, 0, 0, 127));
        imagealphablending($imagemFinal, true);
        imagecopyresampled($imagemFinal, $imagemOrigem, 0, 0, 0, 0, $larguraFinal, $alturaFinal, $largura, $altura);

        $ok = imagepng($imagemFinal, $destino);

        imagedestroy($imagemOrigem);
        imagedestroy($imagemFinal);

        return $ok;
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

        $coletado = (new SnmpService())->coletar($ativo['ip'], $community, $ativo['tipo_slug'] ?? '');

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

    /**
     * Casa o ativo (por IP exato -- mesma técnica de
     * NetworkToolsService::relacionarComAtivos()) com um dispositivo do
     * UniFi Controller, e grava modelo/firmware/status/rádios/clientes
     * conectados em `detalhes`. Ao contrário do SNMP, os rádios e a lista
     * de clientes ficam fora de CAMPOS_DETALHES (são arrays, não texto) --
     * ver.php lê essas duas chaves direto na aba "Wi-Fi".
     */
    /**
     * Casa um IP contra a lista de dispositivos do UniFi Controller --
     * usado tanto por coletarUnifi() quanto por avaliarInternetUnifi(),
     * num método só, exatamente pra não duplicar (e desalinhar, como já
     * aconteceu uma vez) o fallback de gateway abaixo.
     *
     * Gateways (UCG etc.) reportam em 'ipAddress' o IP do WAN, não o de
     * gerenciamento na LAN que a gente cadastra em Ativos -- quando o
     * casamento direto falha, procura o IP entre as portas do dispositivo
     * via API legada e casa por MAC.
     */
    private function buscarDispositivoUnifiPorIp(UnifiService $unifi, string $ip): ?array
    {
        $dispositivos = $unifi->listarDispositivos();

        foreach ($dispositivos as $d) {
            if (($d['ipAddress'] ?? '') === $ip) {
                return $d;
            }
        }

        foreach ($unifi->listarDispositivosLegado() as $legado) {
            $temIp = false;
            foreach ($legado['port_table'] ?? [] as $porta) {
                if (($porta['ip'] ?? '') === $ip) {
                    $temIp = true;
                    break;
                }
            }

            if (!$temIp) {
                continue;
            }

            foreach ($dispositivos as $d) {
                if (($d['macAddress'] ?? '') === ($legado['mac'] ?? '')) {
                    return $d;
                }
            }
        }

        return null;
    }

    public function coletarUnifi(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if (empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Este ativo não tem IP cadastrado.'];
        }

        $unifi = new UnifiService();

        if (!$unifi->configurado()) {
            return ['success' => false, 'message' => 'Integração com o UniFi Controller ainda não configurada -- veja Integrações.'];
        }

        $dispositivo = $this->buscarDispositivoUnifiPorIp($unifi, $ativo['ip']);

        if ($dispositivo === null) {
            return ['success' => false, 'message' => 'Nenhum dispositivo com esse IP foi encontrado no UniFi Controller.'];
        }

        $detalheDispositivo = $unifi->buscarDetalheDispositivo($dispositivo['id']) ?? [];

        // Registro legado do MESMO dispositivo -- só ele traz uso de
        // CPU/memória, temperatura, uptime, contagem de clientes e (pra
        // gateways) status do WAN/IP público/último speedtest, nada disso
        // existe em /integration/v1.
        $legadoDispositivo = null;
        foreach ($unifi->listarDispositivosLegado() as $legado) {
            if (($legado['mac'] ?? '') === ($dispositivo['macAddress'] ?? '')) {
                $legadoDispositivo = $legado;
                break;
            }
        }

        $radios = [];
        foreach ($detalheDispositivo['interfaces']['radios'] ?? [] as $radio) {
            $radios[] = [
                'banda' => self::rotuloBandaRadio((float)($radio['frequencyGHz'] ?? 0)),
                'canal' => $radio['channel'] ?? null,
                'largura_mhz' => $radio['channelWidthMHz'] ?? null,
                'padrao' => $radio['wlanStandard'] ?? null,
            ];
        }

        // API legada (stat/sta) em vez de /integration/v1/clients -- essa
        // traz 'essid' (rede Wi-Fi) e 'signal' (dBm), que a API nova ainda
        // não expõe. O mesmo registro já carrega praticamente tudo que o app
        // oficial mostra na ficha do cliente (confirmei campo a campo contra
        // um cliente real) -- guarda tudo junto, exibido num detalhe/modal na
        // view em vez de aba nova, pra não pedir outro endpoint.
        $clientesWifi = [];
        foreach ($unifi->listarClientesLegado() as $c) {
            if (($c['ap_mac'] ?? '') === ($dispositivo['macAddress'] ?? '') && empty($c['is_wired'])) {
                $clientesWifi[] = [
                    'nome' => $c['hostname'] ?? ($c['name'] ?? ($c['mac'] ?? '')),
                    'mac' => $c['mac'] ?? '',
                    'ip' => $c['ip'] ?? '',
                    'rede' => $c['essid'] ?? '',
                    'sinal_dbm' => $c['signal'] ?? null,
                    // 'assoc_time' vem em epoch (segundos) -- date('c', ...) já formata
                    // em ISO 8601, sem depender de data_br() (não carregado no cron).
                    'conectado_em' => !empty($c['assoc_time']) ? date('c', (int)$c['assoc_time']) : '',
                    'ap_nome' => $c['last_uplink_name'] ?? '',
                    'canal' => $c['channel'] ?? null,
                    'largura_canal_mhz' => $c['channel_width'] ?? null,
                    'padrao_wifi' => self::rotuloPadraoWifi((string)($c['radio_proto'] ?? '')),
                    'rx_rate_mbps' => isset($c['rx_rate']) ? round($c['rx_rate'] / 1000, 1) : null,
                    'tx_rate_mbps' => isset($c['tx_rate']) ? round($c['tx_rate'] / 1000, 1) : null,
                    'retentativas_tx_pct' => $c['wifi_tx_retries_percentage'] ?? null,
                    'vlan' => $c['vlan'] ?? null,
                    // 'satisfaction' é literalmente o score de "Experiência WiFi" (%) mostrado no app oficial.
                    'experiencia_pct' => $c['satisfaction'] ?? null,
                    'uptime_sessao' => !empty($c['uptime']) ? self::duracaoLegivel((int)$c['uptime']) : '',
                    'dados_sessao' => (isset($c['tx_bytes']) || isset($c['rx_bytes']))
                        ? self::tamanhoLegivel((int)($c['tx_bytes'] ?? 0) + (int)($c['rx_bytes'] ?? 0))
                        : '',
                    'rede_logica' => $c['network'] ?? '',
                    'fabricante' => $c['oui'] ?? '',
                ];
            }
        }

        $coletado = [
            'unifi_model' => $dispositivo['model'] ?? '',
            'unifi_firmware' => $dispositivo['firmwareVersion'] ?? '',
            'unifi_status' => self::rotuloStatusUnifi($dispositivo['state'] ?? ''),
            // ISO 8601 cru (ex: "2026-06-18T18:35:06Z") -- formatado com
            // data_br() só na view (ver.php), nunca aqui: este método também
            // roda via `rd ativos:coletar-unifi` (cron), que não carrega
            // app/bootstrap.php (só o CLI/`rd` em si), logo não tem data_br().
            'unifi_adotado_em' => $detalheDispositivo['adoptedAt'] ?? '',
            'unifi_radios' => $radios,
            'unifi_clientes' => $clientesWifi,
        ];

        // Só existem na API legada, e nem todo tipo de dispositivo reporta
        // todos eles (gateways têm WAN/speedtest, AP e switch não) -- por
        // isso cada chave só entra em $coletado quando o dado realmente veio.
        // date() é função nativa do PHP, funciona em qualquer contexto
        // (diferente de data_br()) -- por isso já formata aqui, sem precisar
        // de um campo cru + formatação adiada como o unifi_adotado_em acima.
        if ($legadoDispositivo !== null) {
            if (isset($legadoDispositivo['system-stats']['cpu'])) {
                $coletado['unifi_cpu_pct'] = round((float)$legadoDispositivo['system-stats']['cpu'], 1) . '%';
            }
            if (isset($legadoDispositivo['system-stats']['mem'])) {
                $coletado['unifi_mem_pct'] = round((float)$legadoDispositivo['system-stats']['mem'], 1) . '%';
            }
            if (!empty($legadoDispositivo['uptime'])) {
                $coletado['unifi_uptime'] = self::duracaoLegivel((int)$legadoDispositivo['uptime']);
            }
            if (isset($legadoDispositivo['temperatures'][0]['value'])) {
                $coletado['unifi_temperatura_c'] = round((float)$legadoDispositivo['temperatures'][0]['value']) . '°C';
            }
            if (isset($legadoDispositivo['num_sta'])) {
                $coletado['unifi_clientes_total'] = (string)$legadoDispositivo['num_sta'];
            }
            if (!empty($legadoDispositivo['last_wan_status']) && is_array($legadoDispositivo['last_wan_status'])) {
                $partes = [];
                foreach ($legadoDispositivo['last_wan_status'] as $wan => $status) {
                    $partes[] = "{$wan}: " . ucfirst((string)$status);
                }
                $coletado['unifi_wan_status'] = implode(' · ', $partes);
            }
            if (!empty($legadoDispositivo['last_wan_ip'])) {
                $coletado['unifi_wan_ip_publico'] = $legadoDispositivo['last_wan_ip'];
            }

            $speedtest = $legadoDispositivo['speedtest-status'] ?? null;
            if (!empty($speedtest) && (isset($speedtest['xput_download']) || isset($speedtest['xput_upload']))) {
                $download = round((float)($speedtest['xput_download'] ?? 0), 1);
                $upload = round((float)($speedtest['xput_upload'] ?? 0), 1);
                $coletado['unifi_velocidade'] = "{$download} Mbps ↓ / {$upload} Mbps ↑"
                    . (!empty($speedtest['rundate']) ? ' (testado em ' . date('d/m/Y H:i', (int)$speedtest['rundate']) . ')' : '');
            }

            // Só gateways têm wan1/wan2 -- o resto (config de failover/balanceamento,
            // diagnóstico 24h por alvo, redes LAN/DHCP) só faz sentido pra eles.
            if (isset($legadoDispositivo['wan1']) || isset($legadoDispositivo['wan2'])) {
                $redesWan = array_values(array_filter(
                    $unifi->listarRedesConfiguradas(),
                    fn($r) => ($r['purpose'] ?? '') === 'wan'
                ));

                // wan_load_balance_type NÃO é "o modo dessa WAN" isolado -- o Controller
                // marca a WAN de prioridade 1 como "weighted" e as demais como
                // "failover-only" mesmo com "WAN Mode: Failover Only" selecionado (foi
                // assim que descobri ao vivo: achava que "weighted" numa WAN só já
                # significava balanceamento, e errava o modo sempre que a config real era
                // failover). O modo de verdade é do SITE inteiro: só é balanceamento de
                // carga quando TODAS as WANs estão "weighted" -- qualquer "failover-only"
                // no meio indica failover.
                $modoGlobal = (!empty($redesWan) && count($redesWan) === count(array_filter(
                    $redesWan,
                    fn($r) => ($r['wan_load_balance_type'] ?? '') === 'weighted'
                ))) ? 'Balanceamento de carga' : 'Failover';

                $wanConfig = [];
                foreach ($redesWan as $rede) {
                    $item = [
                        'grupo' => $rede['wan_networkgroup'] ?? '',
                        'nome' => $rede['name'] ?? '',
                        'tipo' => strtoupper((string)($rede['wan_type'] ?? '')),
                        'prioridade' => $rede['wan_failover_priority'] ?? null,
                        'download_contratado_mbps' => isset($rede['wan_provider_capabilities']['download_kilobits_per_second'])
                            ? round($rede['wan_provider_capabilities']['download_kilobits_per_second'] / 1000, 1) : null,
                        'upload_contratado_mbps' => isset($rede['wan_provider_capabilities']['upload_kilobits_per_second'])
                            ? round($rede['wan_provider_capabilities']['upload_kilobits_per_second'] / 1000, 1) : null,
                        'habilitada' => (bool)($rede['enabled'] ?? true),
                    ];

                    // PPPoE guarda usuário/senha do provedor em texto puro no próprio
                    // UniFi -- o usuário pediu explicitamente pra poder ver aqui (confirmar
                    // com o provedor em caso de problema), então passa direto, sem mascarar
                    // no dado gravado. A view (ver.php) que decide mostrar oculto por padrão.
                    if (($rede['wan_type'] ?? '') === 'pppoe') {
                        $item['pppoe_usuario'] = $rede['wan_username'] ?? '';
                        $item['pppoe_senha'] = $rede['x_wan_password'] ?? '';
                    }

                    $wanConfig[] = $item;
                }
                usort($wanConfig, fn($a, $b) => ($a['prioridade'] ?? 99) <=> ($b['prioridade'] ?? 99));
                $coletado['unifi_wan_config'] = $wanConfig;
                $coletado['unifi_wan_modo'] = $modoGlobal;

                $diagnostico = [];
                foreach (['WAN', 'WAN2'] as $grupo) {
                    if (!isset($legadoDispositivo['uptime_stats'][$grupo])) {
                        continue;
                    }

                    $stats = $legadoDispositivo['uptime_stats'][$grupo];
                    $alvos = array_merge($stats['monitors'] ?? [], $stats['alerting_monitors'] ?? []);

                    $diagnostico[$grupo] = [
                        'disponibilidade_pct' => $stats['availability'] ?? null,
                        'latencia_media_ms' => $stats['latency_average'] ?? null,
                        'alvos' => array_map(fn($m) => [
                            'alvo' => $m['target'] ?? '',
                            'tipo' => strtoupper((string)($m['type'] ?? '')),
                            'disponibilidade_pct' => $m['availability'] ?? null,
                            'latencia_ms' => $m['latency_average'] ?? null,
                        ], $alvos),
                    ];
                }
                $coletado['unifi_wan_diagnostico'] = $diagnostico;

                $redesLan = [];
                foreach ($unifi->listarRedesConfiguradas() as $rede) {
                    if (($rede['purpose'] ?? '') !== 'corporate') {
                        continue;
                    }

                    $redesLan[] = [
                        'nome' => $rede['name'] ?? '',
                        'vlan' => $rede['vlan'] ?? null,
                        'subnet' => $rede['ip_subnet'] ?? '',
                        'dhcp_habilitado' => (bool)($rede['dhcpd_enabled'] ?? false),
                    ];
                }
                $coletado['unifi_redes_lan'] = $redesLan;
            }
        }

        $detalhesAtuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $detalhesNovos = array_merge($detalhesAtuais, $coletado);

        $this->repository->atualizarDetalhesApi($id, json_encode($detalhesNovos, JSON_UNESCAPED_UNICODE));

        AuditService::registrar('Ativos', 'Coleta UniFi', 'Dados coletados via API do UniFi Controller para ' . $ativo['codigo_patrimonio'] . '.');

        return ['success' => true, 'message' => 'Dados coletados com sucesso via UniFi Controller.'];
    }

    /**
     * Dispara um speedtest de verdade no gateway (não é o valor salvo antigo)
     * e espera o resultado -- o próprio Controller demora uns 15-20s pra
     * rodar. Síncrono de propósito (mesmo padrão do "Testar conexão" do
     * Backup, que também usa set_time_limit maior): é uma ação explícita
     * do usuário clicando um botão, não algo que precise ser assíncrono.
     */
    public function avaliarInternetUnifi(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        $unifi = new UnifiService();

        if (!$unifi->configurado()) {
            return ['success' => false, 'message' => 'Integração com o UniFi Controller ainda não configurada -- veja Integrações.'];
        }

        $dispositivo = $this->buscarDispositivoUnifiPorIp($unifi, (string)$ativo['ip']);

        if ($dispositivo === null || empty($dispositivo['macAddress'])) {
            return ['success' => false, 'message' => 'Dispositivo não encontrado no UniFi Controller -- colete os dados normais primeiro.'];
        }

        $mac = $dispositivo['macAddress'];

        $antes = $unifi->buscarDispositivoLegado($mac);
        $timestampAntes = $antes['speedtest-status']['timestamp'] ?? 0;

        $disparo = $unifi->dispararSpeedtest();
        if (!$disparo['success']) {
            return $disparo;
        }

        set_time_limit(45);

        $resultado = null;
        for ($tentativa = 0; $tentativa < 10; $tentativa++) {
            sleep(3);

            $legado = $unifi->buscarDispositivoLegado($mac);
            $pendente = !empty($legado['speedtest-pending-interfaces']);
            $statusAtual = $legado['speedtest-status'] ?? [];
            $timestampAtual = $statusAtual['timestamp'] ?? 0;

            // O Controller grava o registro em mais de uma etapa durante o teste
            // (a primeira, inclusive, com timestamp novo mas taxas zeradas) --
            // só aceita quando realmente tem throughput medido, senão continua
            // esperando em vez de reportar 0 Mbps como se fosse resultado real.
            if (!$pendente && $timestampAtual > $timestampAntes && (float)($statusAtual['xput_download'] ?? 0) > 0) {
                $resultado = $statusAtual;
                break;
            }
        }

        if ($resultado === null) {
            return ['success' => false, 'message' => 'O speedtest não terminou a tempo -- tente de novo em alguns instantes.'];
        }

        $download = round((float)($resultado['xput_download'] ?? 0), 1);
        $upload = round((float)($resultado['xput_upload'] ?? 0), 1);
        $latencia = $resultado['latency'] ?? null;
        $provedor = $resultado['server']['provider'] ?? '';
        $wanTestada = $resultado['wan_networkgroup'] ?? '';

        $detalhesAtuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $detalhesAtuais['unifi_velocidade'] = "{$download} Mbps ↓ / {$upload} Mbps ↑"
            . (!empty($resultado['rundate']) ? ' (testado em ' . date('d/m/Y H:i', (int)$resultado['rundate']) . ')' : '');
        $this->repository->atualizarDetalhesApi($id, json_encode($detalhesAtuais, JSON_UNESCAPED_UNICODE));

        AuditService::registrar('Ativos', 'Avaliar internet (UniFi)', "Speedtest sob demanda em {$ativo['codigo_patrimonio']}: {$download}↓/{$upload}↑ Mbps, {$latencia}ms.");

        $mensagem = "Resultado: {$download} Mbps de download, {$upload} Mbps de upload, {$latencia} ms de latência";
        if ($wanTestada !== '') {
            $mensagem .= " (via {$wanTestada}";
            $mensagem .= $provedor !== '' ? ", {$provedor})" : ')';
        }
        $mensagem .= '.';

        return ['success' => true, 'message' => $mensagem];
    }

    /**
     * Troca a WAN primária direto do RD.Intranet -- sem precisar abrir o
     * UniFi Network pra reordenar em Configurações > Internet. Recoleta os
     * dados do gateway em seguida pra "Conexões WAN" já refletir a mudança.
     */
    public function trocarWanPrimariaUnifi(int $id, string $grupo): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        $unifi = new UnifiService();

        if (!$unifi->configurado()) {
            return ['success' => false, 'message' => 'Integração com o UniFi Controller ainda não configurada -- veja Integrações.'];
        }

        $resultado = $unifi->definirWanPrimaria($grupo);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'Trocar WAN primária (UniFi)', "{$ativo['codigo_patrimonio']}: WAN primária alterada para {$grupo}.");
            $this->coletarUnifi($id);
        }

        return $resultado;
    }

    /**
     * Troca o modo global das WANs (Failover Only / Balanceamento de carga)
     * direto do RD.Intranet -- mesmo controle de "WAN Mode" no app UniFi.
     */
    public function trocarModoWanUnifi(int $id, string $modo): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        $unifi = new UnifiService();

        if (!$unifi->configurado()) {
            return ['success' => false, 'message' => 'Integração com o UniFi Controller ainda não configurada -- veja Integrações.'];
        }

        $resultado = $unifi->definirModoWan($modo);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'Trocar modo WAN (UniFi)', "{$ativo['codigo_patrimonio']}: modo WAN alterado para {$modo}.");
            $this->coletarUnifi($id);
        }

        return $resultado;
    }

    private static function rotuloPadraoWifi(string $radioProto): string
    {
        return match ($radioProto) {
            'be' => 'WiFi 7',
            'ax' => 'WiFi 6',
            'ac' => 'WiFi 5',
            'n' => 'WiFi 4',
            'g' => 'WiFi 3 (g)',
            'a' => 'WiFi 2 (a)',
            'b' => 'WiFi 1 (b)',
            default => $radioProto !== '' ? strtoupper($radioProto) : '—',
        };
    }

    private static function tamanhoLegivel(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $valor = (float)$bytes;

        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return round($valor, $i === 0 ? 0 : 1) . ' ' . $unidades[$i];
    }

    private static function rotuloBandaRadio(float $ghz): string
    {
        if ($ghz >= 6) {
            return '6 GHz';
        }
        if ($ghz >= 5) {
            return '5 GHz';
        }
        return '2,4 GHz';
    }

    private static function rotuloStatusUnifi(string $state): string
    {
        return match ($state) {
            'ONLINE' => 'Online',
            'OFFLINE' => 'Offline',
            'PENDING' => 'Pendente de adoção',
            default => $state !== '' ? $state : 'Desconhecido',
        };
    }

    /**
     * Roda a coleta UniFi em todos os ativos do tipo "Ponto de Acesso" com
     * IP cadastrado -- chamado pelo cron (rd ativos:coletar-unifi), mesmo
     * padrão de coletarSnmpTodos().
     */
    /** @var string[] Tipos de ativo gerenciados pelo UniFi Controller (pontos de acesso + o gateway/roteador). */
    private const TIPOS_UNIFI = ['ponto_acesso', 'roteador'];

    public function coletarUnifiTodos(): array
    {
        $ativos = [];
        foreach (self::TIPOS_UNIFI as $slug) {
            $ativos = array_merge($ativos, $this->repository->listarPorTipoSlugComIp($slug));
        }

        $sucesso = 0;

        foreach ($ativos as $ativo) {
            $resultado = $this->coletarUnifi((int)$ativo['id']);
            if ($resultado['success']) {
                $sucesso++;
            }
        }

        return ['total' => count($ativos), 'sucesso' => $sucesso];
    }

    public function nomeJobCronUnifi(): string
    {
        return self::NOME_JOB_CRON_UNIFI;
    }

    /**
     * Casa o ativo (por IP -- a Open API do Omada já reporta o IP de
     * gerenciamento direto, sem o problema de WAN/LAN que a UniFi tem pro
     * gateway) com um dispositivo do Omada Controller, e grava
     * modelo/firmware/status/uptime em `detalhes`. Mesmo tipo de ativo
     * (`switch`) que já existe pra switches manuais/SNMP -- só acrescenta
     * campos novos em CAMPOS_DETALHES, não cria tipo nem migration.
     */
    public function coletarOmada(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if (empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Este ativo não tem IP cadastrado.'];
        }

        $omada = new OmadaService();

        if (!$omada->configurado()) {
            return ['success' => false, 'message' => 'Integração com o Omada Controller ainda não configurada -- veja Integrações.'];
        }

        $dispositivo = null;
        foreach ($omada->listarDispositivos() as $d) {
            if (($d['ip'] ?? '') === $ativo['ip']) {
                $dispositivo = $d;
                break;
            }
        }

        if ($dispositivo === null) {
            return ['success' => false, 'message' => 'Nenhum dispositivo com esse IP foi encontrado no Omada Controller.'];
        }

        $coletado = [
            'omada_model' => $dispositivo['modelName'] ?? ($dispositivo['model'] ?? ''),
            'omada_firmware' => $dispositivo['firmwareVersion'] ?? '',
            // status 2 = conectado (confirmado ao vivo) -- qualquer outro valor mostra o código cru em vez de arriscar um rótulo errado.
            'omada_status' => (int)($dispositivo['status'] ?? -1) === 2 ? 'Online' : ('Status ' . ($dispositivo['status'] ?? '?')),
            // já vem pronto como texto ("42day(s) 18h 52m 21s") -- sem conversão nenhuma.
            'omada_uptime' => $dispositivo['uptime'] ?? '',
        ];

        $detalhesAtuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $detalhesNovos = array_merge($detalhesAtuais, $coletado);

        $this->repository->atualizarDetalhesApi($id, json_encode($detalhesNovos, JSON_UNESCAPED_UNICODE));

        AuditService::registrar('Ativos', 'Coleta Omada', 'Dados coletados via API do Omada Controller para ' . $ativo['codigo_patrimonio'] . '.');

        return ['success' => true, 'message' => 'Dados coletados com sucesso via Omada Controller.'];
    }

    /**
     * Roda a coleta Omada em todos os ativos do tipo "switch" com IP
     * cadastrado -- chamado pelo cron (rd ativos:coletar-omada), mesmo
     * padrão de coletarUnifiTodos()/coletarSnmpTodos().
     */
    public function coletarOmadaTodos(): array
    {
        $ativos = $this->repository->listarPorTipoSlugComIp('switch');
        $sucesso = 0;

        foreach ($ativos as $ativo) {
            $resultado = $this->coletarOmada((int)$ativo['id']);
            if ($resultado['success']) {
                $sucesso++;
            }
        }

        return ['total' => count($ativos), 'sucesso' => $sucesso];
    }

    public function nomeJobCronOmada(): string
    {
        return self::NOME_JOB_CRON_OMADA;
    }

    public function coletarIntelbrasDvr(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if (empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Este ativo não tem IP cadastrado.'];
        }

        $dvr = new IntelbrasDvrService();

        if (!$dvr->configurado()) {
            return ['success' => false, 'message' => 'Integração com DVR/NVR Intelbras ainda não configurada -- veja Integrações.'];
        }

        $resultado = $dvr->coletar($ativo['ip']);

        if (!$resultado['success']) {
            return $resultado;
        }

        $coletado = [
            'dvr_modelo' => $resultado['modelo'] ?? '',
            'dvr_serial' => $resultado['serial'] ?? '',
            'dvr_firmware' => $resultado['firmware'] ?? '',
            'dvr_hardware' => $resultado['hardware'] ?? '',
            'dvr_nome_dispositivo' => $resultado['nome_dispositivo'] ?? '',
            'dvr_status_disco' => $resultado['status_disco'] ?? '',
            'dvr_canais' => $resultado['canais'] ?? [],
        ];

        $detalhesAtuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $detalhesNovos = array_merge($detalhesAtuais, $coletado);

        $this->repository->atualizarDetalhesApi($id, json_encode($detalhesNovos, JSON_UNESCAPED_UNICODE));

        AuditService::registrar('Ativos', 'Coleta DVR/NVR Intelbras', 'Dados coletados via API do DVR/NVR para ' . $ativo['codigo_patrimonio'] . '.');

        return ['success' => true, 'message' => 'Dados coletados com sucesso via API do DVR/NVR.'];
    }

    /**
     * Roda a coleta Intelbras DVR/NVR em todos os ativos do tipo "dvr_nvr"
     * com IP cadastrado -- chamado pelo cron (rd ativos:coletar-intelbras-dvr),
     * mesmo padrão de coletarOmadaTodos()/coletarUnifiTodos().
     */
    public function coletarIntelbrasDvrTodos(): array
    {
        $ativos = $this->repository->listarPorTipoSlugComIp('dvr_nvr');
        $sucesso = 0;

        foreach ($ativos as $ativo) {
            $resultado = $this->coletarIntelbrasDvr((int)$ativo['id']);
            if ($resultado['success']) {
                $sucesso++;
            }
        }

        return ['total' => count($ativos), 'sucesso' => $sucesso];
    }

    public function nomeJobCronIntelbrasDvr(): string
    {
        return self::NOME_JOB_CRON_INTELBRAS_DVR;
    }

    /**
     * Mesma ideia de coletarAutomatico(), mas ANTES do ativo existir --
     * usada no formulário de "Novo Ativo" pra pré-preencher tipo/marca/
     * modelo a partir só do IP digitado, sem obrigar quem está cadastrando
     * a digitar de novo o que o sistema já sabe descobrir sozinho.
     *
     * @return array{success:bool, tipo_slug?:string, nome?:string, marca?:string, modelo?:string, firmware?:string, message:string}
     */
    public function detectarPorIp(string $ip): array
    {
        $ip = trim($ip);

        if ($ip === '') {
            return ['success' => false, 'message' => 'Informe um IP.'];
        }

        $integracoesTentadas = [];

        $unifi = new UnifiService();
        if ($unifi->configurado()) {
            $integracoesTentadas[] = 'UniFi';
            $dispositivo = $this->buscarDispositivoUnifiPorIp($unifi, $ip);

            if ($dispositivo !== null) {
                $legado = null;
                foreach ($unifi->listarDispositivosLegado() as $l) {
                    if (($l['mac'] ?? '') === ($dispositivo['macAddress'] ?? '')) {
                        $legado = $l;
                        break;
                    }
                }

                // Gateway se tiver wan1/wan2 no registro legado (mesmo critério
                // de coletarUnifi()) -- a listagem "nova" (/integration/v1) não
                // diferencia gateway de switch puro no campo "features".
                if ($legado !== null && (isset($legado['wan1']) || isset($legado['wan2']))) {
                    $tipoSlug = 'roteador';
                } elseif (in_array('accessPoint', $dispositivo['features'] ?? [], true)) {
                    $tipoSlug = 'ponto_acesso';
                } else {
                    $tipoSlug = 'switch';
                }

                return [
                    'success' => true,
                    'tipo_slug' => $tipoSlug,
                    'nome' => $dispositivo['name'] ?? '',
                    'marca' => 'Ubiquiti/UniFi',
                    'modelo' => $dispositivo['model'] ?? '',
                    'firmware' => $dispositivo['firmwareVersion'] ?? '',
                    'message' => 'Encontrado no UniFi Controller: ' . ($dispositivo['name'] ?? $dispositivo['model'] ?? 'dispositivo') . '.',
                ];
            }
        }

        $omada = new OmadaService();
        if ($omada->configurado()) {
            $integracoesTentadas[] = 'Omada';

            foreach ($omada->listarDispositivos() as $d) {
                if (($d['ip'] ?? '') === $ip) {
                    return [
                        'success' => true,
                        'tipo_slug' => 'switch',
                        'nome' => $d['name'] ?? '',
                        'marca' => 'TP-Link',
                        'modelo' => $d['modelName'] ?? ($d['model'] ?? ''),
                        'firmware' => $d['firmwareVersion'] ?? '',
                        'message' => 'Encontrado no Omada Controller: ' . ($d['name'] ?? $d['modelName'] ?? 'dispositivo') . '.',
                    ];
                }
            }
        }

        $dvr = new IntelbrasDvrService();
        if ($dvr->configurado()) {
            $integracoesTentadas[] = 'DVR/NVR Intelbras';
            $resultado = $dvr->coletar($ip);

            if ($resultado['success']) {
                return [
                    'success' => true,
                    'tipo_slug' => 'dvr_nvr',
                    'nome' => $resultado['nome_dispositivo'] ?: ($resultado['modelo'] ?? ''),
                    'marca' => 'Intelbras',
                    'modelo' => $resultado['modelo'] ?? '',
                    'firmware' => $resultado['firmware'] ?? '',
                    'message' => 'Encontrado como DVR/NVR Intelbras: ' . ($resultado['modelo'] ?? 'dispositivo') . '.',
                ];
            }
        }

        if (empty($integracoesTentadas)) {
            return ['success' => false, 'message' => 'Nenhuma integração de rede configurada (UniFi/Omada/DVR-NVR Intelbras) -- veja Integrações.'];
        }

        return ['success' => false, 'message' => 'Nenhum dispositivo com esse IP foi encontrado em: ' . implode(', ', $integracoesTentadas) . '.'];
    }

    /**
     * Botão único da ficha do ativo -- o usuário não precisa saber se o
     * equipamento é UniFi ou TP-Link/Omada (nem se vai existir um terceiro
     * fabricante amanhã): tenta cada integração já configurada, na ordem,
     * e usa a primeira que encontrar o IP. Só olha as integrações que
     * já têm credencial salva -- não faz sentido "tentar" uma que nem
     * está configurada.
     */
    public function coletarAutomatico(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if (empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Este ativo não tem IP cadastrado.'];
        }

        $integracoesTentadas = [];

        if ((new UnifiService())->configurado()) {
            $integracoesTentadas[] = 'UniFi';
            $resultado = $this->coletarUnifi($id);
            if ($resultado['success']) {
                return ['success' => true, 'message' => 'Detectado como UniFi -- ' . $resultado['message']];
            }
        }

        if ((new OmadaService())->configurado()) {
            $integracoesTentadas[] = 'TP-Link/Omada';
            $resultado = $this->coletarOmada($id);
            if ($resultado['success']) {
                return ['success' => true, 'message' => 'Detectado como TP-Link/Omada -- ' . $resultado['message']];
            }
        }

        if ((new IntelbrasDvrService())->configurado()) {
            $integracoesTentadas[] = 'DVR/NVR Intelbras';
            $resultado = $this->coletarIntelbrasDvr($id);
            if ($resultado['success']) {
                return ['success' => true, 'message' => 'Detectado como DVR/NVR Intelbras -- ' . $resultado['message']];
            }
        }

        if (empty($integracoesTentadas)) {
            return ['success' => false, 'message' => 'Nenhuma integração (UniFi, TP-Link/Omada, DVR/NVR Intelbras) está configurada ainda -- veja Integrações.'];
        }

        return [
            'success' => false,
            'message' => 'Não encontrado em nenhuma integração configurada (tentado: ' . implode(', ', $integracoesTentadas) . '). Se o equipamento responder SNMP, use "Coletar via SNMP".',
        ];
    }

    /**
     * Ações de gerenciamento de cliente Wi-Fi (desconectar/bloquear/
     * desbloquear) -- afetam de verdade um dispositivo real na rede do
     * cliente, por isso cada uma vira um registro de auditoria próprio,
     * igual às ações remotas de Ativos (reiniciar/desligar máquina).
     */
    public function desconectarClienteUnifi(string $mac, string $nomeAuditoria): array
    {
        $resultado = (new UnifiService())->desconectarCliente($mac);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'UniFi - Desconectar cliente', "Cliente \"{$nomeAuditoria}\" ({$mac}) desconectado via UniFi Controller.");
        }

        return $resultado;
    }

    public function bloquearClienteUnifi(string $mac, string $nomeAuditoria): array
    {
        $resultado = (new UnifiService())->bloquearCliente($mac);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'UniFi - Bloquear cliente', "Cliente \"{$nomeAuditoria}\" ({$mac}) bloqueado via UniFi Controller.");
        }

        return $resultado;
    }

    public function desbloquearClienteUnifi(string $mac, string $nomeAuditoria): array
    {
        $resultado = (new UnifiService())->desbloquearCliente($mac);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'UniFi - Desbloquear cliente', "Cliente \"{$nomeAuditoria}\" ({$mac}) desbloqueado via UniFi Controller.");
        }

        return $resultado;
    }

    /** @return array<int, array{mac:string, nome:string}> */
    public function listarClientesUnifiBloqueados(): array
    {
        $bloqueados = [];

        foreach ((new UnifiService())->listarClientesBloqueados() as $u) {
            $bloqueados[] = [
                'mac' => $u['mac'] ?? '',
                'nome' => $u['hostname'] ?? ($u['name'] ?? ($u['mac'] ?? '')),
            ];
        }

        return $bloqueados;
    }

    /*
     |---------------------------------------------------------
     | Agente Windows (Fase 3) -- checkin autenticado por chave de API,
     | não por sessão. A chave é compartilhada por todo o parque
     | (mesmo modelo do "deploy key" do OCS Inventory/GLPI). Histórico de
     | VÁRIAS chaves (ativos_chaves_api), não uma config única: gerar uma
     | nova NÃO invalida as anteriores automaticamente -- elas continuam
     | valendo até serem desativadas explicitamente, então regenerar deixa
     | de ser uma operação que derruba a frota inteira sem aviso.
     |---------------------------------------------------------
     */

    /** A chave mais recente ainda ativa -- é essa que vai embutida em novos downloads de script/exe. */
    public function chaveAgente(): string
    {
        $ativas = $this->repository->chavesAtivas();

        if (!empty($ativas)) {
            return $ativas[0]['chave'];
        }

        // Nunca deveria ficar sem nenhuma chave ativa (regenerarChaveAgente
        // sempre cria uma nova antes de qualquer desativação ser permitida),
        // mas se acontecer (banco zerado, primeira instalação), cria uma.
        return $this->regenerarChaveAgente('Sistema', false);
    }

    public function chaveValida(string $chaveEnviada): bool
    {
        if ($chaveEnviada === '') {
            return false;
        }

        foreach ($this->repository->chavesAtivas() as $c) {
            if (hash_equals($c['chave'], $chaveEnviada)) {
                return true;
            }
        }

        return false;
    }

    public function historicoChavesAgente(): array
    {
        $chaveAtual = $this->chaveAgente();
        $chaves = $this->repository->todasChaves();

        foreach ($chaves as &$c) {
            $c['eh_atual'] = $c['ativa'] && hash_equals($c['chave'], $chaveAtual);
            $c['ativos_usando'] = (int)$c['ativa'] ? $this->repository->contarAtivosUsandoChave($c['chave']) : 0;
        }

        return $chaves;
    }

    /** Chave que está sendo empurrada ativamente (via heartbeat/checkin) pros agentes já conectados -- null se nenhuma chave ativa está marcada pra rollout. */
    public function chaveParaRollout(): ?string
    {
        return $this->repository->chaveParaRollout();
    }

    public function regenerarChaveAgente(string $geradoPor, bool $notificarAgentes = true): string
    {
        $chave = bin2hex(random_bytes(32));
        $this->repository->criarChaveApi($chave, $geradoPor ?: null, $notificarAgentes);

        if (!$notificarAgentes) {
            // "Não notificar" só significa que os agentes já conectados não
            // vão receber essa chave nova via heartbeat/checkin -- eles
            // continuam funcionando normalmente com a chave anterior
            // (que segue ativa). Só instalações NOVAS (download do
            // script/exe a partir de agora) já saem com a chave nova.
            AuditService::registrar('Ativos', 'Chave de API', "Nova chave de API gerada por {$geradoPor}, sem notificar agentes já conectados.");
        } else {
            AuditService::registrar('Ativos', 'Chave de API', "Nova chave de API gerada por {$geradoPor}.");
        }

        return $chave;
    }

    /**
     * Desativa uma chave -- ao contrário de gerar, ISSO sim quebra na hora
     * qualquer agente que ainda esteja usando essa chave especificamente
     * (não recebeu/não aplicou a chave nova ainda). Nunca desativa a
     * única chave ativa restante, senão nenhum agente (novo ou existente)
     * consegue mais se autenticar.
     */
    public function desativarChaveAgente(int $id, string $desativadoPor): bool
    {
        $ativas = $this->repository->chavesAtivas();

        if (count($ativas) <= 1) {
            NotificationService::error('Essa é a única chave ativa -- desativar deixaria todos os agentes (novos e já instalados) sem conseguir se autenticar.');
            return false;
        }

        $chave = $this->repository->buscarChaveApiPorId($id);
        if (!$chave || !$chave['ativa']) {
            NotificationService::error('Chave não encontrada ou já desativada.');
            return false;
        }

        $this->repository->desativarChaveApi($id, $desativadoPor);

        AuditService::registrar('Ativos', 'Chave de API', "Chave de API (#{$id}) desativada por {$desativadoPor}.");
        NotificationService::success('Chave desativada. Agentes que ainda estiverem usando ela vão parar de se autenticar.');

        return true;
    }

    /*
     |---------------------------------------------------------
     | Credenciais de elevação -- POR MÁQUINA (coluna em `ativos`), não
     | uma conta única pra frota inteira: cada Windows normalmente tem sua
     | própria conta de administrador local, com senha diferente. Usada
     | pelo agente pra rodar CMD/PowerShell "como administrador" via
     | schtasks /ru /rp quando a própria conta que roda o agente não é
     | administradora da máquina -- se já for administradora, o Windows
     | só mostra o prompt de confirmação do UAC (Sim/Não), sem precisar de
     | usuário/senha nenhum, e é exatamente o que a tarefa agendada sem
     | /ru já contorna sozinha (ver ExecutarElevado no agente). Senha
     | cifrada em repouso (CryptoService, mesma AES-256-GCM já usada pra
     | senha de conexão de banco de clientes) -- nunca é reexibida depois
     | de salva.
     |---------------------------------------------------------
     */

    public function credenciaisElevacaoConfiguradas(int $ativoId): bool
    {
        $ativo = $this->repository->buscarPorId($ativoId);

        return $ativo !== null
            && trim((string)($ativo['elevacao_usuario'] ?? '')) !== ''
            && trim((string)($ativo['elevacao_senha_cifrada'] ?? '')) !== '';
    }

    public function usuarioElevacaoAtual(int $ativoId): string
    {
        $ativo = $this->repository->buscarPorId($ativoId);

        return trim((string)($ativo['elevacao_usuario'] ?? ''));
    }

    public function salvarCredenciaisElevacao(int $ativoId, string $usuario, string $senha): bool
    {
        $usuario = trim($usuario);
        $senha = trim($senha);

        // Senha em branco mantém a atual (só o usuário está sendo trocado) --
        // só exige senha nova quando ainda não havia nenhuma configurada.
        if ($usuario === '' || ($senha === '' && !$this->credenciaisElevacaoConfiguradas($ativoId))) {
            NotificationService::error('Informe o usuário e a senha da conta administradora desta máquina.');
            return false;
        }

        $senhaCifrada = $senha !== '' ? CryptoService::encriptar($senha) : null;
        $this->repository->salvarCredenciaisElevacao($ativoId, $usuario, $senhaCifrada);

        AuditService::registrar('Ativos', 'Credenciais de elevação', "Credenciais de elevação do ativo #{$ativoId} atualizadas (usuário: {$usuario}).");
        NotificationService::success('Credenciais de elevação salvas para esta máquina.');

        return true;
    }

    public function removerCredenciaisElevacao(int $ativoId): void
    {
        $this->repository->salvarCredenciaisElevacao($ativoId, null, null);

        AuditService::registrar('Ativos', 'Credenciais de elevação', "Credenciais de elevação do ativo #{$ativoId} removidas.");
        NotificationService::success('Credenciais de elevação removidas -- elevação volta a depender da própria conta do agente já ser administradora nesta máquina.');
    }

    /** Só chamado internamente ao montar a resposta do heartbeat pra uma solicitação elevada -- nunca exposto pra fora. */
    private function credenciaisElevacaoParaAgente(int $ativoId): ?array
    {
        $ativo = $this->repository->buscarPorId($ativoId);
        $senhaCifrada = trim((string)($ativo['elevacao_senha_cifrada'] ?? ''));
        $usuario = trim((string)($ativo['elevacao_usuario'] ?? ''));

        if ($usuario === '' || $senhaCifrada === '') {
            return null;
        }

        try {
            return [
                'usuario' => $usuario,
                'senha' => CryptoService::decriptar($senhaCifrada),
            ];
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /*
     |---------------------------------------------------------
     | Credencial de acesso a unidade de rede mapeada, por MAQUINA -- mesmo
     | raciocinio da elevação acima: a mesma pasta pode estar mapeada em
     | várias máquinas, cada uma logada com um usuário diferente, então não
     | dá pra reaproveitar uma credencial única por caminho. Usada pelo
     | agente (rodando como SYSTEM) pra autenticar via `net use` antes de
     | explorar arquivos num caminho \\servidor\pasta -- SYSTEM não enxerga
     | o mapeamento feito pela sessão do usuário logado, então precisa da
     | própria credencial pra montar a sessão SMB dele mesmo.
     |---------------------------------------------------------
     */

    public function credenciaisRedeConfiguradas(int $ativoId): bool
    {
        $ativo = $this->repository->buscarPorId($ativoId);

        return $ativo !== null
            && trim((string)($ativo['rede_usuario'] ?? '')) !== ''
            && trim((string)($ativo['rede_senha_cifrada'] ?? '')) !== '';
    }

    public function usuarioRedeAtual(int $ativoId): string
    {
        $ativo = $this->repository->buscarPorId($ativoId);

        return trim((string)($ativo['rede_usuario'] ?? ''));
    }

    public function salvarCredenciaisRede(int $ativoId, string $usuario, string $senha): bool
    {
        $usuario = trim($usuario);
        $senha = trim($senha);

        if ($usuario === '' || ($senha === '' && !$this->credenciaisRedeConfiguradas($ativoId))) {
            NotificationService::error('Informe o usuário e a senha de acesso à unidade de rede.');
            return false;
        }

        $senhaCifrada = $senha !== '' ? CryptoService::encriptar($senha) : null;
        $this->repository->salvarCredenciaisRede($ativoId, $usuario, $senhaCifrada);

        AuditService::registrar('Ativos', 'Credenciais de rede', "Credenciais de rede do ativo #{$ativoId} atualizadas (usuário: {$usuario}).");
        NotificationService::success('Credenciais de rede salvas para esta máquina.');

        return true;
    }

    public function removerCredenciaisRede(int $ativoId): void
    {
        $this->repository->salvarCredenciaisRede($ativoId, null, null);

        AuditService::registrar('Ativos', 'Credenciais de rede', "Credenciais de rede do ativo #{$ativoId} removidas.");
        NotificationService::success('Credenciais de rede removidas -- explorar arquivos numa unidade de rede mapeada volta a falhar (SYSTEM não tem a sessão do usuário que mapeou).');
    }

    /** Só chamado internamente ao montar a resposta do heartbeat pra uma solicitação de arquivo em caminho de rede -- nunca exposto pra fora. */
    private function credenciaisRedeParaAgente(int $ativoId): ?array
    {
        $ativo = $this->repository->buscarPorId($ativoId);
        $senhaCifrada = trim((string)($ativo['rede_senha_cifrada'] ?? ''));
        $usuario = trim((string)($ativo['rede_usuario'] ?? ''));

        if ($usuario === '' || $senhaCifrada === '') {
            return null;
        }

        try {
            return [
                'usuario' => $usuario,
                'senha' => CryptoService::decriptar($senhaCifrada),
            ];
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Distribuição do agente Windows em C#/WinForms (.exe) -- diferente do
     * .ps1 (que é texto, gerado sob demanda a cada download), o .exe é um
     * binário que precisa ser compilado no Windows (fora deste ambiente).
     * O admin compila, sobe aqui com um número de versão, e os agentes já
     * instalados se autoatualizam comparando essa versão com a própria.
     */
    private function caminhoAgenteExe(): string
    {
        return __DIR__ . '/../../storage/uploads/agente/RdIntranetAgente.exe';
    }

    public function versaoAgenteExe(): string
    {
        return ConfigService::get('ativos_agente_exe_versao', '') ?: '';
    }

    public function agenteExeDisponivel(): bool
    {
        return $this->versaoAgenteExe() !== '' && file_exists($this->caminhoAgenteExe());
    }

    public function caminhoAgenteExePublico(): ?string
    {
        return $this->agenteExeDisponivel() ? $this->caminhoAgenteExe() : null;
    }

    public function salvarNovoAgenteExe(string $caminhoTemporario, string $versao): array
    {
        $versao = trim($versao);

        if (!preg_match('/^\d+\.\d+\.\d+$/', $versao)) {
            NotificationService::error('Versão inválida -- use o formato X.Y.Z (mesmo número do <Version> no .csproj), ex: 1.0.1.');
            return ['success' => false];
        }

        if (!is_uploaded_file($caminhoTemporario)) {
            NotificationService::error('Upload inválido.');
            return ['success' => false];
        }

        $destino = $this->caminhoAgenteExe();
        $pasta = dirname($destino);

        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Falha ao criar a pasta de destino no servidor.');
            return ['success' => false];
        }

        if (!@move_uploaded_file($caminhoTemporario, $destino)) {
            NotificationService::error('Falha ao salvar o arquivo no servidor (permissão de escrita?).');
            return ['success' => false];
        }

        ConfigService::set('ativos_agente_exe_versao', $versao);
        AuditService::registrar('Ativos', 'Agente Windows', "Nova versão do agente .exe enviada: {$versao}.");
        NotificationService::success("Versão {$versao} do agente enviada. Agentes já instalados vão se autoatualizar no próximo check-in.");

        return ['success' => true];
    }

    /**
     * Alternativa ao upload manual: busca o .exe já compilado direto do
     * repositório git (agente-windows/dist/RdIntranetAgente.exe +
     * VERSION.txt, publicados lá pelo próprio processo de build) --
     * pensado pra quem roda o RD Intranet em vários servidores e não
     * quer repetir o upload em cada um, só dar "git push" uma vez e
     * clicar aqui em cada servidor. Só leitura do git (fetch + show),
     * nunca mexe na working tree deste checkout. Reaproveita o mesmo
     * mecanismo de scripts com sudo já usado por Atualizações do Sistema
     * (LinuxService::executarScript, script sincronizado em
     * /opt/rdtecnologia/scripts/ via scripts/sync-system-scripts.sh).
     */
    public function atualizarAgenteViaGit(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/agente_baixar_git.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (!is_array($dados) || !($dados['success'] ?? false)) {
            $mensagem = $dados['message'] ?? ($resultado['output'] ?: 'Erro desconhecido ao buscar o agente no repositório.');
            NotificationService::error('Erro ao buscar o agente no repositório.', $mensagem);
            return ['success' => false];
        }

        $versao = $dados['versao'] ?? '';
        ConfigService::set('ativos_agente_exe_versao', $versao);
        AuditService::registrar('Ativos', 'Agente Windows', "Agente .exe atualizado a partir do repositório git: versão {$versao}.");
        NotificationService::success("Versão {$versao} do agente baixada do repositório. Agentes já instalados vão se autoatualizar no próximo check-in.");

        return ['success' => true, 'versao' => $versao];
    }

    /*
     |---------------------------------------------------------
     | .NET Desktop Runtime -- o agente .exe framework-dependent (menor)
     | precisa disso instalado na máquina pra rodar. Hospedar aqui evita
     | ter que buscar no site da Microsoft toda vez que uma máquina nova
     | for configurada. Não tem versão comparada por código nenhuma (não
     | é autoatualizável, é só um instalador que a gente aponta manualmente
     | pra máquina) -- o rótulo é livre, só pra identificar o que foi
     | enviado (ex: "8.0.11 (win-x64)").
     |---------------------------------------------------------
     */
    private function caminhoDotnetRuntime(): string
    {
        return __DIR__ . '/../../storage/uploads/agente/dotnet-desktop-runtime.exe';
    }

    public function dotnetRuntimeLabel(): string
    {
        return ConfigService::get('ativos_dotnet_runtime_label', '') ?: '';
    }

    public function dotnetRuntimeDisponivel(): bool
    {
        return file_exists($this->caminhoDotnetRuntime());
    }

    public function caminhoDotnetRuntimePublico(): ?string
    {
        return $this->dotnetRuntimeDisponivel() ? $this->caminhoDotnetRuntime() : null;
    }

    public function salvarDotnetRuntime(string $caminhoTemporario, string $label): array
    {
        $label = trim($label);

        if ($label === '') {
            NotificationService::error('Informe um rótulo pra identificar a versão enviada (ex: 8.0.11 win-x64).');
            return ['success' => false];
        }

        if (!is_uploaded_file($caminhoTemporario)) {
            NotificationService::error('Upload inválido.');
            return ['success' => false];
        }

        $destino = $this->caminhoDotnetRuntime();
        $pasta = dirname($destino);

        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Falha ao criar a pasta de destino no servidor.');
            return ['success' => false];
        }

        if (!@move_uploaded_file($caminhoTemporario, $destino)) {
            NotificationService::error('Falha ao salvar o arquivo no servidor (permissão de escrita?).');
            return ['success' => false];
        }

        ConfigService::set('ativos_dotnet_runtime_label', $label);
        AuditService::registrar('Ativos', 'Agente Windows', "Novo .NET Desktop Runtime enviado: {$label}.");
        NotificationService::success("Runtime \"{$label}\" enviado.");

        return ['success' => true];
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
    public function checkinAgente(array $payload, string $chaveUsada = ''): array
    {
        $machineGuid = trim((string)($payload['machine_guid'] ?? ''));

        if ($machineGuid === '') {
            return ['success' => false, 'message' => 'machine_guid é obrigatório.'];
        }

        $slugTipo = in_array($payload['tipo'] ?? '', ['computador', 'servidor'], true) ? $payload['tipo'] : 'computador';

        $existente = $this->repository->buscarPorMachineGuid($machineGuid);

        $camposBase = [
            'nome' => trim((string)($payload['nome'] ?? '')) ?: ($existente['nome'] ?? 'Ativo sem nome'),
            'marca' => trim((string)($payload['marca'] ?? '')) ?: null,
            'modelo' => trim((string)($payload['modelo'] ?? '')) ?: null,
            'numero_serie' => trim((string)($payload['numero_serie'] ?? '')) ?: null,
            'ip' => trim((string)($payload['ip'] ?? '')) ?: null,
            'agente_versao' => trim((string)($payload['versao_agente'] ?? '')) ?: null,
        ];

        $camposTecnicos = $this->extrairDetalhes($slugTipo, $payload);

        if ($existente) {
            $id = (int)$existente['id'];
            $detalhesAtuais = json_decode($existente['detalhes'] ?? '', true) ?: [];
            $detalhesNovos = array_merge($detalhesAtuais, $camposTecnicos);

            $this->repository->atualizarViaAgente($id, $camposBase, json_encode($detalhesNovos, JSON_UNESCAPED_UNICODE));
        } else {
            // O agente não sabe em qual unidade física a máquina está --
            // entra na unidade padrão até um admin reatribuir manualmente.
            $tipo = (new AtivoTipoService())->buscarPorSlug($slugTipo);
            $unidade = (new UnidadeService())->padrao();

            $id = $this->repository->criarViaAgente(array_merge($camposBase, [
                'tipo_id' => (int)$tipo['id'],
                'unidade_id' => (int)$unidade['id'],
                'codigo_patrimonio' => $this->proximoCodigo($tipo, $unidade),
                'machine_guid' => $machineGuid,
                'detalhes' => json_encode($camposTecnicos, JSON_UNESCAPED_UNICODE),
            ]));
        }

        $this->repository->substituirProgramas($id, array_slice($payload['programas'] ?? [], 0, 500));
        $this->repository->inserirAlertas($id, array_slice($payload['alertas'] ?? [], 0, 200));
        $this->repository->substituirRedes($id, array_slice($payload['redes'] ?? [], 0, 20));
        $this->repository->substituirVolumes($id, array_slice($payload['volumes'] ?? [], 0, 20));
        $this->repository->substituirPortas($id, array_slice($payload['portas'] ?? [], 0, 100));
        $this->repository->substituirPortasRede($id, array_slice($payload['portas_rede'] ?? [], 0, 300));
        $this->repository->substituirMemoria($id, array_slice($payload['memoria_modulos'] ?? [], 0, 32));
        $this->repository->substituirAtualizacoesWindows($id, array_slice($payload['atualizacoes_windows'] ?? [], 0, 500));
        $this->repository->substituirPlacasVideo($id, array_slice($payload['placas_video'] ?? [], 0, 8));
        $this->repository->substituirControladoras($id, array_slice($payload['controladoras'] ?? [], 0, 60));
        $this->repository->substituirBateria($id, array_slice($payload['bateria'] ?? [], 0, 4));

        // Limpa um eventual pedido de "forçar checkin" -- esse checkin que
        // acabou de chegar já é o que estava sendo esperado.
        $this->repository->limparSolicitacaoCheckin($id);

        if ($chaveUsada !== '') {
            $this->repository->atualizarChaveUsada($id, $chaveUsada);
        }

        // Comandos remotos pendentes (desligar/reiniciar/desinstalar) --
        // entregues agora, junto com a resposta deste checkin. O agente
        // é quem decide como/quando executar (com aviso pro usuário,
        // quando aplicável).
        $pendentes = $this->repository->comandosPendentes($id);
        if (!empty($pendentes)) {
            $this->repository->marcarComandosEntregues(array_column($pendentes, 'id'));
        }

        $resposta = [
            'success' => true,
            'message' => 'Check-in recebido.',
            'ativo_id' => $id,
            'comandos' => array_map(fn($c) => [
                'id' => (int)$c['id'],
                'comando' => $c['comando'],
                'alvo' => $c['alvo'],
                'alvo_label' => $c['alvo_label'],
            ], $pendentes),
        ];

        // Só manda a chave nova se essa solicitação já não veio autenticada
        // com ela -- evita ficar reenviando à toa em todo checkin.
        $chaveRollout = $this->chaveParaRollout();
        if ($chaveRollout !== null && $chaveRollout !== $chaveUsada) {
            $resposta['chave_api_atual'] = $chaveRollout;
        }

        return $resposta;
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

    private const COMANDOS_VALIDOS = [
        'desligar', 'reiniciar', 'desinstalar_atualizacao', 'desinstalar_programa',
        'executar_arquivo', 'encerrar_processo', 'renomear_arquivo', 'enviar_arquivo',
    ];

    public function enviarComando(int $ativoId, string $comando, ?string $solicitadoPor, ?string $alvo = null, ?string $alvoLabel = null, ?string $arquivoAnexo = null): array
    {
        if (!in_array($comando, self::COMANDOS_VALIDOS, true)) {
            return ['success' => false, 'message' => 'Comando inválido.'];
        }

        if (in_array($comando, ['desinstalar_atualizacao', 'desinstalar_programa', 'executar_arquivo', 'renomear_arquivo'], true) && empty($alvo)) {
            return ['success' => false, 'message' => 'Informe o que deve ser desinstalado/executado/renomeado.'];
        }

        if ($comando === 'renomear_arquivo' && empty($alvoLabel)) {
            return ['success' => false, 'message' => 'Informe o novo nome.'];
        }

        if ($comando === 'encerrar_processo' && !ctype_digit((string)$alvo)) {
            return ['success' => false, 'message' => 'PID inválido.'];
        }

        if ($comando === 'enviar_arquivo' && (empty($alvo) || empty($arquivoAnexo))) {
            return ['success' => false, 'message' => 'Selecione um arquivo pra enviar.'];
        }

        $ativo = $this->repository->buscarPorId($ativoId);
        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if ($ativo['origem'] !== 'agente') {
            return ['success' => false, 'message' => 'Este ativo não tem o agente Windows instalado -- não é possível enviar comandos remotos.'];
        }

        $this->repository->criarComando($ativoId, $comando, $solicitadoPor, $alvo, $alvoLabel, $arquivoAnexo);

        // Comandos são entregues na resposta do checkin completo -- forçar
        // um agora (mesmo canal do botão "Forçar coleta agora") evita
        // esperar o ciclo normal, chega em poucos segundos via o próximo
        // heartbeat em vez de até intervaloAproximado().
        $this->repository->solicitarCheckin($ativoId);

        $labels = [
            'desligar' => 'Desligamento',
            'reiniciar' => 'Reinício',
            'desinstalar_atualizacao' => 'Desinstalação da atualização ' . $alvoLabel,
            'desinstalar_programa' => 'Desinstalação de ' . $alvoLabel,
            'executar_arquivo' => 'Execução de ' . $alvoLabel,
            'encerrar_processo' => 'Encerramento do processo ' . ($alvoLabel ?: $alvo),
            'renomear_arquivo' => 'Renomeação para ' . $alvoLabel,
            'enviar_arquivo' => 'Envio de ' . $alvoLabel,
        ];
        $label = $labels[$comando];

        AuditService::registrar(
            'Ativos',
            'Comando remoto',
            $label . ' solicitado(a) para ' . $ativo['codigo_patrimonio'] . ' (' . $ativo['nome'] . ').'
        );

        return [
            'success' => true,
            'message' => "{$label} agendado(a) -- deve chegar em poucos segundos (próximo heartbeat).",
        ];
    }

    public function historicoComandos(int $ativoId): array
    {
        return $this->repository->historicoComandos($ativoId);
    }

    private function pastaTransferencias(): string
    {
        return __DIR__ . '/../../storage/uploads/ativos_transferencias';
    }

    /** Agente (autenticado por machine_guid) baixando o anexo de um comando 'enviar_arquivo'. */
    public function buscarAnexoComando(string $machineGuid, int $comandoId): ?array
    {
        $ativo = $this->repository->buscarPorMachineGuid($machineGuid);
        if (!$ativo) {
            return null;
        }

        $comando = $this->repository->buscarComandoPorId($comandoId);
        if (!$comando || (int)$comando['ativo_id'] !== (int)$ativo['id'] || empty($comando['arquivo_anexo'])) {
            return null;
        }

        if (!is_file($comando['arquivo_anexo'])) {
            return null;
        }

        return ['caminho' => $comando['arquivo_anexo'], 'nome' => $comando['alvo_label'] ?? basename($comando['arquivo_anexo'])];
    }

    /** Chamado depois de servir o anexo (sucesso ou não) -- não deixa cópia de arquivo enviado pra sempre no servidor. */
    public function limparAnexoComando(int $comandoId): void
    {
        $comando = $this->repository->buscarComandoPorId($comandoId);
        if ($comando && !empty($comando['arquivo_anexo']) && is_file($comando['arquivo_anexo'])) {
            @unlink($comando['arquivo_anexo']);
        }
        $this->repository->limparAnexoComando($comandoId);
    }

    /**
     * Intervalo esperado entre checkins COMPLETOS (hardware/programas/
     * alertas), configurável em Ativos > Dashboard. Não tem mais relação
     * com "está ligada" -- isso agora vem do heartbeat (ver
     * heartbeatIntervaloSegundos()/estaLigada()) -- é só gravado no .ps1
     * baixado a partir de agora -- agentes já instalados mantêm o intervalo
     * antigo até serem reinstalados.
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

        AuditService::registrar('Ativos', 'Config. Comunicação', "Intervalo de coleta completa alterado para {$minutos} min.");

        NotificationService::success('Intervalo salvo. Vale pra novos agentes instalados a partir de agora -- os já instalados mantêm o intervalo com que foram configurados.');

        return true;
    }

    /**
     * Intervalo do "ping" de ligado/desligado -- bem mais curto que o
     * checkin completo, porque é só o agente mandando o machine_guid
     * (uma UPDATE só, indexada por chave única) pra confirmar que está
     * ligado. É esse canal, também, que carrega o aviso de "forçar
     * checkin" pedido pelo portal -- por isso ele chega em poucos segundos
     * em vez de esperar o próximo ciclo completo.
     */
    public function heartbeatIntervaloSegundos(): int
    {
        return (int)(ConfigService::get('ativos_heartbeat_intervalo_seg', '1') ?? 1);
    }

    public function salvarHeartbeatIntervaloSegundos(int $segundos): bool
    {
        if ($segundos < 1 || $segundos > 60) {
            NotificationService::error('O intervalo de heartbeat deve ser entre 1 e 60 segundos.');
            return false;
        }

        ConfigService::set('ativos_heartbeat_intervalo_seg', (string)$segundos);

        AuditService::registrar('Ativos', 'Config. Comunicação', "Intervalo de heartbeat alterado para {$segundos}s.");

        NotificationService::success('Intervalo de heartbeat salvo. Vale pra novos agentes instalados a partir de agora -- os já instalados mantêm o intervalo com que foram configurados.');

        return true;
    }

    private function intervaloAproximado(): string
    {
        $min = $this->intervaloComunicacao();
        return "{$min}-" . ($min * 2) . ' min';
    }

    /**
     * "Ligada" agora vem do heartbeat (ping leve, a cada poucos segundos),
     * não mais do checkin completo -- janela de tolerância de 3x o
     * intervalo configurado (mínimo 5s), como margem de um ping perdido.
     * Ativos com agente antigo (ainda sem heartbeat) ou sem nenhum
     * heartbeat ainda caem no fallback via ultimo_checkin, pra não virar
     * "Desligado" incorretamente logo após a atualização do agente.
     */
    public static function estaLigada(array $ativo): bool
    {
        if (!empty($ativo['ultimo_heartbeat'])) {
            $segundos = (int)(ConfigService::get('ativos_heartbeat_intervalo_seg', '1') ?? 1);

            return (time() - strtotime($ativo['ultimo_heartbeat'])) <= max(5, $segundos * 3);
        }

        if (empty($ativo['ultimo_checkin'])) {
            return false;
        }

        $minutos = (int)(ConfigService::get('ativos_intervalo_comunicacao_min', '15') ?? 15);

        return (time() - strtotime($ativo['ultimo_checkin'])) <= $minutos * 2 * 60;
    }

    /**
     * Ping leve de "estou ligado" -- chamado pelo agente a cada
     * heartbeatIntervaloSegundos(). Devolve se há um checkin completo
     * pendente de ser forçado (pedido pelo portal via solicitarCheckin()).
     */
    public function registrarHeartbeat(string $machineGuid, string $chaveUsada = ''): array
    {
        $resultado = $this->repository->registrarHeartbeat($machineGuid);

        if ($resultado === null) {
            return ['success' => false, 'message' => 'Ativo ainda não cadastrado -- aguardando o primeiro check-in completo.'];
        }

        $ativoId = (int)$resultado['id'];

        if ($chaveUsada !== '') {
            $this->repository->atualizarChaveUsada($ativoId, $chaveUsada);
        }

        $solicitacoes = $this->repository->solicitacoesPendentes($ativoId);

        $resposta = [
            'success' => true,
            'forcar_checkin' => $resultado['forcar_checkin'],
            'solicitacoes' => array_map(function ($s) use ($ativoId) {
                $item = [
                    'id' => (int)$s['id'],
                    'tipo' => $s['tipo'],
                    'parametro' => $s['parametro'],
                    'elevado' => (bool)$s['elevado'],
                ];

                // So manda a credencial (decifrada) quando de fato precisa
                // dela -- nao em todo heartbeat, so na resposta da
                // solicitacao elevada que vai usa-la na hora. Credencial e
                // POR MAQUINA (ativoId), nao global pra frota.
                if ($item['elevado']) {
                    $credencial = $this->credenciaisElevacaoParaAgente($ativoId);
                    if ($credencial !== null) {
                        $item['usuario_elevacao'] = $credencial['usuario'];
                        $item['senha_elevacao'] = $credencial['senha'];
                    }
                }

                // Mesma lógica -- só manda quando a solicitação de fato
                // aponta pra um caminho de rede (\\servidor\pasta). SYSTEM
                // (conta que roda o agente) não enxerga o mapeamento feito
                // pela sessão do usuário logado, então precisa autenticar
                // sozinho antes de acessar.
                if (in_array($item['tipo'], ['listar_arquivos', 'baixar_arquivo'], true)
                    && str_starts_with((string)$item['parametro'], '\\\\')) {
                    $credencialRede = $this->credenciaisRedeParaAgente($ativoId);
                    if ($credencialRede !== null) {
                        $item['usuario_rede'] = $credencialRede['usuario'];
                        $item['senha_rede'] = $credencialRede['senha'];
                    }
                }

                return $item;
            }, $solicitacoes),
        ];

        // Empurra a chave nova pro agente adotar sozinho -- só se ele ainda
        // não estiver usando ela (evita ficar reenviando à toa).
        $chaveRollout = $this->chaveParaRollout();
        if ($chaveRollout !== null && $chaveRollout !== $chaveUsada) {
            $resposta['chave_api_atual'] = $chaveRollout;
        }

        return $resposta;
    }

    /**
     * Pedido pelo admin, pelo portal, de rodar a coleta completa fora do
     * ciclo normal. Não é entregue direto ao agente (ele não escuta
     * conexões de fora) -- fica marcado no banco e é entregue na resposta
     * do próximo heartbeat, que já chega em poucos segundos.
     */
    public function solicitarCheckin(int $ativoId): array
    {
        $ativo = $this->repository->buscarPorId($ativoId);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if ($ativo['origem'] !== 'agente') {
            return ['success' => false, 'message' => 'Este ativo não tem o agente Windows instalado.'];
        }

        $this->repository->solicitarCheckin($ativoId);

        AuditService::registrar('Ativos', 'Forçar check-in', "Check-in completo solicitado para {$ativo['codigo_patrimonio']} ({$ativo['nome']}).");

        return [
            'success' => true,
            'message' => 'Solicitado! Deve chegar em até ' . $this->heartbeatIntervaloSegundos() . 's (próximo heartbeat do agente).',
        ];
    }

    /** Mesma ação de "Forçar coleta agora", só que em várias máquinas de uma vez -- um resumo só no log de auditoria, não um por máquina, pra não poluir o histórico quando forem dezenas/centenas de máquinas. */
    public function solicitarCheckinEmLote(array $ativoIds): array
    {
        $solicitados = 0;
        $ignorados = 0;

        foreach ($ativoIds as $ativoId) {
            $ativo = $this->repository->buscarPorId((int)$ativoId);

            if (!$ativo || $ativo['origem'] !== 'agente') {
                $ignorados++;
                continue;
            }

            $this->repository->solicitarCheckin((int)$ativoId);
            $solicitados++;
        }

        AuditService::registrar('Ativos', 'Forçar check-in', "Check-in completo solicitado em lote para {$solicitados} máquina(s)." . ($ignorados > 0 ? " {$ignorados} ignorada(s) (sem agente instalado)." : ''));

        if ($solicitados === 0) {
            return ['success' => true, 'message' => 'Nenhuma das máquinas selecionadas tem o agente instalado.'];
        }

        return [
            'success' => true,
            'message' => "Solicitado para {$solicitados} máquina(s)! Deve chegar em até " . $this->heartbeatIntervaloSegundos() . 's (próximo heartbeat de cada uma).' . ($ignorados > 0 ? " {$ignorados} ignorada(s) por não ter o agente instalado." : ''),
        ];
    }

    /*
     |---------------------------------------------------------
     | Explorador de arquivos / gerenciador de processos -- leitura com
     | resposta, entregue e respondida pelo canal de heartbeat (poucos
     | segundos de ida e volta, mesmo em máquinas remotas).
     |---------------------------------------------------------
     */

    private const TIPOS_SOLICITACAO_VALIDOS = [
        'listar_arquivos', 'listar_processos', 'baixar_arquivo', 'executar_cmd', 'executar_powershell',
    ];

    public function solicitarListagem(int $ativoId, string $tipo, ?string $parametro, ?string $solicitadoPor = null, bool $elevado = false): array
    {
        if (!in_array($tipo, self::TIPOS_SOLICITACAO_VALIDOS, true)) {
            return ['success' => false, 'message' => 'Tipo de solicitação inválido.'];
        }

        if (in_array($tipo, ['baixar_arquivo', 'executar_cmd', 'executar_powershell'], true) && empty($parametro)) {
            return ['success' => false, 'message' => 'Informe o caminho do arquivo/comando.'];
        }

        $ativo = $this->repository->buscarPorId($ativoId);
        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if ($ativo['origem'] !== 'agente') {
            return ['success' => false, 'message' => 'Este ativo não tem o agente Windows instalado.'];
        }

        $id = $this->repository->criarSolicitacao($ativoId, $tipo, $parametro, $solicitadoPor, $elevado);

        if (in_array($tipo, ['executar_cmd', 'executar_powershell'], true)) {
            AuditService::registrar(
                'Ativos',
                'Comando remoto',
                ($tipo === 'executar_cmd' ? 'CMD' : 'PowerShell') . ($elevado ? ' (elevado)' : '') . ' em '
                    . $ativo['codigo_patrimonio'] . ' (' . $ativo['nome'] . '): ' . $parametro
            );
        }

        return ['success' => true, 'id' => $id];
    }

    public function resultadoSolicitacao(int $id, int $ativoId): array
    {
        $solicitacao = $this->repository->buscarSolicitacao($id);

        if (!$solicitacao || (int)$solicitacao['ativo_id'] !== $ativoId) {
            return ['success' => false, 'message' => 'Solicitação não encontrada.'];
        }

        if ($solicitacao['status'] === 'pendente') {
            return ['success' => true, 'status' => 'pendente'];
        }

        if ($solicitacao['status'] === 'erro') {
            return ['success' => true, 'status' => 'erro', 'mensagem' => $solicitacao['erro_mensagem']];
        }

        $resultado = json_decode($solicitacao['resultado'] ?? '', true) ?: [];

        if (!empty($solicitacao['arquivo_resultado'])) {
            $resultado['arquivo_pronto'] = is_file($solicitacao['arquivo_resultado']);
        }

        return [
            'success' => true,
            'status' => 'concluido',
            'resultado' => $resultado,
        ];
    }

    /** Chamado pelo agente (autenticado por chave de API) devolvendo o resultado de uma solicitação. */
    public function responderSolicitacao(string $machineGuid, int $id, array $payload): array
    {
        $ativo = $this->repository->buscarPorMachineGuid($machineGuid);
        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        $solicitacao = $this->repository->buscarSolicitacao($id);
        if (!$solicitacao || (int)$solicitacao['ativo_id'] !== (int)$ativo['id']) {
            // Solicitacao de outro ativo -- nao deixa um agente responder
            // por algo que nao e dele.
            return ['success' => false, 'message' => 'Solicitação não encontrada.'];
        }

        if (!empty($payload['erro'])) {
            $this->repository->marcarSolicitacaoErro($id, (string)$payload['erro']);
            return ['success' => true];
        }

        $resultado = is_array($payload['resultado'] ?? null) ? $payload['resultado'] : [];
        $this->repository->marcarSolicitacaoConcluida($id, json_encode($resultado, JSON_UNESCAPED_UNICODE));

        return ['success' => true];
    }

    /** Agente devolvendo o CONTEÚDO de um arquivo (solicitação 'baixar_arquivo') -- endpoint separado do JSON acima, é upload de verdade. */
    public function responderSolicitacaoComArquivo(string $machineGuid, int $id, string $caminhoTemporario, string $nomeOriginal): array
    {
        $ativo = $this->repository->buscarPorMachineGuid($machineGuid);
        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        $solicitacao = $this->repository->buscarSolicitacao($id);
        if (!$solicitacao || (int)$solicitacao['ativo_id'] !== (int)$ativo['id']) {
            return ['success' => false, 'message' => 'Solicitação não encontrada.'];
        }

        if (!is_uploaded_file($caminhoTemporario)) {
            return ['success' => false, 'message' => 'Upload inválido.'];
        }

        $pasta = $this->pastaTransferencias();
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            return ['success' => false, 'message' => 'Falha ao criar pasta de destino no servidor.'];
        }

        $nomeSanitizado = preg_replace('/[^A-Za-z0-9._-]/', '_', $nomeOriginal) ?: 'arquivo';
        $destino = $pasta . '/baixado_' . uniqid('', true) . '_' . $nomeSanitizado;

        if (!@move_uploaded_file($caminhoTemporario, $destino)) {
            return ['success' => false, 'message' => 'Falha ao salvar o arquivo no servidor.'];
        }

        $this->repository->marcarSolicitacaoConcluidaComArquivo($id, $destino, $nomeOriginal);

        return ['success' => true];
    }

    /** Admin (sessão) baixando o resultado de uma solicitação 'baixar_arquivo'. */
    public function baixarResultadoArquivo(int $id, int $ativoId): ?array
    {
        $solicitacao = $this->repository->buscarSolicitacao($id);

        if (!$solicitacao || (int)$solicitacao['ativo_id'] !== $ativoId || empty($solicitacao['arquivo_resultado'])) {
            return null;
        }

        if (!is_file($solicitacao['arquivo_resultado'])) {
            return null;
        }

        $meta = json_decode($solicitacao['resultado'] ?? '', true) ?: [];

        return ['caminho' => $solicitacao['arquivo_resultado'], 'nome' => $meta['arquivo_nome'] ?? basename($solicitacao['arquivo_resultado'])];
    }

    /** Chamado depois de servir o download pro admin -- não deixa cópia pra sempre no servidor. */
    public function limparArquivoResultado(int $id): void
    {
        $solicitacao = $this->repository->buscarSolicitacao($id);
        if ($solicitacao && !empty($solicitacao['arquivo_resultado']) && is_file($solicitacao['arquivo_resultado'])) {
            @unlink($solicitacao['arquivo_resultado']);
        }
        $this->repository->limparArquivoResultado($id);
    }

    public function historicoSolicitacoesExecucao(int $ativoId, int $limite = 5): array
    {
        return $this->repository->historicoSolicitacoesExecucao($ativoId, $limite);
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

    /** Segundos desde o último heartbeat -- null se nunca recebeu um. */
    public static function segundosDesdeUltimoHeartbeat(array $ativo): ?int
    {
        if (empty($ativo['ultimo_heartbeat'])) {
            return null;
        }

        return (int)floor(time() - strtotime($ativo['ultimo_heartbeat']));
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

        return self::duracaoLegivel(max(0, time() - $timestamp));
    }

    /**
     * "X dia(s), HH:MM:SS" (ou só "HH:MM:SS" se for menos de 1 dia) --
     * mesma quebra em dias/horas/minutos/segundos usada no uptime,
     * reaproveitada pra qualquer "há quanto tempo" da tela do ativo
     * (último ping, última coleta) em vez de mostrar segundos/minutos
     * crus.
     */
    public static function duracaoLegivel(int $segundos): string
    {
        $segundos = max(0, $segundos);
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
