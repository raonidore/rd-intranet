<?php

namespace App\Services;

use App\Core\Database;

/**
 * Coleta somente-leitura de DVR/NVR Intelbras via a API HTTP CGI que o
 * próprio firmware já expõe -- confirmado ao vivo contra um MHDX 1116-C
 * real (linha inteira é rebrand Dahua, mesma família de protocolo já
 * documentada publicamente por fora da Intelbras, que trata a própria
 * documentação como confidencial/NDA). Autenticação é Digest (não Basic,
 * nem X-API-KEY/Bearer como UniFi/Omada), e a resposta vem em texto puro
 * "chave=valor" por linha -- não JSON.
 *
 * Credencial é POR IP -- diferente de UniFi/Omada (um Controller central
 * pra tudo), cada DVR/NVR tem o próprio login/senha admin, e nem sempre
 * são iguais entre equipamentos do mesmo cliente. Guarda cada uma na
 * tabela `intelbras_dvr_credenciais` (chave = IP). Existe também uma
 * credencial "padrão" global (mesma ideia da community padrão do SNMP),
 * usada só como fallback quando o IP não tem credencial própria --
 * cobre os casos (comuns, mas não garantidos) onde vários equipamentos
 * do site realmente compartilham a mesma senha admin.
 */
class IntelbrasDvrService
{
    private const CHAVE_USUARIO = 'intelbras_dvr_usuario';
    private const CHAVE_SENHA_CIFRADA = 'intelbras_dvr_senha_cifrada';
    private const CHAVE_DETECCAO_TAMPADA_ATIVA = 'intelbras_dvr_deteccao_tampada_ativa';

    /** Configurado = existe ALGUMA fonte de credencial (padrão global ou pelo menos uma por IP) -- usado só pra decidir se vale tentar essa integração. */
    public function configurado(): bool
    {
        if (ConfigService::get(self::CHAVE_USUARIO, '') !== '' && ConfigService::get(self::CHAVE_SENHA_CIFRADA, '') !== '') {
            return true;
        }

        $stmt = Database::connection()->query('SELECT COUNT(*) FROM intelbras_dvr_credenciais');

        return (int)$stmt->fetchColumn() > 0;
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

    /**
     * Chave geral pra ligar/desligar a detecção de "câmera tampada"
     * (VideoBlind) em todo o sistema -- default ativo, mas o próprio
     * detector do DVR (BlindDetect) já provou dar falso positivo com
     * frequência em cena escura/baixo contraste, então é bom ter como
     * desligar rápido sem precisar mexer em cada canal.
     */
    public function deteccaoTampadaAtiva(): bool
    {
        return ConfigService::get(self::CHAVE_DETECCAO_TAMPADA_ATIVA, '1') === '1';
    }

    public function definirDeteccaoTampadaAtiva(bool $ativo): void
    {
        ConfigService::set(self::CHAVE_DETECCAO_TAMPADA_ATIVA, $ativo ? '1' : '0');

        AuditService::registrar('Intelbras DVR/NVR', 'Configuração', 'Detecção de câmera tampada ' . ($ativo ? 'ativada' : 'desativada') . '.');
        NotificationService::success('Detecção de câmera tampada ' . ($ativo ? 'ativada' : 'desativada') . '.');
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

    /**
     * Todas as credenciais por IP cadastradas, com a senha já decifrada --
     * usada só pra tela de Integrações mostrar/revelar (o pedido explícito
     * foi poder conferir a senha de cada DVR quando precisar confirmar com
     * quem instalou o equipamento). Nunca exposta em nenhum outro lugar.
     *
     * @return array<int, array{id:int, ip:string, usuario:string, senha:string, atualizado_em:string}>
     */
    public function listarCredenciais(): array
    {
        $stmt = Database::connection()->query('SELECT id, ip, usuario, senha_cifrada, atualizado_em FROM intelbras_dvr_credenciais ORDER BY ip');

        $credenciais = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $linha) {
            try {
                $senha = CryptoService::decriptar($linha['senha_cifrada']);
            } catch (\RuntimeException $e) {
                $senha = '';
            }

            $credenciais[] = [
                'id' => (int)$linha['id'],
                'ip' => $linha['ip'],
                'usuario' => $linha['usuario'],
                'senha' => $senha,
                'atualizado_em' => $linha['atualizado_em'],
            ];
        }

        return $credenciais;
    }

    /** @return array{success:bool, message:string} */
    public function salvarCredencialPorIp(string $ip, string $usuario, string $senha): array
    {
        $ip = trim($ip);
        $usuario = trim($usuario);
        $senha = trim($senha);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['success' => false, 'message' => 'IP inválido.'];
        }

        if ($usuario === '' || $senha === '') {
            return ['success' => false, 'message' => 'Informe usuário e senha.'];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO intelbras_dvr_credenciais (ip, usuario, senha_cifrada)
            VALUES (:ip, :usuario, :senha_cifrada)
            ON DUPLICATE KEY UPDATE usuario = VALUES(usuario), senha_cifrada = VALUES(senha_cifrada)
        ');
        $stmt->execute([
            'ip' => $ip,
            'usuario' => $usuario,
            'senha_cifrada' => CryptoService::encriptar($senha),
        ]);

        AuditService::registrar('Intelbras DVR/NVR', 'Credencial por IP', "Credencial de {$ip} salva (usuário: {$usuario}).");

        return ['success' => true, 'message' => "Credencial de {$ip} salva."];
    }

    public function removerCredencialPorIp(string $ip): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM intelbras_dvr_credenciais WHERE ip = ?');
        $stmt->execute([$ip]);

        AuditService::registrar('Intelbras DVR/NVR', 'Credencial por IP', "Credencial de {$ip} removida.");
    }

