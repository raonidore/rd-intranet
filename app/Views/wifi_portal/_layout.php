<?php
/** @var string $titulo @var string $cor @var string $conteudo */
$e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $e($titulo) ?></title>
<style>
:root { --cor: <?= $e($cor) ?>; --fundo: #f3f5f7; --cartao: #fff; --texto: #1d2329; --suave: #66707a; --borda: #dde2e7; --erro: #b42318; --ok: #12784a; }
@media (prefers-color-scheme: dark) { :root { --fundo: #111518; --cartao: #1b2126; --texto: #e7ebee; --suave: #9aa5ae; --borda: #2e373e; --erro: #f97066; --ok: #47cd89; } }
* { box-sizing: border-box; }
body { margin: 0; font: 16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--fundo); color: var(--texto); }
.topo { background: var(--cor); color: #fff; padding: 22px 16px 54px; text-align: center; }
.topo h1 { margin: 0; font-size: 22px; font-weight: 650; }
.topo .icone { width: 42px; height: 42px; margin: 0 auto 6px; display: block; }
main { max-width: 440px; margin: -38px auto 24px; padding: 0 16px; }
.cartao { background: var(--cartao); border: 1px solid var(--borda); border-radius: 14px; padding: 20px; margin-bottom: 14px; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
h2 { font-size: 18px; margin: 0 0 12px; }
p { margin: 0 0 12px; }
.suave { color: var(--suave); font-size: 14px; }
label { display: block; font-size: 14px; font-weight: 600; margin: 12px 0 4px; }
input[type=text], input[type=email], input[type=password], input[type=tel], select, textarea { width: 100%; padding: 12px; font-size: 16px; border: 1px solid var(--borda); border-radius: 10px; background: var(--cartao); color: var(--texto); }
input:focus { outline: 2px solid var(--cor); outline-offset: 1px; }
.check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; margin-top: 14px; }
.check input { width: 20px; height: 20px; margin: 2px 0 0; flex: none; }
.btn { display: block; width: 100%; padding: 13px; margin-top: 16px; border: 0; border-radius: 10px; background: var(--cor); color: #fff; font-size: 16px; font-weight: 600; text-align: center; text-decoration: none; cursor: pointer; }
.btn.sec { background: transparent; color: var(--cor); border: 1px solid var(--cor); }
.btn.peq { display: inline-block; width: auto; padding: 8px 14px; margin: 0; font-size: 14px; }
.link { color: var(--cor); }
.erro { background: color-mix(in srgb, var(--erro) 12%, transparent); color: var(--erro); border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 15px; }
.ok { background: color-mix(in srgb, var(--ok) 12%, transparent); color: var(--ok); border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 15px; }
.termo { max-height: 260px; overflow: auto; border: 1px solid var(--borda); border-radius: 10px; padding: 12px; font-size: 14px; white-space: pre-wrap; }
.perfis a { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border: 1px solid var(--borda); border-radius: 10px; margin-top: 8px; color: var(--texto); text-decoration: none; }
.perfis a span { color: var(--suave); font-size: 13px; display: block; }
.linha { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid var(--borda); }
footer { text-align: center; font-size: 13px; color: var(--suave); padding: 0 16px 24px; }
</style>
</head>
<body>
<header class="topo">
  <svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 8.8a15 15 0 0 1 20 0"/><path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19.5" r="1" fill="currentColor"/></svg>
  <h1><?= $e($titulo) ?></h1>
</header>
<main>
<?= $conteudo ?>
</main>
<footer><a class="link" href="/guest/privacidade">Privacidade e uso dos dados</a></footer>
</body>
</html>
