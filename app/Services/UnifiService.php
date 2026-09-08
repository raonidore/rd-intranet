<?php

namespace App\Services;

/**
 * Integração com a API do UniFi Network Controller (Ubiquiti) -- usada pra
 * coletar dados de pontos de acesso (modelo, firmware, status, rádios,
 * clientes conectados) sem depender de SNMP, que se provou não-funcional
 * nessa linha de APs (config chega no equipamento via API, mas o daemon
 * SNMP nunca sobe na porta 161, confirmado em 2 modelos diferentes).
 *
 * Autenticação: API Key local do Controller (header X-API-KEY), gerada em
 * Configurações > Integração/Integrations no próprio Controller -- não
 * usa a sessão SSO+MFA (essa exige humano digitando código a cada login,
 * inviável pra automação). Confirmado ao vivo antes de escrever esta
 * classe: X-API-KEY funciona contra os endpoints REST em /proxy/network/
 * (tanto os novos /integration/v1/* quanto os legados /api/s/{site}/*).
 *
 * O Controller é local (mesma rede da instalação, certificado
 * autoassinado) -- por isso CURLOPT_SSL_VERIFYPEER/VERIFYHOST ficam
 * desligados aqui, mesmo risco que o próprio app oficial da Ubiquiti
 * assume nessa mesma rede.
 */
class UnifiService
{
    private const CHAVE_URL = 'unifi_url';
    private const CHAVE_API_KEY_CIFRADA = 'unifi_api_key_cifrada';
    private const CHAVE_SITE_ID = 'unifi_site_id';
    private const CHAVE_SITE_REF = 'unifi_site_ref';

    private const LIMITE_PAGINA = 200;

    public function configurado(): bool
    {
        return $this->urlAtual() !== ''
            && (ConfigService::get(self::CHAVE_API_KEY_CIFRADA, '') ?: '') !== '';
    }

