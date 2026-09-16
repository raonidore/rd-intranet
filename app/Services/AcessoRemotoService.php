<?php

namespace App\Services;

use App\Repositories\IptablesRegraRepository;

/**
 * Acesso remoto via MeshCentral (self-hosted, Apache 2.0,
 * https://github.com/Ylianst/MeshCentral) -- NÃO é construído do zero.
 * Roda como serviço systemd próprio (scripts/system/meshcentral_instalar_web.sh),
 * numa porta própria, com o MeshAgent instalado nas máquinas Windows
 * separadamente do nosso agente de inventário.
 */
class AcessoRemotoService
{
    private const MESHCTRL_PATH = '/opt/meshcentral/node_modules/meshcentral/meshctrl.js';

    /** Escrito pelo próprio script (roda em segundo plano, sem stdout capturado) -- ver instalarEmSegundoPlano()/statusInstalacao(). */
    private const STATUS_INSTALACAO_ARQUIVO = __DIR__ . '/../../storage/cache/meshcentral_instalacao.json';

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function porta(): int
    {
        return (int)(ConfigService::get('meshcentral_porta', '4430') ?? 4430);
    }

    /**
     * Credenciais são um Login Token do MeshCentral (Minha conta > Tokens de
     * login), não a senha da conta -- gerado manualmente pelo usuário no
     * console, já que a primeira conta admin só pode ser criada pelo
     * formulário de cadastro do próprio MeshCentral (limitação de segurança
     * dele, não algo que dá pra automatizar por API).
     */
    public function credenciaisConfiguradas(): bool
    {
        return (ConfigService::get('meshcentral_login_usuario', '') ?: '') !== ''
            && (ConfigService::get('meshcentral_login_senha', '') ?: '') !== '';
    }

    public function usuarioTokenAtual(): string
    {
        return ConfigService::get('meshcentral_login_usuario', '') ?: '';
    }

    public function salvarCredenciais(string $usuario, string $senha): bool
    {
        $usuario = trim($usuario);
        $senha = trim($senha);

        if ($usuario === '' || $senha === '') {
            NotificationService::error('Informe o usuário e a senha do Login Token gerado em Minha conta no MeshCentral.');
            return false;
        }

        ConfigService::set('meshcentral_login_usuario', $usuario);
        ConfigService::set('meshcentral_login_senha', $senha);

        AuditService::registrar('Ativos', 'Acesso Remoto', 'Credenciais de integração com o MeshCentral atualizadas.');
        NotificationService::success('Credenciais salvas.');

        return true;
    }

    /**
     * Executa o meshctrl (CLI de automação que já vem com o MeshCentral)
     * como www-data, sem sudo -- é só um cliente WebSocket, não precisa de
     * root. Conecta em 127.0.0.1 (mesma máquina), nunca sai pra fora.
     */
    private function executarMeshctrl(array $acaoEArgumentos): array
    {
        if (!$this->credenciaisConfiguradas()) {
            return ['success' => false, 'message' => 'Credenciais do MeshCentral não configuradas.'];
        }

        $cmd = 'node ' . escapeshellarg(self::MESHCTRL_PATH);

        foreach ($acaoEArgumentos as $parte) {
            $cmd .= ' ' . escapeshellarg((string)$parte);
        }

        $cmd .= ' --loginuser ' . escapeshellarg($this->usuarioTokenAtual());
        $cmd .= ' --loginpass ' . escapeshellarg(ConfigService::get('meshcentral_login_senha', '') ?: '');
        $cmd .= ' --url ' . escapeshellarg('wss://127.0.0.1:' . $this->porta());
        $cmd .= ' --json';

        $resultado = $this->linux->executar($cmd);
        $dados = json_decode($resultado['output'], true);

        if ($dados === null) {
            return ['success' => false, 'message' => 'Falha ao comunicar com o MeshCentral: ' . $resultado['output']];
        }

        return ['success' => true, 'data' => $dados];
    }

    public function listarDispositivos(): array
    {
        $resultado = $this->executarMeshctrl(['ListDevices']);

        if (!$resultado['success'] || !is_array($resultado['data'])) {
            return [];
        }

        return $resultado['data'];
    }

    /**
     * @return array<string, string> meshid (só a parte depois de "mesh/<dominio>/") => nome do grupo
     */
    public function listarGruposDispositivos(): array
    {
        $resultado = $this->executarMeshctrl(['ListDeviceGroups']);

        if (!$resultado['success'] || !is_array($resultado['data'])) {
            return [];
        }

        $grupos = [];
        foreach ($resultado['data'] as $grupo) {
            // "_id" vem como "mesh/<dominio>/<meshid>" -- dominio vazio no
            // domínio padrão (único usado aqui). O "/meshagents?...&meshid="
            // do servidor espera só a última parte.
            $partes = explode('/', (string)($grupo['_id'] ?? ''), 3);
            $meshId = $partes[2] ?? '';

            if ($meshId !== '') {
                $grupos[$meshId] = $grupo['name'] ?? $meshId;
            }
        }

        return $grupos;
    }

