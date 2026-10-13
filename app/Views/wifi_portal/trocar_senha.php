<?php $e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>
<div class="cartao">
  <h2>Crie sua senha</h2>
  <p class="suave">A senha que chegou por e-mail é provisória. Crie a sua (mínimo 8 caracteres, com letras e números).</p>
  <?php if (!empty($erro)): ?><div class="erro"><?= $e($erro) ?></div><?php endif; ?>
  <form method="post" action="/guest/trocar-senha">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <label for="senha">Nova senha</label>
    <input type="password" id="senha" name="senha" autocomplete="new-password" minlength="8" required>
    <label for="confirmacao">Repita a senha</label>
    <input type="password" id="confirmacao" name="confirmacao" autocomplete="new-password" minlength="8" required>
    <button class="btn" type="submit">Salvar e conectar</button>
  </form>
</div>
