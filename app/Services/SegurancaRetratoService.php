<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Último retrato do monitor de conexões/processos/inicialização (agente
 * 1.0.56+) de cada máquina: "rede" (conexões por programa, tentativas,
 * processos suspeitos, firewall) e "inicializacao" (o que o Windows inicia
 * sozinho, já analisado). Só o último fica -- o que importa no histórico
 * vira evento de segurança.
 */
class SegurancaRetratoService
{
    public const TIPOS = ['rede', 'inicializacao'];

    /** Retrato bem maior que isso = algo errado no agente; recusa em vez de lotar o banco. */
    private const TAMANHO_MAXIMO = 4 * 1024 * 1024;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array{success: bool, message?: string} */
    public function guardar(int $ativoId, string $tipo, mixed $dados): array
    {
        if (!in_array($tipo, self::TIPOS, true) || !is_array($dados)) {
            return ['success' => false, 'message' => 'Retrato inválido.'];
        }

        $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || strlen($json) > self::TAMANHO_MAXIMO) {
            return ['success' => false, 'message' => 'Retrato grande demais.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ativos_seguranca_retratos (ativo_id, tipo, dados) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE dados = VALUES(dados), recebido_em = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$ativoId, $tipo, $json]);

        return ['success' => true];
    }

    /** @return array<string, array{dados: array, recebido_em: string}> */
    public function doAtivo(int $ativoId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT tipo, dados, recebido_em FROM ativos_seguranca_retratos WHERE ativo_id = ?');
            $stmt->execute([$ativoId]);
        } catch (\Throwable) {
            return []; // servidor ainda sem a migração
        }

        $retratos = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $retratos[$linha['tipo']] = [
                'dados' => json_decode($linha['dados'], true) ?: [],
                'recebido_em' => $linha['recebido_em'],
            ];
        }

        return $retratos;
    }

    /**
     * IPs bloqueados pelo RD.Intranet no firewall de cada máquina (regras
     * "RD.Intranet - Bloqueio IP: x") -- base das sugestões de bloqueio no
     * gateway/modem na Central de Segurança.
     *
     * @return list<array{ip: string, ativo_id: int, codigo: string, nome: string, regra: string, descricao: ?string}>
     */
    public function bloqueiosDeIp(): array
    {
        try {
            $linhas = $this->pdo->query(
                "SELECT r.ativo_id, r.dados, a.codigo_patrimonio, a.nome
                 FROM ativos_seguranca_retratos r JOIN ativos a ON a.id = r.ativo_id
                 WHERE r.tipo = 'rede'"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $bloqueios = [];
        foreach ($linhas as $linha) {
            $dados = json_decode($linha['dados'], true) ?: [];
            foreach ($dados['firewall']['regras_rd'] ?? [] as $regra) {
                if (empty($regra['ips']) || ($regra['acao'] ?? '') !== 'bloquear' || ($regra['direcao'] ?? '') !== 'saida') {
                    continue;
                }
                foreach (explode(',', (string)$regra['ips']) as $ip) {
                    // O firewall devolve "a-a" (intervalo de um IP só) ou "x/255.255.255.255": vira o IP puro.
                    $ip = preg_replace('#/255\.255\.255\.255$#', '', trim($ip));
                    if (preg_match('/^([^-]+)-([^-]+)$/', $ip, $m) && $m[1] === $m[2]) {
                        $ip = $m[1];
                    }
                    if ($ip === '' || $ip === '*') {
                        continue;
                    }
                    $bloqueios[] = [
                        'ip' => $ip,
                        'ativo_id' => (int)$linha['ativo_id'],
                        'codigo' => $linha['codigo_patrimonio'],
                        'nome' => $linha['nome'],
                        'regra' => (string)$regra['nome'],
                        'descricao' => $regra['descricao'] ?? null,
                    ];
                }
            }
        }

        return $bloqueios;
    }
}