    /**
     * Gera um link de compartilhamento de uso único (sem exigir login
     * separado no MeshCentral -- é a peça que permite embutir a tela
     * remota num iframe na ficha do ativo). Inclui desktop + arquivos +
     * terminal (--type desktop,files,terminal), então o mesmo link já dá
     * área de trabalho, subir/baixar arquivo e shell remoto, sem precisar
     * gerar outro link pra cada coisa. Área de transferência (clipboard)
     * do sistema fica de fora -- o próprio MeshCentral desabilita isso na
     * página de link de convidado (`QV('DeskClip', false)` no código
     * deles), só existe no console completo com login de verdade.
     * Diferente dos outros comandos do meshctrl, "DeviceSharing --add"
     * NÃO respeita --json (bug/limitação da própria ferramenta --
     * confirmado testando ao vivo), sempre devolve texto simples
     * "ID: ...\nURL: ...".
     */
    public function gerarLinkCompartilhamento(string $meshDeviceId, string $convidado, int $duracaoMinutos = 60): ?string
    {
        if (!$this->credenciaisConfiguradas()) {
            return null;
        }

        $cmd = 'node ' . escapeshellarg(self::MESHCTRL_PATH);
        $cmd .= ' DeviceSharing';
        $cmd .= ' --id ' . escapeshellarg($meshDeviceId);
        $cmd .= ' --add ' . escapeshellarg($convidado);
        $cmd .= ' --type desktop,files,terminal';
        $cmd .= ' --consent notify';
        $cmd .= ' --duration ' . escapeshellarg((string)$duracaoMinutos);
        $cmd .= ' --loginuser ' . escapeshellarg($this->usuarioTokenAtual());
        $cmd .= ' --loginpass ' . escapeshellarg(ConfigService::get('meshcentral_login_senha', '') ?: '');
        $cmd .= ' --url ' . escapeshellarg('wss://127.0.0.1:' . $this->porta());

        $resultado = $this->linux->executar($cmd);

        if (!preg_match('/^URL:\s*(\S+)/m', $resultado['output'], $m)) {
            return null;
        }

        // O MeshCentral monta a URL com o hostname do certificado (fixo,
        // "meshcentral"), que não resolve no navegador de quem acessa --
        // troca pelo mesmo host:porta usados pra abrir o console.
        return preg_replace('~^https?://[^/]+~', rtrim($this->urlConsole(), '/'), $m[1]);
    }

    public function instalado(): bool
    {
        $resultado = $this->linux->executar('systemctl list-unit-files meshcentral.service --no-legend 2>/dev/null');

        return $resultado['success'] && str_contains($resultado['output'], 'meshcentral.service');
    }

    public function rodando(): bool
    {
        $resultado = $this->linux->executar('systemctl is-active meshcentral 2>/dev/null');

        return trim($resultado['output']) === 'active';
    }

    /**
     * Dispara a instalação em segundo plano (npm install do MeshCentral
     * sozinho já passa de 1 minuto) e volta na hora -- a tela acompanha
     * via statusInstalacao(), tanto por polling quanto ao carregar/
     * recarregar a página, pra nunca dar a impressão de ter travado.
     */
    public function instalarEmSegundoPlano(): void
    {
        @unlink(self::STATUS_INSTALACAO_ARQUIVO);
        $this->linux->executarScriptEmSegundoPlano('/opt/rdtecnologia/scripts/meshcentral_instalar_web.sh');
    }

    /**
     * @return array{status: string, etapa?: string, percentual?: int, mensagem?: string, iniciado_em?: int, atualizado_em?: int}
     *   status: "ausente" (nunca rodou, ou já foi consultado até concluir e a tela seguiu em frente),
     *   "rodando", "concluido" ou "erro".
     */
    public function statusInstalacao(): array
    {
        $conteudo = @file_get_contents(self::STATUS_INSTALACAO_ARQUIVO);
        $dados = $conteudo !== false ? json_decode($conteudo, true) : null;

        if (!is_array($dados)) {
            return ['status' => 'ausente'];
        }

        if (($dados['status'] ?? '') === 'concluido' && !empty($dados['mensagem'])) {
            AuditService::registrar('Ativos', 'Acesso Remoto', 'MeshCentral instalado.');
            // Só registra uma vez -- limpa pra essa checagem não repetir a
            // cada novo carregamento da tela depois de já concluído.
            @unlink(self::STATUS_INSTALACAO_ARQUIVO);
        }

        return $dados;
    }

