<?php $e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>
<div class="cartao">
  <h2>Limite de aparelhos</h2>
  <p class="suave">Seu acesso permite <?= (int)$max ?> aparelho<?= $max > 1 ? 's' : '' ?> conectado<?= $max > 1 ? 's' : '' ?> ao mesmo tempo. Desconecte um para usar este.</p>
  <?php if (!empty($erro)): ?><div class="erro"><?= $e($erro) ?></div><?php endif; ?>
  <?php foreach ($sessoes as $s): ?>
  <form method="post" action="/guest/aparelhos" class="linha">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="sessao_id" value="<?= (int)$s['id'] ?>">
    <div><strong><?= $e(strtoupper($s['mac'])) ?></strong><br><span class="suave">desde <?= $e(date('d/m H:i', strtotime($s['inicio']))) ?></span></div>
    <button class="btn peq" type="submit">Desconectar</button>
  </form>
  <?php endforeach; ?>
</div>
