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

    /** Todos os clientes (com/sem fio) do site -- cada um traz uplinkDeviceId, usado pra saber em qual AP/switch está conectado. */
    public function listarClientes(): array
    {
        $siteId = $this->siteIdAtual();

        if ($siteId === null) {
            NotificationService::error('Site do UniFi Controller ainda não identificado -- use "Testar conexão" em Integrações > UniFi.');
            return [];
        }

        return $this->paginar("/proxy/network/integration/v1/sites/{$siteId}/clients");
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
    private function chamarApi(string $metodo, string $caminho): array
    {
        if (!$this->configurado()) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => 'Integração com o UniFi Controller ainda não configurada -- veja Integrações.'];
        }

        $apiKey = $this->apiKeyAtual();

        if ($apiKey === null) {
            return ['sucesso' => false, 'dados' => null, 'mensagem' => 'Não foi possível ler a API Key configurada.'];
        }

        $url = $this->urlAtual() . $caminho;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => ['X-API-KEY: ' . $apiKey, 'Accept: application/json'],
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

        if ($codigo >= 200 && $codigo < 300) {
            return ['sucesso' => true, 'dados' => $dados, 'mensagem' => ''];
        }

        $mensagemErro = $dados['message'] ?? ($dados['error']['message'] ?? "Erro HTTP {$codigo} ao falar com o Controller.");

        return ['sucesso' => false, 'dados' => null, 'mensagem' => is_string($mensagemErro) ? $mensagemErro : "Erro HTTP {$codigo}."];
    }
}
