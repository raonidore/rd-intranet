<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Programas confiáveis em todas as máquinas do cliente (Central de
 * Segurança > Exceções). Diferente do "Confiar" da máquina (agente ->
 * registro + firewall local), aqui é uma regra do cliente inteiro:
 *  - o portal descarta os alertas de processo, inicialização e conexão
 *    desses programas, mesmo vindos de agente antigo;
 *  - o agente 1.0.66+ recebe a lista no heartbeat e mostra "confiável (TI)".
 * Varredura de rede continua alertando mesmo de programa confiável -- é o
 * sinal mais forte de malware escondido numa pasta conhecida.
 */
class SegurancaConfiavelService
{
    /** Tipos de evento que um programa confiável deixa de gerar. */
    public const TIPOS_SUPRIMIDOS = ['SUSPICIOUS_PROCESS', 'SUSPICIOUS_STARTUP', 'SUSPICIOUS_CONNECTION'];

    private PDO $pdo;
    private ?array $cache = null;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return list<array{id: int, padrao: string, descricao: string, criado_por: ?string, criado_em: string}> */
    public function listar(): array
    {
        if ($this->cache === null) {
            try {
                $this->cache = $this->pdo->query('SELECT * FROM seguranca_confiaveis ORDER BY padrao')->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                $this->cache = []; // servidor ainda sem a migração
            }
        }

        return $this->cache;
    }

    /** Só o que o agente precisa (bloco "monitor_rede" do heartbeat). */
    public function paraAgente(): array
    {
        return array_map(fn (array $c) => ['padrao' => $c['padrao'], 'descricao' => $c['descricao']], $this->listar());
    }

    /** @return array{success: bool, message: string, resolvidos?: int} */
    public function adicionar(string $padrao, string $descricao, string $usuario): array
    {
        $padrao = self::normalizarPadrao($padrao);
        $descricao = mb_substr(trim(preg_replace('/\s+/', ' ', $descricao)), 0, 300);

        if ($erro = self::validarPadrao($padrao)) {
            return ['success' => false, 'message' => $erro];
        }
        if (mb_strlen($descricao) < 3) {
            return ['success' => false, 'message' => 'Escreva o que é o programa (ex.: "ShiftApp -- sistema do ERP").'];
        }
        foreach ($this->listar() as $c) {
            if (mb_strtolower($c['padrao']) === mb_strtolower($padrao)) {
                return ['success' => false, 'message' => 'Esse caminho já está na lista de confiáveis.'];
            }
        }

        $this->pdo->prepare('INSERT INTO seguranca_confiaveis (padrao, descricao, criado_por) VALUES (?, ?, ?)')->execute([$padrao, $descricao, $usuario]);
        $this->cache = null;

        $resolvidos = $this->resolverEventosAbertos($padrao, $descricao, $usuario);
        AuditService::registrar('Segurança', 'Programa confiável (todas as máquinas)', "Adicionado: {$padrao} ({$descricao}); {$resolvidos} evento(s) aberto(s) encerrado(s) como falso positivo.");

        return [
            'success' => true,
            'resolvidos' => $resolvidos,
            'message' => 'Confiável em todas as máquinas.' . ($resolvidos ? " {$resolvidos} alerta(s) aberto(s) desse programa foram encerrados como falso positivo." : ''),
        ];
    }

    public function remover(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT padrao FROM seguranca_confiaveis WHERE id = ?');
        $stmt->execute([$id]);
        $padrao = $stmt->fetchColumn();
        if ($padrao === false) {
            return;
        }

        $this->pdo->prepare('DELETE FROM seguranca_confiaveis WHERE id = ?')->execute([$id]);
        $this->cache = null;
        AuditService::registrar('Segurança', 'Programa confiável (todas as máquinas)', "Removido: {$padrao}.");
    }

