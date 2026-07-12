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
            'armazenamento' => 'Armazenamento',
            'placa_mae' => 'Placa-mãe',
            'usuario_logado' => 'Usuário',
            'snmp_sys_descr' => 'Descrição (SNMP)',
            'snmp_uptime' => 'Uptime (SNMP)',
        ],
        'servidor' => [
            'sistema_operacional' => 'Sistema operacional',
            'processador' => 'Processador',
            'memoria_ram' => 'Memória RAM',
            'armazenamento' => 'Armazenamento',
            'funcao' => 'Função',
            'virtualizado' => 'Virtualizado',
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
            'setor' => trim($post['setor'] ?? '') ?: null,
            'localizacao' => trim($post['localizacao'] ?? '') ?: null,
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
            'setor' => trim($post['setor'] ?? '') ?: null,
            'localizacao' => trim($post['localizacao'] ?? '') ?: null,
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

    private function proximoCodigo(string $tipo): string
    {
        $prefixo = self::TIPOS[$tipo]['prefixo'];
        $ultimo = $this->repository->ultimoCodigoPorTipo($tipo);

        $numero = 0;
        if ($ultimo && preg_match('/(\d+)$/', $ultimo, $m)) {
            $numero = (int)$m[1];
        }

        return sprintf('RD-%s-%06d', $prefixo, $numero + 1);
    }

    private function extrairDetalhes(string $tipo, array $post): array
    {
        $campos = self::CAMPOS_DETALHES[$tipo] ?? [];
        $detalhes = [];

        foreach (array_keys($campos) as $campo) {
            $valor = trim($post[$campo] ?? '');
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
}
