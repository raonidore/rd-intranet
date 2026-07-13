<?php

namespace App\Services;

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
     * remota num iframe na ficha do ativo). Inclui desktop + arquivos
     * (--type desktop,files), então o mesmo link já dá acesso a subir/
     * baixar arquivos da máquina remota, sem precisar gerar outro.
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
        $cmd .= ' --type desktop,files';
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
}
