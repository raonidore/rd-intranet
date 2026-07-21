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
     * Colunas disponíveis em Ativos > Lista -- controla o menu de "quais
     * colunas mostrar" (visibilidade é só client-side/localStorage, não
     * precisa de round-trip; ordenação usa 'ordenar', que é validado contra
     * AtivoRepository::ORDENACAO_PERMITIDA antes de virar SQL). 'condicao'
     * é o status Ligado/Desligado ao vivo -- calculado, não tem coluna própria
     * pra ordenar por ela. 'ip' e 'so' são as duas colunas novas pedidas,
     * por isso nascem desmarcadas por padrão (não muda a visão de quem já
     * usa a tela hoje até a pessoa optar por ligar).
     */
    public const COLUNAS_LISTA = [
        'codigo' => ['label' => 'Código', 'ordenar' => 'codigo_patrimonio', 'padrao' => true],
        'nome' => ['label' => 'Nome', 'ordenar' => 'nome', 'padrao' => true],
        'apelido' => ['label' => 'Apelido', 'ordenar' => 'apelido', 'padrao' => true],
        'tipo' => ['label' => 'Tipo', 'ordenar' => 'tipo', 'padrao' => true],
        'status' => ['label' => 'Status', 'ordenar' => 'status', 'padrao' => true],
        'condicao' => ['label' => 'Condição', 'ordenar' => null, 'padrao' => true],
        'setor' => ['label' => 'Setor', 'ordenar' => 'setor_nome', 'padrao' => true],
        'localizacao' => ['label' => 'Localização', 'ordenar' => 'localizacao_nome', 'padrao' => true],
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
        $tipo = $post['tipo'] ?? '';

        if (!isset(self::TIPOS[$tipo])) {
            NotificationService::error('Tipo de ativo inválido.');
            return null;
        }

        $dados = [
            'tipo' => $tipo,
            'codigo_patrimonio' => $this->proximoCodigo($tipo),
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
            'apelido' => trim($post['apelido'] ?? '') ?: null,
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

    public function listarPortasRede(int $ativoId): array
    {
        return $this->repository->listarPortasRede($ativoId);
    }

    private function proximoCodigo(string $tipo): string
    {
        $prefixo = self::TIPOS[$tipo]['prefixo'];
        $ultimo = $this->repository->ultimoCodigoPorTipo($tipo);

        $numero = 0;
        if ($ultimo && preg_match('/(\d+)$/', $ultimo, $m)) {
            $numero = (int)$m[1];
        }

        return sprintf('%s-%s-%0' . $this->codigoDigitos() . 'd', $this->siglaEmpresa(), $prefixo, $numero + 1);
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

        $tipo = in_array($payload['tipo'] ?? '', ['computador', 'servidor'], true) ? $payload['tipo'] : 'computador';

        $existente = $this->repository->buscarPorMachineGuid($machineGuid);

        $camposBase = [
            'nome' => trim((string)($payload['nome'] ?? '')) ?: ($existente['nome'] ?? 'Ativo sem nome'),
            'marca' => trim((string)($payload['marca'] ?? '')) ?: null,
            'modelo' => trim((string)($payload['modelo'] ?? '')) ?: null,
            'numero_serie' => trim((string)($payload['numero_serie'] ?? '')) ?: null,
            'ip' => trim((string)($payload['ip'] ?? '')) ?: null,
            'agente_versao' => trim((string)($payload['versao_agente'] ?? '')) ?: null,
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
        $this->repository->substituirPortasRede($id, array_slice($payload['portas_rede'] ?? [], 0, 300));
        $this->repository->substituirMemoria($id, array_slice($payload['memoria_modulos'] ?? [], 0, 32));
        $this->repository->substituirAtualizacoesWindows($id, array_slice($payload['atualizacoes_windows'] ?? [], 0, 500));

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
