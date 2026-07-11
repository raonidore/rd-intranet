<?php

// Roda periodicamente via cron (ver AtualizacaoService::garantirCronsColeta())
// como www-data -- guarda os eventos de log do firewall (IPs
// bloqueados/liberados por regras com "registrar_log" ligado) em
// iptables_log_eventos, pra alimentar o ranking de IPs mais bloqueados
// (Infraestrutura > Firewall > Ao Vivo). So-leitura sobre o log do kernel
// (via iptables_logs_todos_web.sh); nunca aplica nada.
//   */2 * * * * /usr/bin/php /var/www/rd.intranet/scripts/system/coletar_logs_iptables.php
//
// Timestamp de cada evento e o momento da coleta (nao o do kernel) --
// precisao de minutos e suficiente pro ranking, e evita parsear a data do
// journalctl (sem ano, mais fragil). A marca "coletado ate" (epoch, em
// configuracoes) evita reprocessar a mesma linha entre execucoes.

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;
use App\Services\ConfigService;
use App\Services\LinuxService;

const CHAVE_MARCA = 'iptables_logs_coletados_ate';

$desde = (int)(ConfigService::get(CHAVE_MARCA, '') ?: (time() - 300));
$agora = time();

$linux = new LinuxService();
$resultado = $linux->executarScript('/opt/rdtecnologia/scripts/iptables_logs_todos_web.sh', [(string)$desde]);

$pdo = Database::connection();
$stmt = $pdo->prepare('
    INSERT INTO iptables_log_eventos (regra_id, ip_origem, ip_destino, protocolo, porta_destino)
    VALUES (?, ?, ?, ?, ?)
');

foreach (explode("\n", $resultado['output']) as $linha) {
    $linha = trim($linha);
    if ($linha === '' || !preg_match('/RD-FW-(\d+):/', $linha, $mRegra)) {
        continue;
    }

    preg_match('/\bSRC=(\S+)/', $linha, $mSrc);
    preg_match('/\bDST=(\S+)/', $linha, $mDst);
    preg_match('/\bPROTO=(\S+)/', $linha, $mProto);
    preg_match('/\bDPT=(\S+)/', $linha, $mDpt);

    $stmt->execute([
        (int)$mRegra[1],
        $mSrc[1] ?? null,
        $mDst[1] ?? null,
        $mProto[1] ?? null,
        $mDpt[1] ?? null,
    ]);
}

ConfigService::set(CHAVE_MARCA, (string)$agora);