    /**
     * "rede" (CommonName do cert do MeshCentral tem ponto) alcança qualquer
     * VLAN roteada até o servidor; "lan" (sem ponto) faz o MeshCentral
     * entrar sozinho em modo LAN-only, e os agentes só se registram por
     * broadcast/multicast local -- nunca atravessam VLAN roteada, mesmo com
     * a porta acessível. Ver scripts/system/meshcentral_configurar_rede_web.sh.
     */
    public function modoRedeAtual(): string
    {
        $config = @file_get_contents('/opt/meshcentral/meshcentral-data/config.json');
        $dados = $config !== false ? json_decode($config, true) : null;
        $cert = (string)($dados['settings']['cert'] ?? '');

        return str_contains($cert, '.') ? 'rede' : 'lan';
    }

    public function configurarModoRede(string $modo): array
    {
        if ($modo !== 'lan' && $modo !== 'rede') {
            return ['success' => false, 'message' => 'Modo inválido.'];
        }

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/meshcentral_configurar_rede_web.sh', [$modo]);
        $dados = json_decode($resultado['output'], true);

        if (!is_array($dados)) {
            return ['success' => false, 'message' => 'Resposta inesperada ao trocar o modo de rede: ' . $resultado['output']];
        }

        if (!empty($dados['success'])) {
            $label = $modo === 'rede' ? 'Toda a rede (inclusive VLANs)' : 'Somente rede local';
            AuditService::registrar('Ativos', 'Acesso Remoto', "Modo de alcance do MeshCentral alterado para: {$label}.");
        }

        return $dados;
    }

    public function urlConsole(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = explode(':', $host)[0];

        return "https://{$host}:{$this->porta()}/";
    }