    public function urlAtual(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_URL, '') ?: ''));
    }

    public function siteIdAtual(): ?string
    {
        $id = trim((string)(ConfigService::get(self::CHAVE_SITE_ID, '') ?: ''));

        return $id !== '' ? $id : null;
    }

    /** Identificador curto do site (ex: "default") -- usado nos endpoints legados /api/s/{ref}/*, diferente do UUID usado em /integration/v1/sites/{id}/*. */
    public function siteRefAtual(): ?string
    {
        $ref = trim((string)(ConfigService::get(self::CHAVE_SITE_REF, '') ?: ''));

        return $ref !== '' ? $ref : null;
    }

    public function salvarConfiguracao(string $url, string $apiKey): bool
    {
        $url = rtrim(trim($url), '/');
        $apiKey = trim($apiKey);

        if ($url === '') {
            NotificationService::error('Informe a URL do UniFi Controller.');
            return false;
        }

        // API key em branco mantém a atual -- só exige uma chave nova quando ainda não havia nenhuma.
        if ($apiKey === '' && !$this->configurado()) {
            NotificationService::error('Informe a API Key.');
            return false;
        }

        ConfigService::set(self::CHAVE_URL, $url);

        if ($apiKey !== '') {
            ConfigService::set(self::CHAVE_API_KEY_CIFRADA, CryptoService::encriptar($apiKey));
            // Chave nova -- o site resolvido pela chave anterior pode não valer mais (ou nem existir), então limpa e força um novo "Testar conexão".
            ConfigService::set(self::CHAVE_SITE_ID, '');
        }

        AuditService::registrar('UniFi Controller', 'Configuração', "URL do Controller atualizada ({$url}).");
        NotificationService::success('Configuração salva -- use "Testar conexão" para validar e identificar o site.');

        return true;
    }

    public function removerConfiguracao(): void
    {
        ConfigService::set(self::CHAVE_URL, '');
        ConfigService::set(self::CHAVE_API_KEY_CIFRADA, '');
        ConfigService::set(self::CHAVE_SITE_ID, '');

        AuditService::registrar('UniFi Controller', 'Configuração', 'Configuração removida.');
        NotificationService::success('Configuração removida.');
    }

    private function apiKeyAtual(): ?string
    {
        $cifrada = ConfigService::get(self::CHAVE_API_KEY_CIFRADA, '');

        if (!$cifrada) {
            return null;
        }

        try {
            return CryptoService::decriptar($cifrada);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Valida a API key contra o Controller e identifica o site (a grande
     * maioria das instalações tem só um, "Default" -- por isso pega o
     * primeiro da lista em vez de pedir o ID pro usuário digitar).
     *
     * @return array{success:bool, message:string}
     */
    public function testarConexao(): array
    {
        $resultado = $this->chamarApi('GET', '/proxy/network/integration/v1/sites');

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => 'Falha ao conectar: ' . $resultado['mensagem']];
        }

        $sites = $resultado['dados']['data'] ?? [];

        if (empty($sites)) {
            return ['success' => false, 'message' => 'Conectou no Controller, mas nenhum site foi encontrado.'];
        }

        $site = $sites[0];
        ConfigService::set(self::CHAVE_SITE_ID, (string)$site['id']);
        // "internalReference" (ex: "default") é o identificador curto que os endpoints legados /api/s/{ref}/* esperam -- diferente do UUID usado em /integration/v1/sites/{id}/*.
        ConfigService::set(self::CHAVE_SITE_REF, (string)($site['internalReference'] ?? 'default'));

        AuditService::registrar('UniFi Controller', 'Testar conexão', "Conexão validada -- site \"{$site['name']}\" ({$site['id']}).");

        return ['success' => true, 'message' => "Conectado com sucesso -- site \"{$site['name']}\" identificado e salvo."];
    }

    /** Dispositivos (APs, switches, gateway) do site configurado -- id, nome, modelo, mac, ip, firmware, estado. */
    public function listarDispositivos(): array
    {
        $siteId = $this->siteIdAtual();

        if ($siteId === null) {
            NotificationService::error('Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.');
            return [];
        }

        return $this->paginar("/proxy/network/integration/v1/sites/{$siteId}/devices");
    }

    /** Detalhe de um dispositivo específico -- traz o array de rádios (canal/largura/padrão) e adoptedAt, que não vêm na listagem. */
    public function buscarDetalheDispositivo(string $deviceId): ?array
    {
        $siteId = $this->siteIdAtual();

        if ($siteId === null) {
            return null;
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/integration/v1/sites/{$siteId}/devices/" . rawurlencode($deviceId));

        return $resultado['sucesso'] ? $resultado['dados'] : null;
    }

    /**
     * Todos os dispositivos via API LEGADA (/api/s/{ref}/stat/device) --
     * usada só como fallback de casamento por IP: dispositivos gateway
     * reportam em /integration/v1 o IP do WAN (o que faz sentido pro
     * Controller, mas não é o IP de gerenciamento na LAN que a gente
     * cadastra em Ativos). A API legada expõe `port_table[].ip` por
     * porta, incluindo o IP da LAN -- ver coletarUnifi() em AtivoService.
     */
    public function listarDispositivosLegado(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return [];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/stat/device");

        return $resultado['sucesso'] ? ($resultado['dados']['data'] ?? []) : [];
    }

    /**
     * Clientes conectados via API LEGADA (/api/s/{ref}/stat/sta) -- ao
     * contrário de /integration/v1/clients, traz `essid` (rede Wi-Fi),
     * `signal` (dBm) e `ap_mac` (MAC do AP, casamento direto sem precisar
     * do id interno do dispositivo).
     */
    public function listarClientesLegado(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            NotificationService::error('Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.');
            return [];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/stat/sta");

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao listar clientes do UniFi Controller.', $resultado['mensagem']);
            return [];
        }

        return $resultado['dados']['data'] ?? [];
    }

    /** Registro completo (API legada) de UM dispositivo específico por MAC -- traz system-stats, temperatura, uptime, wan1/wan2, uptime_stats (disponibilidade/latência por alvo, ICMP e DNS) e speedtest-status, nada disso existe em /integration/v1. */
    public function buscarDispositivoLegado(string $mac): ?array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return null;
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/stat/device/" . rawurlencode($mac));

        return $resultado['sucesso'] ? ($resultado['dados']['data'][0] ?? null) : null;
    }

    /**
     * Todas as redes configuradas no site (WAN e LAN) -- só os campos
     * seguros pra guardar em `detalhes` e mostrar na ficha do ativo.
     * Deliberadamente NUNCA inclui `x_wan_password`/`wan_password` aqui:
     * a senha do PPPoE é sensível de verdade (credencial do provedor) e só
     * é buscada sob demanda (ver AtivoService::coletarUnifi()), nunca
     * ficando espalhada por outros métodos que não precisam dela.
     */
    public function listarRedesConfiguradas(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return [];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/rest/networkconf");

        if (!$resultado['sucesso']) {
            return [];
        }

        return $resultado['dados']['data'] ?? [];
    }

    /** Dispara um speedtest sob demanda no gateway (mesmo comando usado pelo próprio app oficial) -- ver AtivoService::avaliarInternetUnifi() pra acompanhar o resultado. */
    public function dispararSpeedtest(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado.'];
        }

        $resultado = $this->chamarApi('POST', "/proxy/network/api/s/{$ref}/cmd/devmgr", ['cmd' => 'speedtest']);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => 'Speedtest disparado.'];
    }

    /**
     * Todos os clientes já vistos pelo Controller (não só os conectados
     * agora) com `blocked = true` -- fonte pro botão "Desbloquear": um
     * cliente bloqueado desconecta na hora e some de listarClientesLegado()
     * (só mostra quem está conectado), então precisa dessa lista à parte
     * pra conseguir desbloquear alguém que já caiu da rede.
     */
    public function listarClientesBloqueados(): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return [];
        }

        $resultado = $this->chamarApi('GET', "/proxy/network/api/s/{$ref}/list/user");

        if (!$resultado['sucesso']) {
            NotificationService::error('Erro ao listar clientes bloqueados do UniFi Controller.', $resultado['mensagem']);
            return [];
        }

        return array_values(array_filter($resultado['dados']['data'] ?? [], fn($u) => !empty($u['blocked'])));
    }

    /** Desconecta o cliente agora -- reconecta sozinho em seguida (não é um bloqueio, só força uma nova associação). */
    public function desconectarCliente(string $mac): array
    {
        return $this->executarComandoStamgr('kick-sta', $mac, 'Cliente desconectado -- pode reconectar normalmente em seguida.');
    }

    /** Bloqueia o cliente -- fica impedido de conectar em qualquer AP deste site até ser desbloqueado (não existe bloqueio por tempo determinado nativo no Controller). */
    public function bloquearCliente(string $mac): array
    {
        return $this->executarComandoStamgr('block-sta', $mac, 'Cliente bloqueado -- vai continuar impedido de conectar até ser desbloqueado.');
    }

    public function desbloquearCliente(string $mac): array
    {
        return $this->executarComandoStamgr('unblock-sta', $mac, 'Cliente desbloqueado.');
    }

    /** @return array{success:bool, message:string} */
    private function executarComandoStamgr(string $cmd, string $mac, string $mensagemSucesso): array
    {
        $ref = $this->siteRefAtual();

        if ($ref === null) {
            return ['success' => false, 'message' => 'Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.'];
        }

        $resultado = $this->chamarApi('POST', "/proxy/network/api/s/{$ref}/cmd/stamgr", ['cmd' => $cmd, 'mac' => strtolower(trim($mac))]);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => $mensagemSucesso];
    }

    /** offset/limit até esgotar totalCount -- na prática quase sempre cabe numa página só, mas evita truncar silenciosamente em instalações maiores. */
    private function paginar(string $caminhoBase): array
    {
        $todos = [];
        $offset = 0;

        do {
            $separador = str_contains($caminhoBase, '?') ? '&' : '?';
            $resultado = $this->chamarApi('GET', "{$caminhoBase}{$separador}offset={$offset}&limit=" . self::LIMITE_PAGINA);

            if (!$resultado['sucesso']) {
                NotificationService::error('Erro ao consultar o UniFi Controller.', $resultado['mensagem']);
                return $todos;
            }

            $pagina = $resultado['dados']['data'] ?? [];
            foreach ($pagina as $item) {
                $todos[] = $item;
            }

            $total = (int)($resultado['dados']['totalCount'] ?? count($todos));
            $offset += self::LIMITE_PAGINA;
        } while ($offset < $total);

        return $todos;
    }

    /**
     * Chokepoint único de curl -- mesmo idioma do EntraService::chamarGraph()/KbService::chamarCentral(),
     * trocando só o header de autenticação (X-API-KEY em vez de Bearer token).
     *
     * @return array{sucesso:bool, dados:mixed, mensagem:string}
     */
    private function chamarApi(string $metodo, string $caminho, ?array $corpo = null): array
    {
        if (!$this->configurado()) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => 'Integração com o UniFi Controller ainda não configurada -- veja Integrações.'];
        }

        $apiKey = $this->apiKeyAtual();

        if ($apiKey === null) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => 'Não foi possível ler a API Key configurada.'];
        }

        $url = $this->urlAtual() . $caminho;

        $cabecalhos = ['X-API-KEY: ' . $apiKey, 'Accept: application/json'];
        if ($corpo !== null) {
            $cabecalhos[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $cabecalhos,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
        }

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => "Erro de comunicação com o Controller: {$erroCurl}"];
        }

        $dados = $resposta !== '' ? json_decode($resposta, true) : null;

        if ($codigo >= 200 && $codigo < 300) {
            return ['sucesso' => true, 'dados' => $dados, 'mensagem' => ''];
        }

        $mensagemErro = $dados['message'] ?? ($dados['error']['message'] ?? "Erro HTTP {$codigo} ao falar com o Controller.");

        return ['sucesso' => false, 'dados' => null, 'mensagem' => is_string($mensagemErro) ? $mensagemErro : "Erro HTTP {$codigo}."];
    }
}