    /** Credencial própria do IP tem prioridade; sem ela, cai pra padrão global (se houver). */
    private function credencialParaIp(string $ip): ?array
    {
        $stmt = Database::connection()->prepare('SELECT usuario, senha_cifrada FROM intelbras_dvr_credenciais WHERE ip = ?');
        $stmt->execute([$ip]);
        $linha = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($linha) {
            try {
                return ['usuario' => $linha['usuario'], 'senha' => CryptoService::decriptar($linha['senha_cifrada'])];
            } catch (\RuntimeException $e) {
                return null;
            }
        }

        $usuarioPadrao = $this->usuarioAtual();
        $senhaPadrao = $this->senhaAtual();

        if ($usuarioPadrao !== '' && $senhaPadrao !== null) {
            return ['usuario' => $usuarioPadrao, 'senha' => $senhaPadrao];
        }

        return null;
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
     *   hardware?:?string, nome_dispositivo?:?string, status_disco?:?string, disco_total_gb?:?float,
     *   disco_usado_gb?:?float, hd_problema?:?string, canais?:array}
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

        // Chave geral desligada = nem consulta o VideoBlind nem a
        // sensibilidade -- o próprio detector do DVR (BlindDetect) provou
        // dar falso positivo com frequência em cena escura/baixo contraste
        // (confirmado ao vivo comparando com o snapshot real), então quem
        // desligar a chave não paga nem o custo das 2 chamadas HTTP extras.
        $deteccaoTampadaAtiva = $this->deteccaoTampadaAtiva();
        $canaisComBlind = [];
        $blindConfig = null;
        if ($deteccaoTampadaAtiva) {
            $videoBlind = $this->chamarApi($ip, '/cgi-bin/eventManager.cgi?action=getEventIndexes&code=VideoBlind');
            $canaisComBlind = $this->extrairCanaisDoEvento($videoBlind);
            // Level = sensibilidade do detector de blind, 1 (menos sensível)
            // a 6 (mais sensível), 3 é o padrão de fábrica -- vem junto de
            // BlindDetect[N].Enable etc. numa única chamada, um índice por
            // canal (0-based), documentado na API oficial da Intelbras/Dahua.
            $blindConfig = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=BlindDetect');
        }

        // Ausência/falha de HD e pouco espaço livre -- quando responde
        // "Error: No Events" (nunca aconteceu), o parser genérico de
        // chamarApi() já trata como sucesso com $dados vazio (não é erro de
        // comunicação de verdade, só "sem ocorrência").
        $semHd = $this->chamarApi($ip, '/cgi-bin/eventManager.cgi?action=getEventIndexes&code=StorageNotExist');
        $poucoEspaco = $this->chamarApi($ip, '/cgi-bin/eventManager.cgi?action=getEventIndexes&code=StorageLowSpace');

