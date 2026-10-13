<?php $e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>
<div class="cartao">
  <div class="erro"><?= $e($mensagem) ?></div>
  <?php if (!empty($voltar)): ?><a class="btn sec" href="/guest/">Voltar ao início</a><?php endif; ?>
</div>
