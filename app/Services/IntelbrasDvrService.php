<?php

namespace App\Services;

/**
 * Coleta somente-leitura de DVR/NVR Intelbras via a API HTTP CGI que o
 * próprio firmware já expõe -- confirmado ao vivo contra um MHDX 1116-C
 * real (linha inteira é rebrand Dahua, mesma família de protocolo já
 * documentada publicamente por fora da Intelbras, que trata a própria
 * documentação como confidencial/NDA). Autenticação é Digest (não Basic,
 * nem X-API-KEY/Bearer como UniFi/Omada), e a resposta vem em texto puro
 * "chave=valor" por linha -- não JSON.
 *
 * Credencial é uma só, GLOBAL (usuário/senha admin do DVR) -- aplicada a
 * todos os ativos tipo dvr_nvr com IP cadastrado, mesmo modelo da
 * community padrão do SNMP (a grande maioria dos clientes usa a mesma
 * senha admin em todos os DVR/NVR do site). Não existe um "site"/URL fixo
 * pra testar como UniFi/Omada -- o teste de verdade acontece na coleta em
 * si, contra o IP de cada ativo.
 */
class IntelbrasDvrService
{
    private const CHAVE_USUARIO = 'intelbras_dvr_usuario';
    private const CHAVE_SENHA_CIFRADA = 'intelbras_dvr_senha_cifrada';

    public function configurado(): bool
    {
        return ConfigService::get(self::CHAVE_USUARIO, '') !== '' && ConfigService::get(self::CHAVE_SENHA_CIFRADA, '') !== '';
    }

    public function usuarioAtual(): string
    {
        return (string)ConfigService::get(self::CHAVE_USUARIO, '');
    }

    public function salvarConfiguracao(string $usuario, string $senha): bool
    {
        $usuario = trim($usuario);
        $senha = trim($senha);

        if ($usuario === '') {
            NotificationService::error('Informe o usuário admin do DVR/NVR.');
            return false;
        }

        // Senha em branco mantém a atual -- só exige quando ainda não havia nenhuma.
        if ($senha === '' && !$this->configurado()) {
            NotificationService::error('Informe a senha.');
            return false;
        }

        ConfigService::set(self::CHAVE_USUARIO, $usuario);
        if ($senha !== '') {
            ConfigService::set(self::CHAVE_SENHA_CIFRADA, CryptoService::encriptar($senha));
        }

        AuditService::registrar('Intelbras DVR/NVR', 'Configuração', "Usuário admin atualizado ({$usuario}).");
        NotificationService::success('Configuração salva -- aplicada a todos os DVR/NVR cadastrados com IP.');

        return true;
    }

    public function removerConfiguracao(): void
    {
        ConfigService::set(self::CHAVE_USUARIO, '');
        ConfigService::set(self::CHAVE_SENHA_CIFRADA, '');

        AuditService::registrar('Intelbras DVR/NVR', 'Configuração', 'Configuração removida.');
        NotificationService::success('Configuração removida.');
    }