        // Dahua reporta o canal em índice 0 -- some 1 pra bater com a
        // numeração "Canal 1..N" que o próprio DVR mostra na tela dele.
        $canaisComPerda = $this->extrairCanaisDoEvento($videoLoss);

        $canais = [];
        if ($canaisNome['sucesso']) {
            $i = 0;
            while (isset($canaisNome['dados']["table.ChannelTitle[{$i}].Name"])) {
                $numero = $i + 1;
                $nivelSensibilidade = $blindConfig && $blindConfig['sucesso'] && isset($blindConfig['dados']["table.BlindDetect[{$i}].Level"])
                    ? (int)$blindConfig['dados']["table.BlindDetect[{$i}].Level"]
                    : null;
                $canais[] = [
                    'numero' => $numero,
                    'nome' => $canaisNome['dados']["table.ChannelTitle[{$i}].Name"],
                    'com_sinal' => !in_array($numero, $canaisComPerda, true),
                    'tampada' => $deteccaoTampadaAtiva && in_array($numero, $canaisComBlind, true),
                    'sensibilidade_tampada' => $nivelSensibilidade,
                ];
                $i++;
            }
        }

        // Não temos como testar a forma exata de uma resposta POSITIVA desses
        // dois (não vamos tirar o HD de um DVR em produção só pra ver o
        // formato) -- por isso não tenta reconhecer um "channels[]" como no
        // VideoLoss/VideoBlind. "Error: No Events" (o caso sem ocorrência,
        // confirmado ao vivo) vira $dados vazio no parser genérico; qualquer
        // outra coisa que não seja esse vazio já é sinal de problema, seja
        // qual for o formato exato.
        $hdProblema = null;
        if ($semHd['sucesso'] && !empty($semHd['dados'])) {
            $hdProblema = 'Disco não encontrado';
        } elseif ($poucoEspaco['sucesso'] && !empty($poucoEspaco['dados'])) {
            $hdProblema = 'Pouco espaço livre no disco';
        }

        // TotalBytes/UsedBytes são por partição (cada disco físico vem
        // fatiado em várias "Detail[]", confirmado ao vivo: 4 partições de
        // ~500GB somando ~2TB, batendo com um disco real de 2TB) -- soma
        // tudo (todos os discos, todas as partições) pra virar uma
        // capacidade só. Usado == Total é o normal aqui (grava em buffer
        // circular, disco sempre cheio de gravação -- não é um alerta de
        // disco quase lotado como seria num servidor comum).
        $discoTotalBytes = 0.0;
        $discoUsadoBytes = 0.0;
        $temDisco = false;
        if ($storage['sucesso']) {
            $i = 0;
            while (isset($storage['dados']["list.info[{$i}].Name"])) {
                $j = 0;
                while (isset($storage['dados']["list.info[{$i}].Detail[{$j}].TotalBytes"])) {
                    $discoTotalBytes += (float)$storage['dados']["list.info[{$i}].Detail[{$j}].TotalBytes"];
                    $discoUsadoBytes += (float)($storage['dados']["list.info[{$i}].Detail[{$j}].UsedBytes"] ?? 0);
                    $temDisco = true;
                    $j++;
                }
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
            'status_disco' => $storage['sucesso'] ? ($storage['dados']['list.info[0].State'] ?? null) : null,
            'disco_total_gb' => $temDisco ? round($discoTotalBytes / 1_000_000_000, 1) : null,
            'disco_usado_gb' => $temDisco ? round($discoUsadoBytes / 1_000_000_000, 1) : null,
            'hd_problema' => $hdProblema,
            'canais' => $canais,
        ];
    }

    /**
     * A resposta de getEventIndexes vem como "channels[N]=X" -- N é só a
     * posição na lista (0, 1, 2...), o canal afetado de verdade é o VALOR X
     * (0-based). Soma 1 pra bater com a numeração "Canal 1..N" que o próprio
     * DVR mostra.
     *
     * @return int[]
     */
    private function extrairCanaisDoEvento(array $resultado): array
    {
        if (!$resultado['sucesso']) {
            return [];
        }

        $canais = [];
        foreach ($resultado['dados'] as $chave => $valor) {
            if (str_starts_with($chave, 'channels[')) {
                $canais[] = (int)$valor + 1;
            }
        }

        return $canais;
    }

