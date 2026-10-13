<?php $e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>
<div class="cartao" style="text-align:center">
  <h2>Conectado<?= $nome ? ', ' . $e($nome) : '' ?>!</h2>
  <div class="ok">Seu acesso está liberado<?= $minutos ? ' por ' . ($minutos >= 120 ? round($minutos / 60) . ' horas' : $minutos . ' minutos') : '' ?>.</div>
  <p class="suave">Pode levar alguns segundos para a internet começar a funcionar.</p>
  <?php if ($url): ?>
  <a class="btn" href="<?= $e($url) ?>">Continuar</a>
  <script>setTimeout(function () { location.href = <?= json_encode($url) ?>; }, 4000);</script>
  <?php endif; ?>
</div>
