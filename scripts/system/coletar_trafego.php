<?php

// Coleta periodica do trafego de rede (RX/TX bytes e pacotes por interface)
// para o historico de consumo. Chamado via cron, ex.: a cada 5 minutos.
//   */5 * * * * /usr/bin/php /var/www/rd.intranet/scripts/system/coletar_trafego.php

require_once __DIR__ . '/../../app/bootstrap.php';

(new \App\Services\TrafegoHistoricoService())->coletarAmostra();
