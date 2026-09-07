<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class SegurancaAuditoriaService
{
    private const STATUS_DIR = '/var/www/rd.intranet/storage/seguranca_forca_bruta_status';

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * Mesmo critério de rede privada/link-local do IP Scanner
     * (NetworkToolsService::validarFaixaScan()), adaptado pra UM IP em
     * vez de uma faixa -- os alvos de auditoria de credenciais são
     * sempre um host só.
     */
    private function validarIpPrivado(string $ip): bool
    {
        if (!preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$#', $ip, $m)) {
            return false;
        }

        for ($i = 1; $i <= 4; $i++) {
            if ((int)$m[$i] > 255) {
                return false;
            }
        }

        $octeto1 = (int)$m[1];
        $octeto2 = (int)$m[2];

        return ($octeto1 === 10)
            || ($octeto1 === 172 && $octeto2 >= 16 && $octeto2 <= 31)
            || ($octeto1 === 192 && $octeto2 === 168)
            || ($octeto1 === 169 && $octeto2 === 254);
    }

    public function auditarLocal(?int $usuarioId): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/seguranca_auditoria_local_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        if (!is_array($dados)) {
            return ['success' => false, 'message' => $resultado['output']];
        }

        if ($dados['success']) {
            $this->registrarAuditoria('local', null, $dados, $usuarioId);
        }

        return $dados;
    }

    public function testarCredenciaisPadrao(string $ip, string $servico, ?int $usuarioId): array
    {
        if (!$this->validarIpPrivado($ip)) {
            return ['success' => false, 'message' => 'Só é permitido testar hosts em faixa de rede privada (RFC1918) ou link-local.'];
        }

        if (!in_array($servico, ['ssh', 'ftp', 'telnet'], true)) {
            return ['success' => false, 'message' => 'Serviço não suportado.'];
        }

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/seguranca_credenciais_padrao_web.sh', [$ip, $servico]);
        $dados = json_decode(trim($resultado['output']), true);

        if (!is_array($dados)) {
            return ['success' => false, 'message' => $resultado['output']];
        }

        if ($dados['success']) {
            $this->registrarAuditoria('credenciais_padrao', "{$ip} ({$servico})", $dados, $usuarioId);
        }

        return $dados;
    }

    public function iniciarTesteSsh(string $ip, string $usuario): array
    {
        if (!$this->validarIpPrivado($ip)) {
            return ['success' => false, 'message' => 'Só é permitido testar hosts em faixa de rede privada (RFC1918) ou link-local.'];
        }

        $usuario = trim($usuario);
        if ($usuario === '') {
            return ['success' => false, 'message' => 'Informe a conta a testar.'];
        }

        $execucaoId = bin2hex(random_bytes(8));

        $this->linux->executarScriptEmSegundoPlano(
            '/opt/rdtecnologia/scripts/seguranca_forca_bruta_ssh_web.sh',
            [$execucaoId, $ip, $usuario]
        );

        AuditService::registrar('Segurança', 'Auditoria de Credenciais', "Teste de senha SSH iniciado -- host {$ip}, conta \"{$usuario}\".");

        return ['success' => true, 'execucao_id' => $execucaoId, 'ip' => $ip, 'usuario' => $usuario];
    }

    public function statusTesteSsh(string $execucaoId): array
    {
        $id = preg_replace('/[^a-f0-9]/', '', $execucaoId);
        $arquivo = self::STATUS_DIR . "/{$id}.json";

        if ($id === '' || !is_file($arquivo)) {
            return ['status' => 'desconhecido'];
        }

        $dados = json_decode((string)file_get_contents($arquivo), true);

        return is_array($dados) ? $dados : ['status' => 'desconhecido'];
    }

    /**
     * Chamado pelo controller quando o polling detecta status=concluido
     * do teste de SSH -- grava o resultado no histórico (não é o script
     * em segundo plano que grava direto no banco: ele não tem as
     * credenciais de conexão do PDO, só o processo PHP tem).
     */
    public function registrarResultadoTesteSsh(string $ip, string $usuario, ?array $encontrado, ?int $usuarioId): void
    {
        $this->registrarAuditoria('forca_bruta_ssh', "{$ip} ({$usuario})", [
            'ip' => $ip,
            'usuario' => $usuario,
            'encontrado' => $encontrado,
        ], $usuarioId);
    }

    private function registrarAuditoria(string $tipo, ?string $alvo, array $resultado, ?int $usuarioId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("INSERT INTO seguranca_auditorias (tipo, alvo, resultado, executado_em, executado_por) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$tipo, $alvo, json_encode($resultado), $usuarioId]);

        AuditService::registrar('Segurança', 'Auditoria de Credenciais', "Auditoria \"{$tipo}\"" . ($alvo ? " ({$alvo})" : '') . ' concluída.');
    }

    public function listarHistorico(int $limite = 20): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT a.id, a.tipo, a.alvo, a.resultado, a.executado_em, u.nome AS executado_por_nome
            FROM seguranca_auditorias a
            LEFT JOIN usuarios u ON u.id = a.executado_por
            ORDER BY a.executado_em DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