    private function senhaAtual(): ?string
    {
        $cifrada = ConfigService::get(self::CHAVE_SENHA_CIFRADA, '');

        if (!$cifrada) {
            return null;
        }

        try {
            return CryptoService::decriptar($cifrada);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /** @return array{success:bool, message:string} */
    public function testarConexao(string $ip): array
    {
        $resultado = $this->chamarApi($ip, '/cgi-bin/magicBox.cgi?action=getDeviceType');

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $tipo = $resultado['dados']['type'] ?? '?';

        return ['success' => true, 'message' => "Conectado com sucesso -- dispositivo \"{$tipo}\"."];
    }

    /**
     * @return array{success:bool, message?:string, modelo?:?string, serial?:?string, firmware?:?string,
     *   hardware?:?string, nome_dispositivo?:?string, status_disco?:?string, canais?:array}
     */
    public function coletar(string $ip): array
    {
        $sysInfo = $this->chamarApi($ip, '/cgi-bin/magicBox.cgi?action=getSystemInfo');

        if (!$sysInfo['sucesso']) {
            return ['success' => false, 'message' => $sysInfo['mensagem']];
        }

        $tipo = $this->chamarApi($ip, '/cgi-bin/magicBox.cgi?action=getDeviceType');
        $sw = $this->chamarApi($ip, '/cgi-bin/magicBox.cgi?action=getSoftwareVersion');
        $hw = $this->chamarApi($ip, '/cgi-bin/magicBox.cgi?action=getHardwareVersion');
        $geral = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=General');
        $canaisNome = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=ChannelTitle');
        $storage = $this->chamarApi($ip, '/cgi-bin/storageDevice.cgi?action=getDeviceAllInfo');
        $videoLoss = $this->chamarApi($ip, '/cgi-bin/eventManager.cgi?action=getEventIndexes&code=VideoLoss');

        // Dahua reporta o canal em índice 0 -- some 1 pra bater com a
        // numeração "Canal 1..N" que o próprio DVR mostra na tela dele.
        $canaisComPerda = [];
        if ($videoLoss['sucesso']) {
            foreach ($videoLoss['dados'] as $chave => $valor) {
                if (str_starts_with($chave, 'channels[')) {
                    $canaisComPerda[] = (int)$valor + 1;
                }
            }
        }

        $canais = [];
        if ($canaisNome['sucesso']) {
            $i = 0;
            while (isset($canaisNome['dados']["table.ChannelTitle[{$i}].Name"])) {
                $numero = $i + 1;
                $canais[] = [
                    'numero' => $numero,
                    'nome' => $canaisNome['dados']["table.ChannelTitle[{$i}].Name"],
                    'com_sinal' => !in_array($numero, $canaisComPerda, true),
                ];
                $i++;
            }
        }

        return [
            'success' => true,
            'modelo' => $tipo['dados']['type'] ?? null,
            'serial' => $sysInfo['dados']['serialNumber'] ?? null,
            // getSoftwareVersion vem como "4.002.00IB000.0.T,build:2024-04-17 15:10:54" -- só a versão interessa aqui.
            'firmware' => isset($sw['dados']['version']) ? explode(',', $sw['dados']['version'])[0] : null,
            'hardware' => $hw['dados']['version'] ?? null,
            'nome_dispositivo' => $geral['dados']['table.General.MachineName'] ?? null,
            // 'State' (Success/Error) -- não tenta calcular capacidade usada em % aqui:
            // os discos de DVR gravam em buffer circular (fica sempre ~100% "usado"
            // por design), então um % de uso não significa a mesma coisa que num
            // servidor comum -- mostrar isso sem contexto induziria a um alarme falso.
            'status_disco' => $storage['sucesso'] ? ($storage['dados']['list.info[0].State'] ?? null) : null,
            'canais' => $canais,
        ];
    }

    /**
     * Chokepoint único de curl -- Digest auth e resposta em texto puro
     * "chave=valor" por linha (não JSON), confirmado ao vivo contra o
     * firmware real.
     *
     * @return array{sucesso:bool, dados:array<string,string>, mensagem:string}
     */
    private function chamarApi(string $ip, string $caminho): array
    {
        if (!$this->configurado()) {
            return ['sucesso' => false, 'dados' => [], 'mensagem' => 'Integração com DVR/NVR Intelbras ainda não configurada -- veja Integrações.'];
        }

        $senha = $this->senhaAtual();

        if ($senha === null) {
            return ['sucesso' => false, 'dados' => [], 'mensagem' => 'Não foi possível ler a senha configurada.'];
        }

        $url = "http://{$ip}{$caminho}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $this->usuarioAtual() . ':' . $senha,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            return ['sucesso' => false, 'dados' => [], 'mensagem' => "Erro de comunicação com o DVR/NVR: {$erroCurl}"];
        }

        if ($codigo === 401) {
            return ['sucesso' => false, 'dados' => [], 'mensagem' => 'Usuário/senha recusados pelo DVR/NVR.'];
        }

        if ($codigo < 200 || $codigo >= 300) {
            return ['sucesso' => false, 'dados' => [], 'mensagem' => "Erro HTTP {$codigo} ao falar com o DVR/NVR."];
        }

        if (str_contains($resposta, 'Bad Request') || str_contains($resposta, 'Not Implemented')) {
            return ['sucesso' => false, 'dados' => [], 'mensagem' => 'Comando não suportado por este dispositivo.'];
        }

        $dados = [];
        foreach (explode("\n", trim($resposta)) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || !str_contains($linha, '=')) {
                continue;
            }
            [$chave, $valor] = explode('=', $linha, 2);
            $dados[$chave] = $valor;
        }

        return ['sucesso' => true, 'dados' => $dados, 'mensagem' => ''];
    }
}
