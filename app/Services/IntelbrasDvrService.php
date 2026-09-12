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
     * Só troca hostname e DNS -- IP/máscara/gateway/DHCP ficam de fora DE
     * PROPÓSITO (decisão explícita, dado o risco: um valor errado nesses
     * campos pode deixar o DVR inacessível pela rede, exigindo alguém ir
     * até o equipamento fisicamente pra corrigir).
     */
    public function definirRedeSegura(string $ip, string $hostname, array $dnsServers): array
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

        // Índice do array vai como %5B/%5D (bracket percent-encoded) -- mesmo
        // padrão já usado (e confirmado ao vivo) em renomearCanal()/
        // definirSensibilidadeTampada(), evita depender de como cada
        // implementação de CGI tolera "[" "]" crus na query string.
        $query = 'Network.Hostname=' . rawurlencode($hostname);
        foreach ($dnsServers as $i => $dns) {
            $query .= "&Network.eth0.DnsServers%5B{$i}%5D=" . rawurlencode($dns);
        }

        $resultado = $this->chamarApi($ip, '/cgi-bin/configManager.cgi?action=setConfig&' . $query);

        if (!$resultado['sucesso']) {
            return ['success' => false, 'message' => $resultado['mensagem']];
        }

        return ['success' => true, 'message' => 'Configuração de rede (hostname/DNS) salva.'];
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