    /** O item confiável que cobre esse caminho, ou null. */
    public function corresponde(?string $caminho): ?array
    {
        $caminho = self::normalizarCaminho((string)$caminho);
        if ($caminho === '') {
            return null;
        }
        foreach ($this->listar() as $c) {
            if (self::casa($c['padrao'], $caminho)) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Aplica a lista num evento que acabou de chegar do agente.
     * @return bool true = descartar o evento inteiro
     */
    public function filtrarEvento(string $tipo, array &$detalhes): bool
    {
        if (!in_array($tipo, self::TIPOS_SUPRIMIDOS, true) || !$this->listar()) {
            return false;
        }

        if ($tipo !== 'SUSPICIOUS_STARTUP') {
            return $this->itemConfiavel($detalhes);
        }

        // Inicialização: tira só os itens confiáveis; descarta se não sobrar nenhum.
        $itens = is_array($detalhes['itens'] ?? null) ? $detalhes['itens'] : [];
        $restantes = array_values(array_filter($itens, fn ($i) => !is_array($i) || !$this->itemConfiavel($i)));
        if (count($restantes) === count($itens)) {
            return false;
        }
        if (!$restantes) {
            return true;
        }

        $detalhes['itens'] = $restantes;
        $detalhes['confiaveis_removidos'] = count($itens) - count($restantes);
        $nomes = array_slice(array_map(fn ($i) => (string)($i['nome'] ?? '?'), $restantes), 0, 3);
        $detalhes['resumo_agente'] = count($restantes) . ' item(ns) suspeito(s) na inicialização do Windows: ' . implode(', ', $nomes)
            . (count($restantes) > 3 ? ' e mais ' . (count($restantes) - 3) : '');

        return false;
    }

    /**
     * Item/processo é confiável se o executável OU o script que ele roda estiver na lista:
     * "powershell.exe -File C:\ARSistemas\IDLE.ps1" casa com a regra "C:\ARSistemas\IDLE.ps1"
     * (Doremedicina, 16/10/2026 -- antes só o caminho do powershell era comparado).
     */
    public function itemConfiavel(array $item): bool
    {
        foreach (self::caminhosDoItem($item) as $caminho) {
            if ($this->corresponde($caminho) !== null) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> executável + script (campo "alvo" do agente 1.0.67+, ou tirado da linha de comando) */
    public static function caminhosDoItem(array $item): array
    {
        $caminhos = array_filter([(string)($item['caminho'] ?? ''), (string)($item['alvo'] ?? '')]);
        $linha = (string)($item['comando'] ?? $item['linha_comando'] ?? '');
        if ($linha !== '' && preg_match_all('#"?([a-z]:\\\\[^"]+?\.(?:ps1|vbs|vbe|js|jse|bat|cmd|hta|jar))(?="|\s|$)#i', $linha, $m)) {
            array_push($caminhos, ...$m[1]);
        }

        return array_values(array_unique($caminhos));
    }

    /** Pasta "do programa" sugerida para confiar: a primeira abaixo de Program Files / ProgramData / AppData\Local\Programs. */
    public static function pastaDoPrograma(string $caminho): ?string
    {
        $caminho = self::normalizarCaminho($caminho);
        if (preg_match('#^([a-z]:\\\\(?:program files(?: \(x86\))?|programdata|users\\\\[^\\\\]+\\\\appdata\\\\local\\\\programs)\\\\[^\\\\]+)\\\\#i', $caminho, $m)) {
            return $m[1] . '\\*';
        }
        // Fora das pastas de instalação (ex.: C:\Sistemas\Erp\bin\x.exe): a pasta do próprio arquivo.
        return str_contains($caminho, '\\') ? substr($caminho, 0, strrpos($caminho, '\\')) . '\\*' : null;
    }

    public static function normalizarPadrao(string $padrao): string
    {
        $padrao = trim($padrao, " \t\"'");
        $padrao = str_replace('/', '\\', $padrao);
        $padrao = preg_replace('#\\\\\.(?=\\\\)#', '', $padrao) ?? $padrao; // "ShiftApp\.\jre" -> "ShiftApp\jre"
        $padrao = preg_replace('#\\\\{2,}#', '\\', $padrao) ?? $padrao;
        // Pasta sem curinga ("C:\Erp\") vira "C:\Erp\*".
        if (str_ends_with($padrao, '\\')) {
            $padrao .= '*';
        }

        return $padrao;
    }

    /** Mensagem de erro, ou null se o padrão for aceitável. */
    public static function validarPadrao(string $padrao): ?string
    {
        if (!preg_match('#^[a-z]:\\\\#i', $padrao)) {
            return 'Use o caminho completo, começando pela unidade (ex.: C:\Program Files (x86)\ShiftApp\*).';
        }
        if (mb_strlen($padrao) > 500 || preg_match('/[<>"|?]/', $padrao)) {
            return 'Caminho inválido.';
        }
        // Pelo menos duas pastas fixas e a última pasta sem curinga: "C:\*", "C:\Program Files\*" ou
        // "C:\Users\*\*" confiariam em tudo; "C:\Users\*\AppData\Local\Programs\Erp\*" é aceito.
        $pastas = array_slice(explode('\\', preg_replace('#\\\\\*$#', '', $padrao)), 1);
        $fixas = array_filter($pastas, fn ($p) => $p !== '' && !str_contains($p, '*'));
        if (count($fixas) < 2 || str_contains((string)end($pastas), '*')) {
            return 'Amplo demais: informe a pasta do programa (ex.: C:\Program Files (x86)\ShiftApp\*), não a unidade ou o Program Files inteiro.';
        }
        // Pastas onde qualquer usuário (ou um download) grava: confiar nelas abre a porta pra malware.
        if (preg_match('#\\\\(temp|tmp|downloads|desktop|área de trabalho|\$recycle\.bin)(\\\\|$)#iu', $padrao) || preg_match('#\\\\users\\\\public\\\\#i', $padrao)) {
            return 'Não dá para confiar em pasta temporária, de downloads ou na área de trabalho -- qualquer arquivo baixado cairia na regra.';
        }

        return null;
    }

    private static function normalizarCaminho(string $caminho): string
    {
        $caminho = trim($caminho, " \t\"'");
        if (str_starts_with($caminho, '\\\\?\\')) {
            $caminho = substr($caminho, 4);
        }
        $caminho = str_replace('/', '\\', $caminho);
        $caminho = preg_replace('#\\\\\.(?=\\\\)#', '', $caminho) ?? $caminho;

        return preg_replace('#\\\\{2,}#', '\\', $caminho) ?? $caminho;
    }

    /** * = qualquer sequência (inclusive subpastas); sem diferença de maiúsculas. */
    private static function casa(string $padrao, string $caminho): bool
    {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($padrao, '#')) . '$#iu';

        return (bool)preg_match($regex, $caminho);
    }

    /** Ao confiar: encerra como falso positivo os alertas abertos desse programa. */
    private function resolverEventosAbertos(string $padrao, string $descricao, string $usuario): int
    {
        $marcadores = implode(',', array_fill(0, count(self::TIPOS_SUPRIMIDOS), '?'));
        $stmt = $this->pdo->prepare("SELECT id, tipo, detalhes FROM ativos_eventos_seguranca WHERE resolvido_em IS NULL AND tipo IN ({$marcadores})");
        $stmt->execute(self::TIPOS_SUPRIMIDOS);

        $eventos = new SegurancaEventoService();
        $nota = "Programa confiável em todas as máquinas: {$padrao} ({$descricao}).";
        $total = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $detalhes = json_decode((string)$e['detalhes'], true) ?: [];
            $itens = $e['tipo'] === 'SUSPICIOUS_STARTUP' ? array_filter((array)($detalhes['itens'] ?? []), 'is_array') : [$detalhes];
            $cobre = fn (array $i) => (bool)array_filter(self::caminhosDoItem($i), fn ($c) => self::casa($padrao, self::normalizarCaminho($c)));
            // Inicialização com vários itens: só encerra se TODOS forem cobertos.
            if ($itens && count(array_filter($itens, $cobre)) === count($itens)) {
                $eventos->marcarResolvido((int)$e['id'], $usuario, 'falso_positivo', $nota);
                $total++;
            }
        }

        return $total;
    }
}
