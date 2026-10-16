<?php

namespace App\Services;

use App\Core\Database;
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
            'firewall_dominio' => 'Firewall (Domínio)',
            'firewall_privado' => 'Firewall (Privado)',
            'firewall_publico' => 'Firewall (Público)',
            'defender_ativo' => 'Windows Defender',
            'defender_tempo_real' => 'Defender - Proteção em tempo real',
            'defender_assinatura_data' => 'Defender - Assinatura atualizada em',
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
            'firewall_dominio' => 'Firewall (Domínio)',
            'firewall_privado' => 'Firewall (Privado)',
            'firewall_publico' => 'Firewall (Público)',
            'defender_ativo' => 'Windows Defender',
            'defender_tempo_real' => 'Defender - Proteção em tempo real',
            'defender_assinatura_data' => 'Defender - Assinatura atualizada em',
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
            // vida_* -- só Brother (MIB próprio, ver SnmpService::manutencaoBrother()).
            'vida_cilindro' => 'Vida restante do cilindro',
            'vida_fusor' => 'Vida restante do fusor',
            'vida_laser' => 'Vida restante da unidade laser',
            'snmp_modelo' => 'Modelo (SNMP)',
            'snmp_serial' => 'Número de série (SNMP)',
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
            'dvr_hd_problema' => 'Problema no HD (Intelbras)',
            'snmp_sys_descr' => 'Descrição (SNMP)',
            'snmp_uptime' => 'Uptime (SNMP)',
            // protect_* -- câmera UniFi avulsa, gravada pelo UniFi Protect
            // (UDM/UNVR), coletada pela API do Protect (coletarProtect()).
            // 'protect_camera_id' fica FORA daqui (só identificador interno).
            'protect_nome' => 'Nome no Protect',
            'protect_modelo' => 'Modelo (Protect)',
            'protect_status' => 'Status no Protect',
            'protect_nvr' => 'Gravando em (NVR)',
            'protect_conexao' => 'Conectada em',
            'protect_uptime' => 'Ligada há',
        ],
        'switch' => [
            'numero_portas' => 'Número de portas',
            'gerenciavel' => 'Gerenciável',
            'firmware' => 'Firmware',
            'snmp_sys_descr' => 'Descrição (SNMP)',
            'snmp_uptime' => 'Uptime (SNMP)',
            // omada_* -- coletados via Omada Controller (TP-Link) ou via SSH
            // direto no switch (OmadaSshService), não SNMP. Ficam ao lado dos
            // campos manuais/SNMP acima, sem tirar nada: um switch TP-Link pode
            // ter os dois preenchidos ao mesmo tempo. 'omada_portas'/'omada_vlans'
            // (arrays) ficam FORA daqui -- mesmo motivo de 'dvr_canais', lidos
            // direto de $detalhes na aba "Portas do switch" de ver.php.
            'omada_model' => 'Modelo (Omada)',
            'omada_firmware' => 'Versão de firmware (Omada)',
            'omada_status' => 'Status (Omada)',
            'omada_uptime' => 'Uptime (Omada)',
            'omada_nome' => 'Nome no switch',
            'omada_serial' => 'Número de série (Omada)',
            'omada_mac' => 'MAC (Omada)',
            'omada_cpu_pct' => 'Uso de CPU',
            'omada_mem_pct' => 'Uso de memória',
            'omada_portas_resumo' => 'Portas com link',
            'omada_mac_total' => 'Endereços MAC aprendidos',
            'omada_coleta_via' => 'Coletado via',
            'omada_controller_status' => 'Status no Omada Controller',
            // unifi_* -- switch UniFi, coletado pelo UniFi Controller
            // (coletarUnifi()). 'unifi_portas'/'unifi_vlans' (arrays) ficam
            // FORA daqui, igual omada_portas -- aba "Portas do switch".
            'unifi_model' => 'Modelo (UniFi)',
            'unifi_firmware' => 'Versão de firmware (UniFi)',
            'unifi_status' => 'Status no Controller',
            'unifi_uptime' => 'Uptime (UniFi)',
            'unifi_serial' => 'Número de série (UniFi)',
            'unifi_cpu_pct' => 'Uso de CPU (UniFi)',
            'unifi_mem_pct' => 'Uso de memória (UniFi)',
            'unifi_poe_consumo' => 'Consumo PoE',
            'unifi_uplink' => 'Uplink',
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
        // periferico_* -- ping pra todos; o resto só quando o equipamento é
        // reconhecido (por enquanto conversor serial USR IoT, via UsrIotService).
        'periferico' => [
            'funcao' => 'Função / equipamento ligado',
            'periferico_status' => 'Status (ping)',
            'periferico_modelo' => 'Modelo (coletado)',
            'periferico_firmware' => 'Firmware',
            'periferico_serial' => 'Número de série (coletado)',
            'periferico_mac' => 'MAC',
            'periferico_serial_config' => 'Porta serial',
            'periferico_modo' => 'Modo de trabalho',
            'periferico_destino' => 'Destino configurado',
            'periferico_conexao' => 'Conexão com o destino',
            'periferico_trafego' => 'Tráfego desde que ligou (enviado / recebido)',
            'periferico_ultimo_trafego' => 'Último tráfego visto em',
            'periferico_coletado' => 'Última coleta',
        ],
    ];

    private const NOME_JOB_CRON_SNMP = 'Coleta SNMP de Ativos de TI';
    private const NOME_JOB_CRON_PERIFERICOS = 'Coleta de Periféricos (Ativos de TI)';
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

    /**
     * Regenera o código de patrimônio pegando um número novo na sequência
     * da unidade ATUAL do ativo (tipo+unidade, ver proximoCodigo()) --
     * necessário depois de mudar a unidade pela tela de edição, já que
     * "editar" nunca mexe em codigo_patrimonio sozinho (o código antigo,
     * com a sigla da unidade errada, ficaria preso pra sempre).
     */
    public function regenerarCodigo(int $id): array
    {
        $ativo = $this->buscar($id);
        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        $tipo = (new AtivoTipoService())->buscar((int)$ativo['tipo_id']);
        $unidade = (new UnidadeService())->buscar((int)$ativo['unidade_id']);

        if (!$tipo || !$unidade) {
            return ['success' => false, 'message' => 'Tipo ou unidade do ativo inválidos.'];
        }

        $codigoAntigo = $ativo['codigo_patrimonio'];
        $codigoNovo = $this->proximoCodigo($tipo, $unidade);

        $this->repository->atualizarCodigoPatrimonio($id, $codigoNovo);

        AuditService::registrar('Ativos', 'Atualizar Código', "Código do ativo \"{$ativo['nome']}\" alterado de \"{$codigoAntigo}\" para \"{$codigoNovo}\" (unidade: {$unidade['nome']}).");

        $this->registrarChamadoTrocaCodigo($ativo, $codigoAntigo, $codigoNovo, $unidade['nome']);

        return ['success' => true, 'codigo' => $codigoNovo];
    }

    /**
     * Troca o código de patrimônio, deixando o usuário escolher só o
     * NÚMERO sequencial -- sigla da empresa, da unidade e do tipo sempre
     * vêm do cadastro real do ativo (unidade_id/tipo_id), nunca digitadas
     * livres, pra impedir montar um código com sigla de unidade/tipo que
     * não existe no sistema. Usado pela tela "Ajustar Código" e pelo
     * modal do botão de regenerar (quando o usuário edita o número
     * sugerido em vez de aceitar o padrão).
     */
    public function ajustarNumeroCodigo(int $id, int $numero): array
    {
        $ativo = $this->buscar($id);
        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        if ($numero < 1) {
            return ['success' => false, 'message' => 'Informe um número válido (maior que zero).'];
        }

        $tipo = (new AtivoTipoService())->buscar((int)$ativo['tipo_id']);
        $unidade = (new UnidadeService())->buscar((int)$ativo['unidade_id']);
        if (!$tipo || !$unidade) {
            return ['success' => false, 'message' => 'Tipo ou unidade do ativo inválidos.'];
        }

        $resultado = $this->ajustarCodigoManual($ativo, $unidade, $this->formatarCodigo($tipo, $unidade, $numero));
        if ($resultado['success']) {
            // Número escolhido à mão à frente do contador: avança o contador, senão
            // o próximo cadastro tentaria reaproveitar um número já usado.
            $this->repository->avancarContador((int)$tipo['id'], (int)$unidade['id'], $numero);
        }

        return $resultado;
    }

    /**
     * Espia o próximo número da sequência do tipo+unidade ATUAL do
     * ativo, sem consumir (diferente de proximoCodigo(), que incrementa
     * de vez) -- usado só pra sugerir um valor inicial no campo de número
     * antes do usuário confirmar (em ajustarNumeroCodigo() ou
     * regenerarCodigo()). Pode ficar levemente desatualizado se dois
     * administradores previrem ao mesmo tempo; não é uma reserva.
     */
    public function previsaoProximoCodigo(int $id): array
    {
        $ativo = $this->buscar($id);
        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        $tipo = (new AtivoTipoService())->buscar((int)$ativo['tipo_id']);
        $unidade = (new UnidadeService())->buscar((int)$ativo['unidade_id']);
        if (!$tipo || !$unidade) {
            return ['success' => false, 'message' => 'Tipo ou unidade do ativo inválidos.'];
        }

        // Mesma regra de proximoCodigo(): sugere o próximo número LIVRE.
        $numero = $this->repository->espiarProximoNumeroContador((int)$tipo['id'], (int)$unidade['id']);
        while ($this->repository->buscarPorCodigoPatrimonio($this->formatarCodigo($tipo, $unidade, $numero)) !== null) {
            $numero++;
        }

        return [
            'success' => true,
            'codigo' => $this->formatarCodigo($tipo, $unidade, $numero),
            'numero' => $numero,
            'numero_atual' => $this->numeroDoCodigo($ativo['codigo_patrimonio']),
        ];
    }

    /** Extrai o número sequencial do final de um código já existente (ex: "EP-AV-PC-000015" -> 15), pra sugerir como ponto de partida ao ajustar. Sem grupo numérico no final, devolve null. */
    private function numeroDoCodigo(string $codigo): ?int
    {
        return preg_match('/-(\d+)$/', $codigo, $m) ? (int)$m[1] : null;
    }

    /**
     * Grava um código já MONTADO (string completa, composta só a partir
     * de formatarCodigo() -- nunca texto livre do usuário). A coluna
     * codigo_patrimonio já tem UNIQUE KEY no banco, então a checagem de
     * duplicidade aqui é só pra dar um erro amigável antes do UPDATE, em
     * vez de estourar a exceção do PDO sem explicação (mesmo raciocínio
     * já usado pra machine_guid em editar()).
     */
    private function ajustarCodigoManual(array $ativo, array $unidade, string $novoCodigo): array
    {
        $codigoAntigo = $ativo['codigo_patrimonio'];
        if ($novoCodigo === $codigoAntigo) {
            return ['success' => false, 'message' => 'Esse já é o código atual.'];
        }

        $conflito = $this->repository->buscarPorCodigoPatrimonio($novoCodigo);
        if ($conflito && (int)$conflito['id'] !== (int)$ativo['id']) {
            return ['success' => false, 'message' => "Esse código já pertence ao ativo \"{$conflito['nome']}\" -- não dá pra usar o mesmo em dois ativos."];
        }

        try {
            $this->repository->atualizarCodigoPatrimonio((int)$ativo['id'], $novoCodigo);
        } catch (\PDOException $e) {
            // rede de seguranca contra corrida (dois ajustes simultaneos pro
            // mesmo codigo) -- a checagem acima ja cobre o caso normal
            return ['success' => false, 'message' => 'Esse código acabou de ser usado por outro ativo. Tente outro.'];
        }

        AuditService::registrar('Ativos', 'Ajustar Código', "Código do ativo \"{$ativo['nome']}\" ajustado manualmente de \"{$codigoAntigo}\" para \"{$novoCodigo}\".");

        $this->registrarChamadoTrocaCodigo($ativo, $codigoAntigo, $novoCodigo, $unidade['nome'] ?? '');

        return ['success' => true, 'codigo' => $novoCodigo];
    }

    /**
     * Abre e já fecha um chamado automático (robô, mesmo padrão dos
     * chamados de alerta de DVR/NVR -- ver abrirChamadoCanalDvrSemSinal())
     * registrando a troca de código no histórico do ativo, com o nome de
     * quem clicou no botão. Não bloqueia a troca do código se a categoria
     * "Patrimônio" ainda não existir nessa instância (migration cria na
     * primeira atualização; se por algum motivo não rodou, só não abre
     * o chamado, silenciosamente).
     */
    private function registrarChamadoTrocaCodigo(array $ativo, string $codigoAntigo, string $codigoNovo, string $nomeUnidade): void
    {
        $categoriaId = $this->categoriaChamadoPatrimonioId();
        if ($categoriaId === null) {
            return;
        }

        $nomeAdmin = trim((string)($_SESSION['usuario']['nome'] ?? '')) ?: 'um administrador';
        $chamadoService = new ChamadoService();

        $resultado = $chamadoService->abrir([
            'titulo' => "{$codigoAntigo} -- Código de patrimônio atualizado para {$codigoNovo}",
            'descricao' => "Registro automático: o código de patrimônio do ativo \"{$ativo['nome']}\" foi alterado de \"{$codigoAntigo}\" para \"{$codigoNovo}\", refletindo a unidade atual ({$nomeUnidade}).\n\n"
                . "Se este equipamento já tem etiqueta impressa com o código antigo, ela precisa ser reimpressa e trocada -- o código antigo deixou de existir e não deve mais ser usado pra identificar o equipamento.",
            'categoria_id' => $categoriaId,
            'unidade_id' => $ativo['unidade_id'],
            'ativo_id' => $ativo['id'],
            'prioridade' => 'baixa',
            'solicitante_nome' => 'RD.Intranet - Robô',
            'solicitante_email' => 'robo@rd.intranet',
        ], 'sistema');

        if (!$resultado['success']) {
            return;
        }

        $chamadoId = (int)$resultado['id'];
        $usuarioId = (int)($_SESSION['usuario']['id'] ?? 0);

        $chamadoService->responder(
            $chamadoId,
            "Alteração feita por {$nomeAdmin}, pela tela de Ativos.",
            'interna',
            $usuarioId ?: null
        );

        $chamadoService->mudarStatus($chamadoId, 'fechado', $usuarioId);
    }

    private function categoriaChamadoPatrimonioId(): ?int
    {
        $stmt = Database::connection()->prepare("SELECT id FROM chamados_categorias WHERE nome = 'Patrimônio' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();

        return $id !== false ? (int)$id : null;
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
            'detalhes' => json_encode($this->detalhesAposEdicao($ativo, $post), JSON_UNESCAPED_UNICODE),
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
        $this->removerImagensReferenciaDvr($id);

        AuditService::registrar('Ativos', 'Excluir Ativo', 'Ativo ' . $ativo['codigo_patrimonio'] . ' (' . $ativo['nome'] . ') removido.');

        NotificationService::success('Ativo removido do cadastro.');

        return true;
    }

    public function buscarPorIds(array $ids): array
    {
        return $this->repository->buscarPorIds($ids);
    }

    /** @return array<string, array{id:int, codigo_patrimonio:string, nome:string}> indexado pelo nome em maiúsculas */
    public function buscarPorNomesMaquina(array $nomes): array
    {
        return $this->repository->buscarPorNomesMaquina($nomes);
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

    /** Snapshot de processos da última coleta periódica (Fase 1 de detecção de ameaças) -- diferente do Explorador de Processos, que é sob demanda/ao vivo. */
    public function listarProcessosColetados(int $ativoId): array
    {
        return $this->repository->listarProcessosColetados($ativoId);
    }

    /**
     * Próximo código livre do tipo+unidade. O contador pode estar atrás dos
     * códigos que já existem (código ajustado à mão, ativo movido de unidade,
     * troca do número de dígitos) -- então pula os que já estão em uso em vez
     * de devolver um repetido. Visto no enzilabprime: APs ajustados à mão para
     * EP-AV-AP-0001/0003/0004 com o contador parado em 2; o próximo cadastro
     * gerou EP-AV-AP-0003 e estourou o UNIQUE (erro 500 ao salvar).
     *
     * @param array $tipo linha de ativos_tipos; @param array $unidade linha de unidades
     */
    private function proximoCodigo(array $tipo, array $unidade): string
    {
        for ($tentativa = 0; $tentativa < 10000; $tentativa++) {
            $codigo = $this->formatarCodigo($tipo, $unidade, $this->repository->proximoNumeroContador((int)$tipo['id'], (int)$unidade['id']));
            if ($this->repository->buscarPorCodigoPatrimonio($codigo) === null) {
                return $codigo;
            }
        }

        throw new \RuntimeException('Não foi possível gerar um código de patrimônio livre.');
    }

    /** Monta a string do código a partir dos dados REAIS do ativo (sigla da empresa + sigla da unidade + sigla do tipo, sempre do cadastro) e de um número já escolhido -- nunca aceita sigla digitada livre. */
    private function formatarCodigo(array $tipo, array $unidade, int $numero): string
    {
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

    /**
     * Campos do formulário (CAMPOS_DETALHES) vêm do POST; todo o resto que
     * já estava em `detalhes` é dado de coleta (portas do switch, canais do
     * DVR, vínculo com a câmera do Protect, rádios do AP...) e é mantido --
     * antes, editar o ativo apagava tudo isso até a próxima coleta.
     */
    private function detalhesAposEdicao(array $ativo, array $post): array
    {
        $tipo = $ativo['tipo_slug'] ?? '';
        $atuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $coletados = array_diff_key($atuais, self::CAMPOS_DETALHES[$tipo] ?? []);

        return array_merge($coletados, $this->extrairDetalhes($tipo, $post));
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
        $clientesLegado = $unifi->listarClientesLegado();
        $clientesWifi = [];
        foreach ($clientesLegado as $c) {
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

        // SSIDs que ESTE AP está transmitindo agora (vap_table da API legada:
        // um registro por SSID por rádio) -- agrupado por nome, com bandas,
        // canal, clientes e a segurança/VLAN vindas da configuração do Wi-Fi.
        if ($legadoDispositivo !== null && isset($legadoDispositivo['vap_table'])) {
            $configWifi = [];
            foreach ((new UnifiService())->listarRedesWifiConfiguradas()['redes'] ?? [] as $w) {
                $configWifi[$w['nome']] = $w;
            }
            $ssids = [];
            foreach ($legadoDispositivo['vap_table'] as $vap) {
                $nome = (string)($vap['essid'] ?? '');
                if ($nome === '') {
                    continue;
                }
                $ssids[$nome] ??= [
                    'nome' => $nome,
                    'bandas' => [],
                    'canais' => [],
                    'bssids' => [],
                    'clientes' => 0,
                    'ativo' => false,
                    'seguranca' => $configWifi[$nome]['seguranca'] ?? '',
                    'convidado' => (bool)($vap['is_guest'] ?? ($configWifi[$nome]['convidado'] ?? false)),
                    'oculta' => (bool)($configWifi[$nome]['oculta'] ?? false),
                    'vlan' => null,
                ];
                $ssids[$nome]['bandas'][] = self::rotuloBandaRadio(($vap['radio'] ?? '') === 'ng' ? 2.4 : (($vap['radio'] ?? '') === '6e' ? 6 : 5));
                if (!empty($vap['channel'])) {
                    $ssids[$nome]['canais'][] = (int)$vap['channel'];
                }
                if (!empty($vap['bssid'])) {
                    $ssids[$nome]['bssids'][] = $vap['bssid'];
                }
                $ssids[$nome]['clientes'] += (int)($vap['num_sta'] ?? 0);
                $ssids[$nome]['ativo'] = $ssids[$nome]['ativo'] || !empty($vap['up']);
                if (!empty($vap['vlan'])) {
                    $ssids[$nome]['vlan'] = (int)$vap['vlan'];
                }
            }
            $coletado['unifi_ssids'] = array_values($ssids);
        }

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

            // Switch UniFi: painel de portas no mesmo formato do Omada
            // (omada_portas), pra reaproveitar a aba "Portas do switch".
            if (!empty($legadoDispositivo['port_table']) && ($ativo['tipo_slug'] ?? '') === 'switch') {
                $coletado = array_merge($coletado, $this->coletarPortasSwitchUnifi($unifi, $legadoDispositivo, $clientesLegado, $id));
            }
        }

        $detalhesAtuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $detalhesNovos = array_merge($detalhesAtuais, $coletado);

        $this->repository->atualizarDetalhesApi($id, json_encode($detalhesNovos, JSON_UNESCAPED_UNICODE));

        AuditService::registrar('Ativos', 'Coleta UniFi', 'Dados coletados via API do UniFi Controller para ' . $ativo['codigo_patrimonio'] . '.');

        return ['success' => true, 'message' => 'Dados coletados com sucesso via UniFi Controller.'];
    }

    /**
     * Portas do switch UniFi no MESMO formato de omada_portas (porta, link,
     * velocidade, duplex, meio, descricao, dispositivos[]), mais o que só a
     * UniFi entrega por porta: PoE, tráfego, erros, STP e uplink. Quem está
     * em cada porta vem dos clientes cabeados do Controller (sw_mac/sw_port,
     * já com nome, IP e fabricante) e do LLDP (AP, gateway, outro switch --
     * dispositivos UniFi não aparecem como cliente).
     */
    private function coletarPortasSwitchUnifi(UnifiService $unifi, array $legado, array $clientesLegado, ?int $ativoId = null): array
    {
        $macSwitch = OmadaSshService::normalizarMac($legado['mac'] ?? '');

        $porPorta = [];
        $adicionar = function (int $porta, array $disp) use (&$porPorta) {
            if ($disp['mac'] === '' || isset($porPorta[$porta][$disp['mac']])) {
                return;
            }
            $porPorta[$porta][$disp['mac']] = $disp;
        };

        foreach ($clientesLegado as $c) {
            if (empty($c['is_wired']) || OmadaSshService::normalizarMac($c['sw_mac'] ?? '') !== $macSwitch || !isset($c['sw_port'])) {
                continue;
            }
            $ip = (string)($c['ip'] ?? ($c['last_ip'] ?? ''));
            $adicionar((int)$c['sw_port'], [
                'mac' => OmadaSshService::normalizarMac($c['mac'] ?? ''),
                'ip' => $ip,
                'ip_origem' => $ip !== '' ? 'controller' : '',
                'hostname' => (string)($c['name'] ?? ($c['hostname'] ?? '')),
                'fabricante' => (string)($c['oui'] ?? ''),
                'rede' => (string)($c['network'] ?? ''),
                'network_id' => (string)($c['network_id'] ?? ''),
            ]);
        }

        $dispositivosUnifi = [];
        foreach ($unifi->listarDispositivosLegado() as $d) {
            $dispositivosUnifi[OmadaSshService::normalizarMac($d['mac'] ?? '')] = $d;
        }
        foreach ($legado['lldp_table'] ?? [] as $vizinho) {
            $mac = OmadaSshService::normalizarMac($vizinho['chassis_id'] ?? '');
            $dispositivo = $dispositivosUnifi[$mac] ?? null;
            $ipv4 = '';
            foreach ($vizinho['mgmt_ips'] ?? [] as $ipVizinho) {
                if (filter_var($ipVizinho, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipv4 = $ipVizinho;
                    break;
                }
            }
            $adicionar((int)($vizinho['local_port_idx'] ?? 0), [
                'mac' => $mac,
                'ip' => $ipv4,
                'ip_origem' => $ipv4 !== '' ? 'controller' : '',
                'hostname' => (string)($dispositivo['name'] ?? ''),
                'fabricante' => $dispositivo ? 'Ubiquiti' : '',
                'unifi_dispositivo' => (string)($dispositivo['model'] ?? ''),
                'lldp' => true, // vizinho anunciado pelo próprio cabo: é o que está plugado
            ]);
        }

        // Redes LAN do site (= VLANs). A padrão (sem vlan_enabled) é a VLAN 1.
        $redes = [];
        $redePadraoId = null;
        $dispositivosPorRede = [];
        foreach ($clientesLegado as $c) {
            if (!empty($c['network_id'])) {
                $dispositivosPorRede[$c['network_id']] = ($dispositivosPorRede[$c['network_id']] ?? 0) + 1;
            }
        }
        foreach ($unifi->listarRedesConfiguradas() as $rede) {
            if (!in_array($rede['purpose'] ?? '', ['corporate', 'guest', 'vlan-only'], true)) {
                continue;
            }
            $padrao = empty($rede['vlan_enabled']);
            if ($padrao) {
                $redePadraoId = $rede['_id'];
            }
            $redes[$rede['_id']] = [
                'id' => $padrao ? 1 : (int)($rede['vlan'] ?? 0),
                'nome' => (string)($rede['name'] ?? ''),
                'id_rede' => (string)$rede['_id'],
                'padrao' => $padrao,
                'subnet' => (string)($rede['ip_subnet'] ?? ''),
                'dhcp' => !empty($rede['dhcpd_enabled']),
                'dhcp_inicio' => (string)($rede['dhcpd_start'] ?? ''),
                'dhcp_fim' => (string)($rede['dhcpd_stop'] ?? ''),
                'isolada' => !empty($rede['network_isolation_enabled']),
                'internet' => ($rede['internet_access_enabled'] ?? true) !== false,
                'dispositivos' => $dispositivosPorRede[$rede['_id']] ?? 0,
                'portas' => 0,
            ];
        }

        $portas = [];
        $comLink = 0;
        foreach ($legado['port_table'] as $p) {
            $numero = (int)($p['port_idx'] ?? 0);
            $link = !empty($p['up']);
            $comLink += $link ? 1 : 0;
            $nome = (string)($p['name'] ?? "Port {$numero}");
            $dispositivos = array_values($porPorta[$numero] ?? []);
            foreach ($dispositivos as &$disp) {
                if (isset($disp['network_id'])) {
                    $disp['vlan'] = $redes[$disp['network_id']]['id'] ?? null;
                    unset($disp['network_id']);
                }
            }
            unset($disp);

            // Equipamento que o Controller não lista como cliente (ex.: sem IP
            // na rede) mas que a própria porta lembra como último conectado.
            $ultimo = $p['last_connection'] ?? [];
            if ($link && !$dispositivos && !empty($ultimo['connected']) && !empty($ultimo['mac'])) {
                $dispositivos[] = [
                    'mac' => OmadaSshService::normalizarMac($ultimo['mac']),
                    'ip' => (string)($ultimo['ip'] ?? ''),
                    'ip_origem' => !empty($ultimo['ip']) ? 'controller' : '',
                    'hostname' => '',
                    'fabricante' => '',
                ];
            }

            $portas[] = [
                'porta' => $nome,
                'numero' => $numero,
                'link' => $link,
                'velocidade' => $link && !empty($p['speed']) ? $p['speed'] . 'M' : '',
                'duplex' => $link ? (!empty($p['full_duplex']) ? 'Full' : 'Half') : '',
                'meio' => str_starts_with((string)($p['media'] ?? ''), 'SFP') ? 'Fibra' : 'Cobre',
                // Nome padrão ("Port 3", "SFP 1") não é descrição -- só o que alguém renomeou.
                'descricao' => preg_match('/^(Port|SFP\+?) \d+$/', $nome) ? '' : $nome,
                'habilitada' => ($p['enable'] ?? true) !== false,
                'uplink' => !empty($p['is_uplink']),
                'poe' => !empty($p['port_poe']) ? [
                    'ligado' => !empty($p['poe_enable']),
                    'potencia_w' => round((float)($p['poe_power'] ?? 0), 2),
                    'classe' => (string)($p['poe_class'] ?? ''),
                    'modo' => (string)($p['poe_mode'] ?? ''),
                ] : null,
                'trafego_rx' => self::tamanhoLegivel((int)($p['rx_bytes'] ?? 0)),
                'trafego_tx' => self::tamanhoLegivel((int)($p['tx_bytes'] ?? 0)),
                'erros' => (int)($p['rx_errors'] ?? 0) + (int)($p['tx_errors'] ?? 0),
                'stp' => (string)($p['stp_state'] ?? ''),
                'dispositivos' => $dispositivos,
            ] + $this->vlansPortaUnifi($p, $redes, $redePadraoId);
        }

        foreach ($portas as $porta) {
            foreach ($porta['vlans'] as $v) {
                foreach ($redes as &$r) {
                    if ($r['id'] === $v['id']) {
                        $r['portas']++;
                    }
                }
                unset($r);
            }
        }
        $vlans = array_values($redes);
        usort($vlans, fn ($a, $b) => $a['id'] <=> $b['id']);

        $uplink = $legado['uplink'] ?? [];
        $coletado = [
            'unifi_nome' => (string)($legado['name'] ?? ''),
            'unifi_serial' => (string)($legado['serial'] ?? ''),
            'unifi_portas' => $this->marcarPontaFisica($this->enriquecerDispositivosPortas($portas, $clientesLegado), (string)($legado['mac'] ?? ''), $ativoId),
            'unifi_vlans' => $vlans,
            'unifi_portas_resumo' => "{$comLink} de " . count($portas),
            'unifi_mac_total' => (string)array_sum(array_map(fn ($p) => count($p['dispositivos']), $portas)),
            'unifi_coletado_em' => date('Y-m-d H:i:s'),
            'unifi_dispositivo_id' => (string)($legado['_id'] ?? ''),
        ];
        if (isset($legado['total_used_power'], $legado['total_max_power'])) {
            $coletado['unifi_poe_consumo'] = round((float)$legado['total_used_power'], 1) . ' W de ' . round((float)$legado['total_max_power']) . ' W';
        }
        if (!empty($uplink['uplink_device_name'])) {
            $coletado['unifi_uplink'] = 'Porta ' . ($uplink['port_idx'] ?? '?') . ' → ' . $uplink['uplink_device_name']
                . (isset($uplink['uplink_remote_port']) ? ' (porta ' . $uplink['uplink_remote_port'] . ')' : '');
        }

        return $coletado;
    }

    /**
     * VLANs de uma porta UniFi a partir de forward/native_networkconf_id/
     * tagged_vlan_mgmt/excluded_networkconf_ids (formato confirmado ao vivo,
     * ver UnifiService). Sem override a porta leva TODAS as VLANs.
     *
     * @return array{vlan_nativa: ?int, vlans: array, vlan_todas: bool, vlan_desabilitada: bool}
     */
    private function vlansPortaUnifi(array $p, array $redes, ?string $redePadraoId): array
    {
        $forward = (string)($p['forward'] ?? 'all');

        if ($forward === 'disabled') {
            return ['vlan_nativa' => null, 'vlans' => [], 'vlan_todas' => false, 'vlan_desabilitada' => true];
        }

        $nativaId = $forward === 'all' ? $redePadraoId : ($p['native_networkconf_id'] ?? $redePadraoId);
        $gestao = (string)($p['tagged_vlan_mgmt'] ?? ($forward === 'all' ? 'auto' : ''));
        $excluidas = $p['excluded_networkconf_ids'] ?? [];

        $vlans = [];
        if ($nativaId !== null && isset($redes[$nativaId])) {
            $vlans[] = ['id' => $redes[$nativaId]['id'], 'tagueada' => false];
        }
        foreach ($redes as $id => $rede) {
            if ($id === $nativaId) {
                continue;
            }
            $leva = match (true) {
                $forward === 'all', $gestao === 'auto' => true,
                $forward === 'native', $gestao === 'block_all' => false,
                default => !in_array($id, $excluidas, true),
            };
            if ($leva) {
                $vlans[] = ['id' => $rede['id'], 'tagueada' => true];
            }
        }

        return [
            'vlan_nativa' => $nativaId !== null && isset($redes[$nativaId]) ? $redes[$nativaId]['id'] : null,
            'vlans' => $vlans,
            'vlan_todas' => $forward === 'all',
            'vlan_desabilitada' => false,
        ];
    }

    /* ==================================================================
     * Manutenção de VLAN do switch (UniFi pelo Controller, TP-Link por SSH)
     *
     * Toda alteração: valida, aplica, guarda o estado anterior em
     * detalhes['vlan_desfazer'] (um nível de "Desfazer"), registra na
     * auditoria e recoleta o switch pra tela mostrar o estado real.
     * Travas: não mexe na porta de uplink, nem em porta onde está o gateway
     * da rede, este servidor ou outro equipamento UniFi (AP/gateway/switch);
     * não exclui VLAN padrão, VLAN com dispositivo em uso ou nativa de porta.
     * ================================================================== */

    /** 'unifi' | 'omada' | null -- de onde vêm as VLANs desse switch. */
    public static function fonteVlanSwitch(array $ativo, array $detalhes): ?string
    {
        if (($ativo['tipo_slug'] ?? '') !== 'switch') {
            return null;
        }
        if (!empty($detalhes['unifi_portas'])) {
            return 'unifi';
        }

        return !empty($detalhes['omada_portas']) ? 'omada' : null;
    }

    public static function manutencaoVlanDisponivel(array $ativo, array $detalhes): bool
    {
        return match (self::fonteVlanSwitch($ativo, $detalhes)) {
            'unifi' => (new UnifiService())->configurado(),
            'omada' => (new OmadaSshService())->manutencaoDisponivel(),
            default => false,
        };
    }

    /** @return array{0: ?array, 1: array, 2: ?string, 3: ?string} ativo, detalhes, fonte, erro */
    private function contextoVlan(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);
        $detalhes = $ativo ? (json_decode($ativo['detalhes'] ?? '', true) ?: []) : [];
        $fonte = $ativo ? self::fonteVlanSwitch($ativo, $detalhes) : null;

        if (!$ativo || $fonte === null) {
            return [null, [], null, 'Switch não encontrado ou ainda sem portas coletadas (UniFi ou TP-Link).'];
        }
        if (!self::manutencaoVlanDisponivel($ativo, $detalhes)) {
            return [null, [], null, 'A integração deste switch não está configurada para manutenção -- veja Integrações.'];
        }

        return [$ativo, $detalhes, $fonte, null];
    }

    /** IPs que nunca podem perder acesso: este servidor e o gateway padrão dele. */
    private function ipsProtegidosRede(): array
    {
        $ips = preg_split('/\s+/', trim((string)@shell_exec('hostname -I 2>/dev/null'))) ?: [];
        if (preg_match('/default via (\S+)/', (string)@shell_exec('ip route show default 2>/dev/null'), $m)) {
            $ips[] = $m[1];
        }

        return array_values(array_filter($ips, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)));
    }

    /** Motivo pra não mexer na porta, ou null se pode. */
    private function travaPortaVlan(array $porta): ?string
    {
        if (!empty($porta['uplink'])) {
            return 'Esta é a porta de uplink do switch -- alterar as VLANs dela derruba o switch inteiro.';
        }
        $protegidos = $this->ipsProtegidosRede();
        foreach ($porta['dispositivos'] ?? [] as $d) {
            if (!empty($d['unifi_dispositivo'])) {
                return 'Nesta porta está outro equipamento UniFi (' . ($d['hostname'] ?: $d['unifi_dispositivo']) . ') -- ele precisa levar as VLANs (ex.: redes Wi-Fi). Ajuste pelo UniFi se for mesmo necessário.';
            }
            if (($d['ip'] ?? '') !== '' && in_array($d['ip'], $protegidos, true)) {
                return 'Nesta porta está o gateway da rede ou o próprio servidor do RD.Intranet (' . $d['ip'] . ') -- alterar as VLANs dela pode derrubar a rede e o sistema.';
            }
        }

        return null;
    }

    private function gravarVlanDesfazer(int $id, ?array $desfazer): void
    {
        $ativo = $this->repository->buscarPorId($id);
        $detalhes = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        if ($desfazer === null) {
            unset($detalhes['vlan_desfazer']);
        } else {
            $usuario = $_SESSION['usuario']['nome'] ?? 'Sistema';
            $detalhes['vlan_desfazer'] = $desfazer + ['quando' => date('Y-m-d H:i:s'), 'usuario' => $usuario];
        }
        $this->repository->atualizarDetalhesApi($id, json_encode($detalhes, JSON_UNESCAPED_UNICODE));
    }

    private function concluirVlan(int $id, string $fonte, array $ativo, string $descricao, ?array $desfazer, bool $esperarProvisionamento = false): array
    {
        $this->gravarVlanDesfazer($id, $desfazer);
        AuditService::registrar('Ativos', 'VLAN', $ativo['codigo_patrimonio'] . ' (' . $ativo['nome'] . '): ' . $descricao);

        if ($esperarProvisionamento) {
            sleep(4); // o Controller leva uns segundos pra refletir a porta no registro do switch
        }
        $fonte === 'unifi' ? $this->coletarUnifi($id) : $this->coletarOmada($id);

        return ['success' => true, 'message' => $descricao];
    }

    public static function redeValida(string $cidr): bool
    {
        if (!preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', $cidr, $m)) {
            return false;
        }

        return filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (int)$m[2] >= 8 && (int)$m[2] <= 30;
    }

    public static function ipNaRede(string $ip, string $cidr): bool
    {
        [$rede, $bits] = explode('/', $cidr);
        $mascara = -1 << (32 - (int)$bits);

        return (ip2long($ip) & $mascara) === (ip2long($rede) & $mascara);
    }

    /**
     * Cria (sem id_rede / VLAN nova) ou edita uma VLAN.
     * UniFi: nome, sub-rede (IP do gateway/prefixo), DHCP, isolamento, internet.
     * TP-Link: só número e nome (switch L2 -- IP/DHCP ficam no roteador).
     */
    public function salvarVlan(int $id, array $dados): array
    {
        [$ativo, $detalhes, $fonte, $erro] = $this->contextoVlan($id);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }

        $vlan = (int)($dados['vlan'] ?? 0);
        $nome = trim((string)($dados['nome'] ?? ''));
        $existentes = $detalhes[$fonte . '_vlans'] ?? [];
        $atual = null;
        foreach ($existentes as $v) {
            if ((int)$v['id'] === $vlan) {
                $atual = $v;
            }
        }

        if ($fonte === 'omada') {
            if ($atual === null && $vlan === 1) {
                return ['success' => false, 'message' => 'A VLAN 1 já existe em todo switch.'];
            }
            $resultado = (new OmadaSshService())->salvarVlan($ativo['ip'], $vlan, $nome);
            if (!$resultado['success']) {
                return $resultado;
            }

            return $atual === null
                ? $this->concluirVlan($id, $fonte, $ativo, "VLAN {$vlan} \"{$nome}\" criada.", ['acao' => 'vlan_criada', 'vlan' => $vlan])
                : $this->concluirVlan($id, $fonte, $ativo, "VLAN {$vlan} renomeada de \"{$atual['nome']}\" para \"{$nome}\".", ['acao' => 'vlan_editada', 'vlan' => $vlan, 'antes' => ['nome' => $atual['nome']]]);
        }

        // ---- UniFi ----
        if ($nome === '' || mb_strlen($nome) > 32) {
            return ['success' => false, 'message' => 'Informe um nome de até 32 caracteres.'];
        }
        $subnet = trim((string)($dados['subnet'] ?? ''));
        if (!self::redeValida($subnet)) {
            return ['success' => false, 'message' => 'Sub-rede inválida -- use o IP do gateway com o prefixo, ex.: 10.20.0.1/24.'];
        }
        $dhcp = !empty($dados['dhcp']);
        $inicio = trim((string)($dados['dhcp_inicio'] ?? ''));
        $fim = trim((string)($dados['dhcp_fim'] ?? ''));
        if ($dhcp && (!filter_var($inicio, FILTER_VALIDATE_IP) || !filter_var($fim, FILTER_VALIDATE_IP)
            || !self::ipNaRede($inicio, $subnet) || !self::ipNaRede($fim, $subnet) || ip2long($inicio) > ip2long($fim))) {
            return ['success' => false, 'message' => 'Faixa de DHCP inválida -- início e fim precisam estar dentro da sub-rede, nessa ordem.'];
        }
        foreach ($existentes as $v) {
            if (($v['id_rede'] ?? '') !== ($atual['id_rede'] ?? null) && $v['subnet'] !== '' && self::ipNaRede(explode('/', $subnet)[0], $v['subnet'])) {
                return ['success' => false, 'message' => "Essa sub-rede já é usada pela VLAN {$v['id']} ({$v['nome']})."];
            }
        }

        $campos = [
            'name' => $nome,
            'ip_subnet' => $subnet,
            'dhcpd_enabled' => $dhcp,
            'network_isolation_enabled' => !empty($dados['isolada']),
            'internet_access_enabled' => !empty($dados['internet']),
        ];
        if ($dhcp) {
            $campos['dhcpd_start'] = $inicio;
            $campos['dhcpd_stop'] = $fim;
        }

        $unifi = new UnifiService();

        if ($atual === null) {
            if ($vlan < 2 || $vlan > 4094) {
                return ['success' => false, 'message' => 'Número de VLAN de 2 a 4094.'];
            }
            $resultado = $unifi->criarRede($campos + ['purpose' => 'corporate', 'vlan_enabled' => true, 'vlan' => $vlan, 'networkgroup' => 'LAN', 'enabled' => true]);
            if (!$resultado['success']) {
                return $resultado;
            }

            return $this->concluirVlan($id, $fonte, $ativo, "VLAN {$vlan} \"{$nome}\" criada ({$subnet}" . ($dhcp ? ", DHCP {$inicio}-{$fim}" : ', sem DHCP') . ').',
                ['acao' => 'vlan_criada', 'vlan' => $vlan, 'id_rede' => $resultado['id']]);
        }

        // Sub-rede da rede por onde este servidor acessa não muda pela tela (cortaria o acesso).
        if ($subnet !== $atual['subnet']) {
            foreach ($this->ipsProtegidosRede() as $ip) {
                if ($atual['subnet'] !== '' && self::ipNaRede($ip, $atual['subnet'])) {
                    return ['success' => false, 'message' => "A sub-rede da VLAN {$vlan} não pode ser alterada por aqui: é por ela que o RD.Intranet ({$ip}) acessa a rede."];
                }
            }
        }

        $resultado = $unifi->editarRede($atual['id_rede'], $campos);
        if (!$resultado['success']) {
            return $resultado;
        }
        $antes = [
            'name' => $atual['nome'], 'ip_subnet' => $atual['subnet'], 'dhcpd_enabled' => $atual['dhcp'],
            'network_isolation_enabled' => $atual['isolada'], 'internet_access_enabled' => $atual['internet'],
        ];
        if ($atual['dhcp']) {
            $antes['dhcpd_start'] = $atual['dhcp_inicio'];
            $antes['dhcpd_stop'] = $atual['dhcp_fim'];
        }

        return $this->concluirVlan($id, $fonte, $ativo, "VLAN {$vlan} \"{$nome}\" alterada.", ['acao' => 'vlan_editada', 'vlan' => $vlan, 'id_rede' => $atual['id_rede'], 'antes' => $antes]);
    }

    public function excluirVlan(int $id, int $vlan): array
    {
        [$ativo, $detalhes, $fonte, $erro] = $this->contextoVlan($id);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }

        $atual = null;
        foreach ($detalhes[$fonte . '_vlans'] ?? [] as $v) {
            if ((int)$v['id'] === $vlan) {
                $atual = $v;
            }
        }
        if ($atual === null) {
            return ['success' => false, 'message' => "VLAN {$vlan} não encontrada -- atualize a tela."];
        }
        if ($vlan === 1 || !empty($atual['padrao'])) {
            return ['success' => false, 'message' => 'A VLAN padrão (1) não pode ser excluída.'];
        }
        foreach ($detalhes[$fonte . '_portas'] ?? [] as $p) {
            if (($p['vlan_nativa'] ?? null) === $vlan) {
                return ['success' => false, 'message' => "A VLAN {$vlan} é a nativa da porta " . ($p['numero'] ?? $p['porta']) . ' -- troque a VLAN dessa porta antes de excluir.'];
            }
        }

        if ($fonte === 'omada') {
            $membros = array_filter($detalhes['omada_portas'] ?? [], fn ($p) => in_array($vlan, array_column($p['vlans'] ?? [], 'id'), true));
            if ($membros) {
                return ['success' => false, 'message' => "A VLAN {$vlan} ainda está em " . count($membros) . ' porta(s) -- tire-a das portas antes de excluir.'];
            }
            $resultado = (new OmadaSshService())->excluirVlan($ativo['ip'], $vlan);
            if (!$resultado['success']) {
                return $resultado;
            }

            return $this->concluirVlan($id, $fonte, $ativo, "VLAN {$vlan} \"{$atual['nome']}\" excluída.", ['acao' => 'vlan_excluida', 'vlan' => $vlan, 'nome' => $atual['nome']]);
        }

        if (($atual['dispositivos'] ?? 0) > 0) {
            return ['success' => false, 'message' => "A VLAN {$vlan} tem {$atual['dispositivos']} dispositivo(s) conectado(s) agora -- mova-os antes de excluir."];
        }
        $resultado = (new UnifiService())->excluirRede($atual['id_rede']);
        if (!$resultado['success']) {
            return $resultado;
        }
        $recriar = [
            'name' => $atual['nome'], 'purpose' => 'corporate', 'vlan_enabled' => true, 'vlan' => $vlan, 'networkgroup' => 'LAN', 'enabled' => true,
            'ip_subnet' => $atual['subnet'], 'dhcpd_enabled' => $atual['dhcp'], 'network_isolation_enabled' => $atual['isolada'],
            'internet_access_enabled' => $atual['internet'],
        ] + ($atual['dhcp'] ? ['dhcpd_start' => $atual['dhcp_inicio'], 'dhcpd_stop' => $atual['dhcp_fim']] : []);

        return $this->concluirVlan($id, $fonte, $ativo, "VLAN {$vlan} \"{$atual['nome']}\" excluída ({$atual['subnet']}).", ['acao' => 'vlan_excluida', 'vlan' => $vlan, 'recriar' => $recriar]);
    }

    /** @param int[] $tagueadas */
    public function salvarVlansPorta(int $id, string $porta, int $nativa, array $tagueadas): array
    {
        [$ativo, $detalhes, $fonte, $erro] = $this->contextoVlan($id);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }

        $dadosPorta = null;
        foreach ($detalhes[$fonte . '_portas'] ?? [] as $p) {
            if ((string)$p['porta'] === $porta) {
                $dadosPorta = $p;
            }
        }
        if ($dadosPorta === null) {
            return ['success' => false, 'message' => 'Porta não encontrada -- atualize a tela.'];
        }
        if ($trava = $this->travaPortaVlan($dadosPorta)) {
            return ['success' => false, 'message' => $trava];
        }

        $tagueadas = array_values(array_diff(array_unique(array_map('intval', $tagueadas)), [$nativa]));
        $conhecidas = array_map('intval', array_column($detalhes[$fonte . '_vlans'] ?? [], 'id'));
        foreach (array_merge([$nativa], $tagueadas) as $v) {
            if (!in_array($v, $conhecidas, true)) {
                return ['success' => false, 'message' => "A VLAN {$v} não existe neste switch."];
            }
        }
        sort($tagueadas);
        $rotulo = 'Porta ' . ($dadosPorta['numero'] ?? $porta) . ": nativa VLAN {$nativa}" . ($tagueadas ? ', tagueadas ' . implode(', ', $tagueadas) : ', sem tagueadas');

        if ($fonte === 'omada') {
            $atuais = array_map('intval', array_column($dadosPorta['vlans'] ?? [], 'id'));
            $resultado = (new OmadaSshService())->definirVlansPorta($ativo['ip'], $porta, $nativa, $tagueadas, $atuais);
            if (!$resultado['success']) {
                return $resultado;
            }
            $antesTag = array_values(array_map('intval', array_column(array_filter($dadosPorta['vlans'] ?? [], fn ($v) => !empty($v['tagueada'])), 'id')));

            return $this->concluirVlan($id, $fonte, $ativo, $rotulo . '.', [
                'acao' => 'porta', 'porta' => $porta, 'nativa' => (int)($dadosPorta['vlan_nativa'] ?? 1), 'tagueadas' => $antesTag,
            ]);
        }

        // ---- UniFi: monta o override da porta (lista inteira é regravada) ----
        $unifi = new UnifiService();
        $legado = null;
        foreach ($unifi->listarDispositivosLegado() as $d) {
            if (($d['_id'] ?? '') === ($detalhes['unifi_dispositivo_id'] ?? '')) {
                $legado = $d;
            }
        }
        if ($legado === null) {
            return ['success' => false, 'message' => 'Switch não encontrado no Controller agora -- tente de novo em instantes.'];
        }

        $redePorVlan = [];
        $padraoId = null;
        foreach ($detalhes['unifi_vlans'] as $v) {
            $redePorVlan[(int)$v['id']] = $v['id_rede'];
            if (!empty($v['padrao'])) {
                $padraoId = $v['id_rede'];
            }
        }
        $outras = array_values(array_diff(array_keys($redePorVlan), [$nativa]));
        sort($outras);

        $overrides = $legado['port_overrides'] ?? [];
        $indice = null;
        foreach ($overrides as $i => $o) {
            if ((int)$o['port_idx'] === (int)$dadosPorta['numero']) {
                $indice = $i;
            }
        }
        $antes = $indice !== null ? $overrides[$indice] : null;

        $novo = $antes ?? ['port_idx' => (int)$dadosPorta['numero']];
        unset($novo['forward'], $novo['native_networkconf_id'], $novo['tagged_vlan_mgmt'], $novo['excluded_networkconf_ids'], $novo['portconf_id']);

        $voltaAoPadrao = $redePorVlan[$nativa] === $padraoId && $tagueadas === $outras;
        if (!$voltaAoPadrao) {
            $novo['native_networkconf_id'] = $redePorVlan[$nativa];
            if (!$tagueadas) {
                $novo['tagged_vlan_mgmt'] = 'block_all';
            } elseif ($tagueadas === $outras) {
                $novo['tagged_vlan_mgmt'] = 'auto';
            } else {
                $novo['tagged_vlan_mgmt'] = 'custom';
                $novo['excluded_networkconf_ids'] = array_values(array_map(fn ($v) => $redePorVlan[$v], array_diff($outras, $tagueadas)));
            }
        }

        // Override sem nada além do número da porta = porta no padrão: some da lista.
        $novaLista = array_values(array_filter($overrides, fn ($o) => (int)$o['port_idx'] !== (int)$dadosPorta['numero']));
        if (count($novo) > 1) {
            $novaLista[] = $novo;
        }

        $resultado = $unifi->gravarOverridesPortas($legado['_id'], $novaLista);
        if (!$resultado['success']) {
            return $resultado;
        }

        return $this->concluirVlan($id, $fonte, $ativo, $rotulo . ($voltaAoPadrao ? ' (padrão: todas as VLANs).' : '.'), [
            'acao' => 'porta', 'porta' => $porta, 'numero' => (int)$dadosPorta['numero'], 'override' => $antes,
        ], true);
    }

    /** Reverte a última alteração de VLAN feita pela tela neste switch. */
    public function desfazerVlan(int $id): array
    {
        [$ativo, $detalhes, $fonte, $erro] = $this->contextoVlan($id);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }
        $d = $detalhes['vlan_desfazer'] ?? null;
        if (!$d) {
            return ['success' => false, 'message' => 'Não há alteração de VLAN para desfazer.'];
        }

        $unifi = new UnifiService();
        $ssh = new OmadaSshService();
        $vlan = (int)($d['vlan'] ?? 0);

        switch ($d['acao']) {
            case 'vlan_criada':
                $resultado = $fonte === 'unifi' ? $unifi->excluirRede($d['id_rede']) : $ssh->excluirVlan($ativo['ip'], $vlan);
                $descricao = "Desfeito: VLAN {$vlan} criada antes foi excluída.";
                break;
            case 'vlan_editada':
                $resultado = $fonte === 'unifi' ? $unifi->editarRede($d['id_rede'], $d['antes']) : $ssh->salvarVlan($ativo['ip'], $vlan, $d['antes']['nome']);
                $descricao = "Desfeito: VLAN {$vlan} voltou à configuração anterior.";
                break;
            case 'vlan_excluida':
                $resultado = $fonte === 'unifi' ? $unifi->criarRede($d['recriar']) : $ssh->salvarVlan($ativo['ip'], $vlan, $d['nome']);
                $descricao = "Desfeito: VLAN {$vlan} recriada" . ($fonte === 'omada' ? ' (sem portas, como estava ao ser excluída).' : '.');
                break;
            case 'porta':
                if ($fonte === 'omada') {
                    $atual = null;
                    foreach ($detalhes['omada_portas'] as $p) {
                        if ($p['porta'] === $d['porta']) {
                            $atual = $p;
                        }
                    }
                    $resultado = $ssh->definirVlansPorta($ativo['ip'], $d['porta'], (int)$d['nativa'], $d['tagueadas'], array_map('intval', array_column($atual['vlans'] ?? [], 'id')));
                } else {
                    $legado = null;
                    foreach ($unifi->listarDispositivosLegado() as $dev) {
                        if (($dev['_id'] ?? '') === ($detalhes['unifi_dispositivo_id'] ?? '')) {
                            $legado = $dev;
                        }
                    }
                    $lista = array_values(array_filter($legado['port_overrides'] ?? [], fn ($o) => (int)$o['port_idx'] !== (int)$d['numero']));
                    if (!empty($d['override'])) {
                        $lista[] = $d['override'];
                    }
                    $resultado = $legado ? $unifi->gravarOverridesPortas($legado['_id'], $lista) : ['success' => false, 'message' => 'Switch não encontrado no Controller.'];
                }
                $descricao = 'Desfeito: VLANs da porta ' . ($d['numero'] ?? $d['porta']) . ' voltaram ao que eram.';
                break;
            default:
                return ['success' => false, 'message' => 'Alteração desconhecida -- nada a desfazer.'];
        }

        if (!$resultado['success']) {
            return $resultado;
        }

        return $this->concluirVlan($id, $fonte, $ativo, $descricao, null, $fonte === 'unifi' && $d['acao'] === 'porta');
    }

    /**
     * Quem está FISICAMENTE plugado em cada porta -- o switch só informa
     * quem passou por ela (todo MAC de origem que entrou), então isto é
     * deduzido e gravado em $porta['fisico'] = ['tipo' => direto|equipamento|
     * indeterminado, 'macs' => [...]]:
     *  1. um aparelho só -> é ele;
     *  2. vizinho anunciado por LLDP (switch UniFi) -> certeza;
     *  3. equipamentos de rede da porta (switch/gateway/AP, pelo UniFi ou pelo
     *     tipo do ativo cadastrado), tirando quem outro switch CADASTRADO vê
     *     em outra porta dele que não a que volta pra cá (= está atrás dele);
     *     fica o mais próximo: switch > gateway > AP;
     *  4. AP que não é UniFi: quem está no Wi-Fi não está no cabo;
     *  5. não deu pra saber -> 'indeterminado' (a tela diz isso, não chuta).
     * Visto no enzilabprime: na porta 25 do AV-SW2 aparecem AV-SW1, gateway,
     * USW-Lite e 5 APs -- só o AV-SW1 está no cabo, o resto está atrás dele.
     */
    private function marcarPontaFisica(array $portas, string $macProprio, ?int $ativoProprioId): array
    {
        $macProprio = OmadaSshService::normalizarMac($macProprio);
        $cache = [];
        $portasDe = function (int $ativoId) use (&$cache, $ativoProprioId): array {
            if ($ativoId === $ativoProprioId) {
                return [];
            }
            if (!array_key_exists($ativoId, $cache)) {
                $outro = $this->repository->buscarPorId($ativoId);
                $d = json_decode($outro['detalhes'] ?? '', true) ?: [];
                $cache[$ativoId] = $d['omada_portas'] ?? ($d['unifi_portas'] ?? []);
            }

            return $cache[$ativoId];
        };

        foreach ($portas as &$porta) {
            $porta['fisico'] = $this->pontaFisica($porta['dispositivos'] ?? [], $macProprio, $portasDe);
        }
        unset($porta);

        return $portas;
    }

    private static function papelRede(array $d): ?string
    {
        $modelo = (string)($d['unifi_dispositivo'] ?? '');
        $tipo = (string)($d['ativo_tipo'] ?? '');

        if ($tipo === 'roteador' || preg_match('/^(UDM|UDR|UXG|USG|UCG|UDW|UX\d|EFG)/i', $modelo)) {
            return 'gateway';
        }
        if ($tipo === 'switch' || preg_match('/^(US|USW|USL|USM|USP|USF|USC)/i', $modelo)) {
            return 'switch';
        }

        return $tipo === 'ponto_acesso' || $modelo !== '' ? 'ap' : null;
    }

    private function pontaFisica(array $ds, string $macProprio, callable $portasDe): array
    {
        if (count($ds) <= 1) {
            return ['tipo' => 'direto', 'macs' => array_column($ds, 'mac')];
        }

        $lldp = array_values(array_filter($ds, fn ($d) => !empty($d['lldp'])));
        if ($lldp) {
            return ['tipo' => 'equipamento', 'macs' => array_column($lldp, 'mac')];
        }

        $candidatos = [];
        foreach ($ds as $d) {
            if ($papel = self::papelRede($d)) {
                $candidatos[] = $d + ['_papel' => $papel];
            }
        }

        if ($candidatos) {
            // Atrás de outro candidato: o switch cadastrado X vê o MAC em uma porta dele
            // que não é a que volta pra este switch.
            $atras = [];
            foreach ($candidatos as $x) {
                $portasX = !empty($x['ativo_id']) ? $portasDe((int)$x['ativo_id']) : [];
                if (!$portasX) {
                    continue;
                }
                $macsPorPorta = [];
                $portaDeVolta = null;
                foreach ($portasX as $px) {
                    $macsPorPorta[$px['porta']] = array_column($px['dispositivos'] ?? [], 'mac');
                    if (in_array($macProprio, $macsPorPorta[$px['porta']], true)) {
                        $portaDeVolta = $px['porta'];
                    }
                }
                foreach ($macsPorPorta as $nomePorta => $macs) {
                    if ($nomePorta !== $portaDeVolta) {
                        foreach ($macs as $m) {
                            $atras[$m] = true;
                        }
                    }
                }
            }
            $candidatos = array_values(array_filter($candidatos, fn ($c) => !isset($atras[$c['mac']])));

            foreach (['switch', 'gateway', 'ap'] as $papel) {
                $nivel = array_values(array_filter($candidatos, fn ($c) => $c['_papel'] === $papel));
                if (!$nivel) {
                    continue;
                }
                // Mesmo equipamento com mais de um MAC (ex.: USW-Lite: MAC principal + interface).
                $equipamentos = array_unique(array_map(fn ($c) => $c['ativo_id'] ?: ($c['hostname'] ?: ($c['ip'] ?: $c['mac'])), $nivel));
                if (count($equipamentos) === 1) {
                    return ['tipo' => 'equipamento', 'macs' => array_column($nivel, 'mac')];
                }
                // Gateway com mais de um MAC na mesma porta é a mesma caixa (uma interface
                // por VLAN/rede) -- visto no enzilabprime: UDR pelo UniFi + o ativo "UCG".
                if ($papel === 'gateway') {
                    $cadastrados = array_values(array_filter($nivel, fn ($c) => !empty($c['ativo_id'])));

                    return ['tipo' => 'equipamento', 'macs' => array_column($cadastrados ?: $nivel, 'mac')];
                }

                return ['tipo' => 'indeterminado', 'macs' => []];
            }
        }

        $noCabo = array_values(array_filter($ds, fn ($d) => !isset($d['wifi'])));
        if (count($noCabo) === 1) {
            return ['tipo' => 'direto', 'macs' => [$noCabo[0]['mac']]];
        }

        return ['tipo' => 'indeterminado', 'macs' => []];
    }

    /**
     * Câmera avulsa do UniFi Protect (G3/G4/G5...) cadastrada como DVR/NVR.
     * Acha a câmera pelo id já vinculado ou, na primeira vez, pelo MAC do
     * cliente da rede com o IP do ativo (a API de integração do Protect não
     * informa IP). Uptime, porta do switch e rede vêm desse mesmo cliente.
     */
    public function coletarProtect(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo || ($ativo['tipo_slug'] ?? '') !== 'dvr_nvr') {
            return ['success' => false, 'message' => 'Ativo não encontrado ou não é do tipo DVR/NVR.'];
        }

        $unifi = new UnifiService();

        if (!$unifi->configurado()) {
            return ['success' => false, 'message' => 'Integração com o UniFi Controller ainda não configurada -- veja Integrações.'];
        }

        $cameras = $unifi->listarCamerasProtect();
        if (!$cameras) {
            return ['success' => false, 'message' => 'Nenhuma câmera encontrada no UniFi Protect (ou o Protect não está disponível neste console).'];
        }

        $detalhes = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $clientes = $unifi->listarClientesLegado();

        $camera = null;
        foreach ($cameras as $c) {
            if (!empty($detalhes['protect_camera_id']) && ($c['id'] ?? '') === $detalhes['protect_camera_id']) {
                $camera = $c;
            }
        }

        $cliente = null;
        foreach ($clientes as $c) {
            if (!empty($ativo['ip']) && ($c['ip'] ?? '') === $ativo['ip']) {
                $cliente = $c;
                break;
            }
        }

        if ($camera === null && $cliente !== null) {
            $macCliente = OmadaSshService::normalizarMac($cliente['mac'] ?? '');
            foreach ($cameras as $c) {
                if (OmadaSshService::normalizarMac($c['mac'] ?? '') === $macCliente) {
                    $camera = $c;
                }
            }
        }

        if ($camera === null) {
            return ['success' => false, 'message' => 'Nenhuma câmera do UniFi Protect com esse IP.'];
        }

        $macCamera = OmadaSshService::normalizarMac($camera['mac'] ?? '');
        if ($cliente === null || OmadaSshService::normalizarMac($cliente['mac'] ?? '') !== $macCamera) {
            $cliente = null;
            foreach ($clientes as $c) {
                if (OmadaSshService::normalizarMac($c['mac'] ?? '') === $macCamera) {
                    $cliente = $c;
                    break;
                }
            }
        }

        $recursos = $camera['featureFlags'] ?? [];
        $info = $unifi->infoProtect();
        $estado = (string)($camera['state'] ?? '');

        $coletado = [
            'protect_camera_id' => (string)$camera['id'],
            'protect_nome' => (string)($camera['name'] ?? ''),
            'protect_modelo' => (string)($camera['type'] ?? ''),
            'protect_status' => $estado === 'CONNECTED' ? 'Conectada' : ($estado === 'DISCONNECTED' ? 'Desconectada' : ucfirst(strtolower($estado))),
            'protect_mac' => $macCamera,
            'protect_nvr' => $info ? trim($info['nvr'] . ($info['nvr_modelo'] !== '' ? " ({$info['nvr_modelo']})" : '')) : '',
            'protect_versao' => $info['versao'] ?? '',
            'protect_microfone' => empty($recursos['hasMic']) ? 'Não tem' : (!empty($camera['isMicEnabled']) ? 'Ligado (volume ' . (int)($camera['micVolume'] ?? 0) . '%)' : 'Desligado'),
            'protect_hdr' => empty($recursos['hasHdr']) ? 'Não suporta' : (($camera['hdrType'] ?? 'off') === 'off' ? 'Desligado' : (string)$camera['hdrType']),
            'protect_modo_video' => (string)($camera['videoMode'] ?? ''),
            'protect_deteccao' => implode(', ', $camera['smartDetectSettings']['objectTypes'] ?? []) ?: 'Nenhuma',
            'protect_led' => !empty($camera['ledSettings']['isEnabled']) ? 'Ligado' : 'Desligado',
            'protect_coletado_em' => date('Y-m-d H:i:s'),
        ];

        if ($cliente !== null) {
            $coletado['protect_conexao'] = trim(($cliente['last_uplink_name'] ?? '') . (isset($cliente['sw_port']) ? ' · porta ' . $cliente['sw_port'] : '')
                . (!empty($cliente['wired_rate_mbps']) ? ' · ' . $cliente['wired_rate_mbps'] . ' Mbps' : ''), ' ·');
            $coletado['protect_rede'] = (string)($cliente['network'] ?? '');
            if (!empty($cliente['uptime'])) {
                $coletado['protect_uptime'] = self::duracaoLegivel((int)$cliente['uptime']);
            }
            // Do ponto de vista do cliente: "rx" é o que a câmera mandou pra rede (o vídeo).
            if (isset($cliente['wired-rx_bytes'])) {
                $coletado['protect_dados_enviados'] = self::tamanhoLegivel((int)$cliente['wired-rx_bytes']);
            }
        }

        $this->repository->atualizarDetalhesApi($id, json_encode(array_merge($detalhes, $coletado), JSON_UNESCAPED_UNICODE));

        AuditService::registrar('Ativos', 'Coleta UniFi Protect', 'Dados da câmera coletados via UniFi Protect para ' . $ativo['codigo_patrimonio'] . '.');

        return ['success' => true, 'message' => 'Dados da câmera coletados via UniFi Protect.'];
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
    private const TIPOS_UNIFI = ['ponto_acesso', 'roteador', 'switch'];

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

        // Câmeras do UniFi Protect -- só as já vinculadas (protect_camera_id),
        // pra não ficar batendo no Protect por todo DVR Intelbras.
        foreach ($this->repository->listarPorTipoSlugComIp('dvr_nvr') as $ativo) {
            $detalhes = json_decode($ativo['detalhes'] ?? '', true) ?: [];
            if (empty($detalhes['protect_camera_id'])) {
                continue;
            }
            $ativos[] = $ativo;
            if ($this->coletarProtect((int)$ativo['id'])['success']) {
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
            return ['success' => false, 'message' => 'Integração TP-Link/Omada ainda não configurada -- veja Integrações.'];
        }

        // Modo combinado: Controller primeiro (status), SSH por cima (portas,
        // MACs, VLANs, CPU...). Basta um dar certo; o outro vira só um aviso.
        $coletado = [];
        $fontes = [];
        $falhas = [];

        $tentativas = [];
        if ($omada->controllerAtivo()) {
            $tentativas['Omada Controller'] = fn () => $this->coletarOmadaController($ativo, $omada);
        }
        if ($omada->sshAtivo()) {
            $tentativas['SSH (direto no switch)'] = fn () => $this->coletarOmadaSsh($ativo);
        }

        foreach ($tentativas as $fonte => $coletar) {
            $resultado = $coletar();
            if ($resultado['success']) {
                $fontes[] = $fonte;
                $coletado = array_merge($coletado, $resultado['dados']);
            } else {
                $falhas[] = $resultado['message'];
            }
        }

        if (!$fontes) {
            return ['success' => false, 'message' => implode(' ', $falhas)];
        }

        $coletado['omada_coleta_via'] = implode(' + ', $fontes);
        if (!$omada->controllerAtivo()) {
            // Modo só SSH: o status/alerta de adoção de uma coleta antiga pelo Controller não vale mais.
            $coletado['omada_controller_status'] = '';
            $coletado['omada_adocao_alerta'] = '';
        }

        $detalhesAtuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $this->repository->atualizarDetalhesApi($id, json_encode(array_merge($detalhesAtuais, $coletado), JSON_UNESCAPED_UNICODE));

        AuditService::registrar('Ativos', 'Coleta Omada', 'Dados coletados via ' . $coletado['omada_coleta_via'] . ' para ' . $ativo['codigo_patrimonio'] . '.');

        return [
            'success' => true,
            'message' => 'Dados coletados com sucesso via ' . $coletado['omada_coleta_via'] . '.'
                . ($falhas ? ' Atenção: ' . implode(' ', $falhas) : ''),
        ];
    }

    /**
     * `status` da listagem de dispositivos da Open API (DeviceInfo) -- tabela
     * oficial da documentação do Controller (/v3/api-docs). Antes o código
     * tratava 2 como "conectado", mas 2 é "Pendente": switch descoberto e
     * NÃO adotado (visto no enzilabprime, aparecia "Online" na ficha).
     */
    private const STATUS_OMADA = [0 => 'Desconectado', 1 => 'Conectado', 2 => 'Pendente', 3 => 'Sem heartbeat', 4 => 'Isolado'];

    /** `detailStatus` -- mesma documentação; dá o motivo exato (ex.: 24 = adoção falhou). */
    private const STATUS_DETALHADO_OMADA = [
        0 => 'Desconectado', 1 => 'Desconectado (migrando)', 10 => 'Provisionando', 11 => 'Configurando',
        12 => 'Atualizando firmware', 13 => 'Reiniciando', 14 => 'Conectado', 15 => 'Conectado (sem fio)',
        16 => 'Conectado (migrando)', 17 => 'Conectado (sem fio, migrando)', 20 => 'Pendente de adoção',
        21 => 'Pendente de adoção (sem fio)', 22 => 'Adotando', 23 => 'Adotando (sem fio)', 24 => 'Adoção falhou',
        25 => 'Adoção falhou (sem fio)', 26 => 'Gerenciado por outro Controller', 27 => 'Gerenciado por outro Controller (sem fio)',
        30 => 'Sem heartbeat', 31 => 'Sem heartbeat (sem fio)', 32 => 'Sem heartbeat (migrando)',
        33 => 'Sem heartbeat (sem fio, migrando)', 40 => 'Isolado', 41 => 'Isolado (migrando)', 50 => 'Configurando slice',
    ];

    /**
     * Open API do Omada Controller -- casa o ativo por IP (o Controller já
     * reporta o IP de gerenciamento, sem o problema de WAN/LAN da UniFi).
     * Só modelo/firmware/status/uptime: a Open API não expõe portas/clientes
     * do switch (confirmado no Controller 6.2 do enzilabprime).
     *
     * @return array{success: bool, message?: string, dados?: array}
     */
    private function coletarOmadaController(array $ativo, OmadaService $omada): array
    {
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

        $status = (int)($dispositivo['status'] ?? -1);
        $detalhe = (int)($dispositivo['detailStatus'] ?? -1);
        $rotulo = self::STATUS_DETALHADO_OMADA[$detalhe] ?? (self::STATUS_OMADA[$status] ?? "Status {$status}");
        // 20-27: descoberto mas não adotado (pendente, adotando, falhou, de outro Controller) --
        // o Controller não aplica nenhuma configuração nele (SSH, conta do dispositivo, VLANs...).
        $naoAdotado = $detalhe >= 20 && $detalhe <= 27;

        return ['success' => true, 'dados' => [
            'omada_model' => $dispositivo['modelName'] ?? ($dispositivo['model'] ?? ''),
            'omada_firmware' => $dispositivo['firmwareVersion'] ?? '',
            // "Online" é o rótulo que a ficha/painel usam pra badge verde -- só quando o Controller diz Conectado.
            'omada_status' => $status === 1 ? 'Online' : $rotulo,
            'omada_controller_status' => $rotulo,
            'omada_adocao_alerta' => $naoAdotado
                ? "Este switch não está adotado no Omada Controller ({$rotulo}) -- o Controller não aplica configuração nele."
                : '',
            // já vem pronto como texto ("42day(s) 18h 52m 21s") -- sem conversão nenhuma.
            'omada_uptime' => $dispositivo['uptime'] ?? '',
        ]];
    }

    /**
     * SSH direto no switch: lê a CLI (só comandos `show`, ver
     * OmadaSshService) -- portas, MACs por porta, VLANs, CPU, serial...
     * Funciona também em switch adotado pelo Controller, desde que o SSH
     * esteja liberado nele (usuário = Conta do Dispositivo do Controller).
     *
     * @return array{success: bool, message?: string, dados?: array}
     */
    private function coletarOmadaSsh(array $ativo): array
    {
        $resultado = (new OmadaSshService())->coletar($ativo['ip']);

        if (!$resultado['success']) {
            return $resultado;
        }

        $d = $resultado['dados'];

        return ['success' => true, 'dados' => [
            'omada_model' => $d['modelo'],
            'omada_firmware' => $d['firmware'],
            // Respondeu ao SSH agora -- diferente do Controller, não existe um "status" pra ler.
            'omada_status' => 'Online',
            'omada_uptime' => $d['uptime'],
            'omada_nome' => $d['nome'],
            'omada_serial' => $d['serial'],
            'omada_mac' => $d['mac'],
            'omada_cpu_pct' => $d['cpu_pct'] !== null ? $d['cpu_pct'] . '%' : '',
            'omada_mem_pct' => $d['mem_pct'] !== null ? $d['mem_pct'] . '%' : '',
            'omada_portas_resumo' => "{$d['portas_ativas']} de {$d['portas_total']}",
            'omada_mac_total' => $d['mac_total'] !== null ? (string)$d['mac_total'] : '',
            'omada_rotas' => $d['rotas'] ?? [],
            'omada_interfaces_ip' => $d['interfaces_ip'] ?? [],
            'omada_portas' => $this->marcarPontaFisica($this->enriquecerDispositivosPortas($d['portas']), (string)$d['mac'], (int)$ativo['id']),
            'omada_vlans' => $d['vlans'],
            'omada_coletado_em' => date('Y-m-d H:i:s'),
        ]];
    }

    /**
     * Periféricos (conversor serial, relógio de ponto...): ping sempre; se
     * houver credencial web e o equipamento for reconhecido (USR IoT), lê
     * também a página de status. Gera alerta na ficha quando o equipamento
     * cai/volta e quando a conexão com o destino configurado (ex.: o ARIIS)
     * cai/volta -- é isso que importa num conversor que leva resultado de
     * equipamento de laboratório pro sistema.
     */
    public function coletarPeriferico(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo || empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Ativo não encontrado ou sem IP cadastrado.'];
        }

        $antes = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $agora = date('d/m/Y H:i');
        $coletado = ['periferico_coletado' => $agora];
        $alertas = [];

        exec('ping -c 2 -W 2 ' . escapeshellarg($ativo['ip']) . ' 2>&1', $saidaPing, $codigoPing);
        $online = $codigoPing === 0;
        $coletado['periferico_status'] = $online ? 'Online' : 'Offline';

        if (($antes['periferico_status'] ?? '') !== '' && $antes['periferico_status'] !== $coletado['periferico_status']) {
            $alertas[] = $online
                ? ['nivel' => 'informacao', 'origem_evento' => 'Periférico', 'mensagem' => 'Voltou a responder ao ping.']
                : ['nivel' => 'erro', 'origem_evento' => 'Periférico', 'mensagem' => 'Parou de responder ao ping.'];
        }

        $mensagem = $online ? 'Online (responde ao ping).' : 'Offline -- não respondeu ao ping.';
        $credencial = $this->credencialWeb($id);
        $usr = new UsrIotService();

        if ($online && $credencial !== null) {
            $resultado = $usr->coletar($ativo['ip'], $credencial['usuario'], $credencial['senha']);

            if ($resultado['success']) {
                $d = $resultado['dados'];
                $p = $d['porta'];

                $coletado += [
                    'periferico_modelo' => 'USR IoT ' . $d['modelo'],
                    'periferico_firmware' => $d['firmware'],
                    'periferico_serial' => $d['serial'],
                    'periferico_mac' => strtolower(str_replace('-', ':', $d['mac'])),
                    'periferico_trafego' => number_format($d['tx_total'], 0, ',', '.') . ' B / ' . number_format($d['rx_total'], 0, ',', '.') . ' B',
                    'periferico_conexoes' => $d['conexoes'],
                    'periferico_tx_total' => $d['tx_total'],
                    'periferico_rx_total' => $d['rx_total'],
                ];

                // Contador subiu desde a última coleta = o equipamento está mandando/recebendo dados.
                $totalAntes = (int)($antes['periferico_tx_total'] ?? -1) + (int)($antes['periferico_rx_total'] ?? 0);
                if ($totalAntes >= 0 && $d['tx_total'] + $d['rx_total'] !== $totalAntes) {
                    $coletado['periferico_ultimo_trafego'] = $agora;
                }

                if ($p) {
                    $coletado['periferico_serial_config'] = $p['baud'] . ' bps, ' . $p['formato'];
                    $coletado['periferico_modo'] = $p['modo'];

                    if ($p['cliente'] && $p['destino_ip'] !== '') {
                        $destino = $p['destino_ip'] . ($p['destino_porta'] ? ':' . $p['destino_porta'] : '');
                        $destinoAtivo = $this->repository->buscarPrimeiroPorIp($p['destino_ip']);
                        $coletado['periferico_destino'] = $destino . ($destinoAtivo ? ' (' . $destinoAtivo['nome'] . ')' : '');

                        $conectado = (bool)array_filter($d['conexoes'], fn ($c) => $c['ip'] === $p['destino_ip']);
                        $coletado['periferico_conexao'] = $conectado ? 'Conectado' : 'Sem conexão';

                        if (($antes['periferico_conexao'] ?? '') !== '' && $antes['periferico_conexao'] !== $coletado['periferico_conexao']) {
                            $alertas[] = $conectado
                                ? ['nivel' => 'informacao', 'origem_evento' => 'Periférico', 'mensagem' => "Conexão com {$destino} restabelecida."]
                                : ['nivel' => 'erro', 'origem_evento' => 'Periférico', 'mensagem' => "Perdeu a conexão com {$destino} -- os dados da serial não estão chegando no destino."];
                        }
                    } elseif ($p['porta_local']) {
                        $coletado['periferico_destino'] = 'Aguarda conexões na porta ' . $p['porta_local'];
                        $coletado['periferico_conexao'] = count($d['conexoes']) . ' conectado(s)';
                    }
                }

                $mensagem = 'Online -- dados lidos da interface web (USR IoT).';
            } else {
                $mensagem = 'Online, mas a coleta web falhou: ' . $resultado['message'];
            }
        } elseif ($online && $usr->pareceUsrIot($ativo['ip'])) {
            $mensagem = 'Online. Parece um conversor USR IoT -- cadastre o login da interface web na ficha pra coletar status e conexão.';
        }

        $this->repository->atualizarDetalhesApi($id, json_encode(array_merge($antes, $coletado), JSON_UNESCAPED_UNICODE));
        $this->repository->inserirAlertas($id, array_map(fn ($a) => $a + ['ocorrido_em' => date('Y-m-d H:i:s')], $alertas));

        return ['success' => true, 'message' => $mensagem];
    }

    public function coletarPerifericosTodos(): array
    {
        $ativos = $this->repository->listarPorTipoSlugComIp('periferico');
        $sucesso = 0;

        foreach ($ativos as $ativo) {
            if ($this->coletarPeriferico((int)$ativo['id'])['success']) {
                $sucesso++;
            }
        }

        return ['total' => count($ativos), 'sucesso' => $sucesso];
    }

    public function nomeJobCronPerifericos(): string
    {
        return self::NOME_JOB_CRON_PERIFERICOS;
    }

    /** @return array{usuario:string, senha:string}|null */
    public function credencialWeb(int $ativoId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT usuario, senha_cifrada FROM ativos_credenciais_web WHERE ativo_id = ?');
        $stmt->execute([$ativoId]);
        $linha = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$linha) {
            return null;
        }

        try {
            return ['usuario' => $linha['usuario'], 'senha' => CryptoService::decriptar($linha['senha_cifrada'])];
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    public function usuarioCredencialWeb(int $ativoId): ?string
    {
        $stmt = Database::connection()->prepare('SELECT usuario FROM ativos_credenciais_web WHERE ativo_id = ?');
        $stmt->execute([$ativoId]);
        $usuario = $stmt->fetchColumn();

        return $usuario === false ? null : (string)$usuario;
    }

    /** Senha em branco mantém a atual. */
    public function salvarCredencialWeb(int $ativoId, string $usuario, string $senha): array
    {
        $ativo = $this->repository->buscarPorId($ativoId);
        $usuario = trim($usuario);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }
        if ($usuario === '') {
            return ['success' => false, 'message' => 'Informe o usuário.'];
        }

        $atual = $this->credencialWeb($ativoId);
        if ($senha === '' && $atual === null) {
            return ['success' => false, 'message' => 'Informe a senha.'];
        }

        $stmt = Database::connection()->prepare('
            INSERT INTO ativos_credenciais_web (ativo_id, usuario, senha_cifrada) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE usuario = VALUES(usuario), senha_cifrada = VALUES(senha_cifrada)
        ');
        $stmt->execute([$ativoId, $usuario, CryptoService::encriptar($senha !== '' ? $senha : $atual['senha'])]);

        AuditService::registrar('Ativos', 'Credencial web', "Login da interface web salvo para {$ativo['codigo_patrimonio']} (usuário \"{$usuario}\").");

        return ['success' => true, 'message' => 'Login salvo.'];
    }

    /**
     * Completa o "quem está em cada porta" do switch: a ARP do próprio
     * switch só conhece quem falou com ele, então o IP que faltar vem da
     * ARP deste servidor (mesma LAN, dado de agora) e, por último, da
     * varredura mais recente do IP Scanner (pode estar velha -- por isso a
     * origem fica gravada). Hostname/fabricante vêm do IP Scanner, e o IP
     * é casado com o cadastro de Ativos pra virar link na ficha.
     */
    private function enriquecerDispositivosPortas(array $portas, ?array $clientesUnifi = null): array
    {
        // Clientes do UniFi Controller (quando a integração existe): sabe IP,
        // nome e VLAN até de quem está em outra VLAN que este servidor não
        // enxerga -- ex.: Wi-Fi de visitantes passando pela porta trunk do AP
        // num switch TP-Link (visto no enzilabprime: 4 MACs "IP desconhecido"
        // eram celulares/PCs no Wi-Fi das VLANs 2, 3 e 4 do AP Recepção).
        if ($clientesUnifi === null) {
            $unifi = new UnifiService();
            $clientesUnifi = $unifi->configurado() ? $unifi->listarClientesLegado() : [];
        }
        $unifiPorMac = [];
        foreach ($clientesUnifi as $c) {
            $unifiPorMac[OmadaSshService::normalizarMac($c['mac'] ?? '')] = $c;
        }

        // Vizinhos IPv6 deste servidor: aparelho que não tem IPv4 em lugar nenhum
        // às vezes fala IPv6 -- e a marca "router" diz que ele anuncia rota IPv6
        // na rede (visto no Maurílio: um aparelho com MAC 00:00:00:a1:00:03 e um
        // modem de operadora com IPv6 público 2804:14c:... fazendo isso na LAN).
        $ipv6Servidor = [];
        foreach (explode("\n", (string)@shell_exec('ip -6 neigh show 2>/dev/null')) as $linha) {
            if (!preg_match('/^(\S+)\s.*lladdr\s+(\S+)(.*)$/', $linha, $m)) {
                continue;
            }
            $macV6 = OmadaSshService::normalizarMac($m[2]);
            $global = !str_starts_with(strtolower($m[1]), 'fe80');
            $atual = $ipv6Servidor[$macV6] ?? null;
            // Prefere o endereço global ao link-local (fe80::), que só vale no próprio segmento.
            if ($atual === null || ($global && !$atual['global'])) {
                $ipv6Servidor[$macV6] = ['ip' => $m[1], 'global' => $global, 'roteador' => ($atual['roteador'] ?? false) || str_contains($m[3], 'router')];
            } elseif (str_contains($m[3], 'router')) {
                $ipv6Servidor[$macV6]['roteador'] = true;
            }
        }
        // Equipamentos UniFi (switch, gateway, AP) não são "clientes": vêm da lista
        // de dispositivos, pelo MAC principal ou de uma interface (ethernet_table).
        $unifiDispPorMac = [];
        $ipGatewayPorVlan = [];
        if ($clientesUnifi !== [] || (new UnifiService())->configurado()) {
            foreach ((new UnifiService())->listarDispositivosLegado() as $dev) {
                foreach (array_merge([$dev['mac'] ?? ''], array_column($dev['ethernet_table'] ?? [], 'mac')) as $m) {
                    $unifiDispPorMac[OmadaSshService::normalizarMac($m)] = $dev;
                }
            }
            // IP do gateway em cada rede local ("192.168.0.1/24" -> 192.168.0.1), pra
            // mostrar o gateway pelo IP da LAN em vez do IP de WAN que o UniFi informa.
            foreach ((new UnifiService())->listarRedesConfiguradas() as $rede) {
                if (!empty($rede['ip_subnet']) && in_array($rede['purpose'] ?? '', ['corporate', 'guest'], true)) {
                    $ipGatewayPorVlan[empty($rede['vlan_enabled']) ? 1 : (int)($rede['vlan'] ?? 0)] = explode('/', $rede['ip_subnet'])[0];
                }
            }
        }

        $arpServidor = [];
        foreach (explode("\n", (string)@shell_exec('ip neigh show 2>/dev/null')) as $linha) {
            if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})\s.*lladdr\s+(\S+)/', $linha, $m)) {
                $arpServidor[OmadaSshService::normalizarMac($m[2])] = $m[1];
            }
        }

        $scanner = [];
        $scannerEm = null;
        $linhaScan = Database::connection()->query('SELECT executado_em, hosts FROM ip_scanner_execucoes ORDER BY executado_em DESC LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        if ($linhaScan) {
            $scannerEm = $linhaScan['executado_em'];
            foreach (json_decode($linhaScan['hosts'], true) ?: [] as $h) {
                $mac = OmadaSshService::normalizarMac($h['mac'] ?? '');
                if ($mac !== '') {
                    $scanner[$mac] = $h;
                }
            }
        }

        $ativosPorIp = [];
        foreach (Database::connection()->query("SELECT a.id, a.codigo_patrimonio, a.nome, a.ip, t.slug AS tipo_slug FROM ativos a LEFT JOIN ativos_tipos t ON t.id = a.tipo_id WHERE a.ip IS NOT NULL AND a.ip <> ''") as $a) {
            $ativosPorIp[$a['ip']] = $a;
        }

        foreach ($portas as &$porta) {
            foreach ($porta['dispositivos'] as &$disp) {
                $mac = $disp['mac'];
                $doScan = $scanner[$mac] ?? null;

                if ($disp['ip'] === '' && isset($arpServidor[$mac])) {
                    $disp['ip'] = $arpServidor[$mac];
                    $disp['ip_origem'] = 'servidor';
                }
                $doUnifi = $unifiPorMac[$mac] ?? null;
                if ($doUnifi) {
                    $ipUnifi = (string)($doUnifi['ip'] ?? ($doUnifi['last_ip'] ?? ''));
                    if ($disp['ip'] === '' && $ipUnifi !== '') {
                        $disp['ip'] = $ipUnifi;
                        $disp['ip_origem'] = 'unifi';
                    }
                    if (($disp['hostname'] ?? '') === '') {
                        $disp['hostname'] = (string)($doUnifi['name'] ?? ($doUnifi['hostname'] ?? ''));
                    }
                    if (($disp['fabricante'] ?? '') === '') {
                        $disp['fabricante'] = (string)($doUnifi['oui'] ?? '');
                    }
                    if (!isset($disp['vlan']) && isset($doUnifi['vlan'])) {
                        $disp['vlan'] = (int)$doUnifi['vlan'];
                    }
                    if (empty($doUnifi['is_wired'])) {
                        $disp['wifi'] = (string)($doUnifi['essid'] ?? '');
                        $disp['wifi_ap'] = (string)($doUnifi['last_uplink_name'] ?? '');
                    }
                }
                $devUnifi = $unifiDispPorMac[$mac] ?? null;
                if ($devUnifi) {
                    if (($disp['hostname'] ?? '') === '') {
                        $disp['hostname'] = (string)($devUnifi['name'] ?? '');
                    }
                    $disp['fabricante'] = ($disp['fabricante'] ?? '') !== '' ? $disp['fabricante'] : 'Ubiquiti';
                    // Marca como equipamento UniFi -- também trava a troca de VLAN dessa porta.
                    $disp['unifi_dispositivo'] = ($disp['unifi_dispositivo'] ?? '') !== '' ? $disp['unifi_dispositivo'] : (string)($devUnifi['model'] ?? 'UniFi');
                    // Gateway reporta o IP da WAN em 'ip' -- não serve pra identificar na LAN:
                    // usa o IP dele na rede (VLAN) em que o switch o viu.
                    $ehGateway = isset($devUnifi['wan1']) || isset($devUnifi['wan2']);
                    if ($disp['ip'] === '' && $ehGateway && isset($ipGatewayPorVlan[$disp['vlan'] ?? 1])) {
                        $disp['ip'] = $ipGatewayPorVlan[$disp['vlan'] ?? 1];
                        $disp['ip_origem'] = 'unifi';
                    }
                    if ($disp['ip'] === '' && !$ehGateway && !empty($devUnifi['ip'])) {
                        $disp['ip'] = (string)$devUnifi['ip'];
                        $disp['ip_origem'] = 'unifi';
                    }
                }
                if ($disp['ip'] === '' && $doScan && !empty($doScan['ip'])) {
                    $disp['ip'] = $doScan['ip'];
                    $disp['ip_origem'] = 'scanner';
                }
                if (isset($ipv6Servidor[$mac])) {
                    $disp['ipv6'] = $ipv6Servidor[$mac]['ip'];
                    $disp['roteador_ipv6'] = $ipv6Servidor[$mac]['roteador'];
                }

                // Controller UniFi já traz nome/fabricante -- o IP Scanner só completa o que faltar.
                $disp['hostname'] = ($disp['hostname'] ?? '') !== '' ? $disp['hostname'] : ($doScan['hostname'] ?? '');
                $disp['fabricante'] = ($disp['fabricante'] ?? '') !== '' ? $disp['fabricante'] : ($doScan['vendor'] ?? '');
                $disp['scanner_em'] = $disp['ip_origem'] === 'scanner' ? $scannerEm : null;

                $ativo = $disp['ip'] !== '' ? ($ativosPorIp[$disp['ip']] ?? null) : null;
                $disp['ativo_id'] = $ativo ? (int)$ativo['id'] : null;
                $disp['ativo_nome'] = $ativo ? $ativo['codigo_patrimonio'] . ' - ' . $ativo['nome'] : '';
                $disp['ativo_tipo'] = $ativo['tipo_slug'] ?? null;
                // Nome anunciado por LLDP só como último recurso: o TP-Link tira os acentos ("Clnica").
                if (($disp['hostname'] ?? '') === '' && !empty($disp['lldp_nome'])) {
                    $disp['hostname'] = $disp['lldp_nome'];
                }
            }
            unset($disp);
        }
        unset($porta);

        return $portas;
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

    /**
     * Lê + modifica + grava `detalhes` sob lock de linha (SELECT ... FOR
     * UPDATE), pra evitar corrida quando duas ações na mesma ficha (ex:
     * marcar "em uso" em vários canais rápido, ou uma coleta periódica
     * caindo no meio de um clique) chegam quase juntas -- sem o lock, cada
     * uma lê o "antes" da outra e a segunda grava por cima, perdendo a
     * primeira mudança silenciosamente (foi exatamente esse o bug relatado:
     * várias chaves marcadas em sequência, só a última ficava salva).
     *
     * $mutador recebe o array de detalhes já decodificado e devolve
     * ['ok' => bool, 'detalhes' => array (se ok), 'message' => string|null].
     *
     * @return array{success:bool, message:string, codigo_patrimonio?:string}
     */
    private function alterarDetalhesComLock(int $id, callable $mutador): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT codigo_patrimonio, detalhes FROM ativos WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $ativo = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$ativo) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Ativo não encontrado.'];
            }

            $detalhesAtuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
            $resultado = $mutador($detalhesAtuais);

            if (empty($resultado['ok'])) {
                $pdo->rollBack();
                return ['success' => false, 'message' => $resultado['message'] ?? 'Falha ao salvar.'];
            }

            $pdo->prepare('UPDATE ativos SET detalhes = ? WHERE id = ?')
                ->execute([json_encode($resultado['detalhes'], JSON_UNESCAPED_UNICODE), $id]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao salvar: ' . $e->getMessage()];
        }

        return ['success' => true, 'message' => $resultado['message'] ?? 'Salvo.', 'codigo_patrimonio' => $ativo['codigo_patrimonio']];
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

        // Chave de "detecção de câmera tampada" é por Ativo (cada DVR fica
        // num ambiente diferente -- iluminação/contraste que causam falso
        // positivo num não necessariamente se repetem no outro), não global
        // nem do equipamento em si.
        $detalhesExistentes = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $deteccaoTampadaAtiva = (bool)($detalhesExistentes['dvr_deteccao_tampada_ativa'] ?? true);

        $resultado = $dvr->coletar($ativo['ip'], $deteccaoTampadaAtiva);

        if (!$resultado['success']) {
            return $resultado;
        }

        // Usuários ativos agora e eventos recentes de conta (login/logoff/
        // possível falha) -- mesma cadência da coleta principal (mais 2-3
        // chamadas HTTP curtas na LAN), dão visibilidade de segurança que a
        // tela não tinha antes.
        $usuariosAtivos = $dvr->buscarUsuariosAtivos($ativo['ip']);
        $eventosConta = $dvr->buscarEventosConta($ativo['ip']);

        $bloqueado = $this->alterarDetalhesComLock($id, function (array $detalhesAtuais) use ($ativo, $resultado, $usuariosAtivos, $eventosConta) {
            $hdProblemaAtual = $resultado['hd_problema'] ?? null;
            $hdChamadoId = $detalhesAtuais['dvr_hd_chamado_aberto_id'] ?? null;

            if ($hdProblemaAtual !== null && !$this->chamadoDvrAindaAberto($hdChamadoId)) {
                $hdChamadoId = $this->abrirChamadoHdProblemaDvr($ativo, $hdProblemaAtual);
            }

            // Só abre chamado pra evento com timestamp MAIOR que o último já
            // verificado -- o log do DVR mantém histórico (não é "desde a
            // última coleta"), então sem essa marca o mesmo evento antigo
            // reabriria alerta pra sempre, a cada ~30 min. (Não existe RecNo
            // nesse firmware -- ver o comentário em
            // IntelbrasDvrService::buscarEventosConta().)
            //
            // Na PRIMEIRA coleta desse Ativo (chave ainda não existe em
            // detalhes) só estabelece a marca -- não abre chamado pra
            // histórico de dias atrás que o log já tinha antes da gente
            // começar a olhar; só a partir da segunda coleta um evento
            // suspeito de verdade novo dispara o alerta.
            $primeiraColetaDeConta = !array_key_exists('dvr_ultimo_ts_conta_verificado', $detalhesAtuais);
            $ultimoTsVisto = (int)($detalhesAtuais['dvr_ultimo_ts_conta_verificado'] ?? 0);
            $maiorTs = $ultimoTsVisto;
            $novosSuspeitos = [];
            if ($eventosConta['success']) {
                foreach ($eventosConta['eventos'] as $evento) {
                    if ($evento['ts'] > $maiorTs) {
                        $maiorTs = $evento['ts'];
                    }
                    if (!$primeiraColetaDeConta && $evento['suspeito'] && $evento['ts'] > $ultimoTsVisto) {
                        $novosSuspeitos[] = $evento;
                    }
                }
            }

            $segurancaChamadoId = $detalhesAtuais['dvr_seguranca_chamado_aberto_id'] ?? null;
            if (!empty($novosSuspeitos) && !$this->chamadoDvrAindaAberto($segurancaChamadoId)) {
                $segurancaChamadoId = $this->abrirChamadoSegurancaDvr($ativo, $novosSuspeitos);
            }

            $coletado = [
                'dvr_modelo' => $resultado['modelo'] ?? '',
                'dvr_serial' => $resultado['serial'] ?? '',
                'dvr_firmware' => $resultado['firmware'] ?? '',
                'dvr_hardware' => $resultado['hardware'] ?? '',
                'dvr_nome_dispositivo' => $resultado['nome_dispositivo'] ?? '',
                'dvr_status_disco' => $resultado['status_disco'] ?? '',
                'dvr_disco_total_gb' => $resultado['disco_total_gb'] ?? null,
                'dvr_disco_usado_gb' => $resultado['disco_usado_gb'] ?? null,
                'dvr_hd_problema' => $hdProblemaAtual,
                'dvr_hd_chamado_aberto_id' => $hdChamadoId,
                'dvr_canais' => $this->mesclarCanaisDvr($ativo, $detalhesAtuais['dvr_canais'] ?? [], $resultado['canais'] ?? [], $hdProblemaAtual !== null),
                'dvr_usuarios_ativos' => $usuariosAtivos['success'] ? $usuariosAtivos['usuarios'] : ($detalhesAtuais['dvr_usuarios_ativos'] ?? []),
                'dvr_eventos_conta_recentes' => $eventosConta['success'] ? array_slice($eventosConta['eventos'], 0, 15) : ($detalhesAtuais['dvr_eventos_conta_recentes'] ?? []),
                'dvr_ultimo_ts_conta_verificado' => $maiorTs,
                'dvr_seguranca_chamado_aberto_id' => $segurancaChamadoId,
                'dvr_exposicao' => $resultado['exposicao'] ?? ($detalhesAtuais['dvr_exposicao'] ?? []),
            ];

            // RPC2 (firmware que tem): sem resposta nesta coleta, mantém o último
            // retrato em vez de apagar -- a data diz de quando é.
            $rpc2 = $resultado['rpc2'] ?? null;
            if ($rpc2 !== null) {
                $coletado['dvr_rpc2'] = true;
                $coletado['dvr_rpc2_coletado_em'] = date('Y-m-d H:i:s');
                $coletado['dvr_sem_hd'] = $rpc2['sem_hd'];
                $coletado['dvr_discos'] = $rpc2['discos'];
                $coletado['dvr_cameras_ip'] = $rpc2['cameras'];
                $coletado['dvr_poe'] = $rpc2['poe'] ?? null;
                $coletado['dvr_verificacao_seguranca'] = $rpc2['verificacao'];
            }

            return ['ok' => true, 'detalhes' => array_merge($detalhesAtuais, $coletado)];
        });

        if (!$bloqueado['success']) {
            return $bloqueado;
        }

        AuditService::registrar('Ativos', 'Coleta DVR/NVR Intelbras', 'Dados coletados via API do DVR/NVR para ' . $ativo['codigo_patrimonio'] . '.');

        return ['success' => true, 'message' => 'Dados coletados com sucesso via API do DVR/NVR.'];
    }

    /**
     * Junta os canais recém-coletados com o estado que já tínhamos guardado
     * (a API do DVR não sabe de "em uso" nem de qual chamado já está aberto
     * pra cada canal -- isso é só nosso, precisa ser preservado entre
     * coletas). Na mesma passada, abre um chamado automático pra todo canal
     * "em uso" que estiver sem sinal e ainda não tiver um chamado em aberto
     * -- de propósito NÃO exige que ele "tivesse sinal antes": um canal que
     * já estava sem sinal desde antes da coleta periódica existir também
     * precisa ser avisado, não só quem "acabou de cair" enquanto estávamos
     * olhando. O que evita duplicar a cada 30 min é só o chamado já aberto
     * (chamadoDvrAindaAberto()) -- assim que ele for fechado, uma próxima
     * coleta com o canal ainda/de novo sem sinal abre outro.
     *
     * "Tampada" (VideoBlind) NÃO abre chamado automático -- testado ao vivo
     * contra os 3 DVRs reais e confirmado com o usuário canal por canal
     * comparando com a imagem de verdade (snapshot): o próprio algoritmo de
     * detecção do DVR (BlindDetect, sensibilidade "Level" default 3 em todo
     * canal) dispara falso positivo com frequência em cena escura/de baixo
     * contraste (chão liso à noite, parede escura) mesmo sem nada
     * cobrindo a lente de verdade -- de 5 canais checados nos 3 DVRs, só 2
     * eram problema real. Fica só como sinalização (campo 'tampada' exposto
     * na aba "Canais") pra o operador conferir o snapshot e decidir, igual
     * ao processo manual que já vinha sendo feito.
     */
    private function mesclarCanaisDvr(array $ativo, array $canaisAnteriores, array $canaisNovos, bool $hdComProblema = false): array
    {
        $porNumero = [];
        foreach ($canaisAnteriores as $c) {
            $porNumero[(int)($c['numero'] ?? 0)] = $c;
        }

        $mesclados = [];
        foreach ($canaisNovos as $canal) {
            $numero = (int)($canal['numero'] ?? 0);
            $anterior = $porNumero[$numero] ?? null;

            $canal['em_uso'] = $anterior['em_uso'] ?? true;
            $canal['chamado_aberto_id'] = $anterior['chamado_aberto_id'] ?? null;

            // Chave de "detecção de tampada" por canal (além da chave por
            // DVR inteiro) -- alguns canais continuam disparando falso
            // positivo mesmo no nível de sensibilidade mais baixo (1), e o
            // operador pode preferir desligar só aquele canal problemático
            // sem perder a detecção nos outros.
            $canal['deteccao_tampada_canal_ativa'] = $anterior['deteccao_tampada_canal_ativa'] ?? true;
            $canal['tampada'] = $canal['deteccao_tampada_canal_ativa'] && ($canal['tampada'] ?? false);
            $canal['chamado_gravacao_parada_id'] = $anterior['chamado_gravacao_parada_id'] ?? null;
            $canal['chamado_sem_gravar_id'] = $anterior['chamado_sem_gravar_id'] ?? null;

            $temSinalAgora = (bool)($canal['com_sinal'] ?? true);

            if ($canal['em_uso'] && !$temSinalAgora && !$this->chamadoDvrAindaAberto($canal['chamado_aberto_id'])) {
                $canal['chamado_aberto_id'] = $this->abrirChamadoCanalDvrSemSinal($ativo, $numero, $canal['nome'] ?? "Canal {$numero}");
            }

            // Canal configurado pra NUNCA gravar (RecordMode "parado") --
            // diferente de "sem sinal", ninguém percebe isso só olhando a
            // imagem ao vivo (a câmera aparece normal na tela), por isso
            // merece alerta próprio. Ver o comentário em
            // IntelbrasDvrService::coletar() sobre por que RecordMode é o
            // substituto usado (a API de status de gravação em tempo real
            // não existe nesse firmware).
            if ($canal['em_uso'] && ($canal['modo_gravacao'] ?? null) === 'parado' && !$this->chamadoDvrAindaAberto($canal['chamado_gravacao_parada_id'])) {
                $canal['chamado_gravacao_parada_id'] = $this->abrirChamadoGravacaoParadaDvr($ativo, $numero, $canal['nome'] ?? "Canal {$numero}");
            }

            // Gravando de verdade (RPC2): canal em uso, com sinal, configurado pra
            // gravar e mesmo assim parado. Sem HD/HD falhando já tem o chamado do
            // HD -- não abre um por canal repetindo a mesma causa.
            if ($canal['em_uso'] && $temSinalAgora && ($canal['gravando'] ?? null) === false && !$hdComProblema
                && ($canal['modo_gravacao'] ?? null) !== 'parado' && !$this->chamadoDvrAindaAberto($canal['chamado_sem_gravar_id'])) {
                $canal['chamado_sem_gravar_id'] = $this->abrirChamadoCanalSemGravarDvr($ativo, $numero, $canal['nome'] ?? "Canal {$numero}");
            }

            $mesclados[] = $canal;
        }

        return $mesclados;
    }

    private function chamadoDvrAindaAberto(?int $chamadoId): bool
    {
        if ($chamadoId === null) {
            return false;
        }

        $chamado = (new ChamadoService())->buscar($chamadoId);

        return $chamado !== null && !in_array($chamado['status'], ['resolvido', 'fechado'], true);
    }

    /** @return int|null id do chamado aberto, ou null se não conseguiu abrir (categoria "DVR/NVR" ainda não existe, etc.) */
    private function abrirChamadoCanalDvrSemSinal(array $ativo, int $numeroCanal, string $nomeCanal): ?int
    {
        $categoriaId = $this->categoriaChamadoDvrNvrId();

        if ($categoriaId === null) {
            return null;
        }

        $resultado = (new ChamadoService())->abrir([
            'titulo' => "{$ativo['codigo_patrimonio']} -- Canal {$numeroCanal} ({$nomeCanal}) sem sinal",
            'descricao' => "Detecção automática: o canal {$numeroCanal} (\"{$nomeCanal}\") do DVR/NVR {$ativo['codigo_patrimonio']} ({$ativo['nome']}, IP {$ativo['ip']}) está \"Sem sinal\" e marcado como \"Em uso\".\n\n"
                . "Se a câmera desse canal realmente não existe/não está em uso, abra a ficha do ativo, aba \"Canais\", e desmarque \"Em uso\" pra esse canal -- assim ele para de gerar chamado automático. Se for uma falha de verdade, resolva e feche este chamado normalmente; se voltar a cair depois, um novo chamado é aberto na próxima detecção.",
            'categoria_id' => $categoriaId,
            'unidade_id' => $ativo['unidade_id'],
            'ativo_id' => $ativo['id'],
            'prioridade' => 'alta',
            'solicitante_nome' => 'RD.Intranet - Robô',
            'solicitante_email' => 'robo@rd.intranet',
        ], 'sistema');

        return $resultado['success'] ? (int)$resultado['id'] : null;
    }

    /** @return int|null id do chamado aberto, ou null se não conseguiu abrir */
    private function abrirChamadoHdProblemaDvr(array $ativo, string $problema): ?int
    {
        $categoriaId = $this->categoriaChamadoDvrNvrId();

        if ($categoriaId === null) {
            return null;
        }

        $resultado = (new ChamadoService())->abrir([
            'titulo' => "{$ativo['codigo_patrimonio']} -- Problema no HD: {$problema}",
            'descricao' => "Detecção automática: o DVR/NVR {$ativo['codigo_patrimonio']} ({$ativo['nome']}, IP {$ativo['ip']}) reportou \"{$problema}\" via API. Isso normalmente significa perda de gravação -- verifique o disco físico o quanto antes.",
            'categoria_id' => $categoriaId,
            'unidade_id' => $ativo['unidade_id'],
            'ativo_id' => $ativo['id'],
            'prioridade' => 'urgente',
            'solicitante_nome' => 'RD.Intranet - Robô',
            'solicitante_email' => 'robo@rd.intranet',
        ], 'sistema');

        return $resultado['success'] ? (int)$resultado['id'] : null;
    }

    /** @return int|null id do chamado aberto, ou null se não conseguiu abrir */
    private function abrirChamadoGravacaoParadaDvr(array $ativo, int $numeroCanal, string $nomeCanal): ?int
    {
        $categoriaId = $this->categoriaChamadoDvrNvrId();

        if ($categoriaId === null) {
            return null;
        }

        $resultado = (new ChamadoService())->abrir([
            'titulo' => "{$ativo['codigo_patrimonio']} -- Canal {$numeroCanal} ({$nomeCanal}) configurado pra NÃO gravar",
            'descricao' => "Detecção automática: o canal {$numeroCanal} (\"{$nomeCanal}\") do DVR/NVR {$ativo['codigo_patrimonio']} ({$ativo['nome']}, IP {$ativo['ip']}) está com o modo de gravação configurado como \"Parado\" -- mesmo com sinal de vídeo normal na tela, nada está sendo gravado nesse canal.\n\n"
                . "Verifique se isso foi intencional; se não foi, ajuste o modo de gravação de volta pra \"Automático\" ou \"Manual\" na configuração do DVR.",
            'categoria_id' => $categoriaId,
            'unidade_id' => $ativo['unidade_id'],
            'ativo_id' => $ativo['id'],
            'prioridade' => 'alta',
            'solicitante_nome' => 'RD.Intranet - Robô',
            'solicitante_email' => 'robo@rd.intranet',
        ], 'sistema');

        return $resultado['success'] ? (int)$resultado['id'] : null;
    }

    /** @return int|null id do chamado aberto, ou null se não conseguiu abrir */
    private function abrirChamadoCanalSemGravarDvr(array $ativo, int $numeroCanal, string $nomeCanal): ?int
    {
        $categoriaId = $this->categoriaChamadoDvrNvrId();

        if ($categoriaId === null) {
            return null;
        }

        $resultado = (new ChamadoService())->abrir([
            'titulo' => "{$ativo['codigo_patrimonio']} -- Canal {$numeroCanal} ({$nomeCanal}) não está gravando",
            'descricao' => "Detecção automática: o canal {$numeroCanal} (\"{$nomeCanal}\") do DVR/NVR {$ativo['codigo_patrimonio']} ({$ativo['nome']}, IP {$ativo['ip']}) tem imagem, está configurado pra gravar, mas o próprio equipamento informa que NÃO está gravando agora.

"
                . "Confira a agenda de gravação e o HD do equipamento. Se a câmera desse canal não precisa ser gravada, desmarque \"Em uso\" na aba \"Canais\" do ativo.",
            'categoria_id' => $categoriaId,
            'unidade_id' => $ativo['unidade_id'],
            'ativo_id' => $ativo['id'],
            'prioridade' => 'alta',
            'solicitante_nome' => 'RD.Intranet - Robô',
            'solicitante_email' => 'robo@rd.intranet',
        ], 'sistema');

        return $resultado['success'] ? (int)$resultado['id'] : null;
    }

    /** @return int|null id do chamado aberto, ou null se não conseguiu abrir */
    private function abrirChamadoSegurancaDvr(array $ativo, array $eventosSuspeitos): ?int
    {
        $categoriaId = $this->categoriaChamadoDvrNvrId();

        if ($categoriaId === null) {
            return null;
        }

        $linhas = array_map(
            static fn (array $e) => "- {$e['data']} -- usuário \"{$e['usuario']}\": {$e['tipo']}",
            $eventosSuspeitos
        );

        $resultado = (new ChamadoService())->abrir([
            'titulo' => "{$ativo['codigo_patrimonio']} -- Possível tentativa de acesso indevida",
            'descricao' => "Detecção automática: o log de contas do DVR/NVR {$ativo['codigo_patrimonio']} ({$ativo['nome']}, IP {$ativo['ip']}) registrou evento(s) que podem indicar tentativa de login mal-sucedida:\n\n"
                . implode("\n", $linhas)
                . "\n\nConfira na aba \"Segurança\" do ativo -- pode ser uma tentativa de acesso indevido, ou só um erro de digitação de alguém autorizado.",
            'categoria_id' => $categoriaId,
            'unidade_id' => $ativo['unidade_id'],
            'ativo_id' => $ativo['id'],
            'prioridade' => 'urgente',
            'solicitante_nome' => 'RD.Intranet - Robô',
            'solicitante_email' => 'robo@rd.intranet',
        ], 'sistema');

        return $resultado['success'] ? (int)$resultado['id'] : null;
    }

    private function categoriaChamadoDvrNvrId(): ?int
    {
        $stmt = Database::connection()->prepare("SELECT id FROM chamados_categorias WHERE nome = 'DVR/NVR' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();

        return $id !== false ? (int)$id : null;
    }

    /** Liga/desliga o monitoramento automático (chamado por sinal perdido) de um canal específico -- não mexe em mais nada do ativo. */
    public function definirCanalEmUsoDvr(int $id, int $canal, bool $emUso): array
    {
        $mensagemSucesso = $emUso
            ? 'Canal marcado como em uso -- monitoramento automático ativo.'
            : 'Canal marcado como fora de uso -- não vai mais abrir chamado automático.';

        $resultado = $this->alterarDetalhesComLock($id, function (array $detalhes) use ($canal, $emUso, $mensagemSucesso) {
            // Precisa passar por uma variável de verdade antes do foreach por
            // referência -- "foreach ($x['y'] ?? [] as &$c)" NÃO propaga a
            // mutação de volta pra $x['y'] (o "??" devolve uma cópia solta,
            // não uma referência ao array original), mesmo quando $x['y']
            // já existe e a chave nunca cai no fallback. Foi esse o bug real
            // por trás de "a chave não salva" -- não era corrida.
            $canais = $detalhes['dvr_canais'] ?? [];
            $encontrado = false;

            foreach ($canais as &$c) {
                if ((int)($c['numero'] ?? 0) === $canal) {
                    $c['em_uso'] = $emUso;
                    $encontrado = true;
                }
            }
            unset($c);

            if (!$encontrado) {
                return ['ok' => false, 'message' => 'Canal não encontrado.'];
            }

            $detalhes['dvr_canais'] = $canais;

            return ['ok' => true, 'detalhes' => $detalhes, 'message' => $mensagemSucesso];
        });

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'DVR/NVR - Canal em uso', "{$resultado['codigo_patrimonio']}: canal {$canal} marcado como " . ($emUso ? 'em uso' : 'fora de uso') . '.');
        }

        return $resultado;
    }

    /** Liga/desliga a detecção de "câmera tampada" (VideoBlind) só nesse canal -- pra quando um canal específico continua disparando falso positivo mesmo no nível de sensibilidade mais baixo. */
    public function definirDeteccaoTampadaCanalDvr(int $id, int $canal, bool $ativoFlag): array
    {
        $mensagemSucesso = $ativoFlag
            ? 'Detecção de câmera tampada ativada pra esse canal.'
            : 'Detecção de câmera tampada desativada pra esse canal -- badge "Tampada" não aparece mais nele.';

        $resultado = $this->alterarDetalhesComLock($id, function (array $detalhes) use ($canal, $ativoFlag, $mensagemSucesso) {
            // Mesmo cuidado de definirCanalEmUsoDvr() -- "foreach ($x['y'] ?? [] as &$c)" não propaga a mutação de volta.
            $canais = $detalhes['dvr_canais'] ?? [];
            $encontrado = false;

            foreach ($canais as &$c) {
                if ((int)($c['numero'] ?? 0) === $canal) {
                    $c['deteccao_tampada_canal_ativa'] = $ativoFlag;
                    if (!$ativoFlag) {
                        $c['tampada'] = false;
                    }
                    $encontrado = true;
                }
            }
            unset($c);

            if (!$encontrado) {
                return ['ok' => false, 'message' => 'Canal não encontrado.'];
            }

            $detalhes['dvr_canais'] = $canais;

            return ['ok' => true, 'detalhes' => $detalhes, 'message' => $mensagemSucesso];
        });

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'DVR/NVR - Detecção de tampada por canal', "{$resultado['codigo_patrimonio']}: canal {$canal} -- detecção de tampada " . ($ativoFlag ? 'ativada' : 'desativada') . '.');
        }

        return $resultado;
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

    /** @return array{success:bool, message?:string, imagem_base64?:string, content_type?:string} */
    public function snapshotCanalDvr(int $id, int $canal): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo || empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Ativo não encontrado ou sem IP cadastrado.'];
        }

        $resultado = (new IntelbrasDvrService())->snapshot($ativo['ip'], $canal);

        if (!$resultado['success']) {
            return $resultado;
        }

        return [
            'success' => true,
            'imagem_base64' => base64_encode($resultado['imagem']),
            'content_type' => $resultado['content_type'],
        ];
    }

    /**
     * "Imagem de referência" = a última foto que alguém CONFIRMOU que
     * mostrava a câmera funcionando -- diferente do snapshot ao vivo
     * (snapshotCanalDvr()), que mostra o que está na tela agora mesmo
     * (podendo estar preto/quebrado se a câmera caiu). Serve pra saber "como
     * ela deveria estar" quando o canal está sem sinal e ninguém lembra qual
     * câmera é aquela ou se ela já existiu de verdade. Guardada como arquivo
     * (mesmo padrão de storage/ do ChamadoAnexoService), não em base64 no
     * JSON de detalhes -- evita inchar a coluna com até 32 imagens por DVR.
     * NUNCA é sobrescrita sozinha por uma coleta periódica -- só por ação
     * explícita do usuário (botão + confirmação), de propósito: uma foto
     * poderia ficar "confirmada" errada se fosse trocada automaticamente no
     * exato momento em que a câmera parasse de funcionar.
     */
    private function diretorioImagensReferenciaDvr(int $ativoId): string
    {
        $dir = __DIR__ . '/../../storage/dvr_snapshots/' . $ativoId;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function caminhoImagemReferenciaDvr(int $ativoId, int $canal): string
    {
        return $this->diretorioImagensReferenciaDvr($ativoId) . '/canal_' . $canal . '.jpg';
    }

    private function removerImagensReferenciaDvr(int $ativoId): void
    {
        $dir = __DIR__ . '/../../storage/dvr_snapshots/' . $ativoId;

        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $arquivo) {
            @unlink($arquivo);
        }

        @rmdir($dir);
    }

    /** @return array{success:bool, existe?:bool, imagem_base64?:string, content_type?:string, atualizada_em?:?string, message?:string} */
    public function buscarImagemReferenciaDvr(int $id, int $canal): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.'];
        }

        $caminho = $this->caminhoImagemReferenciaDvr($id, $canal);

        if (!is_file($caminho)) {
            return ['success' => true, 'existe' => false];
        }

        $detalhes = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $atualizadaEm = null;

        foreach (($detalhes['dvr_canais'] ?? []) as $c) {
            if ((int)($c['numero'] ?? 0) === $canal) {
                $atualizadaEm = $c['imagem_referencia_atualizada_em'] ?? null;
                break;
            }
        }

        return [
            'success' => true,
            'existe' => true,
            'imagem_base64' => base64_encode((string)file_get_contents($caminho)),
            'content_type' => 'image/jpeg',
            'atualizada_em' => $atualizadaEm,
        ];
    }

    /** @return array{success:bool, message:string, atualizada_em?:string} */
    public function salvarImagemReferenciaDvr(int $id, int $canal): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo || empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Ativo não encontrado ou sem IP cadastrado.'];
        }

        $resultado = (new IntelbrasDvrService())->snapshot($ativo['ip'], $canal);

        if (!$resultado['success']) {
            return $resultado;
        }

        file_put_contents($this->caminhoImagemReferenciaDvr($id, $canal), $resultado['imagem']);

        $agora = date('Y-m-d H:i:s');

        $this->alterarDetalhesComLock($id, function (array $detalhes) use ($canal, $agora) {
            // Mesmo cuidado de definirCanalEmUsoDvr() -- "foreach ($x['y'] ?? [] as &$c)" não propaga a mutação de volta.
            $canais = $detalhes['dvr_canais'] ?? [];

            foreach ($canais as &$c) {
                if ((int)($c['numero'] ?? 0) === $canal) {
                    $c['imagem_referencia_atualizada_em'] = $agora;
                }
            }
            unset($c);

            $detalhes['dvr_canais'] = $canais;

            return ['ok' => true, 'detalhes' => $detalhes];
        });

        AuditService::registrar('Ativos', 'DVR/NVR - Imagem de referência', "{$ativo['codigo_patrimonio']}: canal {$canal} -- imagem de referência atualizada.");

        return ['success' => true, 'message' => 'Imagem de referência salva.', 'atualizada_em' => $agora];
    }

    /** Tira foto de todo canal que ainda não tem imagem de referência salva -- canal sem sinal no momento simplesmente falha e é ignorado (nada sobrescrito). */
    public function autoPreencherImagensReferenciaDvr(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo) {
            return ['success' => false, 'message' => 'Ativo não encontrado.', 'preenchidos' => 0, 'total' => 0];
        }

        $detalhes = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $semReferencia = array_filter($detalhes['dvr_canais'] ?? [], fn (array $c) => empty($c['imagem_referencia_atualizada_em']));

        $preenchidos = 0;
        foreach ($semReferencia as $c) {
            $resultado = $this->salvarImagemReferenciaDvr($id, (int)($c['numero'] ?? 0));
            if ($resultado['success']) {
                $preenchidos++;
            }
        }

        $total = count($semReferencia);

        AuditService::registrar('Ativos', 'DVR/NVR - Imagem de referência', "{$ativo['codigo_patrimonio']}: auto-preenchimento -- {$preenchidos} de {$total} canal(is) sem referência.");

        return [
            'success' => true,
            'message' => "{$preenchidos} de {$total} canal(is) preenchido(s) (os demais estavam sem sinal no momento).",
            'preenchidos' => $preenchidos,
            'total' => $total,
        ];
    }

    public function renomearCanalDvr(int $id, int $canal, string $novoNome): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo || empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Ativo não encontrado ou sem IP cadastrado.'];
        }

        $resultado = (new IntelbrasDvrService())->renomearCanal($ativo['ip'], $canal, $novoNome);

        if (!$resultado['success']) {
            return $resultado;
        }

        // Atualiza o nome já guardado em detalhes, sem esperar a próxima
        // coleta periódica -- a tela reflete a troca na hora.
        $nomeFinal = trim(str_replace('|', ' ', $novoNome));
        $this->alterarDetalhesComLock($id, function (array $detalhes) use ($canal, $nomeFinal) {
            // Mesmo cuidado de definirCanalEmUsoDvr() -- "foreach ($x['y'] ?? [] as &$c)" não propaga a mutação de volta.
            $canais = $detalhes['dvr_canais'] ?? [];

            foreach ($canais as &$c) {
                if ((int)($c['numero'] ?? 0) === $canal) {
                    $c['nome'] = $nomeFinal;
                }
            }
            unset($c);

            $detalhes['dvr_canais'] = $canais;

            return ['ok' => true, 'detalhes' => $detalhes];
        });

        AuditService::registrar('Ativos', 'DVR/NVR - Renomear canal', "{$ativo['codigo_patrimonio']}: canal {$canal} renomeado para \"{$novoNome}\".");

        return $resultado;
    }

    /** Ajusta a sensibilidade (1-6) do detector de "tampada" no próprio DVR pra esse canal -- ver IntelbrasDvrService::definirSensibilidadeTampada(). */
    public function definirSensibilidadeCanalDvr(int $id, int $canal, int $nivel): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo || empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Ativo não encontrado ou sem IP cadastrado.'];
        }

        $resultado = (new IntelbrasDvrService())->definirSensibilidadeTampada($ativo['ip'], $canal, $nivel);

        if (!$resultado['success']) {
            return $resultado;
        }

        // Atualiza o nível já guardado em detalhes, sem esperar a próxima
        // coleta periódica -- a tela reflete a troca na hora.
        $this->alterarDetalhesComLock($id, function (array $detalhes) use ($canal, $nivel) {
            // Mesmo cuidado de definirCanalEmUsoDvr() -- "foreach ($x['y'] ?? [] as &$c)" não propaga a mutação de volta.
            $canais = $detalhes['dvr_canais'] ?? [];

            foreach ($canais as &$c) {
                if ((int)($c['numero'] ?? 0) === $canal) {
                    $c['sensibilidade_tampada'] = $nivel;
                }
            }
            unset($c);

            $detalhes['dvr_canais'] = $canais;

            return ['ok' => true, 'detalhes' => $detalhes];
        });

        AuditService::registrar('Ativos', 'DVR/NVR - Sensibilidade de tampada', "{$ativo['codigo_patrimonio']}: canal {$canal} ajustado pra sensibilidade {$nivel}.");

        return $resultado;
    }

    /**
     * Checagem avulsa e imediata (sem esperar os até 30 min da coleta
     * periódica) de "tampada" pra um canal -- pra dar feedback na hora
     * depois de ajustar a sensibilidade.
     */
    public function testarTampadaCanalDvr(int $id, int $canal): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo || empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Ativo não encontrado ou sem IP cadastrado.'];
        }

        return (new IntelbrasDvrService())->testarTampadaCanal($ativo['ip'], $canal);
    }

    /** Liga/desliga a detecção de "câmera tampada" (VideoBlind) pra esse DVR/NVR específico -- é uma chave por Ativo, não do sistema todo. */
    public function definirDeteccaoTampadaDvr(int $id, bool $ativoFlag): array
    {
        $mensagemSucesso = $ativoFlag
            ? 'Detecção de câmera tampada ativada pra este DVR/NVR.'
            : 'Detecção de câmera tampada desativada pra este DVR/NVR -- o badge "Tampada" some da tela.';

        $resultado = $this->alterarDetalhesComLock($id, function (array $detalhes) use ($ativoFlag, $mensagemSucesso) {
            $detalhes['dvr_deteccao_tampada_ativa'] = $ativoFlag;

            return ['ok' => true, 'detalhes' => $detalhes, 'message' => $mensagemSucesso];
        });

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'DVR/NVR - Detecção de tampada', "{$resultado['codigo_patrimonio']}: detecção de câmera tampada " . ($ativoFlag ? 'ativada' : 'desativada') . '.');
        }

        return $resultado;
    }

    /*
     |---------------------------------------------------------
     | Usuários do DVR/NVR -- CRUD direto no equipamento (userManager.cgi),
     | sempre em tempo real (nunca fica guardado em `detalhes`: é estado
     | mutável do próprio DVR, não faz sentido cachear). Cada wrapper só
     | resolve o $ativo (id -> ip) e delega pro IntelbrasDvrService.
     |---------------------------------------------------------
     */

    private function ativoComIpOuErro(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);

        if (!$ativo || empty($ativo['ip'])) {
            return ['success' => false, 'message' => 'Ativo não encontrado ou sem IP cadastrado.'];
        }

        return ['success' => true, 'ativo' => $ativo];
    }

    /** @return array{success:bool, usuarios?:array, grupos?:string[], message?:string} */
    public function listarUsuariosDvr(int $id): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        $dvr = new IntelbrasDvrService();
        $usuarios = $dvr->listarUsuarios($base['ativo']['ip']);
        if (!$usuarios['success']) {
            return $usuarios;
        }

        $grupos = $dvr->listarGrupos($base['ativo']['ip']);

        return [
            'success' => true,
            'usuarios' => $usuarios['usuarios'],
            'grupos' => $grupos['success'] ? $grupos['grupos'] : ['admin', 'user'],
        ];
    }

    public function buscarUsuariosAtivosDvr(int $id): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        return (new IntelbrasDvrService())->buscarUsuariosAtivos($base['ativo']['ip']);
    }

    public function criarUsuarioDvr(int $id, string $nome, string $senha, string $grupo, string $memo): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        $resultado = (new IntelbrasDvrService())->criarUsuario($base['ativo']['ip'], $nome, $senha, $grupo, $memo);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'DVR/NVR - Usuário', "{$base['ativo']['codigo_patrimonio']}: usuário \"{$nome}\" criado no equipamento.");
        }

        return $resultado;
    }

    public function editarUsuarioDvr(int $id, string $nome, string $grupo, string $memo, bool $compartilhavel): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        $resultado = (new IntelbrasDvrService())->editarUsuario($base['ativo']['ip'], $nome, $grupo, $memo, $compartilhavel);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'DVR/NVR - Usuário', "{$base['ativo']['codigo_patrimonio']}: usuário \"{$nome}\" atualizado no equipamento.");
        }

        return $resultado;
    }

    public function excluirUsuarioDvr(int $id, string $nome): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        $resultado = (new IntelbrasDvrService())->excluirUsuario($base['ativo']['ip'], $nome);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'DVR/NVR - Usuário', "{$base['ativo']['codigo_patrimonio']}: usuário \"{$nome}\" excluído do equipamento.");
        }

        return $resultado;
    }

    public function trocarSenhaUsuarioDvr(int $id, string $nome, string $novaSenha): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        $resultado = (new IntelbrasDvrService())->trocarSenhaUsuario($base['ativo']['ip'], $nome, $novaSenha);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'DVR/NVR - Usuário', "{$base['ativo']['codigo_patrimonio']}: senha do usuário \"{$nome}\" alterada no equipamento.");
        }

        return $resultado;
    }

    /*
     |---------------------------------------------------------
     | TCP/IP do DVR/NVR -- leitura completa (IP/máscara/gateway/DNS/DHCP/
     | link) + edição de hostname/DNS (direto) e IP/máscara/gateway (só
     | quando $ipEstatico vem preenchido, força IP fixo/desliga DHCP) -- ver
     | IntelbrasDvrService::definirRede().
     |---------------------------------------------------------
     */

    public function buscarRedeDvr(int $id): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        return (new IntelbrasDvrService())->buscarRede($base['ativo']['ip']);
    }

    /**
     * @param ?array{ip:string, mascara:string, gateway:string} $ipEstatico null = não mexe em IP/máscara/gateway/DHCP
     */
    public function definirRedeDvr(int $id, string $hostname, array $dnsServers, ?array $ipEstatico): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        $resultado = (new IntelbrasDvrService())->definirRede($base['ativo']['ip'], $hostname, $dnsServers, $ipEstatico);

        if (!$resultado['success']) {
            return $resultado;
        }

        AuditService::registrar('Ativos', 'DVR/NVR - Rede', "{$base['ativo']['codigo_patrimonio']}: configuração de rede atualizada no equipamento.");

        // O IP mudou de verdade no equipamento -- atualiza o cadastro do
        // Ativo pro IP novo, senão o próprio sistema perde o equipamento de
        // vista na próxima coleta/ação (ainda ia tentar falar com o IP
        // antigo, que não responde mais).
        if (!empty($resultado['ip_mudou_para'])) {
            $this->repository->atualizarIp($id, $resultado['ip_mudou_para']);
            AuditService::registrar('Ativos', 'DVR/NVR - Rede', "{$base['ativo']['codigo_patrimonio']}: IP cadastrado atualizado de {$base['ativo']['ip']} para {$resultado['ip_mudou_para']}.");
            $resultado['message'] .= " O cadastro do Ativo foi atualizado pro novo IP ({$resultado['ip_mudou_para']}).";
        }

        return $resultado;
    }

    /*
     |---------------------------------------------------------
     | Segurança de acesso -- política do próprio DVR (alerta de login
     | falho, bloqueio por tentativas) + log de contas recentes. A parte
     * periódica (eventos + chamado automático) já roda dentro de
     * coletarIntelbrasDvr()/mesclarCanaisDvr(); isto aqui é só a
     * tela/toggle sob demanda.
     |---------------------------------------------------------
     */

    public function buscarSegurancaAcessoDvr(int $id): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        return (new IntelbrasDvrService())->buscarSegurancaAcesso($base['ativo']['ip']);
    }

    public function definirAlertaLoginFalhoDvr(int $id, bool $ativoFlag): array
    {
        $base = $this->ativoComIpOuErro($id);
        if (!$base['success']) {
            return $base;
        }

        $resultado = (new IntelbrasDvrService())->definirAlertaLoginFalho($base['ativo']['ip'], $ativoFlag);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'DVR/NVR - Segurança', "{$base['ativo']['codigo_patrimonio']}: alerta de login falho " . ($ativoFlag ? 'ativado' : 'desativado') . ' no equipamento.');
        }

        return $resultado;
    }

    /**
     * Mesma ideia de coletarAutomatico(), mas ANTES do ativo existir --
     * usada no formulário de "Novo Ativo" pra pré-preencher tipo/marca/
     * modelo a partir só do IP digitado, sem obrigar quem está cadastrando
     * a digitar de novo o que o sistema já sabe descobrir sozinho.
     *
     * @return array{success:bool, tipo_slug?:string, nome?:string, marca?:string, modelo?:string, firmware?:string, message:string}
     */
    public function detectarPorIp(string $ip, string $usuarioDvr = '', string $senhaDvr = ''): array
    {
        $ip = trim($ip);

        if ($ip === '') {
            return ['success' => false, 'message' => 'Informe um IP.'];
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['success' => false, 'message' => 'IP inválido.'];
        }

        // Segunda etapa do DVR/NVR: usuário/senha digitados no formulário.
        if ($usuarioDvr !== '' && $senhaDvr !== '') {
            return $this->detectarDvrComCredencial($ip, $usuarioDvr, $senhaDvr);
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
        if ($omada->sshAtivo()) {
            $integracoesTentadas[] = 'TP-Link (SSH)';
            $resultado = (new OmadaSshService())->coletar($ip);

            if ($resultado['success']) {
                $d = $resultado['dados'];

                return [
                    'success' => true,
                    'tipo_slug' => 'switch',
                    'nome' => $d['nome'],
                    'marca' => 'TP-Link',
                    'modelo' => $d['modelo'],
                    'firmware' => $d['firmware'],
                    'message' => 'Switch TP-Link encontrado via SSH: ' . ($d['nome'] ?: $d['modelo']) . '.',
                ];
            }
        }
        // Modo combinado: switch que não respondeu ao SSH ainda pode estar no Controller.
        if ($omada->controllerAtivo()) {
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

        // DVR/NVR Intelbras: cada equipamento tem a própria senha, então não
        // depende de integração configurada -- primeiro tenta com a credencial
        // salva (do IP ou padrão); se não der, reconhece o equipamento sem login
        // e pede usuário/senha no formulário.
        $dvr = new IntelbrasDvrService();
        $integracoesTentadas[] = 'DVR/NVR Intelbras';
        if ($dvr->configurado()) {
            $resultado = $dvr->coletar($ip);
            if ($resultado['success']) {
                return $this->respostaDeteccaoDvr($resultado, 'Encontrado como DVR/NVR Intelbras');
            }
        }

        if ($dvr->pareceIntelbras($ip)) {
            return [
                'success' => true,
                'precisa_credencial' => true,
                'tipo_slug' => 'dvr_nvr',
                'marca' => 'Intelbras',
                'message' => 'É um DVR/NVR Intelbras. Informe o usuário e a senha dele para ler modelo, firmware e câmeras.',
            ];
        }

        return ['success' => false, 'message' => 'Nenhum dispositivo com esse IP foi encontrado em: ' . implode(', ', $integracoesTentadas) . '.'];
    }

    /** Login digitado no Detectar: deu certo = guarda como credencial do IP (a coleta do ativo usa depois). */
    private function detectarDvrComCredencial(string $ip, string $usuario, string $senha): array
    {
        $resultado = (new IntelbrasDvrService())->comCredencial($usuario, $senha)->coletar($ip);

        if (!$resultado['success']) {
            return [
                'success' => false,
                'precisa_credencial' => true,
                'tipo_slug' => 'dvr_nvr',
                'message' => $resultado['message'] ?? 'Não consegui entrar no DVR/NVR.',
            ];
        }

        (new IntelbrasDvrService())->salvarCredencialPorIp($ip, $usuario, $senha);

        return $this->respostaDeteccaoDvr($resultado, 'Conectado ao DVR/NVR Intelbras (credencial salva para este IP)');
    }

    private function respostaDeteccaoDvr(array $resultado, string $prefixo): array
    {
        $canais = count($resultado['canais'] ?? []);

        return [
            'success' => true,
            'tipo_slug' => 'dvr_nvr',
            'nome' => $resultado['nome_dispositivo'] ?: ($resultado['modelo'] ?? ''),
            'marca' => 'Intelbras',
            'modelo' => $resultado['modelo'] ?? '',
            'firmware' => $resultado['firmware'] ?? '',
            'numero_serie' => $resultado['serial'] ?? '',
            'message' => $prefixo . ': ' . ($resultado['modelo'] ?? 'dispositivo') . ($canais ? " -- {$canais} canais" : '') . '.',
        ];
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

        // Periférico não passa pelas integrações de rede -- tem coleta própria (ping + web).
        if (($ativo['tipo_slug'] ?? '') === 'periferico') {
            return $this->coletarPeriferico($id);
        }

        $integracoesTentadas = [];

        if ((new UnifiService())->configurado() && ($ativo['tipo_slug'] ?? '') === 'dvr_nvr') {
            $integracoesTentadas[] = 'UniFi Protect';
            $resultado = $this->coletarProtect($id);
            if ($resultado['success']) {
                return ['success' => true, 'message' => 'Detectado como câmera do UniFi Protect -- ' . $resultado['message']];
            }
        }

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

    /** Regras de firewall (zone-based) do UniFi Controller -- site inteiro (não é por Ativo específico). */
    public function listarFirewallUnifi(): array
    {
        return (new UnifiService())->listarPoliticasFirewall();
    }

    /** Liga/desliga uma regra de firewall existente -- ação real no Controller, por isso vira registro de auditoria (igual às ações remotas de Ativos). */
    public function alterarPoliticaFirewallUnifi(string $policyId, bool $habilitada, string $nomeAuditoria): array
    {
        $resultado = (new UnifiService())->alterarPoliticaFirewall($policyId, $habilitada);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'UniFi - Regra de firewall', "Regra \"{$nomeAuditoria}\" " . ($habilitada ? 'ativada' : 'desativada') . ' via UniFi Controller.');
        }

        return $resultado;
    }

    /** Zonas do firewall (Internal/External/Gateway/Vpn/Hotspot/Dmz) -- pro formulário de nova regra. */
    public function listarZonasFirewallUnifi(): array
    {
        return (new UnifiService())->listarZonasFirewallParaFormulario();
    }

    public function criarPoliticaFirewallUnifi(array $dados): array
    {
        $resultado = (new UnifiService())->criarPoliticaFirewall($dados);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'UniFi - Regra de firewall', "Regra \"{$dados['nome']}\" criada via UniFi Controller.");
        }

        return $resultado;
    }

    public function excluirPoliticaFirewallUnifi(string $policyId, string $nomeAuditoria): array
    {
        $resultado = (new UnifiService())->excluirPoliticaFirewall($policyId, $nomeAuditoria);

        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'UniFi - Regra de firewall', "Regra \"{$nomeAuditoria}\" excluída via UniFi Controller.");
        }

        return $resultado;
    }

    /** Todos os clientes conectados na rede (com fio + Wi-Fi) via UniFi Controller -- só leitura, site inteiro. */
    public function listarClientesRedeUnifi(): array
    {
        return (new UnifiService())->listarClientesRede();
    }

    public function buscarSaudeRedeUnifi(): array
    {
        return (new UnifiService())->buscarSaudeRede();
    }

    public function listarRedesVizinhasUnifi(): array
    {
        return (new UnifiService())->listarRedesVizinhas();
    }

    public function listarPortForwardUnifi(): array
    {
        return (new UnifiService())->listarPortForward();
    }

    public function listarRedesWifiConfiguradasUnifi(): array
    {
        return (new UnifiService())->listarRedesWifiConfiguradas();
    }

    /**
     * Aba "Rotas" do gateway UniFi: redes conectadas, rotas estáticas e as
     * rotas que existem nos switches (TP-Link L3) -- marcando as que o
     * gateway não conhece. Caso real (Maurílio, 13/10/2026): o switch tinha
     * 10.10.2.0/24 e 10.10.4.0/24 via 192.168.0.99, mas o DHCP entrega o UCG
     * como gateway e o UCG não tinha rota nenhuma pra essas redes.
     */
    public function listarRotasGatewayUnifi(): array
    {
        $unifi = new UnifiService();
        $estaticas = $unifi->listarRotasEstaticas();
        if (!$estaticas['success']) {
            return $estaticas;
        }
        $dhcp = $unifi->listarDhcp();
        $conectadas = array_map(fn ($r) => ['nome' => $r['nome'], 'vlan' => $r['vlan'], 'rede' => $r['subnet']], $dhcp['redes'] ?? []);

        $conhecidas = array_merge(
            array_column($conectadas, 'rede'),
            array_column(array_filter($estaticas['rotas'], fn ($r) => $r['habilitada']), 'rede_destino')
        );
        $gatewayConhece = function (string $destino) use ($conhecidas): bool {
            [$ipDestino] = explode('/', $destino);
            foreach ($conhecidas as $rede) {
                if (str_contains($rede, '/') && self::ipNaRede($ipDestino, $rede) && (int)explode('/', $rede)[1] <= (int)(explode('/', $destino)[1] ?? 32)) {
                    return true;
                }
            }

            return false;
        };

        $deSwitches = [];
        $sql = "SELECT a.id, a.nome, a.ip, a.detalhes FROM ativos a JOIN ativos_tipos t ON t.id = a.tipo_id WHERE t.slug = 'switch'";
        foreach (Database::connection()->query($sql) as $sw) {
            $detalhesSw = json_decode($sw['detalhes'] ?? '', true) ?: [];
            foreach ($detalhesSw['omada_rotas'] ?? [] as $rota) {
                if ($rota['tipo'] === 'conectada') {
                    continue;
                }
                $deSwitches[] = $rota + [
                    'switch_id' => (int)$sw['id'],
                    'switch_nome' => $sw['nome'],
                    'switch_ip' => $sw['ip'],
                    'gateway_conhece' => $gatewayConhece($rota['destino']),
                ];
            }
        }

        return ['success' => true, 'conectadas' => $conectadas, 'estaticas' => $estaticas['rotas'], 'switches' => $deSwitches];
    }

    public function listarRotasEstaticasUnifi(): array
    {
        return (new UnifiService())->listarRotasEstaticas();
    }

    /** DHCP do Gateway UniFi + o ativo do RD.Intranet dono de cada MAC (pelos adaptadores que o agente coletou, ou pelo IP). */
    public function listarDhcpUnifi(): array
    {
        $resultado = (new UnifiService())->listarDhcp();
        if (!$resultado['success']) {
            return $resultado;
        }

        [$porMac, $porIp] = $this->mapaAtivosPorMacIp();

        foreach (['reservas' => 'ip_fixo', 'sem_reserva' => 'ip'] as $lista => $campoIp) {
            foreach ($resultado[$lista] as &$item) {
                $item['ativo'] = $porMac[$item['mac']] ?? ($porIp[$item[$campoIp]] ?? null);
            }
            unset($item);
        }

        return $resultado;
    }

    /** [MAC => ativo, IP => ativo] -- MAC pelos adaptadores que o agente coletou, IP pelo cadastro. */
    private function mapaAtivosPorMacIp(): array
    {
        $porMac = [];
        $porIp = [];
        $pdo = Database::connection();
        foreach ($pdo->query("SELECT r.mac, a.id, a.nome, a.codigo_patrimonio FROM ativos_redes r JOIN ativos a ON a.id = r.ativo_id WHERE r.mac IS NOT NULL AND r.mac <> ''") as $l) {
            $porMac[OmadaSshService::normalizarMac($l['mac'])] = ['id' => (int)$l['id'], 'nome' => $l['nome'], 'codigo' => $l['codigo_patrimonio']];
        }
        foreach ($pdo->query("SELECT id, nome, codigo_patrimonio, ip FROM ativos WHERE ip IS NOT NULL AND ip <> ''") as $l) {
            $porIp[$l['ip']] = ['id' => (int)$l['id'], 'nome' => $l['nome'], 'codigo' => $l['codigo_patrimonio']];
        }

        return [$porMac, $porIp];
    }

    /* ---------------------------------------------------------------
     * DHCP do switch TP-Link (modo SSH). Mesma tela do gateway UniFi:
     * pools + IPs fixos (manual-binding) + concessões em uso.
     * --------------------------------------------------------------- */

    /** @return array{0: ?array, 1: ?string} ativo e mensagem de erro */
    private function switchDhcp(int $id): array
    {
        $ativo = $this->repository->buscarPorId($id);
        $detalhes = $ativo ? (json_decode($ativo['detalhes'] ?? '', true) ?: []) : [];
        if (!$ativo || empty($ativo['ip']) || self::fonteVlanSwitch($ativo, $detalhes) !== 'omada' || !(new OmadaService())->sshAtivo()) {
            return [null, 'Este ativo não é um switch TP-Link acessado por SSH.'];
        }

        return [$ativo, null];
    }

    public function listarDhcpSwitch(int $id): array
    {
        [$ativo, $erro] = $this->switchDhcp($id);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }
        $ssh = new OmadaSshService();
        $dados = $ssh->coletarDhcp($ativo['ip']);
        if (!$dados['success']) {
            return $dados;
        }

        [$porMac, $porIp] = $this->mapaAtivosPorMacIp();
        $concessaoPorMac = [];
        foreach ($dados['concessoes'] as $c) {
            if ($c['mac'] !== '') {
                $concessaoPorMac[$c['mac']] = $c;
            }
        }
        $comReserva = [];
        foreach ($dados['reservas'] as &$r) {
            $comReserva[$r['mac']] = true;
            $c = $concessaoPorMac[$r['mac']] ?? null;
            $r['entregue'] = $c !== null && $c['ip'] === $r['ip'];
            $r['ip_atual'] = $c['ip'] ?? '';
            $r['ativo'] = $porMac[$r['mac']] ?? ($porIp[$r['ip']] ?? null);
        }
        unset($r);
        // Concessões dinâmicas = atalho pro "Novo IP fixo" (aparelho já pegou IP do switch).
        $semReserva = [];
        foreach ($dados['concessoes'] as $c) {
            if ($c['tipo'] === 'dinamico' && $c['mac'] !== '' && !isset($comReserva[$c['mac']])) {
                $c['ativo'] = $porMac[$c['mac']] ?? ($porIp[$c['ip']] ?? null);
                $semReserva[] = $c;
            }
        }
        $dados['sem_reserva'] = $semReserva;
        $dados['manutencao_usuario'] = $ssh->usuarioManutencaoAtual() ?: $ssh->usuarioAtual();
        $dados['manutencao_separada'] = $ssh->usuarioManutencaoAtual() !== '';

        return $dados;
    }

    public function definirReservaDhcpSwitch(int $id, string $mac, string $ip, string $pool): array
    {
        [$ativo, $erro] = $this->switchDhcp($id);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }
        $mac = OmadaSshService::normalizarMac($mac);
        $ip = trim($ip);
        if ($mac === '') {
            return ['success' => false, 'message' => 'MAC inválido (use o formato aa:bb:cc:dd:ee:ff).'];
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['success' => false, 'message' => 'IP inválido.'];
        }

        $ssh = new OmadaSshService();
        $atual = $ssh->coletarDhcp($ativo['ip']);
        if (!$atual['success']) {
            return $atual;
        }
        $dadosPool = null;
        foreach ($atual['pools'] as $p) {
            if ($p['nome'] === $pool) {
                $dadosPool = $p;
            }
        }
        if ($dadosPool === null || $dadosPool['cidr'] === '') {
            return ['success' => false, 'message' => 'Pool DHCP não encontrado no switch.'];
        }
        [$base, $bits] = explode('/', $dadosPool['cidr']);
        $broadcast = (ip2long($base) | ~(-1 << (32 - (int)$bits))) & 0xFFFFFFFF;
        if (!self::ipNaRede($ip, $dadosPool['cidr']) || ip2long($ip) === ip2long($base) || ip2long($ip) === $broadcast) {
            return ['success' => false, 'message' => "O IP {$ip} não é um endereço válido do pool {$pool} ({$dadosPool['cidr']})."];
        }
        if ($ip === $dadosPool['gateway']) {
            return ['success' => false, 'message' => "{$ip} é o gateway entregue por esse pool."];
        }
        foreach ($atual['excluidos'] as $e) {
            if (ip2long($ip) >= ip2long($e['inicio']) && ip2long($ip) <= ip2long($e['fim'])) {
                return ['success' => false, 'message' => "O IP {$ip} está na lista de endereços excluídos do DHCP do switch (reservado para equipamento com IP manual)."];
            }
        }
        $ipAnterior = null;
        foreach ($atual['reservas'] as $r) {
            if ($r['ip'] === $ip && $r['mac'] !== $mac) {
                return ['success' => false, 'message' => "O IP {$ip} já está reservado para {$r['mac']}."];
            }
            if ($r['mac'] === $mac) {
                $ipAnterior = $r['ip'];
            }
        }
        foreach ($atual['concessoes'] as $c) {
            if ($c['ip'] === $ip && $c['mac'] !== $mac) {
                return ['success' => false, 'message' => "O IP {$ip} está emprestado agora pelo switch a " . ($c['mac'] ?: 'outro aparelho') . ". Escolha outro IP ou reserve esse para ele."];
            }
        }

        $resultado = $ssh->definirReservaDhcp($ativo['ip'], $pool, $ip, $mac, $ipAnterior);
        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'Switch - IP fixo (DHCP)', "{$ativo['nome']}: reserva {$ip} -> {$mac} no pool {$pool}" . ($ipAnterior ? " (antes {$ipAnterior})" : '') . '.');
        }

        return $resultado;
    }

    public function removerReservaDhcpSwitch(int $id, string $ip): array
    {
        [$ativo, $erro] = $this->switchDhcp($id);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }
        $ssh = new OmadaSshService();
        $atual = $ssh->coletarDhcp($ativo['ip']);
        if (!$atual['success']) {
            return $atual;
        }
        foreach ($atual['reservas'] as $r) {
            if ($r['ip'] === trim($ip)) {
                $resultado = $ssh->removerReservaDhcp($ativo['ip'], $r['pool'], $r['ip']);
                if ($resultado['success']) {
                    AuditService::registrar('Ativos', 'Switch - IP fixo (DHCP)', "{$ativo['nome']}: reserva {$r['ip']} ({$r['mac']}) removida do pool {$r['pool']}.");
                }

                return $resultado;
            }
        }

        return ['success' => false, 'message' => 'Esse IP não tem reserva no switch.'];
    }

    /** "10.10.2.0/24" -> ['10.10.2.0', '255.255.255.0'] (ou null se não for endereço de rede). */
    private static function redeEMascara(string $cidr): ?array
    {
        if (!preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', trim($cidr), $m) || !filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || (int)$m[2] > 32) {
            return null;
        }
        $mascara = (-1 << (32 - (int)$m[2])) & 0xFFFFFFFF;
        if ((ip2long($m[1]) & $mascara) !== ip2long($m[1])) {
            return null;
        }

        return [$m[1], long2ip($mascara)];
    }

    /**
     * Cria ou edita (quando vem $anteriorDestino/$anteriorVia) uma rota
     * estática no switch TP-Link L3, e coleta de novo pra tela refletir.
     */
    public function salvarRotaSwitch(int $id, string $destino, string $via, ?int $distancia, string $anteriorDestino = '', string $anteriorVia = ''): array
    {
        [$ativo, $erro] = $this->switchDhcp($id);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }
        $detalhes = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $destino = trim($destino);
        $via = trim($via);

        $redeMascara = self::redeEMascara($destino);
        if ($redeMascara === null) {
            return ['success' => false, 'message' => 'Rede de destino inválida -- use o endereço da rede com o prefixo, ex.: 10.10.2.0/24 (não um IP de dentro dela).'];
        }
        if (!filter_var($via, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['success' => false, 'message' => 'Próximo salto inválido.'];
        }

        // O próximo salto precisa estar numa rede em que o switch tem IP (senão ele não sabe chegar nele).
        $interfaces = $detalhes['omada_interfaces_ip'] ?? [];
        $alcancavel = false;
        foreach ($interfaces as $interface) {
            if (explode('/', $interface['ip'])[0] === $via) {
                return ['success' => false, 'message' => "{$via} é o próprio switch ({$interface['interface']})."];
            }
            if (self::ipNaRede($via, $interface['ip'])) {
                $alcancavel = true;
            }
            if ($destino !== '0.0.0.0/0' && self::ipNaRede($redeMascara[0], $interface['ip']) && (int)explode('/', $destino)[1] >= (int)explode('/', $interface['ip'])[1]) {
                return ['success' => false, 'message' => "{$destino} já é uma rede ligada direto ao switch ({$interface['interface']} {$interface['ip']})."];
            }
        }
        if ($interfaces && !$alcancavel) {
            return ['success' => false, 'message' => "O próximo salto {$via} não está em nenhuma rede do switch (" . implode(', ', array_column($interfaces, 'ip')) . ').'];
        }

        $anterior = null;
        if ($anteriorDestino !== '') {
            $redeAnterior = self::redeEMascara($anteriorDestino);
            if ($redeAnterior === null || !filter_var($anteriorVia, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return ['success' => false, 'message' => 'Rota original inválida.'];
            }
            $anterior = [$redeAnterior[0], $redeAnterior[1], $anteriorVia];
        }
        foreach ($detalhes['omada_rotas'] ?? [] as $rota) {
            $mesma = $rota['destino'] === $destino && $rota['via'] === $via;
            $ehAnterior = $rota['destino'] === $anteriorDestino && $rota['via'] === $anteriorVia;
            if ($mesma && !$ehAnterior) {
                return ['success' => false, 'message' => "Já existe a rota {$destino} via {$via}."];
            }
        }

        $resultado = (new OmadaSshService())->salvarRotaEstatica($ativo['ip'], $redeMascara[0], $redeMascara[1], $via, $distancia, $anterior);
        if (!$resultado['success']) {
            return $resultado;
        }
        $descricao = $anterior
            ? "Rota {$anteriorDestino} via {$anteriorVia} alterada para {$destino} via {$via}" . ($distancia ? " (distância {$distancia})" : '') . '.'
            : "Rota {$destino} via {$via}" . ($distancia ? " (distância {$distancia})" : '') . ' criada.';
        AuditService::registrar('Ativos', 'Switch - Rota estática', "{$ativo['codigo_patrimonio']} ({$ativo['nome']}): {$descricao}");
        $this->coletarOmada($id);

        return ['success' => true, 'message' => $descricao];
    }

    public function removerRotaSwitch(int $id, string $destino, string $via): array
    {
        [$ativo, $erro] = $this->switchDhcp($id);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }
        $redeMascara = self::redeEMascara($destino);
        if ($redeMascara === null || !filter_var($via, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['success' => false, 'message' => 'Rota inválida.'];
        }

        $resultado = (new OmadaSshService())->removerRotaEstatica($ativo['ip'], $redeMascara[0], $redeMascara[1], $via);
        if (!$resultado['success']) {
            return $resultado;
        }
        AuditService::registrar('Ativos', 'Switch - Rota estática', "{$ativo['codigo_patrimonio']} ({$ativo['nome']}): rota {$destino} via {$via} removida.");
        $this->coletarOmada($id);

        return ['success' => true, 'message' => "Rota {$destino} via {$via} removida."];
    }

    public function definirIpFixoUnifi(string $mac, string $ip, string $redeId, string $nome): array
    {
        $mac = OmadaSshService::normalizarMac($mac);
        $ip = trim($ip);
        if ($mac === '') {
            return ['success' => false, 'message' => 'MAC inválido (use o formato aa:bb:cc:dd:ee:ff).'];
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['success' => false, 'message' => 'IP inválido.'];
        }

        $atual = (new UnifiService())->listarDhcp();
        if (!$atual['success']) {
            return $atual;
        }
        $rede = null;
        foreach ($atual['redes'] as $r) {
            if ($r['id'] === $redeId) {
                $rede = $r;
            }
        }
        if ($rede === null) {
            return ['success' => false, 'message' => 'Rede não encontrada no Controller.'];
        }
        [$ipGateway, $bits] = explode('/', $rede['subnet']);
        $base = ip2long($ipGateway) & (-1 << (32 - (int)$bits));
        $broadcast = $base | ~(-1 << (32 - (int)$bits)) & 0xFFFFFFFF;
        if (!self::ipNaRede($ip, $rede['subnet']) || ip2long($ip) === $base || ip2long($ip) === $broadcast) {
            return ['success' => false, 'message' => "O IP {$ip} não é um endereço válido da rede {$rede['nome']} ({$rede['subnet']})."];
        }
        if ($ip === $ipGateway) {
            return ['success' => false, 'message' => "{$ip} é o próprio gateway da rede."];
        }
        foreach ($atual['reservas'] as $r) {
            if ($r['ip_fixo'] === $ip && $r['mac'] !== $mac) {
                return ['success' => false, 'message' => "O IP {$ip} já está reservado para " . ($r['nome'] ?: $r['hostname'] ?: $r['mac']) . " ({$r['mac']})."];
            }
        }
        foreach ($atual['sem_reserva'] as $c) {
            if ($c['ip'] === $ip && $c['mac'] !== $mac) {
                return ['success' => false, 'message' => "O IP {$ip} está em uso agora por " . ($c['nome'] ?: $c['mac']) . " ({$c['mac']}), que não tem reserva. Escolha outro IP ou reserve esse para ele."];
            }
        }

        $resultado = (new UnifiService())->definirIpFixo($mac, $ip, $redeId, trim($nome));
        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'UniFi - IP fixo', "Reserva DHCP {$ip} -> {$mac}" . (trim($nome) !== '' ? " ({$nome})" : '') . " na rede {$rede['nome']}.");
        }

        return $resultado;
    }

    public function removerIpFixoUnifi(string $mac): array
    {
        $mac = OmadaSshService::normalizarMac($mac);
        if ($mac === '') {
            return ['success' => false, 'message' => 'MAC inválido.'];
        }
        $resultado = (new UnifiService())->removerIpFixo($mac);
        if ($resultado['success']) {
            AuditService::registrar('Ativos', 'UniFi - IP fixo', $resultado['message']);
        }

        return $resultado;
    }

    public function buscarInfoControladorUnifi(): array
    {
        return (new UnifiService())->buscarInfoControlador();
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

    public function idPorMachineGuid(string $machineGuid): ?int
    {
        $ativo = $machineGuid !== '' ? $this->repository->buscarPorMachineGuid($machineGuid) : null;

        return $ativo ? (int)$ativo['id'] : null;
    }

    public function temInstalacaoMeshPendente(string $machineGuid): bool
    {
        $ativoId = $this->idPorMachineGuid($machineGuid);
        return $ativoId !== null && $this->repository->temSolicitacaoPendente($ativoId, 'mesh_install');
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
    private const CHAVE_AGENTE_AUTOMATICO = 'agente_atualizacao_automatica';

    /**
     * "Freio" por servidor: desligado, a sincronização automática continua
     * trazendo as regras do anti-ransomware, mas NÃO baixa executável novo --
     * só anota a versão disponível; a atualização volta a ser pelo botão
     * "Buscar atualização agora" (ex.: segurar um cliente numa versão testada).
     */
    public function atualizacaoAutomaticaAgente(): bool
    {
        return ConfigService::get(self::CHAVE_AGENTE_AUTOMATICO, '1') !== '0';
    }

    public function definirAtualizacaoAutomaticaAgente(bool $ligada): void
    {
        ConfigService::set(self::CHAVE_AGENTE_AUTOMATICO, $ligada ? '1' : '0');
        AuditService::registrar('Ativos', 'Agente Windows', 'Atualização automática do agente ' . ($ligada ? 'LIGADA' : 'DESLIGADA') . ' neste servidor.');
    }

    /** Versão publicada no repositório que ainda não foi baixada (só preenchida com a atualização automática desligada). */
    public function versaoAgenteDisponivelNaoBaixada(): string
    {
        $disponivel = (string)ConfigService::get('agente_versao_disponivel', '');
        $atual = (string)ConfigService::get('ativos_agente_exe_versao', '');

        return $disponivel !== '' && version_compare($disponivel, $atual ?: '0', '>') ? $disponivel : '';
    }

    /**
     * Cron "rd agente:sincronizar" (30 min): lê no repositório, sem mexer no
     * código rodando, a versão publicada do agente e as regras do
     * anti-ransomware. Regras novas: guarda (agentes pegam no próximo
     * heartbeat). Agente novo: baixa o .exe (os agentes se autoatualizam no
     * próximo check-in) -- o mesmo que o botão "Buscar atualização agora", sem clique.
     *
     * @return array{success: bool, mensagens: string[]}
     */
    public function sincronizarAgenteERegras(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/repositorio_agente_regras_git.sh');
        $dados = json_decode(trim($resultado['output']), true);
        if (!is_array($dados) || !($dados['success'] ?? false)) {
            return ['success' => false, 'mensagens' => [$dados['message'] ?? ($resultado['output'] ?: 'Falha ao ler o repositório.')]];
        }

        $mensagens = [];
        $ok = true;

        $regrasJson = base64_decode((string)($dados['regras_base64'] ?? ''), true);
        if ($regrasJson) {
            $regras = (new SegurancaRegrasService())->guardarDoRepositorio($regrasJson);
            $ok = $ok && $regras['success'];
            $mensagens[] = $regras['success']
                ? ($regras['mudou'] ? "Regras do anti-ransomware atualizadas para {$regras['versao']}." : "Regras já na versão {$regras['versao']}.")
                : 'Regras: ' . $regras['message'];
        }

        $versaoRemota = (string)($dados['versao_agente'] ?? '');
        $versaoLocal = (string)ConfigService::get('ativos_agente_exe_versao', '');
        if ($versaoRemota !== '') {
            ConfigService::set('agente_versao_disponivel', $versaoRemota);
        }

        if ($versaoRemota !== '' && version_compare($versaoRemota, $versaoLocal ?: '0', '>') && !$this->atualizacaoAutomaticaAgente()) {
            $mensagens[] = "Versão {$versaoRemota} do agente disponível no repositório, mas a atualização automática está DESLIGADA neste servidor -- continua na {$versaoLocal} (use \"Buscar atualização agora\" quando quiser).";
        } elseif ($versaoRemota !== '' && version_compare($versaoRemota, $versaoLocal ?: '0', '>')) {
            $baixar = json_decode(trim($this->linux->executarScript('/opt/rdtecnologia/scripts/agente_baixar_git.sh')['output']), true);
            if (is_array($baixar) && ($baixar['success'] ?? false)) {
                ConfigService::set('ativos_agente_exe_versao', (string)($baixar['versao'] ?? $versaoRemota));
                AuditService::registrar('Ativos', 'Agente Windows', "Agente .exe atualizado automaticamente do repositório: {$versaoLocal} -> {$versaoRemota}.");
                $mensagens[] = "Agente atualizado de {$versaoLocal} para {$versaoRemota} (as máquinas se autoatualizam no próximo check-in).";
            } else {
                $ok = false;
                $mensagens[] = 'Agente: ' . ($baixar['message'] ?? 'falha ao baixar do repositório.');
            }
        } else {
            $mensagens[] = "Agente já na versão {$versaoLocal}.";
        }

        ConfigService::set('agente_sincronizacao_ultima', date('Y-m-d H:i:s') . ' -- ' . implode(' ', $mensagens));

        return ['success' => $ok, 'mensagens' => $mensagens];
    }

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
    /**
     * Última varredura do IP Scanner de cada faixa (até 26 h) -- base da coluna
     * "Condição" para equipamento sem agente nem API (impressora, monitor de rede,
     * câmera solta...). Carregado uma vez por página.
     *
     * @return list<array{inicio: int, fim: int, quando: string, ips: array<string, true>}>
     */
    public function varredurasRecentes(): array
    {
        try {
            $linhas = \App\Core\Database::connection()->query(
                "SELECT e.cidr, e.executado_em, e.hosts FROM ip_scanner_execucoes e
                 JOIN (SELECT cidr, MAX(id) id FROM ip_scanner_execucoes WHERE executado_em > DATE_SUB(NOW(), INTERVAL 26 HOUR) GROUP BY cidr) u ON u.id = e.id"
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $varreduras = [];
        foreach ($linhas as $l) {
            [$rede, $bits] = array_pad(explode('/', (string)$l['cidr']), 2, '32');
            $inicio = ip2long($rede);
            if ($inicio === false) {
                continue;
            }
            $tamanho = 2 ** (32 - max(0, min(32, (int)$bits)));
            $ips = [];
            foreach (json_decode((string)$l['hosts'], true) ?: [] as $h) {
                if (!empty($h['ip'])) {
                    $ips[$h['ip']] = true;
                }
            }
            $varreduras[] = ['inicio' => $inicio, 'fim' => $inicio + $tamanho - 1, 'quando' => $l['executado_em'], 'ips' => $ips];
        }

        return $varreduras;
    }

    /**
     * Ligado/desligado de QUALQUER ativo, pela melhor evidência disponível:
     * agente (heartbeat) > status do fabricante (UniFi/Omada/Protect/ping de
     * periférico) com coleta recente > coleta SNMP/API ok nas últimas 2 h >
     * IP Scanner (respondeu ou não na última varredura da faixa). null = sem dado.
     *
     * @return array{ligado: bool, dica: string}|null
     */
    public static function condicao(array $a, array $detalhes, array $varreduras): ?array
    {
        $ha = static fn (?string $quando) => $quando ? self::duracaoLegivel(max(0, time() - strtotime($quando))) : '?';

        if ($a['origem'] === 'agente') {
            $segundos = self::segundosDesdeUltimoHeartbeat($a);
            $dica = $segundos !== null
                ? 'Agente -- último sinal há ' . self::duracaoLegivel($segundos)
                : ((($minutos = self::minutosDesdeUltimoCheckin($a)) !== null) ? 'Agente antigo -- último check-in há ' . self::duracaoLegivel($minutos * 60) : 'Agente nunca se comunicou');
            return ['ligado' => self::estaLigada($a), 'dica' => $dica];
        }

        foreach ([
            ['unifi_status', 'unifi_coletado_em', 'UniFi Controller', ['Online']],
            ['omada_status', 'omada_coletado_em', 'Omada Controller', ['Online']],
            ['protect_status', 'protect_coletado_em', 'UniFi Protect', ['Conectada']],
            ['periferico_status', null, 'ping do RD.Intranet', ['Online']],
        ] as [$campo, $campoData, $fonte, $valoresLigado]) {
            if (empty($detalhes[$campo])) {
                continue;
            }
            $quando = $campoData ? ($detalhes[$campoData] ?? $a['ultimo_checkin'] ?? null) : ($a['ultimo_checkin'] ?? null);
            if ($quando && time() - strtotime($quando) > 3 * 3600) {
                continue; // dado velho: tenta outra evidência
            }
            return ['ligado' => in_array($detalhes[$campo], $valoresLigado, true), 'dica' => "{$fonte}: {$detalhes[$campo]} -- última coleta há " . $ha($quando)];
        }

        if (in_array($a['origem'], ['snmp', 'api'], true) && !empty($a['ultimo_checkin']) && time() - strtotime($a['ultimo_checkin']) <= 2 * 3600) {
            return ['ligado' => true, 'dica' => ($a['origem'] === 'snmp' ? 'Respondeu ao SNMP' : 'Respondeu à coleta') . ' há ' . $ha($a['ultimo_checkin'])];
        }

        $ip = trim((string)($a['ip'] ?? ''));
        $ipLong = $ip !== '' ? ip2long($ip) : false;
        if ($ipLong !== false) {
            foreach ($varreduras as $v) {
                if ($ipLong >= $v['inicio'] && $ipLong <= $v['fim']) {
                    return isset($v['ips'][$ip])
                        ? ['ligado' => true, 'dica' => 'Respondeu ao IP Scanner há ' . $ha($v['quando'])]
                        : ['ligado' => false, 'dica' => 'Não respondeu ao IP Scanner de ' . date('d/m H:i', strtotime($v['quando']))];
                }
            }
        }

        if (in_array($a['origem'], ['snmp', 'api'], true) && !empty($a['ultimo_checkin'])) {
            return ['ligado' => false, 'dica' => 'Sem resposta à coleta desde ' . date('d/m H:i', strtotime($a['ultimo_checkin']))];
        }

        return null;
    }

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

        // Agente 1.0.69+: cópias de sombra e verificações do Defender (Central de Segurança > Recomendações).
        if (is_array($payload['protecao'] ?? null)) {
            $camposTecnicos['protecao'] = array_map(
                fn ($v) => is_scalar($v) || $v === null ? (is_string($v) ? mb_substr($v, 0, 60) : $v) : null,
                array_slice($payload['protecao'], 0, 20, true)
            );
        }

        if ($existente) {
            $id = (int)$existente['id'];
            $detalhesAtuais = json_decode($existente['detalhes'] ?? '', true) ?: [];
            $detalhesNovos = array_merge($detalhesAtuais, $camposTecnicos);

            $this->repository->atualizarViaAgente($id, $camposBase, json_encode($detalhesNovos, JSON_UNESCAPED_UNICODE));
        } else {
            // A partir do agente 1.0.24, o instalador pergunta a unidade
            // (obrigatória) e opcionalmente setor/localização antes do
            // primeiro checkin -- ver ConfigForm.cs. Máquinas com agente
            // mais antigo (ou payload sem esses campos) continuam caindo
            // na unidade padrão, como sempre foi.
            $tipo = (new AtivoTipoService())->buscarPorSlug($slugTipo);
            $unidade = (new UnidadeService())->buscar((int)($payload['unidade_id'] ?? 0)) ?? (new UnidadeService())->padrao();
            $setor = (new AtivoCatalogoService())->buscar((int)($payload['setor_id'] ?? 0));
            $localizacao = (new AtivoCatalogoService())->buscar((int)($payload['localizacao_id'] ?? 0));

            $id = $this->repository->criarViaAgente(array_merge($camposBase, [
                'tipo_id' => (int)$tipo['id'],
                'unidade_id' => (int)$unidade['id'],
                'setor_id' => ($setor && $setor['tipo'] === 'setor') ? (int)$setor['id'] : null,
                'localizacao_id' => ($localizacao && $localizacao['tipo'] === 'localizacao') ? (int)$localizacao['id'] : null,
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
        $this->repository->substituirProcessos($id, array_slice($payload['processos'] ?? [], 0, 300));
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
        // Agente preso numa versão cujo script de troca quebra com acento
        // no nome do usuário -- o servidor faz a troca por fora. Antes de
        // buscar os pendentes, pra o envio do .exe já sair nesta resposta.
        // Lista pública de ransomware: renova no máximo 1x/dia, só se ligada.
        (new SegurancaAssinaturaService())->atualizarSeNecessario();

        (new AgenteAtualizacaoAssistidaService($this))->verificar(
            $id,
            $camposBase['agente_versao'],
            (string)($payload['usuario_logado'] ?? '')
        );

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
            'modulo_seguranca' => (new SegurancaModuloService())->paraAgente($id),
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

        // Isolamento em modo "confirmação" cujo prazo venceu -- enfileira
        // antes de montar a lista abaixo, pra já sair neste mesmo heartbeat.
        (new SegurancaIsolamentoService())->processarPendentes($ativoId);

        $solicitacoes = $this->repository->solicitacoesPendentes($ativoId);

        $resposta = [
            'success' => true,
            'forcar_checkin' => $resultado['forcar_checkin'],
            'ativo' => [
                'codigo' => $resultado['codigo_patrimonio'],
                'nome' => $resultado['nome'],
                'ip' => $resultado['ip'],
            ],
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

        // Monitor de conexões/processos (agente 1.0.56+): liga/desliga e
        // intervalo vão no heartbeat pra mudança no portal valer em segundos.
        try {
            $modulos = new SegurancaModuloService();
            $resposta['monitor_rede'] = $modulos->monitorRedeParaAgente($ativoId);
            $resposta['monitor_desempenho'] = $modulos->monitorDesempenhoParaAgente($ativoId);
        } catch (\Throwable) {
            // servidor sem a migração: o agente segue como está
        }

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
        'network_list', 'network_apply', 'network_revert', 'network_ping', 'network_speedtest', 'network_traceroute', 'network_mtr', 'mesh_install',
        // Monitor de conexões/processos e firewall (agente 1.0.56+) -- criados por acaoMonitorSeguranca().
        'seguranca_atualizar', 'firewall_bloquear', 'firewall_desbloquear', 'servico_desativar', 'processo_encerrar',
        // Agente 1.0.58+: remediação (inicialização, quarentena, firewall ligado).
        'inicializacao_alternar', 'arquivo_quarentena', 'quarentena_restaurar', 'firewall_perfil', 'programa_confiar', 'programa_desconfiar',
    ];

    /**
     * Ações da aba Segurança (monitor de conexões/processos). O agente
     * valida de novo e monta o comando ele mesmo -- aqui só vai o pedido
     * estruturado (nunca texto de comando).
     *
     * @return array{success: bool, message: string, id?: int}
     */
    public function acaoMonitorSeguranca(int $ativoId, string $acao, array $dados, string $usuario): array
    {
        $ativo = $this->buscar($ativoId);
        if (!$ativo) {
            return ['success' => false, 'message' => 'Máquina não encontrada.'];
        }

        $tipo = null;
        $parametro = null;
        $descricao = '';
        switch ($acao) {
            case 'atualizar':
                $tipo = 'seguranca_atualizar';
                $descricao = 'retrato e inventário agora';
                break;

            case 'bloquear_programa':
            case 'bloquear_ip':
                $valor = trim((string)($dados['valor'] ?? ''));
                if ($acao === 'bloquear_ip' && !preg_match('#^[0-9a-fA-F:.]+(/\d{1,3})?$#', $valor)) {
                    return ['success' => false, 'message' => 'IP inválido.'];
                }
                if ($acao === 'bloquear_programa' && !preg_match('#^[A-Za-z]:\\\\#', $valor)) {
                    return ['success' => false, 'message' => 'Caminho de programa inválido.'];
                }
                $tipo = 'firewall_bloquear';
                $parametro = json_encode([
                    'tipo' => $acao === 'bloquear_ip' ? 'ip' : 'programa',
                    'valor' => $valor,
                    'motivo' => mb_substr(trim((string)($dados['motivo'] ?? '')), 0, 300),
                    'por' => $usuario,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $descricao = ($acao === 'bloquear_ip' ? 'bloqueio do IP ' : 'bloqueio do programa ') . $valor . ' no firewall do Windows';
                break;

            case 'desbloquear':
                $regra = trim((string)($dados['regra'] ?? ''));
                if (!str_starts_with($regra, 'RD.Intranet - Bloqueio')) {
                    return ['success' => false, 'message' => 'Só regras criadas pelo RD.Intranet podem ser removidas por aqui.'];
                }
                $tipo = 'firewall_desbloquear';
                $parametro = $regra;
                $descricao = 'remoção da regra "' . $regra . '"';
                break;

            case 'desativar_servico':
                $servico = trim((string)($dados['servico'] ?? ''));
                if (!preg_match('/^[A-Za-z0-9_.\- ]{1,80}$/', $servico)) {
                    return ['success' => false, 'message' => 'Nome de serviço inválido.'];
                }
                $tipo = 'servico_desativar';
                $parametro = $servico;
                $descricao = 'desativação do serviço ' . $servico;
                break;

            case 'desativar_inicializacao':
            case 'reativar_inicializacao':
                $tipoItem = (string)($dados['tipo'] ?? '');
                $idItem = (string)($dados['item_id'] ?? '');
                if (!in_array($tipoItem, ['servico', 'tarefa', 'registro', 'pasta_inicializar', 'winlogon', 'ifeo', 'wmi', 'appinit', 'politica'], true) || $idItem === '' || mb_strlen($idItem) > 600) {
                    return ['success' => false, 'message' => 'Item de inicialização inválido.'];
                }
                $ativar = $acao === 'reativar_inicializacao';
                $tipo = 'inicializacao_alternar';
                $parametro = json_encode(['tipo' => $tipoItem, 'id' => $idItem, 'ativar' => $ativar], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $descricao = ($ativar ? 'reativação' : 'desativação') . " de {$tipoItem} \"" . mb_substr((string)($dados['nome'] ?? $idItem), 0, 120) . '" na inicialização';
                break;

            case 'quarentena':
                $caminho = trim((string)($dados['caminho'] ?? ''));
                if (!preg_match('#^[A-Za-z]:\\\\.{3,}#', $caminho)) {
                    return ['success' => false, 'message' => 'Caminho inválido.'];
                }
                $tipo = 'arquivo_quarentena';
                $parametro = json_encode(['caminho' => $caminho, 'motivo' => mb_substr(trim((string)($dados['motivo'] ?? 'Pedido pelo portal')), 0, 300) . ' (por ' . $usuario . ')'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $descricao = 'quarentena de ' . $caminho;
                break;

            case 'confiar_programa':
            case 'desconfiar_programa':
                $caminho = trim((string)($dados['caminho'] ?? ''));
                if (!preg_match('#^[A-Za-z]:\\\\.{3,}#', $caminho)) {
                    return ['success' => false, 'message' => 'Caminho inválido.'];
                }
                if ($acao === 'confiar_programa') {
                    $descricaoPrograma = trim((string)($dados['descricao'] ?? ''));
                    if (mb_strlen($descricaoPrograma) < 3) {
                        return ['success' => false, 'message' => 'Escreva para que serve o programa.'];
                    }
                    $tipo = 'programa_confiar';
                    $parametro = json_encode(['caminho' => $caminho, 'descricao' => mb_substr($descricaoPrograma, 0, 300),
                        'portas' => preg_replace('/[^0-9, ]/', '', (string)($dados['portas'] ?? '')), 'por' => $usuario], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $descricao = "confiar em {$caminho} ({$descricaoPrograma})";
                } else {
                    $tipo = 'programa_desconfiar';
                    $parametro = json_encode(['caminho' => $caminho], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $descricao = "deixar de confiar em {$caminho}";
                }
                break;

            case 'ligar_firewall':
            case 'desligar_firewall':
                $perfil = (string)($dados['perfil'] ?? '');
                if ($perfil !== '' && !in_array($perfil, ['dominio', 'privado', 'publico'], true)) {
                    return ['success' => false, 'message' => 'Camada do firewall inválida.'];
                }
                $ligar = $acao === 'ligar_firewall';
                $tipo = 'firewall_perfil';
                $parametro = json_encode(['ligar' => $ligar, 'forcar' => !empty($dados['forcar']), 'perfil' => $perfil]);
                $descricao = ($ligar ? 'ligar' : 'desligar') . ' o firewall do Windows' . ($perfil !== '' ? " (camada {$perfil})" : ' (todas as camadas)');
                break;

            case 'encerrar_processo':
                $pid = (int)($dados['pid'] ?? 0);
                if ($pid <= 4) {
                    return ['success' => false, 'message' => 'Processo inválido.'];
                }
                $tipo = 'processo_encerrar';
                $parametro = json_encode(['pid' => $pid, 'caminho' => (string)($dados['caminho'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $descricao = 'encerramento do processo ' . $pid . (!empty($dados['caminho']) ? ' (' . $dados['caminho'] . ')' : '');
                break;
        }

        if ($tipo === null) {
            return ['success' => false, 'message' => 'Ação desconhecida.'];
        }

        $resultado = $this->solicitarListagem($ativoId, $tipo, $parametro, $usuario, $tipo !== 'seguranca_atualizar');
        if (!empty($resultado['success'])) {
            AuditService::registrar('Segurança', 'Monitor de conexões', "{$ativo['codigo_patrimonio']} ({$ativo['nome']}): {$usuario} pediu {$descricao}.");
        }

        return $resultado + ['message' => 'Pedido enviado à máquina.'];
    }

    public function solicitarListagem(int $ativoId, string $tipo, ?string $parametro, ?string $solicitadoPor = null, bool $elevado = false): array
    {
        if (!in_array($tipo, self::TIPOS_SOLICITACAO_VALIDOS, true)) {
            return ['success' => false, 'message' => 'Tipo de solicitação inválido.'];
        }

        if (in_array($tipo, ['baixar_arquivo', 'executar_cmd', 'executar_powershell', 'network_apply', 'network_revert', 'network_ping', 'network_traceroute', 'network_mtr'], true) && empty($parametro)) {
            return ['success' => false, 'message' => 'Informe o caminho do arquivo/comando.'];
        }

        if ($tipo === 'network_apply') {
            $config = json_decode((string)$parametro, true);
            if (!is_array($config) || (int)($config['adapter_id'] ?? 0) <= 0) {
                return ['success' => false, 'message' => 'Adaptador de rede inválido.'];
            }
            if (empty($config['dhcp'])) {
                foreach (['ip' => 'IP', 'mascara' => 'Máscara'] as $campo => $nome) {
                    if (!filter_var($config[$campo] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return ['success' => false, 'message' => "{$nome} IPv4 inválido."];
                    }
                }
                if (!empty($config['gateway']) && !filter_var($config['gateway'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return ['success' => false, 'message' => 'Gateway IPv4 inválido.'];
                }
                $dns = $config['dns'] ?? [];
                if (!is_array($dns) || count($dns) > 2) {
                    return ['success' => false, 'message' => 'Informe no máximo dois servidores DNS.'];
                }
                foreach ($dns as $servidorDns) {
                    if (!filter_var($servidorDns, FILTER_VALIDATE_IP)) {
                        return ['success' => false, 'message' => 'Endereço DNS inválido.'];
                    }
                }
            }
        } elseif ($tipo === 'network_revert' && (!ctype_digit((string)$parametro) || (int)$parametro <= 0)) {
            return ['success' => false, 'message' => 'Adaptador inválido para reversão.'];
        } elseif ($tipo === 'network_mtr') {
            // MTR aceita nome além de IP (o agente resolve) -- só letras, números, ponto e hífen.
            $pedido = json_decode((string)$parametro, true);
            $host = trim((string)($pedido['host'] ?? ''));
            $hostValido = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                || (strlen($host) <= 253 && preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i', $host));
            if (!is_array($pedido) || !$hostValido) {
                return ['success' => false, 'message' => 'Destino inválido -- informe um IPv4 ou um nome (ex.: google.com).'];
            }
            $ciclos = (int)($pedido['ciclos'] ?? 10);
            if ($ciclos < 5 || $ciclos > 60) {
                return ['success' => false, 'message' => 'Ciclos devem ficar entre 5 e 60.'];
            }
        } elseif (in_array($tipo, ['network_ping', 'network_traceroute'], true)) {
            $pedido = json_decode((string)$parametro, true);
            if (!is_array($pedido) || !filter_var($pedido['ip'] ?? '', FILTER_VALIDATE_IP)) {
                return ['success' => false, 'message' => 'Digite um endereço IP válido. Nomes de host não são aceitos.'];
            }
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

        if (in_array($tipo, ['network_apply', 'network_revert'], true)) {
            AuditService::registrar('Ativos', 'Configuração de rede', "Solicitação {$tipo} para {$ativo['codigo_patrimonio']} ({$ativo['nome']}).");
        }

        if ($tipo === 'mesh_install') {
            AuditService::registrar('Chamados', 'Suporte remoto', "Instalação assistida do MeshAgent solicitada para {$ativo['codigo_patrimonio']} ({$ativo['nome']}).");
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
        } else {
            $resultado = is_array($payload['resultado'] ?? null) ? $payload['resultado'] : [];
            $this->repository->marcarSolicitacaoConcluida($id, json_encode($resultado, JSON_UNESCAPED_UNICODE));
        }

        // Isolar/remover isolamento: só vale se a máquina confirmar (antes o portal marcava ao enviar).
        try {
            (new SegurancaIsolamentoService())->conferirResposta((int)$ativo['id'], $id, $payload);
        } catch (\Throwable) {
            // conferência é complementar: nunca pode impedir o registro da resposta
        }

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
