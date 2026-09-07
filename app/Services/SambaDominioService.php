<?php

namespace App\Services;

class SambaDominioService
{
    private const STATUS_DIR = '/var/www/rd.intranet/storage/samba_dc_status';

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * Unica fonte de verdade de "este servidor ja e um Controlador de
     * Dominio?" -- sempre lida ao vivo do sistema (nunca de uma flag salva
     * em banco, que poderia dessincronizar da realidade). Mesmo espirito
     * que SambaGlobalConfigService ja usa pro [global] standalone.
     */
    public function status(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_status_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (!is_array($dados)) {
            return [
                'is_dc' => false,
                'server_role' => '',
                'sam_ldb_existe' => false,
                'realm' => '',
                'workgroup' => '',
                'hostname' => '',
                'fqdn' => '',
                'servicos' => [],
                'erro' => 'Resposta inesperada do script: ' . $resultado['output'],
            ];
        }

        return $dados;
    }

    public function ehDC(): bool
    {
        return (bool)($this->status()['is_dc'] ?? false);
    }

    /**
     * Compoe o checklist de pre-requisitos do assistente a partir de
     * servicos que ja existem no app -- nenhuma logica de infraestrutura
     * nova aqui, so leitura/composicao.
     */
    public function checklistPreRequisitos(): array
    {
        $chavesRelevantes = ['samba', 'winbind', 'krb5-user', 'chrony'];

        $pacotes = array_values(array_filter(
            (new DependenciaService())->checklist(),
            fn (array $item) => in_array($item['chave'], $chavesRelevantes, true)
        ));

        $status = $this->status();
        $rede = new NetworkConfigService();
        $interfaces = [];
        foreach ($rede->interfacesValidas() as $nome) {
            $detalhes = $rede->detalhesInterface($nome);
            $interfaces[] = ['nome' => $nome, 'modo' => $detalhes['modo']];
        }

        return [
            'pacotes' => $pacotes,
            'hostname' => $status['hostname'] ?? '',
            'fqdn' => $status['fqdn'] ?? '',
            'interfaces' => $interfaces,
            'ip_estatico_ok' => empty($interfaces) ? false : !in_array('dhcp', array_column($interfaces, 'modo'), true),
        ];
    }

    public function aplicarHostname(string $novo): array
    {
        return (new NetworkConfigService())->aplicarHostname($novo);
    }

    /**
     * Dispara o provisionamento em segundo plano (samba-tool domain
     * provision demora minutos) e devolve na hora um id de execucao pra
     * acompanhar via statusProvisionamento() -- mesmo padrao de
     * SambaArquivosController::iniciarLote()/loteStatus(). A senha do
     * Administrator do dominio nunca passa por argv/banco/log -- vai so
     * pelo stdin do processo em segundo plano (LinuxService::
     * executarScriptEmSegundoPlanoComEntrada) e o script le com "read".
     */
    public function provisionar(string $realm, string $workgroup, string $senha, string $confirmacao): array
    {
        if ($this->ehDC()) {
            return ['success' => false, 'message' => 'Este servidor já é um Controlador de Domínio.'];
        }

        $realm = strtoupper(trim($realm));
        $workgroup = strtoupper(trim($workgroup));

        if (!preg_match('/^[A-Z0-9]+(\.[A-Z0-9]+)+$/', $realm)) {
            return ['success' => false, 'message' => 'Realm inválido. Use o formato de um domínio DNS, ex: EMPRESA.LOCAL.'];
        }

        if (!preg_match('/^[A-Z0-9-]{1,15}$/', $workgroup)) {
            return ['success' => false, 'message' => 'Workgroup/domínio NetBIOS inválido. Use letras maiúsculas, números e "-", até 15 caracteres.'];
        }

        if ($senha === '' || strlen($senha) < 8) {
            return ['success' => false, 'message' => 'A senha do Administrator precisa ter pelo menos 8 caracteres.'];
        }

        if ($senha !== $confirmacao) {
            return ['success' => false, 'message' => 'As senhas não coincidem.'];
        }

        $execucaoId = bin2hex(random_bytes(8));

        $this->linux->executarScriptEmSegundoPlanoComEntrada(
            '/opt/rdtecnologia/scripts/samba_dc_provisionar_web.sh',
            [$execucaoId, $realm, $workgroup, 'SAMBA_INTERNAL'],
            $senha
        );

        AuditService::registrar('Samba', 'Provisionar Controlador de Domínio', "Provisionamento iniciado (realm: {$realm}, domínio: {$workgroup}).");

        return ['success' => true, 'execucao_id' => $execucaoId];
    }

    public function statusProvisionamento(string $execucaoId): array
    {
        $id = preg_replace('/[^a-f0-9]/', '', $execucaoId);
        $arquivo = self::STATUS_DIR . "/{$id}.json";

        if ($id === '' || !is_file($arquivo)) {
            return ['status' => 'desconhecido'];
        }

        $dados = json_decode((string)file_get_contents($arquivo), true);

        return is_array($dados) ? $dados : ['status' => 'desconhecido'];
    }

    // ── Fase 2: gestão básica do domínio (só faz sentido depois de promovido) ──

    public function listarUsuarios(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_usuario_listar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : [];
    }

    public function criarUsuario(string $username, string $nomeCompleto, string $senha, string $confirmacao): array
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9._-]{0,19}$/', $username)) {
            return ['success' => false, 'message' => 'Nome de usuário inválido.'];
        }

        if ($senha === '' || strlen($senha) < 8) {
            return ['success' => false, 'message' => 'A senha precisa ter pelo menos 8 caracteres.'];
        }

        if ($senha !== $confirmacao) {
            return ['success' => false, 'message' => 'As senhas não coincidem.'];
        }

        $resultado = $this->linux->executarScriptComEntrada(
            '/opt/rdtecnologia/scripts/samba_dc_usuario_criar_web.sh',
            [$username, $nomeCompleto],
            $senha
        );

        $dados = json_decode(trim($resultado['output']), true);
        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: criar usuário', "Usuário de domínio \"{$username}\" criado.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function resetarSenha(string $username, string $senha, string $confirmacao): array
    {
        if ($senha === '' || strlen($senha) < 8) {
            return ['success' => false, 'message' => 'A senha precisa ter pelo menos 8 caracteres.'];
        }

        if ($senha !== $confirmacao) {
            return ['success' => false, 'message' => 'As senhas não coincidem.'];
        }

        $resultado = $this->linux->executarScriptComEntrada(
            '/opt/rdtecnologia/scripts/samba_dc_usuario_senha_web.sh',
            [$username],
            $senha
        );

        $dados = json_decode(trim($resultado['output']), true);
        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: resetar senha', "Senha do usuário de domínio \"{$username}\" redefinida.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function ativarUsuario(string $username): array
    {
        return $this->mudarEstadoUsuario($username, 'ativar');
    }

    public function desativarUsuario(string $username): array
    {
        return $this->mudarEstadoUsuario($username, 'desativar');
    }

    private function mudarEstadoUsuario(string $username, string $acao): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/samba_dc_usuario_estado_web.sh',
            [$username, $acao]
        );

        $dados = json_decode(trim($resultado['output']), true);
        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: ' . $acao . ' usuário', "Usuário de domínio \"{$username}\" " . ($acao === 'ativar' ? 'ativado' : 'desativado') . ".");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function listarGrupos(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_grupo_listar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : [];
    }

    public function criarGrupo(string $nome): array
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9._-]{0,63}$/', $nome)) {
            return ['success' => false, 'message' => 'Nome de grupo inválido.'];
        }

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_grupo_criar_web.sh', [$nome]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: criar grupo', "Grupo de domínio \"{$nome}\" criado.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function listarMembrosGrupo(string $grupo): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_grupo_membros_web.sh', [$grupo]);
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : [];
    }

    public function adicionarMembroGrupo(string $grupo, string $usuario): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_grupo_membro_adicionar_web.sh', [$grupo, $usuario]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: adicionar membro', "Usuário \"{$usuario}\" adicionado ao grupo \"{$grupo}\".");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function listarComputadores(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_computador_listar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : [];
    }
}