    public function portaLiberadaNoFirewall(): bool
    {
        $repo = new IptablesRegraRepository();
        $porta = (string)$this->porta();

        foreach ($repo->buscarPorOrigemTemplate('liberar_porta') as $regra) {
            if (!empty($regra['ativo']) && (string)($regra['porta_destino'] ?? '') === $porta) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cria e aplica (via o módulo de Firewall já existente, mesmo template
     * "liberar_porta" usado na tela de Infraestrutura) uma regra ACCEPT de
     * entrada pra porta do MeshCentral. Confirma na hora em vez de deixar
     * pendente na janela de rollback do módulo de Firewall -- liberar porta
     * é aditivo (ACCEPT), não corre o risco de travar o acesso do admin que
     * justifica a confirmação manual pras regras de bloqueio.
     */
    public function liberarPortaNoFirewall(): array
    {
        $firewall = new IptablesService();

        $resultado = $firewall->aplicarTemplate('liberar_porta', [
            'protocolo' => 'tcp',
            'porta' => (string)$this->porta(),
        ]);

        if (!$resultado['success']) {
            return $resultado;
        }

        $confirmado = $firewall->confirmar();

        if ($confirmado['success']) {
            AuditService::registrar('Ativos', 'Acesso Remoto', "Porta {$this->porta()}/tcp liberada no Firewall pra acesso ao MeshCentral.");
        }

        return $confirmado;
    }

    /*
     |---------------------------------------------------------
     | Instaladores do MeshAgent -- o próprio MeshCentral oferece 3
     | variantes (x86-32, x86-64, ARM-64) no diálogo "Adicionar Agente
     | Mesh" do console dele. Hospedar aqui evita ter que entrar no
     | console só pra baixar o instalador de novo em cada máquina.
     |---------------------------------------------------------
     */
    public const ARQUITETURAS_MESH_AGENTE = [
        'x86' => 'Windows x86-32 (.exe)',
        'x64' => 'Windows x86-64 (.exe)',
        'arm64' => 'Windows ARM-64 (.exe)',
    ];

    /**
     * IDs internos do MeshCentral pra cada variante "service" (a que
     * instala como serviço do Windows, mesma coisa que o diálogo
     * "Adicionar Agente Mesh" do console oferece) -- extraídos direto de
     * meshcentral.js (obj.meshAgentsArchitectureNumbers) da versão
     * instalada, não de documentação genérica: 3 = Windows x86-32
     * service, 4 = Windows x86-64 service, 43 = Windows ARM-64 service.
     * As variantes "console" (1/2/42, sem instalação como serviço) e
     * tudo que não é win32 ficam de fora de propósito.
     */
    private const ARQUITETURA_MESHCENTRAL_ID = [
        'x86' => 3,
        'x64' => 4,
        'arm64' => 43,
    ];

    private function caminhoMeshAgente(string $arquitetura): ?string
    {
        if (!isset(self::ARQUITETURAS_MESH_AGENTE[$arquitetura])) {
            return null;
        }

        return __DIR__ . "/../../storage/uploads/mesh/{$arquitetura}.exe";
    }

    public function meshAgenteDisponivel(string $arquitetura): bool
    {
        $caminho = $this->caminhoMeshAgente($arquitetura);

        return $caminho !== null && file_exists($caminho);
    }

    public function caminhoMeshAgentePublico(string $arquitetura): ?string
    {
        return $this->meshAgenteDisponivel($arquitetura) ? $this->caminhoMeshAgente($arquitetura) : null;
    }

    public function salvarMeshAgente(string $arquitetura, string $caminhoTemporario): array
    {
        $destino = $this->caminhoMeshAgente($arquitetura);

        if ($destino === null) {
            NotificationService::error('Arquitetura inválida.');
            return ['success' => false];
        }

        if (!is_uploaded_file($caminhoTemporario)) {
            NotificationService::error('Upload inválido.');
            return ['success' => false];
        }

        $pasta = dirname($destino);

        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Falha ao criar a pasta de destino no servidor.');
            return ['success' => false];
        }

        if (!@move_uploaded_file($caminhoTemporario, $destino)) {
            NotificationService::error('Falha ao salvar o arquivo no servidor (permissão de escrita?).');
            return ['success' => false];
        }

        $label = self::ARQUITETURAS_MESH_AGENTE[$arquitetura];
        AuditService::registrar('Ativos', 'Acesso Remoto', "Instalador do MeshAgent enviado: {$label}.");
        NotificationService::success("Instalador \"{$label}\" enviado.");

        return ['success' => true];
    }

    /**
     * Busca os 3 instaladores direto do MeshCentral (endpoint nativo
     * /meshagents, confirmado ao vivo contra a instalação real -- exige
     * "meshid" só pras variantes Windows, e sem isso o .exe volta genérico,
     * sem servidor/grupo embutido, inútil pra instalar numa máquina sem
     * configuração manual extra). O binário já sai customizado pro grupo
     * escolhido -- a máquina entra nele sozinha ao rodar o instalador,
     * sem precisar digitar nada. Salva no mesmo lugar do upload manual
     * (storage/uploads/mesh/{arquitetura}.exe), então o resto da tela
     * (download, indicador de "enviado") não muda nada.
     */
    public function baixarMeshAgentesAutomaticamente(string $grupoMeshId): array
    {
        if (!$this->credenciaisConfiguradas()) {
            return ['success' => false, 'message' => 'Configure as credenciais de integração antes.'];
        }

        $grupos = $this->listarGruposDispositivos();
        if (!isset($grupos[$grupoMeshId])) {
            return ['success' => false, 'message' => 'Grupo de dispositivos não encontrado -- atualize a página e tente de novo.'];
        }

        $meshIdCodificado = rawurlencode($grupoMeshId);
        $baixados = [];
        $falhas = [];

        foreach (self::ARQUITETURA_MESHCENTRAL_ID as $arquitetura => $agentId) {
            $url = 'https://127.0.0.1:' . $this->porta() . "/meshagents?id={$agentId}&meshid={$meshIdCodificado}";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false, // certificado autoassinado, conexao fica em 127.0.0.1
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30,
            ]);
            $conteudo = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Um .exe de verdade do MeshAgent nunca sai abaixo de ~1MB --
            // corpo pequeno aqui é sinal de página de erro (401/404) em
            // vez do binário, não vale a pena sobrescrever o que já
            // existia com isso.
            if ($conteudo === false || $httpCode !== 200 || strlen($conteudo) < 500000) {
                $falhas[] = self::ARQUITETURAS_MESH_AGENTE[$arquitetura];
                continue;
            }

            $destino = $this->caminhoMeshAgente($arquitetura);
            $pasta = dirname($destino);
            if (!is_dir($pasta)) {
                @mkdir($pasta, 0777, true);
            }
            file_put_contents($destino, $conteudo);
            $baixados[] = self::ARQUITETURAS_MESH_AGENTE[$arquitetura];
        }

        if (empty($baixados)) {
            return ['success' => false, 'message' => 'Falha ao baixar os instaladores do MeshCentral -- confira se o grupo ainda existe.'];
        }

        AuditService::registrar('Ativos', 'Acesso Remoto', 'Instaladores do MeshAgent baixados automaticamente do MeshCentral (grupo "' . $grupos[$grupoMeshId] . '").');

        $mensagem = empty($falhas)
            ? 'Os 3 instaladores foram baixados automaticamente do MeshCentral.'
            : 'Baixados: ' . implode(', ', $baixados) . '. Falharam: ' . implode(', ', $falhas) . '.';

        return ['success' => true, 'message' => $mensagem];
    }
}
