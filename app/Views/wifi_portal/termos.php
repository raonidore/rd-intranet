<?php $e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>
<div class="cartao">
  <h2>Termos de uso</h2>
  <p class="suave">Para continuar, leia e aceite os termos de uso do Wi-Fi.</p>
  <div class="termo"><?= $e($termo['texto'] ?? '') ?></div>
  <form method="post" action="/guest/termos">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <label class="check"><input type="checkbox" name="aceite" value="1" required> Li e aceito.</label>
    <button class="btn" type="submit">Continuar</button>
  </form>
</div>
