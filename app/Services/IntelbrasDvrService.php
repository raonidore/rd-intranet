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

    /**
     * O "Type" do log de conta vem localizado pelo próprio firmware (ex:
     * "Usuário Logado", "Fazer logoff" -- confirmado ao vivo, nem bate com o
     * inglês do manual oficial), então não dá pra fazer uma lista positiva
     * de "isso é falha de login" por string exata. Em vez disso, qualquer
     * "Type" que contenha uma destas palavras (case-insensitive) é tratado
     * como suspeito -- o resto (login/logoff normal, troca de config etc.)
     * fica de fora de propósito, mesma filosofia de "só alerta com
     * confiança" usada pro VideoBlind (evita alarme falso).
     */
    private const PALAVRAS_EVENTO_CONTA_SUSPEITO = ['falha', 'fail', 'incorret', 'wrong', 'bloque', 'lock', 'invalid', 'invál', 'negad', 'denied', 'error', 'erro'];

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

    /** Usuário/senha digitados na hora (Detectar do Novo Ativo) -- ganham da credencial salva. */
    private ?array $credencialForcada = null;

    public function comCredencial(string $usuario, string $senha): static
    {
        $this->credencialForcada = ['usuario' => $usuario, 'senha' => $senha];

        return $this;
    }

    /**
     * Reconhece um DVR/NVR Intelbras (família Dahua) SEM login: a API CGI
     * responde 401 com Digest realm "Login to <id>" (confirmado num NVD 1408 P
     * do enzilab) e a página inicial tem "Intelbras" no título. Uma requisição
     * sem senha não conta como tentativa falha, então não arrisca bloquear a conta.
     */
    public function pareceIntelbras(string $ip): bool
    {
        $cabecalhos = '';
        $ch = curl_init("http://{$ip}/cgi-bin/magicBox.cgi?action=getDeviceType");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HEADERFUNCTION => function ($ch, $linha) use (&$cabecalhos) {
                $cabecalhos .= $linha;
                return strlen($linha);
            },
        ]);
        curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($codigo === 401 && preg_match('/WWW-Authenticate:\s*Digest\s+realm="Login to /i', $cabecalhos)) {
            return true;
        }

        $ch = curl_init("http://{$ip}/");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3]);
        $pagina = (string)curl_exec($ch);
        curl_close($ch);

        return (bool)preg_match('/<title>[^<]*Intelbras/i', $pagina);
    }

    /** Credencial digitada na hora > própria do IP > padrão global (se houver). */
    private function credencialParaIp(string $ip): ?array
    {
        if ($this->credencialForcada !== null) {
            return $this->credencialForcada;
        }

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
     * $deteccaoTampadaAtiva é decidido por fora (é uma chave por Ativo, não
     * do equipamento nem do sistema todo -- ver AtivoService::coletarIntelbrasDvr()).
     *
     * @return array{success:bool, message?:string, modelo?:?string, serial?:?string, firmware?:?string,
     *   hardware?:?string, nome_dispositivo?:?string, status_disco?:?string, disco_total_gb?:?float,
     *   disco_usado_gb?:?float, hd_problema?:?string, canais?:array}
     */
    public function coletar(string $ip, bool $deteccaoTampadaAtiva = true): array
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

        // A sensibilidade (BlindDetect) é sempre lida (é uma config do
        // próprio DVR, útil pra ajustar mesmo com a detecção desligada aqui
        // dentro), mas o evento VideoBlind em si só é consultado com a
        // chave ligada -- o próprio detector do DVR já provou dar falso
        // positivo com frequência em cena escura/baixo contraste
        // (confirmado ao vivo comparando com o snapshot real), então quem
        // desligar a chave desse Ativo não paga nem o custo da chamada extra.
        $canaisComBlind = [];
        if ($deteccaoTampadaAtiva) {
            $videoBlind = $this->chamarApi($ip, '/cgi-bin/eventManager.cgi?action=getEventIndexes&code=VideoBlind');
            $canaisComBlind = $this->extrairCanaisDoEvento($videoBlind);
        }
        // Level = sensibilidade do detector de blind, 1 (menos sensível) a 6
        // (mais sensível), 3 é o padrão de fábrica -- vem junto de
        // BlindDetect[N].Enable etc. numa única chamada, um índice por canal
        // (0-based), documentado na API oficial da Intelbras/Dahua.
        $blindConfig = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=BlindDetect');

        // Substituto viável do "status de gravação ao vivo" que a API mais
        // nova (recordManager/getStateAll, JSON) prometia -- confirmado ao
        // vivo contra os 3 DVR/NVR reais do cliente que aquele endpoint
        // devolve 400/501 (não implementado nesse firmware, nos dois
        // modelos testados). RecordMode é config clássica (mesma família já
        // comprovada) e não diz "está gravando agora" com certeza, mas diz
        // se o canal está CONFIGURADO pra nunca gravar (Mode=2) -- uma
        // câmera com sinal nesse estado está com um problema real e
        // silencioso (ninguém percebe olhando a imagem ao vivo).
        $recordMode = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=RecordMode');

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
                $modoGravacao = $recordMode['sucesso'] && isset($recordMode['dados']["table.RecordMode[{$i}].Mode"])
                    ? match ((int)$recordMode['dados']["table.RecordMode[{$i}].Mode"]) {
                        0 => 'automatico',
                        1 => 'manual',
                        2 => 'parado',
                        default => null,
                    }
                    : null;
                $canais[] = [
                    'numero' => $numero,
                    'nome' => $canaisNome['dados']["table.ChannelTitle[{$i}].Name"],
                    'com_sinal' => !in_array($numero, $canaisComPerda, true),
                    'tampada' => $deteccaoTampadaAtiva && in_array($numero, $canaisComBlind, true),
                    'sensibilidade_tampada' => $nivelSensibilidade,
                    'modo_gravacao' => $modoGravacao,
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

        // RPC2 (quando o firmware tem): o evento clássico StorageNotExist
        // responde "No Events" mesmo SEM HD (NVR do enzilab, 04/10/2026) --
        // aqui "nenhum disco" e o S.M.A.R.T. falhando viram problema de HD.
        $rpc2 = $this->coletarRpc2($ip);
        if ($rpc2 !== null) {
            if ($rpc2['sem_hd']) {
                $hdProblema = 'Sem HD instalado -- nada está sendo gravado';
            } elseif ($hdProblema === null) {
                foreach ($rpc2['discos'] as $disco) {
                    if (($disco['smart']['status'] ?? '') === 'falhando') {
                        $hdProblema = 'HD falhando (S.M.A.R.T.): ' . trim($disco['modelo'] . ' ' . $disco['serial']) . ' -- ' . implode('; ', $disco['smart']['alertas']);
                        break;
                    }
                }
            }
            foreach ($canais as &$canal) {
                $canal['gravando'] = $rpc2['gravando'][$canal['numero']] ?? null;
            }
            unset($canal);
        }

        return [
            'success' => true,
            'rpc2' => $rpc2,
            'exposicao' => $this->configuracaoExposicao($ip),
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

    /*
     |---------------------------------------------------------
     | RPC2 -- JSON-RPC que a própria interface web do equipamento usa.
     | Confirmado ao vivo (04/10/2026) num NVD 1408 P (fw 4.001) e nos
     | XVR4232AN-X / MHDX 1116-C da Patrimonial: dá o que a CGI clássica
     | não dá (gravando de verdade por canal, HDs com S.M.A.R.T., câmeras
     | IP, relatório de segurança do próprio equipamento). A REST
     | "/cgi-bin/api/..." do manual continua inexistente nesses firmwares.
     | Tudo aqui é leitura.
     |---------------------------------------------------------
     */

    /** IDs S.M.A.R.T. que, com valor bruto > 0, indicam setor ruim/erro não corrigido -- disco começando a falhar. */
    private const SMART_ATENCAO = [
        5 => 'setores realocados',
        187 => 'erros não corrigidos',
        196 => 'eventos de realocação',
        197 => 'setores pendentes',
        198 => 'setores irrecuperáveis',
    ];

    /** Nome legível dos itens do relatório SecurityScan do equipamento. */
    private const ITENS_VERIFICACAO = [
        'RTSPLoginMode' => 'Autenticação no RTSP',
        'AnonLoginMode' => 'Login anônimo',
        'PriPwdStat' => 'Senha do usuário admin',
        'OnvifPwdStat' => 'Senha ONVIF',
        'SNMP' => 'SNMP',
        'SMTP' => 'E-mail (SMTP)',
        'FTP' => 'FTP',
        'HTTPS' => 'HTTPS',
        'PriVideoEncTrans' => 'Criptografia do vídeo',
        'RTSP-TLS' => 'RTSP com TLS',
        'SecureBoot' => 'Boot seguro',
        'TrustEnv' => 'Ambiente confiável',
        'TrustUpdate' => 'Atualização confiável',
        'SecWarn' => 'Alerta de segurança',
        'BruteWarn' => 'Alerta de força bruta',
        'SyncFlood' => 'Proteção SYN flood',
        'ICMPFlood' => 'Proteção ICMP flood',
        'Firewall' => 'Firewall',
        'AccountLock' => 'Bloqueio de conta',
        'FirmwareEnc' => 'Firmware criptografado',
    ];

    private function postJson(string $url, array $corpo): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($corpo),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $resposta = curl_exec($ch);
        curl_close($ch);

        $dados = is_string($resposta) ? json_decode($resposta, true) : null;

        return is_array($dados) ? $dados : null;
    }

    /**
     * Login em 2 passos: o 1º (sem senha) devolve realm/random; a senha vai
     * como MD5("user:random:" . MD5("user:realm:senha")), maiúsculo. Devolve
     * a sessão ou null (equipamento sem RPC2, ou credencial recusada).
     */
    private function rpcLogin(string $ip): ?string
    {
        $credencial = $this->credencialParaIp($ip);
        if ($credencial === null) {
            return null;
        }
        $usuario = $credencial['usuario'];

        $desafio = $this->postJson("http://{$ip}/RPC2_Login", [
            'method' => 'global.login',
            'params' => ['userName' => $usuario, 'password' => '', 'clientType' => 'Web3.0'],
            'id' => 1,
        ]);
        if (!isset($desafio['session'], $desafio['params']['realm'], $desafio['params']['random'])) {
            return null;
        }

        $ha1 = strtoupper(md5("{$usuario}:{$desafio['params']['realm']}:{$credencial['senha']}"));
        $login = $this->postJson("http://{$ip}/RPC2_Login", [
            'method' => 'global.login',
            'params' => [
                'userName' => $usuario,
                'password' => strtoupper(md5("{$usuario}:{$desafio['params']['random']}:{$ha1}")),
                'clientType' => 'Web3.0',
                'authorityType' => 'Default',
                'passwordType' => 'Default',
            ],
            'id' => 2,
            'session' => $desafio['session'],
        ]);

        return !empty($login['result']) && !empty($login['session']) ? (string)$login['session'] : null;
    }

    /** @return array|null a resposta inteira ({result, params}) ou null se falhou */
    private function rpc(string $ip, string $sessao, string $metodo, ?array $params = null, ?int $objeto = null): ?array
    {
        static $id = 10;
        $corpo = ['method' => $metodo, 'id' => $id++, 'session' => $sessao];
        if ($params !== null) {
            $corpo['params'] = $params;
        }
        if ($objeto !== null) {
            $corpo['object'] = $objeto;
        }

        $resposta = $this->postJson("http://{$ip}/RPC2", $corpo);

        return $resposta !== null && !empty($resposta['result']) ? $resposta : null;
    }

    /**
     * Coleta extra via RPC2. null = equipamento sem RPC2 (ou login recusado)
     * -- quem chama segue só com a CGI clássica, como sempre foi.
     *
     * @return array{gravando: array<int,bool>, sem_hd: bool, discos: array, cameras: array, verificacao: ?array}|null
     */
    public function coletarRpc2(string $ip): ?array
    {
        $sessao = $this->rpcLogin($ip);
        if ($sessao === null) {
            return null;
        }

        try {
            // Gravando agora, por canal. O NVR aninha em state.state, os DVRs
            // não; canal sem câmera vem null.
            $gravando = [];
            $estado = $this->rpc($ip, $sessao, 'recordManager.getStateAll');
            $lista = $estado['params']['state']['state'] ?? $estado['params']['state'] ?? [];
            foreach (is_array($lista) ? $lista : [] as $i => $canal) {
                if (is_array($canal)) {
                    $gravando[$i + 1] = !empty($canal['Main']['State']) || !empty($canal['Extra1']['State']);
                }
            }

            // HDs + S.M.A.R.T. "device": null = nenhum disco instalado.
            $infos = $this->rpc($ip, $sessao, 'StorageDeviceManager.getDeviceInfos');
            $discos = [];
            foreach (($infos['params']['device'] ?? null) ?: [] as $disco) {
                $total = 0;
                $livre = 0;
                foreach ($disco['Partitions'] ?? [] as $particao) {
                    $total += (float)($particao['Total'] ?? 0);
                    $livre += (float)($particao['Remain'] ?? 0);
                }
                $discos[] = [
                    'nome' => $disco['Name'] ?? '',
                    'modelo' => trim((string)($disco['Module'] ?? '')),
                    'serial' => trim((string)($disco['SerialNo'] ?? '')),
                    'capacidade_gb' => round((float)($disco['Capacity'] ?? 0) / 1_000_000_000),
                    'estado' => $disco['State'] ?? '',
                    'smart' => $this->smartDoDisco($ip, $sessao, (string)($disco['Name'] ?? '')),
                ];
            }

            return [
                'gravando' => $gravando,
                'sem_hd' => $infos !== null && empty($infos['params']['device']),
                'discos' => $discos,
                'cameras' => $this->camerasIp($ip, $sessao),
                'poe' => $this->portasPoe($ip, $sessao),
                'verificacao' => $this->verificacaoSeguranca($ip, $sessao),
            ];
        } finally {
            $this->rpc($ip, $sessao, 'global.logout');
        }
    }

    /** @return array{status: string, horas_ligado: ?int, alertas: string[]}|null */
    private function smartDoDisco(string $ip, string $sessao, string $nome): ?array
    {
        if ($nome === '') {
            return null;
        }

        $instancia = $this->rpc($ip, $sessao, 'devStorage.factory.instance', ['name' => $nome]);
        $objeto = is_int($instancia['result'] ?? null) ? $instancia['result'] : null;
        if ($objeto === null) {
            return null;
        }

        try {
            $valores = $this->rpc($ip, $sessao, 'devStorage.getSmartValue', null, $objeto)['params']['values'] ?? null;
        } finally {
            $this->rpc($ip, $sessao, 'devStorage.destroy', null, $objeto);
        }

        if (!is_array($valores) || !$valores) {
            return null;
        }

        $status = 'ok';
        $alertas = [];
        $horas = null;
        foreach ($valores as $v) {
            $idAtributo = (int)($v['ID'] ?? 0);
            $bruto = is_numeric($v['Raw'] ?? null) ? (int)$v['Raw'] : 0;
            $limite = (int)($v['Threshold'] ?? 0);
            $atual = (int)($v['Current'] ?? 0);

            if ($idAtributo === 9) {
                $horas = $bruto;
            }
            // Abaixo do limite do fabricante = o próprio disco se declara falhando.
            if ($limite > 0 && $atual > 0 && $atual <= $limite) {
                $status = 'falhando';
                $alertas[] = ($v['Name'] ?? "Atributo {$idAtributo}") . " abaixo do limite do fabricante ({$atual} ≤ {$limite})";
            } elseif (isset(self::SMART_ATENCAO[$idAtributo]) && $bruto > 0) {
                if ($status === 'ok') {
                    $status = 'atencao';
                }
                $alertas[] = "{$bruto} " . self::SMART_ATENCAO[$idAtributo];
            }
        }

        return ['status' => $status, 'horas_ligado' => $horas, 'alertas' => $alertas];
    }

    /** Câmeras IP ligadas ao NVR, com o estado de conexão de cada uma. DVR analógico devolve lista vazia. */
    private function camerasIp(string $ip, string $sessao): array
    {
        $todas = $this->rpc($ip, $sessao, 'LogicDeviceManager.getCameraAll')['params']['camera'] ?? [];
        $estados = [];
        foreach ($this->rpc($ip, $sessao, 'LogicDeviceManager.getCameraState', ['uniqueChannels' => [-1]])['params']['states'] ?? [] as $e) {
            if (isset($e['channel'])) {
                $estados[(int)$e['channel']] = $e['connectionState'] ?? null;
            }
        }

        // getCameraAll não traz o firmware da câmera; a config clássica RemoteDevice traz (chave = MAC).
        $firmwarePorMac = [];
        if ($todas) {
            $remotos = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=RemoteDevice');
            $macPorIndice = [];
            foreach ($remotos['dados'] as $chave => $valor) {
                if (preg_match('/INFO_(\d+)\.(Mac|Version)$/', $chave, $m)) {
                    $macPorIndice[$m[1]][$m[2]] = $valor;
                }
            }
            foreach ($macPorIndice as $par) {
                if (!empty($par['Mac'])) {
                    $firmwarePorMac[strtolower($par['Mac'])] = explode(',', (string)($par['Version'] ?? ''))[0];
                }
            }
        }

        $cameras = [];
        foreach (is_array($todas) ? $todas : [] as $c) {
            $info = $c['DeviceInfo'] ?? [];
            if (($c['Type'] ?? '') !== 'Remote' || empty($info['Enable']) || empty($info['Address']) || ($info['Mac'] ?? '') === 'ff:ff:ff:ff:ff:ff') {
                continue;
            }
            $canal = (int)($c['UniqueChannel'] ?? $c['Channel'] ?? 0);
            $cameras[] = [
                'canal' => $canal + 1,
                'ip' => $info['Address'],
                'modelo' => $info['DeviceType'] ?? '',
                'serial' => $info['SerialNo'] ?? '',
                'firmware' => $firmwarePorMac[strtolower((string)($info['Mac'] ?? ''))] ?? '',
                'mac' => $info['Mac'] ?? '',
                'porta_poe' => !empty($info['PoE']) ? (int)($info['PoEPort'] ?? 0) : null,
                'conectada' => isset($estados[$canal]) ? $estados[$canal] === 'Connected' : null,
            ];
        }

        return $cameras;
    }

    /**
     * Switch PoE embutido (modelos "P"). Parâmetro tirado do JS da própria
     * interface web do NVR (jsCore.RPC): getPortStatus/getPortPower recebem
     * {name: <interface>}, e o nome vem de getInfo ("eth1"). Consumo em watts
     * só quando getSwitchCaps diz PowerSupport = 1 -- a própria interface
     * esconde a coluna sem isso (NVD 1408 P: 0, getPowerInfo vem zerado).
     *
     * @return array{total_portas: int, consumo_suportado: bool, consumo_total_w: ?float, consumo_disponivel_w: ?float, portas: array}|null
     */
    private function portasPoe(string $ip, string $sessao): ?array
    {
        $interface = $this->rpc($ip, $sessao, 'SwitchPoE.getInfo')['params']['list'][0] ?? null;
        if (empty($interface['Name'])) {
            return null; // sem switch PoE (DVR, NVR sem "P")
        }

        $status = $this->rpc($ip, $sessao, 'SwitchPoE.getPortStatus', ['name' => $interface['Name']])['params']['list'] ?? [];
        $caps = $this->rpc($ip, $sessao, 'SwitchPoE.getSwitchCaps')['params']['Caps'] ?? [];
        $comConsumo = !empty($caps['PowerSupport']);
        $energia = $comConsumo ? ($this->rpc($ip, $sessao, 'SwitchPoE.getPowerInfo')['params']['Info'] ?? null) : null;

        // A lista não diz o número da porta; a RemoteDevice sabe em qual porta
        // PoE está cada câmera (PoEPort), casando pelo MAC.
        $portaPorMac = [];
        $remotos = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=RemoteDevice');
        $porIndice = [];
        foreach ($remotos['dados'] as $chave => $valor) {
            if (preg_match('/INFO_(\d+)\.(Mac|PoEPort)$/', $chave, $m)) {
                $porIndice[$m[1]][$m[2]] = $valor;
            }
        }
        foreach ($porIndice as $par) {
            if (!empty($par['Mac']) && (int)($par['PoEPort'] ?? 0) > 0) {
                $portaPorMac[strtolower($par['Mac'])] = (int)$par['PoEPort'];
            }
        }

        $portas = [];
        foreach (is_array($status) ? $status : [] as $i => $p) {
            $numero = $portaPorMac[strtolower((string)($p['PhysicalAddress'] ?? ''))] ?? $i + 1;
            $consumo = $energia['Power'][$numero - 1] ?? null;
            $portas[] = [
                'porta' => $numero,
                'link' => !empty($p['Link']),
                'habilitada' => !empty($p['PortEnable']),
                'ip' => $p['IPAddress'] ?? '',
                'mac' => $p['PhysicalAddress'] ?? '',
                'consumo_w' => $comConsumo && $consumo !== null ? round($consumo / 1000, 1) : null,
            ];
        }
        usort($portas, fn ($a, $b) => $a['porta'] <=> $b['porta']);

        return [
            'total_portas' => (int)($interface['PortNum'] ?? count($portas)),
            'consumo_suportado' => $comConsumo,
            'consumo_total_w' => $comConsumo && isset($energia['TotalPower']) ? round($energia['TotalPower'] / 1000, 1) : null,
            'consumo_disponivel_w' => $comConsumo && isset($energia['AvailablePower']) ? round($energia['AvailablePower'] / 1000, 1) : null,
            'portas' => $portas,
        ];
    }

    /** Último relatório do "Verificar segurança" do próprio equipamento (não dispara uma verificação nova). */
    private function verificacaoSeguranca(string $ip, string $sessao): ?array
    {
        $relatorio = $this->rpc($ip, $sessao, 'SecurityScan.getReport')['params'] ?? null;
        if (!is_array($relatorio) || empty($relatorio['SecItemState'])) {
            return null;
        }

        $itens = [];
        foreach ($relatorio['SecItemState'] as $item) {
            $nome = (string)($item['Name'] ?? '');
            $itens[] = [
                'nome' => self::ITENS_VERIFICACAO[$nome] ?? $nome,
                'atencao' => !empty($item['ErrCode']),
            ];
        }

        return ['gerado_em' => $relatorio['ModifyTime'] ?? $relatorio['CreateTime'] ?? null, 'itens' => $itens];
    }

    /**
     * Exposição do equipamento, pela CGI clássica (vale também pra quem não
     * tem RPC2): acesso pela nuvem Intelbras (P2P), UPnP abrindo porta no
     * roteador, Telnet e HTTPS.
     *
     * @return array<string, ?bool> null = o equipamento não informou
     */
    public function configuracaoExposicao(string $ip): array
    {
        $valor = function (string $nome, string $chave) use ($ip): ?bool {
            $r = $this->chamarApi($ip, "/cgi-bin/configManager.cgi?action=getConfig&name={$nome}");
            return $r['sucesso'] && isset($r['dados'][$chave]) ? $r['dados'][$chave] === 'true' : null;
        };

        return [
            'p2p' => $valor('T2UServer', 'table.T2UServer[0].Enable'),
            'upnp' => $valor('UPnP', 'table.UPnP.Enable'),
            'telnet' => $valor('Telnet', 'table.Telnet.Enable'),
            'https' => $valor('Https', 'table.Https.Enable'),
        ];
    }

    /**
     * Imagem em tempo real, sempre como MJPEG (multipart/x-mixed-replace) --
     * o navegador mostra num <img> sem plugin. Três caminhos, nessa ordem:
     *
     * 1. MJPEG do próprio equipamento (cgi-bin/mjpg/video.cgi): só serve
     *    quando o stream está codificado em MJPEG. NVD 1408 P: sub-stream
     *    10 q/s 704x480 ~200 KB/s, principal ~500 KB/s. Os DVRs da
     *    Patrimonial (XVR/MHDX) respondem 200 "image/jpeg", mas cada parte é
     *    H.264 (começa com 00 00 FF FE) -- o navegador não mostra nada. Por
     *    isso confere o 1º quadro antes de mandar qualquer coisa pro navegador.
     * 2. ffmpeg instalado no servidor: lê o RTSP e converte pra MJPEG (vídeo
     *    de verdade em qualquer modelo).
     * 3. Sem ffmpeg: sequência de snapshots no mesmo formato (~1-3 q/s
     *    medidos nos MHDX) -- não é fluido, mas é ao vivo.
     *
     * Para quando o navegador fecha, ou em $maxSegundos (não prende
     * processo do Apache pra sempre).
     *
     * @return string|null mensagem de erro se não conseguiu começar; null = transmitiu
     */
    public function transmitirAoVivo(string $ip, int $canal, string $qualidade = 'normal', int $maxSegundos = 180): ?string
    {
        $credencial = $this->credencialParaIp($ip);
        if ($credencial === null) {
            return "Nenhuma credencial cadastrada para {$ip}.";
        }

        $inicio = microtime(true);
        $nativo = $this->transmitirMjpegNativo($ip, $canal, $qualidade, $maxSegundos, $credencial);
        if ($nativo === 'transmitiu') {
            return null;
        }
        if ($nativo === 'senha') {
            return 'Usuário/senha recusados pelo DVR/NVR.';
        }

        $restante = max(10, $maxSegundos - (int)(microtime(true) - $inicio));
        if ($this->ffmpegDisponivel()) {
            return $this->transmitirViaFfmpeg($ip, $canal, $qualidade, $restante, $credencial);
        }

        return $this->transmitirViaSnapshots($ip, $canal, $restante);
    }

    /** @return string 'transmitiu' | 'senha' | 'indisponivel' (stream não existe ou não é JPEG) */
    private function transmitirMjpegNativo(string $ip, int $canal, string $qualidade, int $maxSegundos, array $credencial): string
    {
        $tipoConteudo = '';
        $codigo = 0;
        $comecou = false;
        $naoEhJpeg = false;
        $buffer = '';
        $ch = curl_init("http://{$ip}/cgi-bin/mjpg/video.cgi?channel={$canal}&subtype=" . ($qualidade === 'alta' ? 0 : 1));
        curl_setopt_array($ch, [
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $credencial['usuario'] . ':' . $credencial['senha'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $maxSegundos,
            CURLOPT_HEADERFUNCTION => function ($ch, $linha) use (&$tipoConteudo, &$codigo) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $linha, $m)) {
                    $codigo = (int)$m[1];
                } elseif (stripos($linha, 'Content-Type:') === 0) {
                    $tipoConteudo = trim(substr($linha, 13));
                }
                return strlen($linha);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, $dados) use (&$tipoConteudo, &$codigo, &$comecou, &$naoEhJpeg, &$buffer) {
                if ($codigo !== 200) {
                    return strlen($dados); // corpo do 401 do 1º passo do Digest: descarta
                }
                if (!$comecou) {
                    // Segura até ver o começo do 1º quadro: JPEG começa com FF D8.
                    $buffer .= $dados;
                    $fimCabecalho = strpos($buffer, "\r\n\r\n");
                    if ($fimCabecalho === false || strlen($buffer) < $fimCabecalho + 6) {
                        return strlen($buffer) > 65536 ? 0 : strlen($dados);
                    }
                    if (substr($buffer, $fimCabecalho + 4, 2) !== "\xFF\xD8") {
                        $naoEhJpeg = true;
                        return 0; // H.264 embrulhado como "image/jpeg": desiste e cai no próximo caminho
                    }
                    $comecou = true;
                    header('Content-Type: ' . ($tipoConteudo ?: 'multipart/x-mixed-replace; boundary=myboundary'));
                    header('Cache-Control: no-store');
                    header('X-Accel-Buffering: no');
                    $dados = $buffer;
                    $buffer = '';
                }
                echo $dados;
                flush();

                return connection_aborted() ? 0 : strlen($dados); // 0 = fecharam a janela, encerra
            },
        ]);
        curl_exec($ch);
        curl_close($ch);

        if ($comecou) {
            return 'transmitiu';
        }

        return $codigo === 401 && $qualidade !== 'alta' && !$naoEhJpeg ? 'senha' : 'indisponivel';
    }

    private function ffmpegDisponivel(): bool
    {
        return is_executable('/usr/bin/ffmpeg');
    }

    /** RTSP do equipamento → MJPEG pelo ffmpeg. A senha vai na URL do RTSP (só visível pra quem já tem shell no servidor). */
    private function transmitirViaFfmpeg(string $ip, int $canal, string $qualidade, int $maxSegundos, array $credencial): ?string
    {
        $url = sprintf('rtsp://%s:%s@%s:554/cam/realmonitor?channel=%d&subtype=%d',
            rawurlencode($credencial['usuario']), rawurlencode($credencial['senha']), $ip, $canal, $qualidade === 'alta' ? 0 : 1);
        $comando = ['/usr/bin/ffmpeg', '-loglevel', 'error', '-rtsp_transport', 'tcp', '-i', $url, '-an',
            '-t', (string)$maxSegundos, '-r', '10', '-q:v', $qualidade === 'alta' ? '4' : '7', '-f', 'mpjpeg', 'pipe:1'];

        $processo = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
        if (!is_resource($processo)) {
            return $this->transmitirViaSnapshots($ip, $canal, $maxSegundos);
        }

        $comecou = false;
        while (!feof($tubos[1])) {
            $dados = fread($tubos[1], 65536);
            if ($dados === '' || $dados === false) {
                continue;
            }
            if (!$comecou) {
                $comecou = true;
                header('Content-Type: multipart/x-mixed-replace; boundary=ffmpeg');
                header('Cache-Control: no-store');
                header('X-Accel-Buffering: no');
            }
            echo $dados;
            flush();
            if (connection_aborted()) {
                break;
            }
        }
        proc_terminate($processo);
        fclose($tubos[1]);
        fclose($tubos[2]);
        proc_close($processo);

        return $comecou ? null : $this->transmitirViaSnapshots($ip, $canal, $maxSegundos);
    }

    /** Snapshots em sequência, no mesmo formato multipart -- funciona em qualquer modelo, no ritmo que o equipamento aguenta. */
    private function transmitirViaSnapshots(string $ip, int $canal, int $maxSegundos): ?string
    {
        $fim = microtime(true) + $maxSegundos;
        $comecou = false;
        while (microtime(true) < $fim) {
            $quadro = $this->snapshot($ip, $canal);
            if (!$quadro['success']) {
                if (!$comecou) {
                    return $quadro['message'];
                }
                usleep(500000);
                continue;
            }
            if (!$comecou) {
                $comecou = true;
                header('Content-Type: multipart/x-mixed-replace; boundary=rdquadro');
                header('Cache-Control: no-store');
                header('X-Accel-Buffering: no');
            }
            echo "--rdquadro\r\nContent-Type: image/jpeg\r\nContent-Length: " . strlen($quadro['imagem']) . "\r\n\r\n" . $quadro['imagem'] . "\r\n";
            flush();
            if (connection_aborted()) {
                break;
            }
            usleep(150000); // não martela o equipamento (~3 q/s no máximo)
        }

        return null;
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
     * Checagem avulsa e imediata de "tampada" pra um canal só -- não espera
     * a próxima coleta periódica (até 30 min), pra dar feedback na hora
     * depois de mudar a sensibilidade e querer saber se ainda dispara.
     *
     * @return array{success:bool, message?:string, tampada?:bool}
     */
    public function testarTampadaCanal(string $ip, int $canal): array
    {
        $videoBlind = $this->chamarApi($ip, '/cgi-bin/eventManager.cgi?action=getEventIndexes&code=VideoBlind');

        if (!$videoBlind['sucesso']) {
            return ['success' => false, 'message' => $videoBlind['mensagem']];
        }

        $canaisComBlind = $this->extrairCanaisDoEvento($videoBlind);

        return ['success' => true, 'tampada' => in_array($canal, $canaisComBlind, true)];
    }

    /**
     * O log devolve a data em "dd-mm-yyyy hh:mm:ss" (confirmado ao vivo),
     * não "yyyy-mm-dd hh:mm:ss" como no exemplo do manual oficial -- tenta
     * os dois formatos, na ordem confirmada primeiro. Sem isso, cai pro
     * timestamp atual (nunca fica pra trás pra sempre por causa de uma
     * linha que não bateu com nenhum formato, mas também não trava a
     * ordenação/dedup por completo).
     */
    private static function converterDataHoraLog(string $texto): int
    {
        $formato = \DateTime::createFromFormat('d-m-Y H:i:s', $texto) ?: \DateTime::createFromFormat('Y-m-d H:i:s', $texto);

        return $formato !== false ? $formato->getTimestamp() : time();
    }

    /**
     * Agrupa um bloco "chave=valor" tipo `prefixo[0].Campo=valor` numa lista
     * de arrays associativos [0 => ['Campo' => 'valor', ...], 1 => [...]] --
     * só pega campos ESCALARES de primeiro nível (ex: ignora de propósito
     * "users[0].AuthorityList[3]", que é um array dentro do índice, ou
     * "users[0].AccessSchedule[0][0]") -- exatamente o que sobra depois
     * disso (Name, Group, Memo, ClientAddress etc.) é o que interessa aqui.
     *
     * @return array<int, array<string,string>>
     */
    private function agruparPorIndice(array $dados, string $prefixo): array
    {
        $itens = [];
        $padrao = '/^' . preg_quote($prefixo, '/') . '\[(\d+)\]\.([A-Za-z0-9]+)$/';

        foreach ($dados as $chave => $valor) {
            if (!preg_match($padrao, $chave, $m)) {
                continue;
            }
            $itens[(int)$m[1]][$m[2]] = $valor;
        }

        ksort($itens);

        return array_values($itens);
    }

    /**
     * Impede excluir/trocar a senha do usuário que É a credencial cadastrada
     * pra esse IP -- fazer isso por aqui deixaria a própria integração sem
     * acesso ao DVR na próxima chamada (a senha nova nunca seria refletida
     * na credencial cifrada que a gente guarda, e excluir o usuário derruba
     * o login de vez).
     */
    private function usuarioEhCredencialAtual(string $ip, string $nome): bool
    {
        $credencial = $this->credencialParaIp($ip);

        return $credencial !== null && strcasecmp($credencial['usuario'], $nome) === 0;
    }

    /** @return array{success:bool, usuarios?:array, message?:string} */
    public function listarUsuarios(string $ip): array
    {
        $resultado = $this->chamarApi($ip, '/cgi-bin/userManager.cgi?action=getUserInfoAll');

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $usuarios = [];
        foreach ($this->agruparPorIndice($resultado['dados'], 'users') as $u) {
            if (empty($u['Name'])) {
                continue;
            }
            $usuarios[] = [
                'nome' => $u['Name'],
                'grupo' => $u['Group'] ?? '',
                'memo' => $u['Memo'] ?? '',
                'compartilhavel' => ($u['Sharable'] ?? 'false') === 'true',
                'reservado' => ($u['Reserved'] ?? 'false') === 'true',
            ];
        }

        return ['success' => true, 'usuarios' => $usuarios];
    }

    /** @return array{success:bool, grupos?:string[], message?:string} */
    public function listarGrupos(string $ip): array
    {
        $resultado = $this->chamarApi($ip, '/cgi-bin/userManager.cgi?action=getGroupInfoAll');

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $grupos = [];
        foreach ($this->agruparPorIndice($resultado['dados'], 'group') as $g) {
            if (!empty($g['Name'])) {
                $grupos[] = $g['Name'];
            }
        }

        return ['success' => true, 'grupos' => $grupos];
    }

    /**
     * Quem está logado no DVR agora -- inclui a própria sessão CGI usada
     * pela nossa integração (ClientType "CGI") e a sessão local do monitor
     * físico conectado nele (ClientAddress "Local"), confirmado ao vivo.
     *
     * @return array{success:bool, usuarios?:array, message?:string}
     */
    public function buscarUsuariosAtivos(string $ip): array
    {
        $resultado = $this->chamarApi($ip, '/cgi-bin/userManager.cgi?action=getActiveUserInfoAll');

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        $usuarios = [];
        foreach ($this->agruparPorIndice($resultado['dados'], 'users') as $u) {
            if (empty($u['Name'])) {
                continue;
            }
            $usuarios[] = [
                'nome' => $u['Name'],
                'ip' => $u['ClientAddress'] ?? '',
                'grupo' => $u['Group'] ?? '',
                'tipo_cliente' => $u['ClientType'] ?? '',
                'login_em' => $u['LoginTime'] ?? '',
            ];
        }

        return ['success' => true, 'usuarios' => $usuarios];
    }

    public function criarUsuario(string $ip, string $nome, string $senha, string $grupo, string $memo = ''): array
    {
        $nome = trim($nome);
        $senha = trim($senha);
        $grupo = trim($grupo) ?: 'user';

        if ($nome === '' || $senha === '') {
            return ['success' => false, 'message' => 'Informe usuário e senha.'];
        }

        $query = 'user.Name=' . rawurlencode($nome)
            . '&user.Password=' . rawurlencode($senha)
            . '&user.Group=' . rawurlencode($grupo)
            . '&user.Memo=' . rawurlencode($memo)
            . '&user.Sharable=true&user.Reserved=false';

        $resultado = $this->chamarApi($ip, '/cgi-bin/userManager.cgi?action=addUser&' . $query);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => "Usuário \"{$nome}\" criado."];
    }

    public function editarUsuario(string $ip, string $nome, string $grupo, string $memo, bool $compartilhavel): array
    {
        $grupo = trim($grupo) ?: 'user';

        $query = 'user.Group=' . rawurlencode($grupo)
            . '&user.Memo=' . rawurlencode($memo)
            . '&user.Sharable=' . ($compartilhavel ? 'true' : 'false')
            . '&user.Reserved=false';

        $resultado = $this->chamarApi($ip, '/cgi-bin/userManager.cgi?action=modifyUser&name=' . rawurlencode($nome) . '&' . $query);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => "Usuário \"{$nome}\" atualizado."];
    }

    public function excluirUsuario(string $ip, string $nome): array
    {
        if ($this->usuarioEhCredencialAtual($ip, $nome)) {
            return ['success' => false, 'message' => "Não é possível excluir \"{$nome}\" por aqui -- é o usuário usado pela integração com este DVR/NVR (veja Integrações antes de removê-lo)."];
        }

        $resultado = $this->chamarApi($ip, '/cgi-bin/userManager.cgi?action=deleteUser&name=' . rawurlencode($nome));

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => "Usuário \"{$nome}\" excluído."];
    }

    /**
     * Troca a senha de OUTRO usuário usando a credencial admin já cadastrada
     * pra esse IP (modifyPasswordByManager -- não precisa da senha antiga do
     * usuário alvo). Recusa trocar a senha do próprio usuário-credencial: a
     * senha nova nunca seria refletida no valor cifrado que guardamos,
     * quebrando a integração na próxima chamada.
     */
    public function trocarSenhaUsuario(string $ip, string $nomeAlvo, string $novaSenha): array
    {
        if ($this->usuarioEhCredencialAtual($ip, $nomeAlvo)) {
            return ['success' => false, 'message' => "Não é possível trocar a senha de \"{$nomeAlvo}\" por aqui -- é o usuário usado pela integração com este DVR/NVR (troque em Integrações, que atualiza os dois lados)."];
        }

        $novaSenha = trim($novaSenha);

        if ($novaSenha === '') {
            return ['success' => false, 'message' => 'Informe a nova senha.'];
        }

        $credencial = $this->credencialParaIp($ip);

        if ($credencial === null) {
            return ['success' => false, 'message' => "Nenhuma credencial cadastrada para {$ip} -- veja Integrações."];
        }

        $query = 'userName=' . rawurlencode($nomeAlvo)
            . '&pwd=' . rawurlencode($novaSenha)
            . '&managerName=' . rawurlencode($credencial['usuario'])
            . '&managerPwd=' . rawurlencode($credencial['senha'])
            . '&accountType=0';

        $resultado = $this->chamarApi($ip, '/cgi-bin/userManager.cgi?action=modifyPasswordByManager&' . $query);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => "Senha de \"{$nomeAlvo}\" alterada."];
    }

    /**
     * TCP/IP do DVR -- confirmado ao vivo contra os 3 equipamentos reais.
     * Junta Network (IP/máscara/gateway/DNS/hostname) com netApp (status do
     * link/velocidade), que vêm de dois comandos clássicos diferentes.
     *
     * @return array{success:bool, message?:string, hostname?:string, dominio?:string, dhcp?:bool, ip?:string,
     *   mascara?:string, gateway?:string, dns?:string[], mac?:string, mtu?:?int, status_link?:?string,
     *   velocidade_mbps?:?int, tipo_interface?:?string}
     */
    public function buscarRede(string $ip): array
    {
        $rede = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=Network');

        if (!$rede['sucesso']) {
            return ['success' => false, 'message' => $rede['mensagem']];
        }

        $interfaces = $this->chamarApi($ip, '/cgi-bin/netApp.cgi?action=getInterfaces');
        $iface = $interfaces['sucesso'] ? ($this->agruparPorIndice($interfaces['dados'], 'netInterface')[0] ?? []) : [];

        $d = $rede['dados'];
        $dns = [];
        foreach ([0, 1] as $i) {
            if (!empty($d["table.Network.eth0.DnsServers[{$i}]"])) {
                $dns[] = $d["table.Network.eth0.DnsServers[{$i}]"];
            }
        }

        return [
            'success' => true,
            'hostname' => $d['table.Network.Hostname'] ?? '',
            'dominio' => $d['table.Network.Domain'] ?? '',
            'dhcp' => ($d['table.Network.eth0.DhcpEnable'] ?? 'false') === 'true',
            'ip' => $d['table.Network.eth0.IPAddress'] ?? '',
            'mascara' => $d['table.Network.eth0.SubnetMask'] ?? '',
            'gateway' => $d['table.Network.eth0.DefaultGateway'] ?? '',
            'dns' => $dns,
            'mac' => $d['table.Network.eth0.PhysicalAddress'] ?? '',
            'mtu' => isset($d['table.Network.eth0.MTU']) ? (int)$d['table.Network.eth0.MTU'] : null,
            'status_link' => $iface['ConnStatus'] ?? null,
            'velocidade_mbps' => isset($iface['Speed']) ? (int)$iface['Speed'] : null,
            'tipo_interface' => $iface['Type'] ?? null,
        ];
    }

    /**
     * Grava hostname/DNS sempre; IP/máscara/gateway só quando $ipEstatico
     * vier preenchido (nesse caso força DhcpEnable=false, porque não faz
     * sentido informar IP manual com DHCP ligado -- o DVR ignoraria).
     * Deixar $ipEstatico null preserva IP/DHCP como estão, do jeito que era
     * antes de existir edição de IP por aqui.
     *
     * AVISO (já comunicado na tela, repetido aqui pra quem mexer no código
     * depois): trocar IP/máscara/gateway remotamente pode deixar o DVR
     * inacessível pela rede se o valor novo estiver errado ou não bater com
     * a rede de verdade -- exigiria alguém ir até o equipamento fisicamente
     * pra corrigir. Habilitar DHCP por aqui NÃO é suportado de propósito: o
     * IP resultante fica desconhecido pro nosso sistema, que precisa de um
     * IP fixo pra continuar enxergando o equipamento.
     *
     * @param ?array{ip:string, mascara:string, gateway:string} $ipEstatico
     * @return array{success:bool, message?:string, ip_mudou_para?:?string}
     */
    public function definirRede(string $ip, string $hostname, array $dnsServers, ?array $ipEstatico): array
    {
        $hostname = trim($hostname);

        if ($hostname === '') {
            return ['success' => false, 'message' => 'Informe um nome de host.'];
        }

        $dnsServers = array_values(array_filter(array_map('trim', $dnsServers)));
        foreach ($dnsServers as $dns) {
            if (filter_var($dns, FILTER_VALIDATE_IP) === false) {
                return ['success' => false, 'message' => "\"{$dns}\" não é um IP válido pra servidor DNS."];
            }
        }

        $novoIp = null;
        if ($ipEstatico !== null) {
            $novoIp = trim($ipEstatico['ip'] ?? '');
            $mascara = trim($ipEstatico['mascara'] ?? '');
            $gateway = trim($ipEstatico['gateway'] ?? '');

            foreach (['Endereço IP' => $novoIp, 'Máscara de sub-rede' => $mascara, 'Gateway padrão' => $gateway] as $rotulo => $valor) {
                if (filter_var($valor, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    return ['success' => false, 'message' => "\"{$rotulo}\" precisa ser um endereço IPv4 válido."];
                }
            }
        }

        // Índice do array vai como %5B/%5D (bracket percent-encoded) -- mesmo
        // padrão já usado (e confirmado ao vivo) em renomearCanal()/
        // definirSensibilidadeTampada(), evita depender de como cada
        // implementação de CGI tolera "[" "]" crus na query string.
        $query = 'Network.Hostname=' . rawurlencode($hostname);
        foreach ($dnsServers as $i => $dns) {
            $query .= "&Network.eth0.DnsServers%5B{$i}%5D=" . rawurlencode($dns);
        }

        if ($ipEstatico !== null) {
            $query .= '&Network.eth0.DhcpEnable=false'
                . '&Network.eth0.IPAddress=' . rawurlencode($novoIp)
                . '&Network.eth0.SubnetMask=' . rawurlencode($mascara)
                . '&Network.eth0.DefaultGateway=' . rawurlencode($gateway);
        }

        $resultado = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=setConfig&' . $query);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        if ($novoIp !== null && $novoIp !== $ip) {
            return ['success' => true, 'message' => 'Configuração de rede salva.', 'ip_mudou_para' => $novoIp];
        }

        return ['success' => true, 'message' => 'Configuração de rede salva.'];
    }

    /**
     * Política de acesso do DVR -- LoginFailureAlarm já vem ligada de
     * fábrica/instalação nos 3 equipamentos testados ao vivo, e
     * LockLoginEnable/Times/Time (bloqueio temporário após N tentativas)
     * mora em General, não junto -- confirmado ao vivo.
     *
     * @return array{success:bool, message?:string, alerta_login_falho_ativo?:bool, bloqueio_ativo?:bool,
     *   bloqueio_tentativas?:?int, bloqueio_duracao_segundos?:?int}
     */
    public function buscarSegurancaAcesso(string $ip): array
    {
        $loginFailure = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=LoginFailureAlarm');

        if (!$loginFailure['sucesso']) {
            return ['success' => false, 'message' => $loginFailure['mensagem']];
        }

        $geral = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=getConfig&name=General');

        return [
            'success' => true,
            'alerta_login_falho_ativo' => ($loginFailure['dados']['table.LoginFailureAlarm.Enable'] ?? 'false') === 'true',
            'bloqueio_ativo' => $geral['sucesso'] ? (($geral['dados']['table.General.LockLoginEnable'] ?? 'false') === 'true') : null,
            'bloqueio_tentativas' => $geral['sucesso'] && isset($geral['dados']['table.General.LockLoginTimes']) ? (int)$geral['dados']['table.General.LockLoginTimes'] : null,
            'bloqueio_duracao_segundos' => $geral['sucesso'] && isset($geral['dados']['table.General.LoginFailLockTime']) ? (int)$geral['dados']['table.General.LoginFailLockTime'] : null,
        ];
    }

    public function definirAlertaLoginFalho(string $ip, bool $ativo): array
    {
        $resultado = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=setConfig&LoginFailureAlarm.Enable=' . ($ativo ? 'true' : 'false'));

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => $ativo ? 'Alerta de login falho ativado no DVR/NVR.' : 'Alerta de login falho desativado no DVR/NVR.'];
    }

    /**
     * Log de conta (login/logoff/troca de senha etc.) dos últimos N dias --
     * protocolo clássico de 3 passos (startFind/doFind/stopFind), confirmado
     * ao vivo contra os 3 DVR/NVR reais (retorna "Usuário Logado"/"Fazer
     * logoff" já traduzido pelo firmware). Cada item vem marcado com
     * 'suspeito' via PALAVRAS_EVENTO_CONTA_SUSPEITO -- ver o comentário da
     * constante pra entender por que é por palavra-chave e não string exata.
     *
     * @return array{success:bool, message?:string, eventos?:array}
     */
    public function buscarEventosConta(string $ip, int $dias = 7): array
    {
        $inicio = date('Y-m-d H:i:s', strtotime("-{$dias} days"));
        $fim = date('Y-m-d H:i:s');

        $inicioParam = rawurlencode($inicio);
        $fimParam = rawurlencode($fim);
        $start = $this->chamarApi($ip, "/cgi-bin/log.cgi?action=startFind&condition.Type=Account&condition.StartTime={$inicioParam}&condition.EndTime={$fimParam}");

        if (!$start['sucesso'] || !isset($start['dados']['token'])) {
            return ['success' => false, 'message' => $start['sucesso'] ? 'Não foi possível iniciar a consulta de log.' : $start['mensagem']];
        }

        $token = $start['dados']['token'];
        $doFind = $this->chamarApi($ip, "/cgi-bin/log.cgi?action=doFind&token={$token}&count=30");
        $this->chamarApi($ip, "/cgi-bin/log.cgi?action=stopFind&token={$token}");

        if (!$doFind['sucesso']) {
            return ['success' => false, 'message' => $doFind['mensagem']];
        }

        $eventos = [];
        foreach ($this->agruparPorIndice($doFind['dados'], 'items') as $item) {
            $tipo = $item['Type'] ?? '';
            $tipoBusca = strtolower($tipo);

            $suspeito = false;
            foreach (self::PALAVRAS_EVENTO_CONTA_SUSPEITO as $palavra) {
                if (str_contains($tipoBusca, $palavra)) {
                    $suspeito = true;
                    break;
                }
            }

            $tempo = $item['Time'] ?? '';

            $eventos[] = [
                // O manual oficial documenta um campo "RecNo" (log number)
                // nos itens de log -- confirmado ao vivo contra os 3
                // DVR/NVR reais que ele NÃO existe nessa versão de firmware
                // (só vêm Time/Type/User/Detail). Por isso o "identificador"
                // de dedup usado aqui é o timestamp do próprio evento
                // (dd-mm-yyyy, formato confirmado ao vivo -- diferente do
                // yyyy-mm-dd do manual), não um RecNo que nunca chega.
                'ts' => self::converterDataHoraLog($tempo),
                'data' => $tempo,
                'usuario' => $item['User'] ?? '',
                'tipo' => $tipo,
                'suspeito' => $suspeito,
            ];
        }

        usort($eventos, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return ['success' => true, 'eventos' => $eventos];
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
            // O corpo do erro costuma trazer o motivo de verdade (ex:
            // "Password Too Weak!", ou "Error" seguido de "Bad Request!") --
            // confirmado ao vivo com o erro real de "Password Too Weak!" ao
            // tentar criar um usuário, que antes desse fix virava só um
            // genérico "Erro HTTP 400" sem pista nenhuma do motivo. Filtra a
            // linha "Error" solta (só um cabeçalho, não é a mensagem) e usa a
            // primeira linha que sobrar.
            $linhas = array_values(array_filter(
                array_map('trim', explode("\n", $resposta)),
                fn ($linha) => $linha !== '' && $linha !== 'Error'
            ));
            $motivo = $linhas[0] ?? null;

            return [
                'sucesso' => false,
                'dados' => [],
                'mensagem' => $motivo !== null
                    ? "O DVR/NVR recusou o comando: {$motivo}"
                    : "Erro HTTP {$codigo} ao falar com o DVR/NVR.",
            ];
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
