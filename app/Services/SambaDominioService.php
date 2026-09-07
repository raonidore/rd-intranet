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

    public function criarUsuario(string $username, string $nomeCompleto, string $senha, string $confirmacao, string $email = '', string $telefone = '', string $descricao = ''): array
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
            [$username, $nomeCompleto, $email, $telefone, $descricao],
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

    public function excluirUsuario(string $username): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_usuario_excluir_web.sh', [$username]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: excluir usuário', "Usuário de domínio \"{$username}\" excluído.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function desbloquearUsuario(string $username): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_usuario_desbloquear_web.sh', [$username]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: desbloquear usuário', "Usuário de domínio \"{$username}\" desbloqueado.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    /** $dias null ou 0 => senha nunca expira. */
    public function definirExpiracaoSenha(string $username, ?int $dias): array
    {
        $valor = ($dias === null || $dias <= 0) ? 'nunca' : (string)$dias;

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_usuario_expiracao_web.sh', [$username, $valor]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: expiração de senha', "Expiração de senha de \"{$username}\" definida para " . ($valor === 'nunca' ? 'nunca expirar' : "{$valor} dia(s)") . ".");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function detalhesUsuario(string $username): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_usuario_detalhes_web.sh', [$username]);
        $dados = json_decode(trim($resultado['output']), true);

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

    public function removerMembroGrupo(string $grupo, string $usuario): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_grupo_membro_remover_web.sh', [$grupo, $usuario]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: remover membro', "Usuário \"{$usuario}\" removido do grupo \"{$grupo}\".");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function excluirGrupo(string $nome): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_grupo_excluir_web.sh', [$nome]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: excluir grupo', "Grupo de domínio \"{$nome}\" excluído.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function listarComputadores(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_computador_listar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : [];
    }

    public function excluirComputador(string $nome): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_computador_excluir_web.sh', [$nome]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: excluir computador', "Computador \"{$nome}\" removido do domínio.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    // ── Política de senha do domínio ────────────────────────────────────

    public function obterPoliticaSenha(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_politica_senha_ver_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function salvarPoliticaSenha(array $dados): array
    {
        $complexidade = !empty($dados['complexidade']) ? 'on' : 'off';

        $campos = ['historico', 'tamanho_minimo', 'idade_minima_dias', 'idade_maxima_dias', 'bloqueio_limite_tentativas', 'bloqueio_duracao_min', 'bloqueio_reset_min'];
        $valores = [];
        foreach ($campos as $campo) {
            $valor = $dados[$campo] ?? '';
            if (!preg_match('/^\d+$/', (string)$valor)) {
                return ['success' => false, 'message' => 'Valores numéricos inválidos.'];
            }
            $valores[] = (string)$valor;
        }

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_politica_senha_salvar_web.sh', array_merge([$complexidade], $valores));
        $resposta = json_decode(trim($resultado['output']), true);

        if (is_array($resposta) && $resposta['success']) {
            AuditService::registrar('Samba', 'Domínio: política de senha', 'Política de senha do domínio atualizada.');
        }

        return is_array($resposta) ? $resposta : ['success' => false, 'message' => $resultado['output']];
    }

    // ── Unidades Organizacionais (OUs) ──────────────────────────────────

    public function listarOus(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_ou_listar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : [];
    }

    public function criarOu(string $nome): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_ou_criar_web.sh', [$nome]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: criar OU', "OU \"{$nome}\" criada.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function excluirOu(string $ouDn): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_ou_excluir_web.sh', [$ouDn]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: excluir OU', "OU \"{$ouDn}\" excluída.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    // ── GPOs (mecânica: criar/excluir/vincular -- conteúdo da política
    // continua exigindo GPMC do Windows, não há como editar por aqui) ────

    public function listarGpos(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_gpo_listar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : [];
    }

    public function criarGpo(string $nome): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_gpo_criar_web.sh', [$nome]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: criar GPO', "GPO \"{$nome}\" criada.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function excluirGpo(string $guid): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_gpo_excluir_web.sh', [$guid]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: excluir GPO', "GPO \"{$guid}\" excluída.");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function vincularGpo(string $guid, string $containerDn): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_gpo_vincular_web.sh', [$guid, $containerDn]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: vincular GPO', "GPO \"{$guid}\" vinculada a \"{$containerDn}\".");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function desvincularGpo(string $guid, string $containerDn): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_gpo_desvincular_web.sh', [$guid, $containerDn]);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && $dados['success']) {
            AuditService::registrar('Samba', 'Domínio: desvincular GPO', "GPO \"{$guid}\" desvinculada de \"{$containerDn}\".");
        }

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function verificarAclSysvol(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/samba_dc_gpo_aclcheck_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}
