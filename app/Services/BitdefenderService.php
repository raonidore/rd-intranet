<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Integração com o Bitdefender GravityZone (API pública JSON-RPC, chave de
 * API do console em Basic auth). Configurada em Administração > Integrações;
 * a tela fica em Segurança > Bitdefender. O cron "rd bitdefender:sincronizar"
 * (15 min) copia máquinas e quarentena para o banco e transforma itens novos
 * de quarentena em alerta da Central de Segurança.
 *
 * Os horários da API vêm no fuso de Bucareste (sede da Bitdefender) --
 * conferido ao vivo no Enzilab NTO: quarentena às 01:19 da API = 19:19 em
 * Recife, minuto em que o agente RD parou.
 */
class BitdefenderService
{
    public const URL_PADRAO = 'https://cloud.gravityzone.bitdefender.com/api';
    private const FUSO_API = 'Europe/Bucharest';

    private const CHAVE_URL = 'bitdefender_url';
    private const CHAVE_API_KEY_CIFRADA = 'bitdefender_api_key_cifrada';
    private const CHAVE_ULTIMA_SINCRONIZACAO = 'bitdefender_ultima_sincronizacao';
    private const CHAVE_ULTIMO_ERRO = 'bitdefender_ultimo_erro';

    /** Arquivos/pastas do próprio RD.Intranet: quarentena deles quebra o agente. */
    private const CAMINHOS_RD = '#\\\\(RDIntranetAgent|RDTecnologia|RDIntranetAgenteExec)(\\\\|$)#i';

    public const TIPOS_VERIFICACAO = [1 => 'Rápida', 2 => 'Completa', 3 => 'Memória'];

    // ------------------------------------------------------------ configuração

    public function configurado(): bool
    {
        return $this->urlAtual() !== '' && (ConfigService::get(self::CHAVE_API_KEY_CIFRADA, '') ?: '') !== '';
    }

    public function urlAtual(): string
    {
        return rtrim(trim((string)(ConfigService::get(self::CHAVE_URL, '') ?: '')), '/');
    }

    public function salvarConfiguracao(string $url, string $apiKey): bool
    {
        $url = rtrim(trim($url), '/') ?: self::URL_PADRAO;
        $url = preg_replace('#/v1\.0/jsonrpc.*$#', '', $url);
        $apiKey = trim($apiKey);

        if (!preg_match('#^https://#i', $url)) {
            NotificationService::error('O endereço da API precisa começar com https://');
            return false;
        }
        if ($apiKey === '' && !$this->configurado()) {
            NotificationService::error('Informe a chave de API gerada no console do GravityZone.');
            return false;
        }

        ConfigService::set(self::CHAVE_URL, $url);
        if ($apiKey !== '') {
            ConfigService::set(self::CHAVE_API_KEY_CIFRADA, CryptoService::encriptar($apiKey));
        }
        (new AtualizacaoService())->garantirCronBitdefender();

        AuditService::registrar('Bitdefender GravityZone', 'Configuração', "Integração configurada ({$url}).");
        NotificationService::success('Configuração salva. Use "Testar conexão"; a sincronização roda sozinha a cada 15 minutos.');

        return true;
    }

    public function removerConfiguracao(): void
    {
        foreach ([self::CHAVE_URL, self::CHAVE_API_KEY_CIFRADA, self::CHAVE_ULTIMA_SINCRONIZACAO, self::CHAVE_ULTIMO_ERRO] as $chave) {
            ConfigService::set($chave, '');
        }
        AuditService::registrar('Bitdefender GravityZone', 'Configuração', 'Integração removida.');
        NotificationService::success('Integração removida. Os dados já copiados continuam até a próxima limpeza.');
    }

    /** @return array{success: bool, message: string} */
    public function testarConexao(): array
    {
        try {
            $endpoints = $this->rpc('network', 'getEndpointsList', ['perPage' => 1]);
            $licenca = $this->rpc('licensing', 'getLicenseInfo');
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => sprintf(
            'Conectado: %d máquina(s) no console, licença com %d de %d vagas em uso%s.',
            (int)($endpoints['total'] ?? 0),
            (int)($licenca['usedSlots'] ?? 0),
            (int)($licenca['totalSlots'] ?? 0),
            !empty($licenca['expiryDate']) ? ', válida até ' . date('d/m/Y', strtotime($licenca['expiryDate'])) : ''
        )];
    }

    public function ultimaSincronizacao(): ?string
    {
        return ConfigService::get(self::CHAVE_ULTIMA_SINCRONIZACAO, '') ?: null;
    }

