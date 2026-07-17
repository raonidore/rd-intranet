<?php

namespace App\Services;

/**
 * Integração com Microsoft Entra ID (Azure AD) via Microsoft Graph API --
 * autenticação OAuth2 client credentials (App Registration por tenant,
 * sem usuário/senha nenhum do lado do RD Intranet). Cliente HTTP é curl
 * puro (sem Guzzle/SDK -- este projeto não tem dependências no
 * composer.json, e não vale abrir exceção só pra isso).
 *
 * Fase atual: só gestão de usuários/licenças (ver, criar, resetar senha,
 * ativar/desativar, atribuir licença). Restringir login por máquina a
 * uma lista de usuários fica pra uma fase futura -- decisão explícita,
 * não esquecimento (ver plano da feature).
 */
class EntraService
{
    private const CHAVE_TENANT_ID = 'entra_tenant_id';
    private const CHAVE_CLIENT_ID = 'entra_client_id';
    private const CHAVE_CLIENT_SECRET_CIFRADO = 'entra_client_secret_cifrado';

    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function configurado(): bool
    {
        return $this->tenantIdAtual() !== ''
            && $this->clientIdAtual() !== ''
            && (ConfigService::get(self::CHAVE_CLIENT_SECRET_CIFRADO, '') ?: '') !== '';
    }

