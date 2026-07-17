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
}
