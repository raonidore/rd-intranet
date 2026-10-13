<?php $e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>
<div class="cartao">
  <h2>Nova senha</h2>
  <?php if (!empty($ok)): ?>
  <div class="ok"><?= $e($ok) ?></div>
  <a class="btn" href="/guest/">Entrar</a>
  <?php elseif (!$valido): ?>
  <div class="erro">Link inválido ou vencido.</div>
  <a class="btn" href="/guest/esqueci">Pedir um novo link</a>
  <?php else: ?>
  <?php if (!empty($erro)): ?><div class="erro"><?= $e($erro) ?></div><?php endif; ?>
  <form method="post" action="/guest/redefinir">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="t" value="<?= $e($token) ?>">
    <label for="senha">Nova senha</label>
    <input type="password" id="senha" name="senha" autocomplete="new-password" minlength="8" required>
    <label for="confirmacao">Repita a senha</label>
    <input type="password" id="confirmacao" name="confirmacao" autocomplete="new-password" minlength="8" required>
    <p class="suave" style="margin-top:8px">Mínimo 8 caracteres, com letras e números.</p>
    <button class="btn" type="submit">Salvar</button>
  </form>
  <?php endif; ?>
</div>