    public function tenantIdAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_TENANT_ID, '') ?: ''));
    }

    public function clientIdAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_CLIENT_ID, '') ?: ''));
    }

    public function salvarConfiguracao(string $tenantId, string $clientId, string $clientSecret): bool
    {
        $tenantId = trim($tenantId);
        $clientId = trim($clientId);
        $clientSecret = trim($clientSecret);

        if ($tenantId === '' || $clientId === '') {
            NotificationService::error('Informe o Tenant ID e o Client ID.');
            return false;
        }

        // Senha em branco mantém a atual -- só exige um secret novo quando ainda não havia nenhum.
        if ($clientSecret === '' && !$this->configurado()) {
            NotificationService::error('Informe o Client Secret.');
            return false;
        }

        ConfigService::set(self::CHAVE_TENANT_ID, $tenantId);
        ConfigService::set(self::CHAVE_CLIENT_ID, $clientId);
        if ($clientSecret !== '') {
            ConfigService::set(self::CHAVE_CLIENT_SECRET_CIFRADO, CryptoService::encriptar($clientSecret));
        }

        AuditService::registrar('Microsoft Entra', 'Configuração', "Configuração do tenant atualizada (tenant: {$tenantId}, client: {$clientId}).");
        NotificationService::success('Configuração salva.');

        return true;
    }

    public function removerConfiguracao(): void
    {
        ConfigService::set(self::CHAVE_TENANT_ID, '');
        ConfigService::set(self::CHAVE_CLIENT_ID, '');
        ConfigService::set(self::CHAVE_CLIENT_SECRET_CIFRADO, '');

        AuditService::registrar('Microsoft Entra', 'Configuração', 'Configuração do tenant removida.');
        NotificationService::success('Configuração removida.');
    }

    private function clientSecretAtual(): ?string
    {
        $cifrado = ConfigService::get(self::CHAVE_CLIENT_SECRET_CIFRADO, '');

        if (!$cifrado) {
            return null;
        }

        try {
            return CryptoService::decriptar($cifrado);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Token novo a cada chamada (client credentials) -- simples e
     * suficiente pro volume de uso esperado aqui; token do Graph dura
     * ~1h, então não há necessidade real de cache pra evitar throttling.
     */
    private function obterToken(): ?string
    {
        $secret = $this->clientSecretAtual();
        $tenantId = $this->tenantIdAtual();
        $clientId = $this->clientIdAtual();

        if ($secret === null || $tenantId === '' || $clientId === '') {
            return null;
        }

        $ch = curl_init("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resposta === false || $codigo !== 200) {
            return null;
        }

        $dados = json_decode($resposta, true);

        return $dados['access_token'] ?? null;
    }

    /**
     * @param array<string,mixed>|null $corpo
     * @return array{sucesso:bool, dados:mixed, mensagem:string}
     */
    private function chamarGraph(string $metodo, string $caminho, ?array $corpo = null): array
    {
        if (!$this->configurado()) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => 'Módulo Entra ainda não configurado -- veja Configuração.'];
        }

        $token = $this->obterToken();

        if ($token === null) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => 'Não foi possível autenticar no tenant -- confira Tenant ID / Client ID / Client Secret em Configuração.'];
        }

        $url = str_starts_with($caminho, 'https://') ? $caminho : self::GRAPH_BASE . $caminho;

        $cabecalhos = ['Authorization: Bearer ' . $token];
        if ($corpo !== null) {
            $cabecalhos[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $cabecalhos,
            CURLOPT_TIMEOUT => 20,
        ]);

        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
        }

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => "Erro de comunicação com a Microsoft: {$erroCurl}"];
        }

        $dados = $resposta !== '' ? json_decode($resposta, true) : null;

        if ($codigo >= 200 && $codigo < 300) {
            return ['sucesso' => true, 'dados' => $dados, 'mensagem' => ''];
        }

        $mensagemErro = $dados['error']['message'] ?? "Erro HTTP {$codigo} ao falar com a Microsoft Graph.";

        return ['sucesso' => false, 'dados' => null, 'mensagem' => $mensagemErro];
    }

    /** Nome, UPN, se a conta está ativa e quais licenças (skuId) tem atribuídas -- paginação seguida automaticamente. */
    public function listarUsuarios(): array
    {
        $todos = [];
        $proximaUrl = '/users?$select=id,displayName,userPrincipalName,accountEnabled,assignedLicenses&$top=999';

        while ($proximaUrl !== null) {
            $resultado = $this->chamarGraph('GET', $proximaUrl);

            if (!$resultado['sucesso']) {
                NotificationService::error('Erro ao listar usuários do Entra.', $resultado['mensagem']);
                return $todos;
            }

            foreach ($resultado['dados']['value'] ?? [] as $u) {
                $todos[] = $u;
            }

            $proximaUrl = $resultado['dados']['@odata.nextLink'] ?? null;
        }

        return $todos;
    }

    public function criarUsuario(string $nome, string $upn, string $senha): bool
    {
        $nome = trim($nome);
        $upn = trim($upn);

        if ($nome === '' || $upn === '' || trim($senha) === '') {
            NotificationService::error('Informe nome, UPN e senha inicial.');
            return false;
        }

        $mailNickname = explode('@', $upn)[0];

        $resultado = $this->chamarGraph('POST', '/users', [
            'accountEnabled' => true,
            'displayName' => $nome,
            'mailNickname' => $mailNickname,
            'userPrincipalName' => $upn,
            'passwordProfile' => [
                'forceChangePasswordNextSignIn' => true,
                'password' => $senha,
            ],
        ]);

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao criar usuário no Entra.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Criar usuário', "Usuário {$upn} criado.");
        NotificationService::success('Usuário criado no Entra.');

        return true;
    }

    public function resetarSenha(string $userId, string $upnParaAuditoria, string $novaSenha): bool
    {
        if (trim($novaSenha) === '') {
            NotificationService::error('Informe a nova senha.');
            return false;
        }

        $resultado = $this->chamarGraph('PATCH', '/users/' . rawurlencode($userId), [
            'passwordProfile' => [
                'forceChangePasswordNextSignIn' => true,
                'password' => $novaSenha,
            ],
        ]);

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao resetar senha.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Resetar senha', "Senha de {$upnParaAuditoria} resetada.");
        NotificationService::success('Senha resetada -- o usuário precisa trocar no próximo login.');

        return true;
    }

    public function ativarDesativar(string $userId, string $upnParaAuditoria, bool $ativo): bool
    {
        $resultado = $this->chamarGraph('PATCH', '/users/' . rawurlencode($userId), ['accountEnabled' => $ativo]);

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao atualizar usuário.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar(
            'Microsoft Entra',
            $ativo ? 'Ativar usuário' : 'Desativar usuário',
            "Usuário {$upnParaAuditoria} " . ($ativo ? 'ativado' : 'desativado') . '.'
        );
        NotificationService::success('Usuário atualizado.');

        return true;
    }

    /** SKUs de licença disponíveis no tenant (id, nome, quantidade usada/disponível) -- usado pra achar o SKU do F3 e mostrar consumo. */
    public function listarSkus(): array
    {
        $resultado = $this->chamarGraph('GET', '/subscribedSkus');

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao listar licenças do tenant.', $resultado['mensagem']);
            return [];
        }

        return $resultado['dados']['value'] ?? [];
    }

    public function atribuirLicenca(string $userId, string $upnParaAuditoria, string $skuId): bool
    {
        $resultado = $this->chamarGraph('POST', '/users/' . rawurlencode($userId) . '/assignLicense', [
            'addLicenses' => [['skuId' => $skuId]],
            'removeLicenses' => [],
        ]);

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao atribuir licença.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Atribuir licença', "Licença atribuída a {$upnParaAuditoria}.");
        NotificationService::success('Licença atribuída.');

        return true;
    }

    public function removerLicenca(string $userId, string $upnParaAuditoria, string $skuId): bool
    {
        $resultado = $this->chamarGraph('POST', '/users/' . rawurlencode($userId) . '/assignLicense', [
            'addLicenses' => [],
            'removeLicenses' => [$skuId],
        ]);

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao remover licença.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Remover licença', "Licença removida de {$upnParaAuditoria}.");
        NotificationService::success('Licença removida.');

        return true;
    }

    public function excluirUsuario(string $userId, string $upnParaAuditoria): bool
    {
        $resultado = $this->chamarGraph('DELETE', '/users/' . rawurlencode($userId));

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao excluir usuário.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Excluir usuário', "Usuário {$upnParaAuditoria} excluído.");
        NotificationService::success('Usuário excluído.');

        return true;
    }

    /** skuPartNumber (nome técnico do Graph, ex. "SPE_F1") -> nome comercial reconhecível. Não exaustivo -- só os planos mais comuns; nome técnico aparece como está pros demais. */
    private const NOMES_AMIGAVEIS_SKU = [
        'SPE_F1' => 'Microsoft 365 F3',
        'M365_F1' => 'Microsoft 365 F1',
        'SPB' => 'Microsoft 365 Business Premium',
        'O365_BUSINESS_PREMIUM' => 'Microsoft 365 Business Standard',
        'SPE_E3' => 'Microsoft 365 E3',
        'SPE_E5' => 'Microsoft 365 E5',
        'ENTERPRISEPACK' => 'Office 365 E3',
        'ENTERPRISEPREMIUM' => 'Office 365 E5',
        'STANDARDPACK' => 'Office 365 E1',
        'EXCHANGESTANDARD' => 'Exchange Online (Plan 1)',
        'EXCHANGEENTERPRISE' => 'Exchange Online (Plan 2)',
        'FLOW_FREE' => 'Power Automate (Free)',
        'POWER_BI_STANDARD' => 'Power BI (Free)',
    ];

    public static function nomeAmigavelSku(string $skuPartNumber): string
    {
        return self::NOMES_AMIGAVEIS_SKU[$skuPartNumber] ?? $skuPartNumber;
    }

    /*
     |---------------------------------------------------------
     | Restringir login local do Windows a uma lista de contas do Entra
     | -- não usa Graph API pra isso (é configuração local de cada
     | máquina, não do tenant). Gera um script PowerShell que mexe no
     | "User Rights Assignment" local (SeInteractiveLogonRight, o mesmo
     | direito da política "Allow log on locally") via secedit, já que
     | funciona sem precisar de Intune/licença adicional -- confirmado:
     | Windows aceita referenciar contas do Entra individualmente nessa
     | política via "AzureAD\usuario@tenant.com", só não aceita GRUPOS do
     | Entra por essa via (isso sim exigiria Intune). O script é entregue
     | pra cada ativo pelo mesmo canal de comando remoto já existente
     | (AtivoService::solicitarListagem, executar_powershell, elevado).
     |
     | Rede de segurança: o grupo local "Administradores" (SID universal
     | *S-1-5-32-544) SEMPRE entra na lista, então essa ação nunca tranca
     | o acesso de quem administra a máquina localmente -- e como essa
     | política só afeta login INTERATIVO no console (não afeta serviços,
     | tarefas agendadas nem o canal de comando remoto do nosso próprio
     | agente), mesmo uma aplicação errada continua sendo revertível
     | remotamente por aqui, sem precisar tocar fisicamente na máquina.
     |---------------------------------------------------------
     */

    private const SID_ADMINISTRADORES_LOCAIS = '*S-1-5-32-544';
    private const SID_USUARIOS_LOCAIS = '*S-1-5-32-545';

    private static function validarUpns(array $upns): array
    {
        return array_values(array_filter(array_map(function ($upn) {
            $upn = trim((string)$upn);
            return preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $upn) === 1 ? $upn : null;
        }, $upns)));
    }

    /** Script que aplica a restrição -- só os UPNs informados (+ administradores locais, sempre) podem logar localmente. */
    public static function gerarScriptRestricaoLogin(array $upnsPermitidos): string
    {
        $upns = self::validarUpns($upnsPermitidos);

        $direitos = array_merge(
            [self::SID_ADMINISTRADORES_LOCAIS],
            array_map(fn($upn) => 'AzureAD\\' . $upn, $upns)
        );

        return self::scriptSeceditLogonRight(
            implode(',', $direitos),
            'Restricao aplicada -- administradores locais + ' . count($upns) . ' conta(s) do Entra autorizadas.'
        );
    }

    /** Script que desfaz a restrição -- volta pro padrão comum do Windows (Administradores + Usuários locais). */
    public static function gerarScriptRemoverRestricaoLogin(): string
    {
        return self::scriptSeceditLogonRight(
            implode(',', [self::SID_ADMINISTRADORES_LOCAIS, self::SID_USUARIOS_LOCAIS]),
            'Restricao removida -- login local liberado pra Administradores e Usuarios locais novamente.'
        );
    }

    private static function scriptSeceditLogonRight(string $listaDireitos, string $mensagemFinal): string
    {
        $template = <<<'PS'
$listaCompleta = '__LISTA__'

$tempCfg = Join-Path $env:TEMP ("rd_secpol_" + [guid]::NewGuid().ToString("N") + ".inf")
$tempDb = Join-Path $env:TEMP ("rd_secpol_" + [guid]::NewGuid().ToString("N") + ".sdb")

try {
    $exportOut = secedit /export /cfg $tempCfg /areas USER_RIGHTS 2>&1
    if (-not (Test-Path $tempCfg)) {
        Write-Output "ERRO: falha ao exportar politica atual de seguranca -- $exportOut"
        exit 1
    }

    $linhas = Get-Content $tempCfg
    $encontrou = $false
    $novasLinhas = foreach ($linha in $linhas) {
        if ($linha -match '^SeInteractiveLogonRight\s*=') {
            $encontrou = $true
            "SeInteractiveLogonRight = $listaCompleta"
        } else {
            $linha
        }
    }

    if (-not $encontrou) {
        $comSecao = @()
        foreach ($linha in $novasLinhas) {
            $comSecao += $linha
            if ($linha -match '^\[Privilege Rights\]') {
                $comSecao += "SeInteractiveLogonRight = $listaCompleta"
            }
        }
        $novasLinhas = $comSecao
    }

    Set-Content -Path $tempCfg -Value $novasLinhas -Encoding Unicode

    $configureOut = secedit /configure /db $tempDb /cfg $tempCfg /areas USER_RIGHTS 2>&1

    if ($LASTEXITCODE -ne 0) {
        Write-Output "ERRO: secedit /configure falhou -- $configureOut"
        exit 1
    }

    Write-Output "__MENSAGEM__"
} finally {
    Remove-Item $tempCfg -ErrorAction SilentlyContinue
    Remove-Item $tempDb -ErrorAction SilentlyContinue
}
PS;

        return str_replace(['__LISTA__', '__MENSAGEM__'], [$listaDireitos, $mensagemFinal], $template);
    }
}
