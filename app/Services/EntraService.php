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

        // Sempre seta POSTFIELDS (mesmo vazio) em métodos com corpo -- CUSTOMREQUEST sozinho não
        // manda Content-Length, e a API do Intune (syncDevice, rebootNow...) exige esse header
        // mesmo sem corpo, senão devolve 411 Length Required.
        if (in_array($metodo, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo !== null ? json_encode($corpo) : '');
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

        // Graph "normal" (users, subscribedSkus...) usa {"error":{"message":...}}; a API do Intune
        // (deviceManagement/*) usa um envelope próprio {"Message":...}, sem o wrapper "error".
        $mensagemErro = $dados['error']['message'] ?? $dados['Message'] ?? "Erro HTTP {$codigo} ao falar com a Microsoft Graph.";

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
     | funciona sem precisar de Intune/licença adicional. O script é
     | entregue pra cada ativo pelo mesmo canal de comando remoto já
     | existente (AtivoService::solicitarListagem, executar_powershell,
     | elevado).
     |
     | Contas e grupos entram pelo SID, nunca pelo nome: nome
     | ("AzureAD\usuario@tenant.com") só resolve pra quem já logou
     | naquela máquina, e grupo do Entra não resolve por nome nenhum. O
     | SID de usuário/grupo do Entra numa máquina ingressada é derivado
     | do objectId (S-1-12-1-...), e o token de login já traz os SIDs dos
     | grupos de segurança do usuário -- então autorizar um GRUPO funciona
     | e incluir/tirar alguém vira só editar o grupo no Entra (vale no
     | próximo login; sessão aberta renova os grupos em até 4h).
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

    /** SID que o Windows usa pra um usuário/grupo do Entra numa máquina ingressada: S-1-12-1 + os 16 bytes do objectId (ordem do .NET Guid.ToByteArray) lidos como 4 inteiros de 32 bits. */
    public static function sidDoObjeto(string $objectId): ?string
    {
        $objectId = strtolower(trim($objectId));
        if (!preg_match('/^([0-9a-f]{8})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{12})$/', $objectId, $p)) {
            return null;
        }

        $bytes = strrev(hex2bin($p[1])) . strrev(hex2bin($p[2])) . strrev(hex2bin($p[3])) . hex2bin($p[4] . $p[5]);

        return 'S-1-12-1-' . implode('-', unpack('V4', $bytes));
    }

    /** SID -> UPN de todos os usuários do tenant (pra trilha de acesso mostrar a conta do Entra no log do Windows). Sem notificação: roda também pelo cron. */
    public function mapaSidUpn(): array
    {
        $mapa = [];
        $proxima = '/users?$select=id,userPrincipalName&$top=999';
        while ($proxima !== null) {
            $r = $this->chamarGraph('GET', $proxima);
            if (!$r['sucesso']) {
                return $mapa;
            }
            foreach ($r['dados']['value'] ?? [] as $u) {
                if ($sid = self::sidDoObjeto($u['id'])) {
                    $mapa[$sid] = $u['userPrincipalName'];
                }
            }
            $proxima = $r['dados']['@odata.nextLink'] ?? null;
        }

        return $mapa;
    }

    /** App "Windows Sign In" -- login no Windows de máquina ingressada no Entra. */
    private const APP_WINDOWS_SIGN_IN = '38aa3b87-a06d-4817-b275-7a316988d93b';

    /**
     * Registro de entradas do Entra (auditLogs/signIns) de login no Windows desde $desdeUtc
     * (ISO 8601). Exige AuditLog.Read.All + Entra ID P1; o Entra retém só 30 dias.
     *
     * @return array{sucesso:bool, itens:array, mensagem:string}
     */
    public function entradasNoWindows(string $desdeUtc): array
    {
        $filtro = rawurlencode("appId eq '" . self::APP_WINDOWS_SIGN_IN . "' and createdDateTime ge {$desdeUtc}");
        $proxima = '/auditLogs/signIns?$filter=' . $filtro . '&$top=500';
        $itens = [];

        while ($proxima !== null) {
            $r = $this->chamarGraph('GET', $proxima);
            if (!$r['sucesso']) {
                return ['sucesso' => false, 'itens' => $itens, 'mensagem' => $r['mensagem']];
            }
            array_push($itens, ...($r['dados']['value'] ?? []));
            $proxima = $r['dados']['@odata.nextLink'] ?? null;
        }

        return ['sucesso' => true, 'itens' => $itens, 'mensagem' => ''];
    }

    /** Grupos de segurança do tenant (os únicos que entram no token de login do Windows). */
    public function listarGruposSeguranca(): array
    {
        $resultado = $this->chamarGraph('GET', '/groups?$filter=securityEnabled%20eq%20true&$select=id,displayName,description&$top=999');

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao listar grupos do Entra.', $resultado['mensagem']);
            return [];
        }

        $grupos = $resultado['dados']['value'] ?? [];
        usort($grupos, fn($a, $b) => strcasecmp($a['displayName'] ?? '', $b['displayName'] ?? ''));

        return $grupos;
    }

    /** Membros diretos de um grupo (id, nome, UPN), pra mostrar na tela quem o grupo libera. */
    public function membrosDoGrupo(string $grupoId): array
    {
        $resultado = $this->chamarGraph('GET', '/groups/' . rawurlencode($grupoId) . '/members?$select=id,displayName,userPrincipalName&$top=999');

        return $resultado['sucesso'] ? ($resultado['dados']['value'] ?? []) : [];
    }

    /** Ajuda pra quando o App Registration ainda não tem Group.ReadWrite.All (só lê grupos). */
    private function mensagemPermissaoGrupos(string $mensagem): string
    {
        return stripos($mensagem, 'privileges') !== false || stripos($mensagem, 'authorization') !== false
            ? 'O App Registration do portal não tem permissão para alterar grupos. No Entra: Registros de aplicativo > (app do RD.Intranet) > Permissões de API > Adicionar > Microsoft Graph > Permissões de aplicativo > Group.ReadWrite.All, e depois "Conceder consentimento do administrador".'
            : $mensagem;
    }

    public function criarGrupo(string $nome, string $descricao): ?string
    {
        $nome = trim($nome);
        if ($nome === '') {
            NotificationService::error('Informe o nome do grupo.');
            return null;
        }

        // mailNickname é obrigatório mesmo em grupo sem e-mail -- só letras/números, único no tenant.
        $apelido = substr(preg_replace('/[^a-z0-9]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: '')), 0, 40) ?: 'grupo';
        $apelido .= substr(bin2hex(random_bytes(3)), 0, 6);

        $corpo = ['displayName' => $nome, 'mailEnabled' => false, 'mailNickname' => $apelido, 'securityEnabled' => true];
        if (trim($descricao) !== '') {
            $corpo['description'] = trim($descricao);
        }

        $resultado = $this->chamarGraph('POST', '/groups', $corpo);
        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao criar o grupo.', $this->mensagemPermissaoGrupos($resultado['mensagem']));
            return null;
        }

        AuditService::registrar('Microsoft Entra', 'Criar grupo', "Grupo de segurança \"{$nome}\" criado.");
        NotificationService::success("Grupo \"{$nome}\" criado. Agora defina os membros.");

        return $resultado['dados']['id'] ?? null;
    }

    /** Deixa o grupo com exatamente os membros informados (adiciona os que faltam, remove os que sobram). */
    public function definirMembros(string $grupoId, string $nomeGrupo, array $usuarioIds): bool
    {
        $desejados = array_values(array_unique(array_filter($usuarioIds, fn($id) => self::sidDoObjeto((string)$id) !== null)));
        // Só mexe em membros que são usuários -- grupo aninhado/dispositivo posto pelo console fica.
        $atuais = array_column(array_filter($this->membrosDoGrupo($grupoId), fn($m) => ($m['@odata.type'] ?? '') === '#microsoft.graph.user'), 'id');

        $adicionar = array_diff($desejados, $atuais);
        $remover = array_diff($atuais, $desejados);
        $erros = [];

        foreach ($adicionar as $id) {
            $r = $this->chamarGraph('POST', '/groups/' . rawurlencode($grupoId) . '/members/$ref', ['@odata.id' => self::GRAPH_BASE . '/directoryObjects/' . $id]);
            if (!$r['sucesso']) {
                $erros[] = $r['mensagem'];
            }
        }
        foreach ($remover as $id) {
            $r = $this->chamarGraph('DELETE', '/groups/' . rawurlencode($grupoId) . '/members/' . rawurlencode($id) . '/$ref');
            if (!$r['sucesso']) {
                $erros[] = $r['mensagem'];
            }
        }

        if ($erros) {
            NotificationService::error('Nem todos os membros foram alterados.', $this->mensagemPermissaoGrupos($erros[0]));
            return false;
        }

        if ($adicionar || $remover) {
            AuditService::registrar('Microsoft Entra', 'Membros do grupo', "Grupo \"{$nomeGrupo}\": " . count($adicionar) . ' adicionado(s), ' . count($remover) . ' removido(s).');
        }
        NotificationService::success("Membros do grupo \"{$nomeGrupo}\" atualizados -- vale no próximo login de cada pessoa.");

        return true;
    }

    public function excluirGrupo(string $grupoId, string $nomeGrupo): bool
    {
        $resultado = $this->chamarGraph('DELETE', '/groups/' . rawurlencode($grupoId));
        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao excluir o grupo.', $this->mensagemPermissaoGrupos($resultado['mensagem']));
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Excluir grupo', "Grupo \"{$nomeGrupo}\" excluído.");
        NotificationService::success("Grupo \"{$nomeGrupo}\" excluído (fica 30 dias na lixeira do Entra).");

        return true;
    }

    /*
     | O que foi aplicado em cada máquina (pela tela Acesso às Máquinas) --
     | o resultado de cada comando fica no histórico do ativo; aqui só o
     | último pedido, pra tela mostrar que restrição cada máquina tem.
     */

    /** @param array<int,array{id:string,nome:string,tipo:string}>|null $autorizados null = restrição removida */
    public static function registrarAcessoAplicado(int $ativoId, ?array $autorizados, ?string $por): void
    {
        \App\Core\Database::connection()->prepare(
            'INSERT INTO entra_acesso_maquinas (ativo_id, autorizados, aplicado_por, aplicado_em) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE autorizados = VALUES(autorizados), aplicado_por = VALUES(aplicado_por), aplicado_em = NOW()'
        )->execute([$ativoId, $autorizados === null ? null : json_encode($autorizados, JSON_UNESCAPED_UNICODE), $por]);
    }

    /** @return array<int,array{autorizados:?array,aplicado_por:?string,aplicado_em:string}> por ativo_id */
    public static function acessosAplicados(): array
    {
        $linhas = [];
        foreach (\App\Core\Database::connection()->query('SELECT * FROM entra_acesso_maquinas')->fetchAll(\PDO::FETCH_ASSOC) as $l) {
            $l['autorizados'] = $l['autorizados'] !== null ? json_decode($l['autorizados'], true) : null;
            $linhas[(int)$l['ativo_id']] = $l;
        }

        return $linhas;
    }

    /**
     * Script que aplica a restrição -- só os usuários/grupos do Entra informados (pelo objectId)
     * + administradores locais (sempre) podem logar localmente.
     */
    public static function gerarScriptRestricaoLogin(array $objectIds): string
    {
        $sids = array_values(array_unique(array_filter(array_map([self::class, 'sidDoObjeto'], $objectIds))));

        return self::scriptSeceditLogonRight(
            implode(',', array_merge([self::SID_ADMINISTRADORES_LOCAIS], array_map(fn($sid) => '*' . $sid, $sids))),
            'Restricao aplicada -- administradores locais + ' . count($sids) . ' usuario(s)/grupo(s) do Entra autorizados.'
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
    $codigo = $LASTEXITCODE

    # Confere relendo a política: o secedit sai com código de aviso quando não acha nome pra
    # algum SID (grupo do Entra que ainda não logou ali), mas grava do mesmo jeito.
    Remove-Item $tempCfg -ErrorAction SilentlyContinue
    secedit /export /cfg $tempCfg /areas USER_RIGHTS 2>&1 | Out-Null
    $gravado = (Get-Content $tempCfg -ErrorAction SilentlyContinue | Where-Object { $_ -match '^SeInteractiveLogonRight\s*=' }) -replace '^SeInteractiveLogonRight\s*=\s*', ''
    $esperado = $listaCompleta -split ','
    $atual = $gravado -split ','
    $faltando = $esperado | Where-Object { $atual -notcontains $_ }

    if ($faltando) {
        Write-Output "ERRO: politica nao ficou como esperado (codigo $codigo) -- faltando: $($faltando -join ', '). Saida: $configureOut"
        exit 1
    }

    Write-Output "__MENSAGEM__"
    Write-Output "Permitidos agora: $gravado"
} finally {
    Remove-Item $tempCfg -ErrorAction SilentlyContinue
    Remove-Item $tempDb -ErrorAction SilentlyContinue
}
PS;

        return str_replace(['__LISTA__', '__MENSAGEM__'], [$listaDireitos, $mensagemFinal], $template);
    }

    /*
     |---------------------------------------------------------
     | Desativar contas LOCAIS antigas do Windows -- passo complementar
     | à restrição de login acima ("trava final", já que o Windows não
     | apaga contas locais sozinho quando a máquina entra no Entra).
     | Mesmo canal de comando remoto (executar_powershell, elevado).
     |
     | Não temos hoje (2026-09) coleta de "quais contas locais existem
     | em cada máquina" -- só usuario_logado (quem está logado agora,
     | via WMI). Por isso o admin digita o(s) nome(s) manualmente, e a
     | tela mostra quem está logado em cada máquina selecionada, pra não
     | desativar por engano a conta que está sendo usada ali agora.
     |
     | Rede de segurança: contas embutidas do Windows (Administrator,
     | Guest, DefaultAccount, WDAGUtilityAccount, e as versões em
     | português) nunca entram na lista, mesmo que digitadas -- some
     | silenciosamente da lista antes de montar o script, pra nunca
     | deixar a máquina sem NENHUMA conta administrativa local. Usa
     | Disable-LocalUser (nunca Remove-LocalUser) -- sempre reversível
     | com Enable-LocalUser, sem perder o perfil/dados da conta.
     |---------------------------------------------------------
     */

    public const CONTAS_LOCAIS_PROTEGIDAS = ['Administrator', 'Administrador', 'DefaultAccount', 'Guest', 'Convidado', 'WDAGUtilityAccount'];

    /** Filtra nomes vazios, contas protegidas, e qualquer caractere que o Windows não aceita em nome de conta local -- devolve só o que é seguro mandar pro script. */
    public static function validarNomesContaLocal(array $nomes): array
    {
        $validos = [];
        foreach ($nomes as $nome) {
            $nome = trim((string)$nome);
            if ($nome === '' || in_array($nome, self::CONTAS_LOCAIS_PROTEGIDAS, true)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9 ._\-]{1,64}$/', $nome)) {
                continue;
            }
            $validos[] = $nome;
        }

        return array_values(array_unique($validos));
    }

    /** Script que desativa (Disable-LocalUser) cada nome informado -- melhor esforço, uma conta inexistente não trava as demais. */
    public static function gerarScriptDesativarContasLocais(array $nomes): string
    {
        return self::scriptContasLocais(self::validarNomesContaLocal($nomes), 'Disable-LocalUser', 'desativada(s)');
    }

    /** Script que reverte (Enable-LocalUser) -- mesma lista, mesma validação. */
    public static function gerarScriptReativarContasLocais(array $nomes): string
    {
        return self::scriptContasLocais(self::validarNomesContaLocal($nomes), 'Enable-LocalUser', 'reativada(s)');
    }

    private static function scriptContasLocais(array $nomes, string $cmdlet, string $participio): string
    {
        if (empty($nomes)) {
            return "Write-Output 'Nenhuma conta local válida informada (vazia ou protegida).'";
        }

        // Nomes já validados só com [A-Za-z0-9 ._-] -- json_encode não produz
        // aspas simples nem barra invertida com esse conjunto de caracteres,
        // seguro embutir direto numa string PowerShell de aspas simples.
        $nomesJson = json_encode(array_values($nomes), JSON_UNESCAPED_UNICODE);

        $template = <<<'PS'
$nomes = '__NOMES_JSON__' | ConvertFrom-Json
$ok = @()
$falhou = @()

foreach ($nome in $nomes) {
    try {
        __CMDLET__ -Name $nome -ErrorAction Stop
        $ok += $nome
    } catch {
        $falhou += "$nome ($($_.Exception.Message))"
    }
}

if ($ok.Count -gt 0) {
    Write-Output ("Contas __PARTICIPIO__: " + ($ok -join ', '))
} else {
    Write-Output "Nenhuma conta foi __PARTICIPIO_SINGULAR__ (verifique se os nomes existem na maquina)."
}
if ($falhou.Count -gt 0) {
    Write-Output ("Falhas: " + ($falhou -join ' | '))
}
PS;

        return str_replace(
            ['__NOMES_JSON__', '__CMDLET__', '__PARTICIPIO__', '__PARTICIPIO_SINGULAR__'],
            [$nomesJson, $cmdlet, $participio, str_replace('(s)', '', $participio)],
            $template
        );
    }

    /*
     |---------------------------------------------------------
     | Dispositivos gerenciados pelo Intune (Graph API) -- a licença do
     | tenant (SPE_F1 / Microsoft 365 F3) inclui Intune Plan 1, então dá
     | pra consultar/agir sobre as máquinas inscritas, não só usuários.
     | Só enxerga o que já estiver inscrito no Intune -- ver seção de
     | inscrição logo abaixo pra levar máquinas até esse ponto.
     |
     | "Retire" remove dados/apps corporativos e desinscreve do Intune;
     | NÃO é reset de fábrica. "Wipe" (esse sim destrutivo, apaga a
     | máquina inteira) fica fora de escopo -- ganho de uso muito menor
     | que o risco pro dia a dia.
     |---------------------------------------------------------
     */

    /** true quando a última chamada a listarDispositivosGerenciados() falhou por falta de permissão no App Registration (não por token/credencial errada) -- usado pra mostrar a ajuda contextual na tela em vez de só o erro cru do Graph. */
    private bool $ultimoErroDispositivosPermissao = false;

    public function listarDispositivosGerenciados(): array
    {
        $this->ultimoErroDispositivosPermissao = false;

        $todos = [];
        $proximaUrl = '/deviceManagement/managedDevices?$select=id,deviceName,userPrincipalName,operatingSystem,osVersion,complianceState,lastSyncDateTime,managementAgent,enrolledDateTime,isEncrypted,manufacturer,model,serialNumber&$top=999';

        while ($proximaUrl !== null) {
            $resultado = $this->chamarGraph('GET', $proximaUrl);

            if (!$resultado['sucesso']) {
                if (stripos($resultado['mensagem'], 'scope') !== false || stripos($resultado['mensagem'], 'not authorized') !== false) {
                    $this->ultimoErroDispositivosPermissao = true;
                }
                NotificationService::error('Erro ao listar dispositivos do Intune.', $resultado['mensagem']);
                return $todos;
            }

            foreach ($resultado['dados']['value'] ?? [] as $d) {
                $todos[] = $d;
            }

            $proximaUrl = $resultado['dados']['@odata.nextLink'] ?? null;
        }

        return $todos;
    }

    public function ultimoErroDispositivosFoiPermissao(): bool
    {
        return $this->ultimoErroDispositivosPermissao;
    }

    public function sincronizarDispositivoIntune(string $deviceId, string $nomeAuditoria): bool
    {
        return $this->acaoDispositivoIntune($deviceId, 'syncDevice', 'Sincronizar dispositivo', "Sincronização solicitada para {$nomeAuditoria}.", $nomeAuditoria);
    }

    public function reiniciarDispositivoIntune(string $deviceId, string $nomeAuditoria): bool
    {
        return $this->acaoDispositivoIntune($deviceId, 'rebootNow', 'Reiniciar dispositivo', "Reinício solicitado para {$nomeAuditoria}.", $nomeAuditoria);
    }

    public function bloquearDispositivoIntune(string $deviceId, string $nomeAuditoria): bool
    {
        return $this->acaoDispositivoIntune($deviceId, 'remoteLock', 'Bloquear dispositivo', "Bloqueio de tela solicitado para {$nomeAuditoria}.", $nomeAuditoria);
    }

    public function retirarDispositivoIntune(string $deviceId, string $nomeAuditoria): bool
    {
        return $this->acaoDispositivoIntune($deviceId, 'retire', 'Retirar do Intune', "Dispositivo {$nomeAuditoria} retirado do Intune -- dados/apps corporativos removidos.", $nomeAuditoria);
    }

    public function desligarDispositivoIntune(string $deviceId, string $nomeAuditoria): bool
    {
        return $this->acaoDispositivoIntune($deviceId, 'shutDown', 'Desligar dispositivo', "Desligamento solicitado para {$nomeAuditoria}.", $nomeAuditoria);
    }

    public function varredurraDefenderDispositivoIntune(string $deviceId, string $nomeAuditoria): bool
    {
        return $this->acaoDispositivoIntune($deviceId, 'windowsDefenderScan', 'Varredura do Defender', "Varredura do Windows Defender solicitada para {$nomeAuditoria}.", $nomeAuditoria);
    }

    public function atualizarAssinaturasDefenderDispositivoIntune(string $deviceId, string $nomeAuditoria): bool
    {
        return $this->acaoDispositivoIntune($deviceId, 'windowsDefenderUpdateSignatures', 'Atualizar assinaturas do Defender', "Atualização de assinaturas do Windows Defender solicitada para {$nomeAuditoria}.", $nomeAuditoria);
    }

    private function acaoDispositivoIntune(string $deviceId, string $acaoGraph, string $rotuloAuditoria, string $detalheAuditoria, string $nomeAuditoria): bool
    {
        $resultado = $this->chamarGraph('POST', '/deviceManagement/managedDevices/' . rawurlencode($deviceId) . "/{$acaoGraph}");

        if (!$resultado['sucesso']) {
            NotificationService::error("Erro ao executar ação em {$nomeAuditoria}.", $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', $rotuloAuditoria, $detalheAuditoria);
        NotificationService::success('Ação enviada -- pode levar alguns minutos pra refletir no dispositivo.');

        return true;
    }

    /*
     |---------------------------------------------------------
     | Inscrever máquinas no Intune usando o agente próprio -- dois
     | caminhos, dependendo se a máquina já tem o domínio Entra
     | configurado ou não. Os dois usam o MESMO canal de comando remoto
     | já existente pra Ativos (executar_powershell / enviar_arquivo),
     | sem nenhuma mudança no agente.
     |---------------------------------------------------------
     */

    /** Máquina já entrou no domínio Entra (manualmente ou via pacote de provisionamento) mas ainda não apareceu no Intune -- força a inscrição na hora em vez de esperar o ciclo automático. */
    public static function scriptForcarEnrollmentIntune(): string
    {
        return <<<'PS'
$saida = & "$env:windir\system32\deviceenroller.exe" /c /AutoEnrollMDM 2>&1
Write-Output "Inscricao no Intune solicitada -- pode levar alguns minutos pra aparecer no portal."
if ($saida) { Write-Output $saida }
PS;
    }

    /** Máquina nova, sem domínio nenhum -- instala o pacote de provisionamento (.ppkg) já entregue via enviar_arquivo, esperando ele existir antes de tentar (evita corrida entre os dois comandos, sem precisar de nenhuma confirmação síncrona do servidor). */
    public static function scriptInstalarProvisioningPackage(string $destino): string
    {
        $template = <<<'PS'
$destino = '__DESTINO__'
$limite = (Get-Date).AddSeconds(90)

while (-not (Test-Path $destino) -and (Get-Date) -lt $limite) {
    Start-Sleep -Seconds 2
}

if (-not (Test-Path $destino)) {
    Write-Output "ERRO: pacote de provisionamento nao chegou a tempo em $destino."
    exit 1
}

try {
    $resultado = Install-ProvisioningPackage -PackagePath $destino -QuietInstall -ForceInstall 2>&1
    Write-Output "Pacote de provisionamento instalado -- a maquina deve entrar no dominio e no Intune em alguns minutos."
    if ($resultado) { Write-Output $resultado }
} catch {
    Write-Output "ERRO ao instalar o pacote de provisionamento: $($_.Exception.Message)"
    exit 1
} finally {
    Remove-Item $destino -ErrorAction SilentlyContinue
}
PS;

        return str_replace('__DESTINO__', $destino, $template);
    }

    /*
     |---------------------------------------------------------
     | Pacote de provisionamento (.ppkg) mestre -- gerado manualmente
     | fora do portal (Windows Configuration Designer, com MFA, sem API
     | suportada pra isso) e enviado aqui UMA vez por tenant. Copiado
     | por máquina na hora de despachar (ver EntraController), já que
     | o canal de envio de arquivo consome a cópia temporária.
     |---------------------------------------------------------
     */

    private const CHAVE_PPKG_NOME = 'entra_ppkg_nome';
    private const CHAVE_PPKG_ENVIADO_EM = 'entra_ppkg_enviado_em';

    public static function caminhoProvisioningPackage(): string
    {
        return __DIR__ . '/../../storage/uploads/entra/bulk_enrollment.ppkg';
    }

    public function provisioningConfigurado(): bool
    {
        return is_file(self::caminhoProvisioningPackage());
    }

    public function provisioningInfo(): ?array
    {
        if (!$this->provisioningConfigurado()) {
            return null;
        }

        return [
            'nome' => ConfigService::get(self::CHAVE_PPKG_NOME, '') ?: 'bulk_enrollment.ppkg',
            'enviado_em' => ConfigService::get(self::CHAVE_PPKG_ENVIADO_EM, '') ?: '',
        ];
    }

    public function salvarProvisioningPackage(string $caminhoTemporario, string $nomeOriginal): bool
    {
        if (strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION)) !== 'ppkg') {
            NotificationService::error('O arquivo precisa ser um pacote de provisionamento (.ppkg).');
            return false;
        }

        $destino = self::caminhoProvisioningPackage();
        $pasta = dirname($destino);
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Não foi possível preparar a pasta de armazenamento no servidor.');
            return false;
        }

        if (!@copy($caminhoTemporario, $destino)) {
            NotificationService::error('Não foi possível salvar o pacote de provisionamento no servidor.');
            return false;
        }

        ConfigService::set(self::CHAVE_PPKG_NOME, basename($nomeOriginal));
        ConfigService::set(self::CHAVE_PPKG_ENVIADO_EM, date('Y-m-d H:i:s'));

        AuditService::registrar('Microsoft Entra', 'Pacote de provisionamento', "Pacote de provisionamento ({$nomeOriginal}) enviado/atualizado.");
        NotificationService::success('Pacote de provisionamento salvo.');

        return true;
    }

    public function removerProvisioningPackage(): void
    {
        @unlink(self::caminhoProvisioningPackage());
        ConfigService::set(self::CHAVE_PPKG_NOME, '');
        ConfigService::set(self::CHAVE_PPKG_ENVIADO_EM, '');

        AuditService::registrar('Microsoft Entra', 'Pacote de provisionamento', 'Pacote de provisionamento removido.');
        NotificationService::success('Pacote de provisionamento removido.');
    }

    /*
     |---------------------------------------------------------
     | Instalador do Company Portal -- confirmado ao vivo numa VM de
     | teste real: quando a inscrição automática (deviceenroller /c
     | /AutoEnrollMDM) não completa (tenant não devolve a URL de MDM na
     | descoberta), o Company Portal é o caminho que funciona -- ele tem
     | tela própria de "Set up a work or school account" com campo pra
     | informar a URL do servidor MDM manualmente quando a auto-descoberta
     | falha. Baixar pela Microsoft Store nem sempre é prático (o cliente
     | relatou dificuldade), então guarda um instalador (.exe/.msix/
     | .msixbundle) aqui, uma vez, pra reenviar pra quantas máquinas
     | precisar.
     |
     | Só entrega o arquivo (reaproveitando enviar_arquivo, já testado) --
     | NÃO tenta instalar/rodar automaticamente: apps de Loja (UWP/MSIX)
     | precisam ser instalados na sessão do próprio usuário logado, sem
     | elevação -- rodar via nosso canal elevado (schtasks /rl highest ou
     | /ru outra conta) tende a falhar ou instalar pro perfil errado. Fica
     | documentado na tela pro admin abrir manualmente, sem "Executar como
     | administrador", depois que o arquivo chegar.
     |---------------------------------------------------------
     */

    private const CHAVE_COMPANY_PORTAL_NOME = 'entra_companyportal_nome';
    private const CHAVE_COMPANY_PORTAL_ENVIADO_EM = 'entra_companyportal_enviado_em';
    private const EXTENSOES_COMPANY_PORTAL_VALIDAS = ['exe', 'msix', 'msixbundle'];

    public static function caminhoCompanyPortal(): string
    {
        return __DIR__ . '/../../storage/uploads/entra/company_portal_installer';
    }

    public function companyPortalConfigurado(): bool
    {
        return is_file(self::caminhoCompanyPortal());
    }

    public function companyPortalInfo(): ?array
    {
        if (!$this->companyPortalConfigurado()) {
            return null;
        }

        return [
            'nome' => ConfigService::get(self::CHAVE_COMPANY_PORTAL_NOME, '') ?: 'company_portal',
            'enviado_em' => ConfigService::get(self::CHAVE_COMPANY_PORTAL_ENVIADO_EM, '') ?: '',
        ];
    }

    public function salvarCompanyPortal(string $caminhoTemporario, string $nomeOriginal): bool
    {
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if (!in_array($extensao, self::EXTENSOES_COMPANY_PORTAL_VALIDAS, true)) {
            NotificationService::error('O arquivo precisa ser o instalador do Company Portal (.exe, .msix ou .msixbundle).');
            return false;
        }

        $destino = self::caminhoCompanyPortal();
        $pasta = dirname($destino);
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Não foi possível preparar a pasta de armazenamento no servidor.');
            return false;
        }

        if (!@copy($caminhoTemporario, $destino)) {
            NotificationService::error('Não foi possível salvar o instalador no servidor.');
            return false;
        }

        ConfigService::set(self::CHAVE_COMPANY_PORTAL_NOME, basename($nomeOriginal));
        ConfigService::set(self::CHAVE_COMPANY_PORTAL_ENVIADO_EM, date('Y-m-d H:i:s'));

        AuditService::registrar('Microsoft Entra', 'Instalador Company Portal', "Instalador do Company Portal ({$nomeOriginal}) enviado/atualizado.");
        NotificationService::success('Instalador do Company Portal salvo.');

        return true;
    }

    public function removerCompanyPortal(): void
    {
        @unlink(self::caminhoCompanyPortal());
        ConfigService::set(self::CHAVE_COMPANY_PORTAL_NOME, '');
        ConfigService::set(self::CHAVE_COMPANY_PORTAL_ENVIADO_EM, '');

        AuditService::registrar('Microsoft Entra', 'Instalador Company Portal', 'Instalador do Company Portal removido.');
        NotificationService::success('Instalador do Company Portal removido.');
    }

    /*
     |---------------------------------------------------------
     | Imagens de papel de parede (área de trabalho / tela de bloqueio) --
     | a Microsoft NÃO hospeda essa imagem pros campos
     | personalizationDesktopImageUrl/personalizationLockScreenImageUrl
     | do perfil de configuração: eles só aceitam uma URL http(s) pública
     | (o Windows baixa de lá) ou uma URL local "file:///C:/..." já
     | presente na máquina. Guardamos aqui só o UPLOAD/armazenamento da
     | imagem mestre -- o envio em lote pra cada máquina (via
     | enviar_arquivo, mesmo canal do Company Portal/.ppkg) e o
     | preenchimento automático do campo "file:///..." no formulário do
     | perfil ficam pra uma próxima etapa.
     |
     | $tipo é sempre um literal vindo do Controller ('desktop' ou
     | 'lockscreen'), nunca de entrada do usuário -- por isso é seguro
     | interpolar direto no caminho do arquivo.
     |---------------------------------------------------------
     */

    private const EXTENSOES_IMAGEM_VALIDAS = ['jpg', 'jpeg', 'png'];

    public static function caminhoWallpaper(string $tipo): string
    {
        return __DIR__ . '/../../storage/uploads/entra/wallpaper_' . $tipo;
    }

    private static function chaveWallpaperNome(string $tipo): string
    {
        return "entra_wallpaper_{$tipo}_nome";
    }

    private static function chaveWallpaperEnviadoEm(string $tipo): string
    {
        return "entra_wallpaper_{$tipo}_enviado_em";
    }

    private static function rotuloWallpaper(string $tipo): string
    {
        return $tipo === 'desktop' ? 'área de trabalho' : 'tela de bloqueio';
    }

    public function wallpaperConfigurado(string $tipo): bool
    {
        return is_file(self::caminhoWallpaper($tipo));
    }

    public function wallpaperInfo(string $tipo): ?array
    {
        if (!$this->wallpaperConfigurado($tipo)) {
            return null;
        }

        return [
            'nome' => ConfigService::get(self::chaveWallpaperNome($tipo), '') ?: 'wallpaper',
            'enviado_em' => ConfigService::get(self::chaveWallpaperEnviadoEm($tipo), '') ?: '',
        ];
    }

    public function salvarWallpaper(string $tipo, string $caminhoTemporario, string $nomeOriginal): bool
    {
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if (!in_array($extensao, self::EXTENSOES_IMAGEM_VALIDAS, true)) {
            NotificationService::error('A imagem precisa ser .jpg, .jpeg ou .png.');
            return false;
        }

        $destino = self::caminhoWallpaper($tipo);
        $pasta = dirname($destino);
        if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
            NotificationService::error('Não foi possível preparar a pasta de armazenamento no servidor.');
            return false;
        }

        if (!@copy($caminhoTemporario, $destino)) {
            NotificationService::error('Não foi possível salvar a imagem no servidor.');
            return false;
        }

        ConfigService::set(self::chaveWallpaperNome($tipo), basename($nomeOriginal));
        ConfigService::set(self::chaveWallpaperEnviadoEm($tipo), date('Y-m-d H:i:s'));

        $rotulo = self::rotuloWallpaper($tipo);
        AuditService::registrar('Microsoft Entra', 'Imagem de papel de parede', "Imagem de papel de parede ({$rotulo}) enviada/atualizada.");
        NotificationService::success('Imagem salva.');

        return true;
    }

    public function removerWallpaper(string $tipo): void
    {
        @unlink(self::caminhoWallpaper($tipo));
        ConfigService::set(self::chaveWallpaperNome($tipo), '');
        ConfigService::set(self::chaveWallpaperEnviadoEm($tipo), '');

        $rotulo = self::rotuloWallpaper($tipo);
        AuditService::registrar('Microsoft Entra', 'Imagem de papel de parede', "Imagem de papel de parede ({$rotulo}) removida.");
        NotificationService::success('Imagem removida.');
    }

    /** Caminho fixo que o arquivo vai ocupar NA MÁQUINA depois de enviado (ProgramData -- persistente, diferente do Temp usado por instaladores avulsos) -- mesma extensão do arquivo original enviado. Null se essa imagem ainda não foi configurada. */
    public function caminhoRemotoWallpaper(string $tipo): ?string
    {
        $info = $this->wallpaperInfo($tipo);
        if ($info === null) {
            return null;
        }

        $extensao = strtolower(pathinfo($info['nome'], PATHINFO_EXTENSION));
        $nomeArquivo = ($tipo === 'desktop' ? 'wallpaper_desktop' : 'wallpaper_lockscreen') . '.' . $extensao;

        return 'C:\\ProgramData\\RDIntranet\\' . $nomeArquivo;
    }

    /** URL "file:///..." pronta pra colar direto no campo personalizationDesktopImageUrl/personalizationLockScreenImageUrl do perfil -- só depois que a imagem já tiver sido enviada pra máquina (ver enviarWallpaperParaAtivo no Controller). */
    public function urlFileWallpaper(string $tipo): ?string
    {
        $caminho = $this->caminhoRemotoWallpaper($tipo);
        if ($caminho === null) {
            return null;
        }

        return 'file:///' . str_replace('\\', '/', $caminho);
    }

    /*
     |---------------------------------------------------------
     | Perfis de Configuração (Intune Configuration Profiles) -- aplicam
     | configurações do Windows automaticamente em toda a frota
     | gerenciada, sem precisar mexer máquina por máquina (papel de
     | parede, bloqueio de USB, tempo de bloqueio de tela, senha mínima,
     | bloquear Windows Store, bloquear acesso às Configurações). Usa o
     | template "Restrições de dispositivo" do Intune
     | (windows10GeneralConfiguration) -- o mesmo tipo usado pela própria
     | tela do Intune admin center pra essas configurações. O "Settings
     | Catalog" (mais moderno/flexível) ainda é só beta no Graph, por
     | isso não usamos.
     |
     | Sem espelho local -- igual usuários/dispositivos, o Graph é a
     | única fonte de verdade, sempre lido/escrito na hora.
     |
     | Fora de escopo por ora: BitLocker
     | (windows10EndpointProtectionConfiguration) e perfil de Wi-Fi
     | (windows81WifiConfiguration) são OUTROS tipos de deviceConfiguration,
     | com campos bem mais complexos (BitLocker tem política aninhada
     | por tipo de unidade; Wi-Fi lida com segredo de rede) -- não cabem
     | no mesmo formulário curado sem inchar demais essa primeira versão.
     |
     | Atribuição: perfil de configuração no Intune se aplica a GRUPOS
     | do Entra, não a dispositivos individuais (diferente do resto do
     | módulo Dispositivos). Pra não precisar de um seletor de grupos
     | agora, a atribuição aqui é sempre pro alvo especial "Todos os
     | dispositivos" (allDevicesAssignmentTarget) -- afeta a FROTA
     | INTEIRA inscrita no Intune, não só uma seleção.
     |---------------------------------------------------------
     */

    private const TIPO_GRAPH_RESTRICOES = '#microsoft.graph.windows10GeneralConfiguration';

    /** campo Graph => [label em português, tipo de input] -- usado tanto pra montar o form quanto pra filtrar o $_POST na hora de salvar. */
    public const CAMPOS_RESTRICOES = [
        'personalizationDesktopImageUrl' => ['label' => 'Papel de parede da área de trabalho (URL da imagem .jpg/.png)', 'tipo' => 'url'],
        'personalizationLockScreenImageUrl' => ['label' => 'Papel de parede da tela de bloqueio (URL da imagem .jpg/.png)', 'tipo' => 'url'],
        'usbBlocked' => ['label' => 'Bloquear conexão USB', 'tipo' => 'bool'],
        'storageBlockRemovableStorage' => ['label' => 'Bloquear armazenamento removível (pen drive, HD externo)', 'tipo' => 'bool'],
        'passwordRequired' => ['label' => 'Exigir senha pra entrar no Windows', 'tipo' => 'bool'],
        'passwordMinimumLength' => ['label' => 'Tamanho mínimo da senha', 'tipo' => 'numero'],
        'passwordMinutesOfInactivityBeforeScreenTimeout' => ['label' => 'Bloquear tela após ficar parado (minutos)', 'tipo' => 'numero'],
        'windowsStoreBlocked' => ['label' => 'Bloquear a Windows Store', 'tipo' => 'bool'],
        'settingsBlockSettingsApp' => ['label' => 'Bloquear acesso ao app de Configurações do Windows', 'tipo' => 'bool'],
    ];

    public function listarPerfisConfiguracao(): array
    {
        $todos = [];
        $proximaUrl = '/deviceManagement/deviceConfigurations?$top=999&$expand=assignments';

        while ($proximaUrl !== null) {
            $resultado = $this->chamarGraph('GET', $proximaUrl);

            if (!$resultado['sucesso']) {
                NotificationService::error('Erro ao listar perfis de configuração.', $resultado['mensagem']);
                return $todos;
            }

            foreach ($resultado['dados']['value'] ?? [] as $perfil) {
                if (($perfil['@odata.type'] ?? '') === self::TIPO_GRAPH_RESTRICOES) {
                    $todos[] = $perfil;
                }
            }

            $proximaUrl = $resultado['dados']['@odata.nextLink'] ?? null;
        }

        return $todos;
    }

    public function buscarPerfilConfiguracao(string $id): ?array
    {
        $resultado = $this->chamarGraph('GET', '/deviceManagement/deviceConfigurations/' . rawurlencode($id));

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao carregar o perfil.', $resultado['mensagem']);
            return null;
        }

        return $resultado['dados'];
    }

    private static function montarCorpoRestricoes(array $post): array
    {
        $corpo = [];

        foreach (self::CAMPOS_RESTRICOES as $campo => $info) {
            if ($info['tipo'] === 'bool') {
                $corpo[$campo] = isset($post[$campo]);
                continue;
            }

            $valor = trim((string)($post[$campo] ?? ''));
            if ($valor === '') {
                continue;
            }

            $corpo[$campo] = $info['tipo'] === 'numero' ? (int)$valor : $valor;
        }

        return $corpo;
    }

    public function criarPerfilConfiguracao(string $nome, string $descricao, array $post): bool
    {
        $nome = trim($nome);
        if ($nome === '') {
            NotificationService::error('Informe um nome pro perfil.');
            return false;
        }

        $corpo = array_merge(
            ['@odata.type' => self::TIPO_GRAPH_RESTRICOES, 'displayName' => $nome, 'description' => trim($descricao)],
            self::montarCorpoRestricoes($post)
        );

        $resultado = $this->chamarGraph('POST', '/deviceManagement/deviceConfigurations', $corpo);

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao criar o perfil de configuração.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Criar perfil de configuração', "Perfil \"{$nome}\" criado.");
        NotificationService::success('Perfil de configuração criado -- lembre de aplicar em "Todos os dispositivos" quando quiser que valha.');

        return true;
    }

    public function editarPerfilConfiguracao(string $id, string $nome, string $descricao, array $post): bool
    {
        $nome = trim($nome);
        if ($nome === '') {
            NotificationService::error('Informe um nome pro perfil.');
            return false;
        }

        $corpo = array_merge(
            ['displayName' => $nome, 'description' => trim($descricao)],
            self::montarCorpoRestricoes($post)
        );

        $resultado = $this->chamarGraph('PATCH', '/deviceManagement/deviceConfigurations/' . rawurlencode($id), $corpo);

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao salvar o perfil de configuração.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Editar perfil de configuração', "Perfil \"{$nome}\" atualizado.");
        NotificationService::success('Perfil de configuração salvo.');

        return true;
    }

    public function excluirPerfilConfiguracao(string $id, string $nomeAuditoria): bool
    {
        $resultado = $this->chamarGraph('DELETE', '/deviceManagement/deviceConfigurations/' . rawurlencode($id));

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao excluir o perfil.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Excluir perfil de configuração', "Perfil \"{$nomeAuditoria}\" excluído.");
        NotificationService::success('Perfil excluído.');

        return true;
    }

    public function atribuirPerfilTodosDispositivos(string $id, string $nomeAuditoria): bool
    {
        return $this->salvarAtribuicaoPerfil(
            $id,
            [['target' => ['@odata.type' => '#microsoft.graph.allDevicesAssignmentTarget']]],
            "Perfil \"{$nomeAuditoria}\" aplicado a todos os dispositivos.",
            'Perfil aplicado a todos os dispositivos -- pode levar alguns minutos pra refletir.'
        );
    }

    public function removerAtribuicaoPerfil(string $id, string $nomeAuditoria): bool
    {
        return $this->salvarAtribuicaoPerfil(
            $id,
            [],
            "Atribuição do perfil \"{$nomeAuditoria}\" removida.",
            'Atribuição removida -- o perfil deixou de valer pra qualquer dispositivo.'
        );
    }

    private function salvarAtribuicaoPerfil(string $id, array $assignments, string $detalheAuditoria, string $mensagemSucesso): bool
    {
        $resultado = $this->chamarGraph('POST', '/deviceManagement/deviceConfigurations/' . rawurlencode($id) . '/assign', ['assignments' => $assignments]);

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao atualizar a atribuição do perfil.', $resultado['mensagem']);
            return false;
        }

        AuditService::registrar('Microsoft Entra', 'Atribuição de perfil de configuração', $detalheAuditoria);
        NotificationService::success($mensagemSucesso);

        return true;
    }
}
