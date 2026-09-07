<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class MapaRedeService
{
    private const TIPOS_VALIDOS = ['roteador', 'switch', 'servidor', 'computador', 'impressora', 'nuvem', 'outro'];

    public function listar(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("
            SELECT id, nome, descricao, dados, criado_em, atualizado_em
            FROM mapas_rede
            ORDER BY atualizado_em DESC
        ");

        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $linha) {
            $dados = json_decode($linha['dados'], true) ?: ['nos' => [], 'conexoes' => []];
            $linha['total_nos'] = count($dados['nos'] ?? []);
            unset($linha['dados']);
            return $linha;
        }, $linhas);
    }

    public function buscar(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT id, nome, descricao, dados, criado_em, atualizado_em FROM mapas_rede WHERE id = ?");
        $stmt->execute([$id]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$linha) {
            return null;
        }

        $linha['dados'] = json_decode($linha['dados'], true) ?: ['nos' => [], 'conexoes' => []];

        return $linha;
    }

    public function criar(string $nome, ?string $descricao, ?int $criadoPor): int
    {
        $nome = trim($nome);
        if ($nome === '') {
            $nome = 'Mapa sem nome';
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("INSERT INTO mapas_rede (nome, descricao, dados, criado_em, atualizado_em, criado_por) VALUES (?, ?, ?, NOW(), NOW(), ?)");
        $stmt->execute([$nome, $descricao ?: null, json_encode(['nos' => [], 'conexoes' => []]), $criadoPor]);

        $id = (int)$pdo->lastInsertId();

        AuditService::registrar('Infraestrutura', 'Mapa de Rede', "Mapa \"{$nome}\" criado.");

        return $id;
    }

    public function renomear(int $id, string $nome, ?string $descricao): bool
    {
        $nome = trim($nome);
        if ($nome === '') {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("UPDATE mapas_rede SET nome = ?, descricao = ?, atualizado_em = NOW() WHERE id = ?");
        $stmt->execute([$nome, $descricao ?: null, $id]);

        return $stmt->rowCount() > 0;
    }

    public function excluir(int $id): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("DELETE FROM mapas_rede WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Autosave do editor -- recebe o JSON inteiro de {nos, conexoes} e
     * grava por cima. Validação rasa (formato geral certo) de propósito:
     * quem manda esse payload é sempre o próprio editor JS desta tela,
     * não um formulário livre.
     */
    public function salvarDados(int $id, array $dados): bool
    {
        if (!isset($dados['nos']) || !is_array($dados['nos']) || !isset($dados['conexoes']) || !is_array($dados['conexoes'])) {
            return false;
        }

        foreach ($dados['nos'] as $no) {
            if (!is_array($no) || !isset($no['id'], $no['tipo'])) {
                return false;
            }
            if (!in_array($no['tipo'], self::TIPOS_VALIDOS, true)) {
                return false;
            }
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("UPDATE mapas_rede SET dados = ?, atualizado_em = NOW() WHERE id = ?");
        $stmt->execute([json_encode($dados), $id]);

        return true;
    }

    /**
     * Importa hosts de uma varredura do IP Scanner pro mapa -- casa por
     * IP contra nós que já vieram de scanner antes (não duplica ao
     * reimportar), atualizando mac/vendor/hostname mas PRESERVANDO a
     * posição (x,y) que o admin já ajustou; hosts novos entram com
     * posição inicial radial (mesmo cálculo de ângulo que
     * rede_scanner.php usa em renderizarMapa()).
     */
    public function importarDoScan(int $mapaId, array $hosts): array
    {
        $mapa = $this->buscar($mapaId);
        if (!$mapa) {
            return ['success' => false, 'message' => 'Mapa não encontrado.'];
        }

        $dados = $mapa['dados'];
        $nos = $dados['nos'] ?? [];

        $porIp = [];
        foreach ($nos as $i => $no) {
            if (($no['origem'] ?? '') === 'scanner' && !empty($no['ip'])) {
                $porIp[$no['ip']] = $i;
            }
        }

        $cx = 400;
        $cy = 300;
        $raio = 220;
        $novosCount = 0;
        $totalNovosEsperado = count(array_filter($hosts, fn ($h) => !isset($porIp[$h['ip'] ?? ''])));

        foreach ($hosts as $host) {
            $ip = $host['ip'] ?? '';
            if ($ip === '') {
                continue;
            }

            if (isset($porIp[$ip])) {
                $idx = $porIp[$ip];
                $nos[$idx]['nome'] = $host['hostname'] ?: $nos[$idx]['nome'];
                $nos[$idx]['mac'] = $host['mac'] ?? '';
                $nos[$idx]['vendor'] = $host['vendor'] ?? '';
                $nos[$idx]['ativo_id'] = $host['ativo']['id'] ?? null;
                continue;
            }

            $ang = $totalNovosEsperado > 0 ? (2 * M_PI * $novosCount / $totalNovosEsperado) - M_PI / 2 : 0;
            $nos[] = [
                'id' => 'n' . bin2hex(random_bytes(4)),
                'nome' => $host['hostname'] ?: $ip,
                'ip' => $ip,
                'mac' => $host['mac'] ?? '',
                'vendor' => $host['vendor'] ?? '',
                'tipo' => 'computador',
                'ativo_id' => $host['ativo']['id'] ?? null,
                'origem' => 'scanner',
                'x' => round($cx + $raio * cos($ang)),
                'y' => round($cy + $raio * sin($ang)),
            ];
            $novosCount++;
        }

        $dados['nos'] = $nos;
        $this->salvarDados($mapaId, $dados);

        AuditService::registrar('Infraestrutura', 'Mapa de Rede', "Varredura importada pro mapa \"{$mapa['nome']}\": {$novosCount} novo(s), " . (count($hosts) - $novosCount) . ' atualizado(s).');

        return ['success' => true, 'novos' => $novosCount, 'atualizados' => count($hosts) - $novosCount];
    }
}
