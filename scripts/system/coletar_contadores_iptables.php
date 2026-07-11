<?php

// Roda periodicamente via cron (ver AtualizacaoService::garantirCronsColeta())
// como www-data -- grava um snapshot dos contadores (pacotes/bytes) de cada
// regra ATIVA do firewall em iptables_regras_historico, pra alimentar o
// grafico de "regras mais acionadas" (Infraestrutura > Firewall > Ao Vivo).
// So-leitura sobre o firewall (via os mesmos scripts *_web.sh que a tela
// usa); nunca aplica nada.
//   */5 * * * * /usr/bin/php /var/www/rd.intranet/scripts/system/coletar_contadores_iptables.php

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;
use App\Services\IptablesService;

$service = new IptablesService();

$posicoes = $service->regrasComPosicao();
if (empty($posicoes)) {
    exit(0);
}

$contadores = $service->contadores();

$pdo = Database::connection();
$stmt = $pdo->prepare('INSERT INTO iptables_regras_historico (regra_id, pkts, bytes) VALUES (?, ?, ?)');

foreach ($posicoes as $p) {
    $porNum = [];
    foreach (($contadores[$p['tabela']][$p['cadeia']]['regras'] ?? []) as $linha) {
        $porNum[$linha['num']] = $linha;
    }

    $c = $porNum[$p['posicao']] ?? null;
    if ($c === null) {
        continue;
    }

    $stmt->execute([$p['id'], $c['pkts'], $c['bytes']]);
}
