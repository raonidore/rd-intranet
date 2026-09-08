<?php

namespace App\Services;

/**
 * Integração com a Open API do Omada SDN Controller (TP-Link) -- usada pra
 * coletar dados dos switches TP-Link (modelo, firmware, uptime, status,
 * portas). Diferente da UniFi, esse switch (TL-SG3428) não tem NENHUMA
 * API própria em modo standalone -- SNMP não responde, SSH/Telnet abrem a
 * porta mas não respondem como serviço real, e a interface web usa uma API
 * JSON privada e não documentada. O caminho oficial confirmado pela
 * TP-Link é sempre via Omada Controller (instalado em
 * scripts/system/... nesta mesma máquina, pacote nativo `.deb`, serviço
 * `tpeap`), nunca direto no equipamento.
 *
 * Autenticação: Open API oficial (Client ID/Secret, OAuth2
 * client_credentials), habilitada em Configurações do Controller >
 * Integração de Plataforma > Open API -- não a sessão de login
 * usuário/senha (essa é interna, usada só pelo próprio navegador).
 *
 * Duas peculiaridades confirmadas ao vivo antes de escrever esta classe
 * (nenhuma documentada com clareza pela TP-Link):
 * 1. O corpo do POST em /openapi/authorize/token precisa incluir o
 *    `omadacId` (identificador fixo desta instalação do Controller) junto
 *    com client_id/client_secret -- sem ele, a API sempre responde
 *    "Client Id Or Client Secret is Invalid" mesmo com credenciais
 *    corretas (o log do servidor mostra a real causa:
 *    "Find no global open api application, omadacId=[null]"). Não existe
 *    endpoint de auto-descoberta desse ID sem já ter um token válido --
 *    por isso ele é um campo de configuração manual aqui (guardado uma
 *    vez, via Integrações > TP-Link Omada).
 * 2. As chamadas autenticadas usam o header `Authorization: AccessToken=
 *    <token>` -- não o `Authorization: Bearer <token>` padrão OAuth2 que
 *    o `tokenType: "bearer"` da resposta sugere.
 */
class OmadaService
{
    private const CHAVE_URL = 'omada_url';
    private const CHAVE_OMADAC_ID = 'omada_omadac_id';
    private const CHAVE_CLIENT_ID = 'omada_client_id';
    private const CHAVE_CLIENT_SECRET_CIFRADO = 'omada_client_secret_cifrado';
    private const CHAVE_SITE_ID = 'omada_site_id';

    public function configurado(): bool
    {
        return $this->urlAtual() !== ''
            && $this->omadacIdAtual() !== ''
            && $this->clientIdAtual() !== ''
            && (ConfigService::get(self::CHAVE_CLIENT_SECRET_CIFRADO, '') ?: '') !== '';
    }

