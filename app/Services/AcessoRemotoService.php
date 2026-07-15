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

    public function instalar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/meshcentral_instalar_web.sh');

        $dados = json_decode($resultado['output'], true);

        if (!is_array($dados)) {
            return ['success' => false, 'message' => 'Resposta inesperada do instalador: ' . $resultado['output']];
        }

        if (!empty($dados['success'])) {
            AuditService::registrar('Ativos', 'Acesso Remoto', 'MeshCentral instalado.');
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
}
