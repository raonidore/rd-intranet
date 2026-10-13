<?php

// Portal público do Wi-Fi: sessão própria, separada do painel.
session_name('RDWIFI');
session_set_cookie_params(['path' => '/guest', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);

require_once __DIR__ . '/../../app/bootstrap.php';

$caminho = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$rota = trim((string)preg_replace('#^.*?/guest/?#', '', $caminho), '/');
if ($rota === 'index.php') {
    $rota = '';
}

(new App\Controllers\WifiPortalController())->dispatch($rota);