    public function urlAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_URL, '') ?: ''));
    }

    public function omadacIdAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_OMADAC_ID, '') ?: ''));
    }

    public function clientIdAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_CLIENT_ID, '') ?: ''));
    }

    public function siteIdAtual(): ?string
    {
        $id = trim((string)(ConfigService::get(self::CHAVE_SITE_ID, '') ?: ''));

        return $id !== '' ? $id : null;
    }

    public function salvarConfiguracao(string $url, string $omadacId, string $clientId, string $clientSecret): bool
    {
        $url = rtrim(trim($url), '/');
        $omadacId = trim($omadacId);
        $clientId = trim($clientId);
        $clientSecret = trim($clientSecret);

        if ($url === '' || $omadacId === '' || $clientId === '') {
            NotificationService::error('Informe a URL do Controller, o Omada ID e o Client ID.');
            return false;
        }

        // Client Secret em branco mantém o atual -- só exige um valor novo quando ainda não havia nenhum.
        if ($clientSecret === '' && !$this->configurado()) {
            NotificationService::error('Informe o Client Secret.');
            return false;
        }

        ConfigService::set(self::CHAVE_URL, $url);
        ConfigService::set(self::CHAVE_OMADAC_ID, $omadacId);
        ConfigService::set(self::CHAVE_CLIENT_ID, $clientId);

        if ($clientSecret !== '') {
            ConfigService::set(self::CHAVE_CLIENT_SECRET_CIFRADO, CryptoService::encriptar($clientSecret));
        }

        AuditService::registrar('Omada Controller', 'Configuração', "URL/Omada ID/Client ID atualizados ({$url}).");
        NotificationService::success('Configuração salva -- use "Testar conexão" para validar e identificar o site.');

        return true;
    }

    public function removerConfiguracao(): void
    {
        ConfigService::set(self::CHAVE_URL, '');
        ConfigService::set(self::CHAVE_OMADAC_ID, '');
        ConfigService::set(self::CHAVE_CLIENT_ID, '');
        ConfigService::set(self::CHAVE_CLIENT_SECRET_CIFRADO, '');
        ConfigService::set(self::CHAVE_SITE_ID, '');

        AuditService::registrar('Omada Controller', 'Configuração', 'Configuração removida.');
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
     * Testa as credenciais (obtendo um token de verdade) e identifica o
     * site (a grande maioria das instalações tem só um) -- guarda o id
     * pra uso nas próximas chamadas.
     */
    public function testarConexao(): array
    {
        $token = $this->obterToken();

        if ($token === null) {
            return ['success' => false, 'message' => 'Falha ao autenticar -- confira URL, Omada ID, Client ID e Client Secret.'];
        }

        $resultado = $this->chamarApi('GET', "/openapi/v1/{$this->omadacIdAtual()}/sites?page=1&pageSize=100", $token);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => 'Conectou e autenticou, mas falhou ao listar sites: ' . $resultado['mensagem']];
        }

        $sites = $resultado['dados']['result']['data'] ?? [];

        if (empty($sites)) {
            return ['success' => false, 'message' => 'Autenticado com sucesso, mas nenhum site foi encontrado.'];
        }

        $site = $sites[0];
        ConfigService::set(self::CHAVE_SITE_ID, (string)$site['siteId']);

        AuditService::registrar('Omada Controller', 'Testar conexão', "Conexão validada -- site \"{$site['name']}\" ({$site['siteId']}).");

        return ['success' => true, 'message' => "Conectado com sucesso -- site \"{$site['name']}\" identificado e salvo."];
    }

    /** Dispositivos (switches, APs, gateways) do site configurado. */
    public function listarDispositivos(): array
    {
        $siteId = $this->siteIdAtual();

        if ($siteId === null) {
            NotificationService::error('Site do Omada Controller ainda não identificado -- use "Testar conexão" em Integrações > TP-Link Omada.');
            return [];
        }

        $token = $this->obterToken();

        if ($token === null) {
            NotificationService::error('Falha ao autenticar no Omada Controller.');
            return [];
        }

        $todos = [];
        $pagina = 1;
        $porPagina = 200;

        do {
            $resultado = $this->chamarApi('GET', "/openapi/v1/{$this->omadacIdAtual()}/sites/{$siteId}/devices?page={$pagina}&pageSize={$porPagina}", $token);

            if (!$resultado['sucesso']) {
                NotificationService::error('Erro ao listar dispositivos do Omada Controller.', $resultado['mensagem']);
                return $todos;
            }

            $itens = $resultado['dados']['result']['data'] ?? [];
            foreach ($itens as $item) {
                $todos[] = $item;
            }

            $total = (int)($resultado['dados']['result']['totalRows'] ?? count($todos));
            $pagina++;
        } while (count($todos) < $total);

        return $todos;
    }

    /**
     * Token novo a cada chamada (client credentials) -- mesmo idioma do
     * EntraService::obterToken(): simples e suficiente pro volume de uso
     * esperado aqui, sem necessidade real de cache (o token dura 2h).
     */
    private function obterToken(): ?string
    {
        if (!$this->configurado()) {
            return null;
        }

        $secret = $this->clientSecretAtual();

        if ($secret === null) {
            return null;
        }

        $corpo = [
            'omadacId' => $this->omadacIdAtual(),
            'client_id' => $this->clientIdAtual(),
            'client_secret' => $secret,
        ];

        $ch = curl_init($this->urlAtual() . '/openapi/authorize/token?grant_type=client_credentials');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($corpo),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resposta === false || $codigo !== 200) {
            return null;
        }

        $dados = json_decode($resposta, true);

        if (($dados['errorCode'] ?? -1) !== 0) {
            return null;
        }

        return $dados['result']['accessToken'] ?? null;
    }

    /**
     * Chokepoint único de curl pras chamadas já autenticadas -- header
     * `Authorization: AccessToken=<token>` (não é o "Bearer" padrão
     * OAuth2, confirmado ao vivo -- ver nota da classe).
     *
     * @return array{sucesso:bool, dados:mixed, mensagem:string}
     */
    private function chamarApi(string $metodo, string $caminho, string $token): array
    {
        $ch = curl_init($this->urlAtual() . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => ['Authorization: AccessToken=' . $token, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => "Erro de comunicação com o Controller: {$erroCurl}"];
        }

        $dados = $resposta !== '' ? json_decode($resposta, true) : null;

        if ($codigo === 200 && ($dados['errorCode'] ?? -1) === 0) {
            return ['sucesso' => true, 'dados' => $dados, 'mensagem' => ''];
        }

        $mensagemErro = $dados['msg'] ?? "Erro HTTP {$codigo} ao falar com o Controller.";

        return ['sucesso' => false, 'dados' => null, 'mensagem' => is_string($mensagemErro) ? $mensagemErro : "Erro HTTP {$codigo}."];
    }
}