    public function ultimoErro(): ?string
    {
        return ConfigService::get(self::CHAVE_ULTIMO_ERRO, '') ?: null;
    }

    // ------------------------------------------------------------ sincronização

    /** @return array{endpoints: int, quarentena: int, alertas: int} */
    public function sincronizar(): array
    {
        if (!$this->configurado()) {
            return ['endpoints' => 0, 'quarentena' => 0, 'alertas' => 0];
        }

        try {
            $resultado = $this->sincronizarAgora();
            ConfigService::set(self::CHAVE_ULTIMA_SINCRONIZACAO, date('Y-m-d H:i:s'));
            ConfigService::set(self::CHAVE_ULTIMO_ERRO, '');
            return $resultado;
        } catch (\RuntimeException $e) {
            ConfigService::set(self::CHAVE_ULTIMO_ERRO, date('d/m H:i') . ' -- ' . mb_substr($e->getMessage(), 0, 300));
            throw $e;
        }
    }

    private function sincronizarAgora(): array
    {
        $pdo = Database::connection();
        $ativosPorMac = $this->ativosPorMac();
        $ativosPorNome = $this->ativosPorNome();

        $vistos = [];
        $pagina = 1;
        do {
            $lista = $this->rpc('network', 'getEndpointsList', ['perPage' => 100, 'page' => $pagina]);
            foreach ($lista['items'] ?? [] as $e) {
                $detalhes = $this->rpc('network', 'getManagedEndpointDetails', ['endpointId' => $e['id']]);
                $ativoId = null;
                foreach ($e['macs'] ?? [] as $mac) {
                    $ativoId ??= $ativosPorMac[strtolower(preg_replace('/[^0-9a-f]/i', '', $mac))] ?? null;
                }
                $ativoId ??= $ativosPorNome[mb_strtolower((string)$e['name'])] ?? $ativosPorNome[mb_strtolower((string)($e['fqdn'] ?? ''))] ?? null;

                $dados = $e + ['detalhes' => $detalhes];
                $pdo->prepare(
                    'INSERT INTO bitdefender_endpoints (endpoint_id, ativo_id, nome, ip, macs, dados) VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE ativo_id = VALUES(ativo_id), nome = VALUES(nome), ip = VALUES(ip), macs = VALUES(macs), dados = VALUES(dados), visto_em = NOW()'
                )->execute([$e['id'], $ativoId, mb_substr((string)$e['name'], 0, 150), $e['ip'] ?? null, implode(',', $e['macs'] ?? []), json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                $vistos[] = $e['id'];

                $this->alertarInfeccao($ativoId, $e, $detalhes);
            }
            $pagina++;
        } while ($pagina <= (int)($lista['pagesCount'] ?? 1));

        // Máquinas que saíram do console.
        if ($vistos) {
            $marcadores = implode(',', array_fill(0, count($vistos), '?'));
            $pdo->prepare("DELETE FROM bitdefender_endpoints WHERE endpoint_id NOT IN ({$marcadores})")->execute($vistos);
        }

        [$quarentena, $alertas] = $this->sincronizarQuarentena();

        return ['endpoints' => count($vistos), 'quarentena' => $quarentena, 'alertas' => $alertas];
    }

    /** @return array{0: int, 1: int} [itens, alertas novos] */
    private function sincronizarQuarentena(): array
    {
        $pdo = Database::connection();
        $porEndpoint = $pdo->query('SELECT endpoint_id, ativo_id FROM bitdefender_endpoints')->fetchAll(PDO::FETCH_KEY_PAIR);
        $existentes = array_flip($pdo->query('SELECT item_id FROM bitdefender_quarentena')->fetchAll(PDO::FETCH_COLUMN));

        $vistos = [];
        $alertas = 0;
        $pagina = 1;
        do {
            $lista = $this->rpc('quarantine/computers', 'getQuarantineItemsList', ['perPage' => 100, 'page' => $pagina]);
            foreach ($lista['items'] ?? [] as $q) {
                $vistos[] = $q['id'];
                $ativoId = isset($porEndpoint[$q['endpointId']]) ? (int)$porEndpoint[$q['endpointId']] ?: null : null;
                $arquivo = (string)($q['details']['filePath'] ?? '');
                $quando = $this->dataLocal($q['quarantinedOn'] ?? null);

                $eventoId = null;
                if (!isset($existentes[$q['id']]) && $ativoId !== null) {
                    $eventoId = $this->alertarQuarentena($ativoId, $q, $arquivo, $quando);
                    $alertas++;
                }

                $pdo->prepare(
                    'INSERT INTO bitdefender_quarentena (item_id, endpoint_id, ativo_id, endpoint_nome, ameaca, arquivo, quarentenado_em, pode_restaurar, pode_remover, evento_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE ativo_id = VALUES(ativo_id), pode_restaurar = VALUES(pode_restaurar), pode_remover = VALUES(pode_remover), visto_em = NOW(),
                       evento_id = COALESCE(evento_id, VALUES(evento_id))'
                )->execute([
                    $q['id'], $q['endpointId'], $ativoId, mb_substr((string)($q['endpointName'] ?? ''), 0, 150), mb_substr((string)($q['threatName'] ?? ''), 0, 200),
                    mb_substr($arquivo, 0, 1000), $quando, !empty($q['canBeRestored']) ? 1 : 0, !empty($q['canBeRemoved']) ? 1 : 0, $eventoId,
                ]);
            }
            $pagina++;
        } while ($pagina <= (int)($lista['pagesCount'] ?? 1));

        if ($vistos) {
            $marcadores = implode(',', array_fill(0, count($vistos), '?'));
            $pdo->prepare("DELETE FROM bitdefender_quarentena WHERE item_id NOT IN ({$marcadores})")->execute($vistos);
        } else {
            $pdo->exec('DELETE FROM bitdefender_quarentena');
        }

        return [count($vistos), $alertas];
    }

    private function alertarQuarentena(int $ativoId, array $q, string $arquivo, ?string $quando): int
    {
        $doRd = (bool)preg_match(self::CAMINHOS_RD, $arquivo);
        $ameaca = (string)($q['threatName'] ?? 'ameaça');

        return (new SegurancaEventoService())->registrarDoServidor(
            $ativoId,
            'BITDEFENDER_QUARENTENA',
            $doRd
                ? "Bitdefender colocou em quarentena arquivo do agente RD.Intranet ({$ameaca}) -- o agente pode ter parado"
                : "Bitdefender colocou em quarentena: " . ($arquivo !== '' ? basename(str_replace('\\', '/', $arquivo)) : $ameaca) . " ({$ameaca})",
            [
                'ameaca' => $ameaca,
                'caminho' => $arquivo,
                'quando' => $quando,
                'arquivo_do_rd' => $doRd,
                'decisao' => $doRd
                    ? 'o Bitdefender bloqueou o agente RD.Intranet: inclua as exclusões do RD na política do GravityZone e restaure os arquivos em Segurança > Bitdefender.'
                    : 'veja em Segurança > Bitdefender (restaurar se for falso positivo, ou remover de vez).',
            ],
            $doRd ? 'CRITICAL' : 'WARNING'
        );
    }

    /** Máquina que o console marca como infectada (detecção não resolvida): alerta uma vez por estado. */
    private function alertarInfeccao(?int $ativoId, array $e, array $detalhes): void
    {
        if ($ativoId === null) {
            return;
        }
        $infectada = !empty($detalhes['malwareStatus']['infected']);
        $chave = 'bitdefender_infectada_' . $e['id'];
        $antes = ConfigService::get($chave, '0') === '1';
        if ($infectada === $antes) {
            return;
        }
        ConfigService::set($chave, $infectada ? '1' : '0');
        if ($infectada) {
            (new SegurancaEventoService())->registrarDoServidor($ativoId, 'BITDEFENDER_INFECTADA',
                'Bitdefender: máquina com ameaça ativa não resolvida', ['endpoint' => $e['name'], 'decisao' => 'rode uma verificação completa pelo Segurança > Bitdefender e veja a quarentena.'], 'CRITICAL');
        }
    }

    // ------------------------------------------------------------ leitura (tela)

    /** @return list<array> máquinas do console já resumidas para a tela */
    public function endpoints(): array
    {
        try {
            $linhas = Database::connection()->query(
                'SELECT b.*, a.codigo_patrimonio, a.nome AS ativo_nome FROM bitdefender_endpoints b LEFT JOIN ativos a ON a.id = b.ativo_id ORDER BY b.nome'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return array_map(function (array $l) {
            $d = json_decode($l['dados'], true) ?: [];
            $det = $d['detalhes'] ?? [];
            $agente = $det['agent'] ?? [];
            $modulos = $det['modules'] ?? [];

            return [
                'endpoint_id' => $l['endpoint_id'],
                'nome' => $l['nome'],
                'ip' => $l['ip'],
                'ativo_id' => $l['ativo_id'] !== null ? (int)$l['ativo_id'] : null,
                'codigo' => $l['codigo_patrimonio'],
                'so' => $det['operatingSystem'] ?? $d['operatingSystemVersion'] ?? '',
                'versao' => $agente['productVersion'] ?? '',
                'assinatura_desatualizada' => !empty($agente['signatureOutdated']),
                'produto_desatualizado' => !empty($agente['productOutdated']),
                'atualizado_em' => $this->dataLocal($agente['lastUpdate'] ?? null),
                'visto_em' => $this->dataLocal($det['lastSeen'] ?? null),
                'online' => ($det['state'] ?? 0) === 1,
                'infectada' => !empty($det['malwareStatus']['infected']),
                'deteccao' => !empty($det['malwareStatus']['detection']),
                'isolada' => !empty($det['isIsolated'] ?? $d['isIsolated'] ?? false),
                'politica' => $det['policy']['name'] ?? ($d['policy']['name'] ?? ''),
                'politica_aplicada' => !empty($det['policy']['applied'] ?? $d['policy']['applied'] ?? false),
                'modulos_desligados' => array_keys(array_filter(
                    array_intersect_key($modulos, array_flip(['antimalware', 'firewall', 'advancedThreatControl', 'antiTampering', 'networkAttackDefense', 'antiphishing'])),
                    fn ($v) => !$v
                )),
                'licenciada' => (int)($agente['licensed'] ?? 0) === 1,
            ];
        }, $linhas);
    }

    /** @return list<array> */
    public function quarentena(): array
    {
        try {
            $linhas = Database::connection()->query(
                'SELECT q.*, a.codigo_patrimonio FROM bitdefender_quarentena q LEFT JOIN ativos a ON a.id = q.ativo_id ORDER BY q.quarentenado_em DESC'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return array_map(fn ($q) => $q + ['do_rd' => (bool)preg_match(self::CAMINHOS_RD, (string)$q['arquivo'])], $linhas);
    }

    /** Máquinas com agente RD que não aparecem no console (sem Bitdefender). @return list<array> */
    public function maquinasSemBitdefender(): array
    {
        try {
            return Database::connection()->query(
                "SELECT a.id, a.codigo_patrimonio, a.nome, a.ultimo_heartbeat FROM ativos a
                 WHERE a.origem = 'agente' AND a.status NOT IN ('baixado', 'estoque')
                   AND NOT EXISTS (SELECT 1 FROM bitdefender_endpoints b WHERE b.ativo_id = a.id)
                 ORDER BY a.codigo_patrimonio"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array|null licença (cacheada pela tela) */
    public function licenca(): ?array
    {
        try {
            return $this->rpc('licensing', 'getLicenseInfo');
        } catch (\RuntimeException) {
            return null;
        }
    }

    // ------------------------------------------------------------ ações

    /** @return array{success: bool, message: string} */
    public function verificar(string $endpointId, int $tipo, string $usuario): array
    {
        $tipo = isset(self::TIPOS_VERIFICACAO[$tipo]) ? $tipo : 1;
        $nome = $this->nomeEndpoint($endpointId);
        if ($nome === null) {
            return ['success' => false, 'message' => 'Máquina não encontrada no console.'];
        }
        try {
            $this->rpc('network', 'createScanTask', ['targetIds' => [$endpointId], 'type' => $tipo, 'name' => 'RD.Intranet - ' . self::TIPOS_VERIFICACAO[$tipo] . ' - ' . $nome]);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        AuditService::registrar('Bitdefender GravityZone', 'Verificação', "Verificação {$this->rotuloTipo($tipo)} em {$nome} (por {$usuario}).");

        return ['success' => true, 'message' => "Verificação " . mb_strtolower(self::TIPOS_VERIFICACAO[$tipo]) . " enviada para {$nome}. O resultado aparece no console e, se achar algo, na quarentena aqui."];
    }

    /** @return array{success: bool, message: string} */
    public function isolar(string $endpointId, bool $isolar, string $usuario): array
    {
        $nome = $this->nomeEndpoint($endpointId);
        if ($nome === null) {
            return ['success' => false, 'message' => 'Máquina não encontrada no console.'];
        }
        try {
            $this->rpc('incidents', $isolar ? 'createIsolateEndpointTask' : 'createRestoreEndpointFromIsolationTask', ['endpointId' => $endpointId]);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            return ['success' => false, 'message' => str_contains($msg, 'EDR')
                ? 'A licença do Bitdefender deste cliente não inclui EDR, que é o recurso de isolamento. Use o isolamento do RD.Intranet (ficha da máquina > Segurança).'
                : $msg];
        }
        AuditService::registrar('Bitdefender GravityZone', $isolar ? 'Isolar' : 'Liberar isolamento', "{$nome} (por {$usuario}).");

        return ['success' => true, 'message' => ($isolar ? 'Isolamento' : 'Liberação') . " enviado ao Bitdefender de {$nome}."];
    }

    /** @param string[] $itens @return array{success: bool, message: string} */
    public function quarentenaAcao(array $itens, bool $restaurar, string $usuario): array
    {
        $itens = array_values(array_filter($itens, fn ($i) => preg_match('/^[0-9a-f]{20,40}$/i', $i)));
        if (!$itens) {
            return ['success' => false, 'message' => 'Nenhum item escolhido.'];
        }
        try {
            $this->rpc('quarantine/computers', $restaurar ? 'createRestoreQuarantineItemTask' : 'createRemoveQuarantineItemTask', ['quarantineItemsIds' => $itens]);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        AuditService::registrar('Bitdefender GravityZone', $restaurar ? 'Restaurar da quarentena' : 'Remover da quarentena', count($itens) . " item(ns) (por {$usuario}): " . implode(', ', $itens));

        return ['success' => true, 'message' => count($itens) . ' item(ns) ' . ($restaurar ? 'enviado(s) para restauração' : 'enviado(s) para remoção definitiva') . '. A lista atualiza na próxima sincronização.'];
    }

    // ------------------------------------------------------------ apoio

    /** @throws \RuntimeException */
    private function rpc(string $modulo, string $metodo, array $params = []): array
    {
        $chave = $this->apiKey();
        if ($chave === null) {
            throw new \RuntimeException('Integração com o Bitdefender não configurada.');
        }
        $ch = curl_init($this->urlAtual() . '/v1.0/jsonrpc/' . $modulo);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERPWD => $chave . ':',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['jsonrpc' => '2.0', 'method' => $metodo, 'params' => (object)$params, 'id' => bin2hex(random_bytes(6))]),
        ]);
        $corpo = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($corpo === false) {
            throw new \RuntimeException("Sem resposta do GravityZone ({$erroCurl}).");
        }
        if ($http === 401) {
            throw new \RuntimeException('Chave de API recusada pelo GravityZone (revogada ou sem permissão para este módulo).');
        }
        $json = json_decode((string)$corpo, true);
        if (!is_array($json)) {
            throw new \RuntimeException("Resposta inesperada do GravityZone (HTTP {$http}).");
        }
        if (isset($json['error'])) {
            $detalhe = is_array($json['error']['data'] ?? null) ? ($json['error']['data']['details'] ?? '') : '';
            throw new \RuntimeException('GravityZone: ' . ($detalhe ?: ($json['error']['message'] ?? 'erro')));
        }

        return is_array($json['result'] ?? null) ? $json['result'] : [];
    }

    private function apiKey(): ?string
    {
        $cifrada = ConfigService::get(self::CHAVE_API_KEY_CIFRADA, '');
        if (!$cifrada) {
            return null;
        }
        try {
            return CryptoService::decriptar($cifrada);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /** "2026-10-17T01:19:30" (Bucareste) -> "2026-10-16 19:19:30" (fuso do servidor). */
    private function dataLocal(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }
        try {
            $d = new \DateTime($valor, new \DateTimeZone(self::FUSO_API));
            $d->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            return $d->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function nomeEndpoint(string $endpointId): ?string
    {
        $stmt = Database::connection()->prepare('SELECT nome FROM bitdefender_endpoints WHERE endpoint_id = ?');
        $stmt->execute([$endpointId]);
        $nome = $stmt->fetchColumn();

        return $nome === false ? null : (string)$nome;
    }

    private function rotuloTipo(int $tipo): string
    {
        return mb_strtolower(self::TIPOS_VERIFICACAO[$tipo] ?? '');
    }

    /** @return array<string, int> mac (12 hex, minúsculo) -> ativo_id */
    private function ativosPorMac(): array
    {
        $mapa = [];
        try {
            foreach (Database::connection()->query('SELECT ativo_id, mac FROM ativos_redes WHERE mac IS NOT NULL') as $r) {
                $mac = strtolower(preg_replace('/[^0-9a-f]/i', '', (string)$r['mac']));
                if (strlen($mac) === 12) {
                    $mapa[$mac] = (int)$r['ativo_id'];
                }
            }
        } catch (\Throwable) {
        }

        return $mapa;
    }

    /** @return array<string, int> nome do computador (minúsculo) -> ativo_id */
    private function ativosPorNome(): array
    {
        $mapa = [];
        foreach (Database::connection()->query("SELECT id, nome, JSON_VALUE(detalhes, '$.nome_computador') nc FROM ativos WHERE origem = 'agente'") as $r) {
            foreach ([$r['nome'], $r['nc']] as $n) {
                if ($n) {
                    $mapa[mb_strtolower((string)$n)] = (int)$r['id'];
                }
            }
        }

        return $mapa;
    }
}