    /**
     * Foto (snapshot) atual de um canal -- confirmado ao vivo (JPEG real,
     * 704x480, ~23KB) contra um MHDX 1116-C real. Binário puro, não passa
     * pelo parser "chave=valor" de chamarApi() (que destruiria os bytes da
     * imagem), por isso tem o próprio curl aqui.
     *
     * @return array{success:bool, imagem?:string, content_type?:string, message?:string}
     */
    public function snapshot(string $ip, int $canal): array
    {
        $credencial = $this->credencialParaIp($ip);

        if ($credencial === null) {
            return ['success' => false, 'message' => "Nenhuma credencial cadastrada para {$ip} -- veja Integrações."];
        }

        $url = "http://{$ip}/cgi-bin/snapshot.cgi?channel={$canal}&type=0";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $credencial['usuario'] . ':' . $credencial['senha'],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tipoConteudo = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            return ['success' => false, 'message' => "Erro de comunicação com o DVR/NVR: {$erroCurl}"];
        }

        if ($codigo === 401) {
            return ['success' => false, 'message' => 'Usuário/senha recusados pelo DVR/NVR.'];
        }

        if ($codigo < 200 || $codigo >= 300 || !str_starts_with((string)$tipoConteudo, 'image/')) {
            return ['success' => false, 'message' => "Canal {$canal} não retornou uma imagem válida (câmera pode estar sem sinal)."];
        }

        return ['success' => true, 'imagem' => $resposta, 'content_type' => $tipoConteudo];
    }

    /**
     * Renomeia um canal -- confirmado ao vivo (setConfig + getConfig de
     * confirmação) contra um MHDX 1116-C real. '|' na API representa quebra
     * de linha no nome exibido na tela do DVR (até 2 linhas) -- aqui só
     * aceita uma linha, então tira qualquer '|' que venha do usuário antes
     * de enviar.
     *
     * @return array{success:bool, message:string}
     */
    public function renomearCanal(string $ip, int $canal, string $novoNome): array
    {
        $novoNome = trim(str_replace('|', ' ', $novoNome));

        if ($novoNome === '') {
            return ['success' => false, 'message' => 'Informe um nome.'];
        }

        // índice do ChannelTitle é 0-based; "canal" na tela/API de leitura é 1-based.
        $indice = $canal - 1;
        $resultado = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=setConfig&ChannelTitle%5B' . $indice . '%5D.Name=' . rawurlencode($novoNome));

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => "Canal {$canal} renomeado para \"{$novoNome}\"."];
    }

    /**
     * Sensibilidade do detector de "tampada" (BlindDetect) do próprio DVR,
     * por canal -- 1 (menos sensível, dispara só com bloqueio bem óbvio) a
     * 6 (mais sensível, dispara com qualquer mudança pequena de cena),
     * faixa documentada oficialmente. Baixar o nível é o jeito de reduzir
     * falso positivo em canal que fica de frente pra cena escura/baixo
     * contraste sem mexer no "com_sinal"/resto da coleta.
     */
    public function definirSensibilidadeTampada(string $ip, int $canal, int $nivel): array
    {
        if ($nivel < 1 || $nivel > 6) {
            return ['success' => false, 'message' => 'Sensibilidade precisa estar entre 1 e 6.'];
        }

        // índice do BlindDetect é 0-based; "canal" na tela/API de leitura é 1-based.
        $indice = $canal - 1;
        $resultado = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=setConfig&BlindDetect%5B' . $indice . '%5D.Level=' . $nivel);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => "Sensibilidade do canal {$canal} ajustada pra {$nivel}."];
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
        $credencial = $this->credencialParaIp($ip);

        if ($credencial === null) {
            return ['sucesso' => false, 'dados' => [], 'mensagem' => "Nenhuma credencial cadastrada para {$ip} (nem padrão) -- veja Integrações."];
        }

        $url = "http://{$ip}{$caminho}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $credencial['usuario'] . ':' . $credencial['senha'],
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
