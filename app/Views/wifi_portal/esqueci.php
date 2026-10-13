<?php $e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>
<div class="cartao">
  <h2>Esqueci a senha</h2>
  <?php if (!empty($enviado)): ?>
  <div class="ok">Se este e-mail tiver cadastro ativo, você vai receber um link para criar uma nova senha (vale por 1 hora).</div>
  <p class="suave">Para abrir o e-mail você pode usar os dados do celular.</p>
  <?php else: ?>
  <p class="suave">Informe o e-mail do cadastro. Enviaremos um link para criar uma nova senha.</p>
  <form method="post" action="/guest/esqueci">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" autocomplete="email" required>
    <button class="btn" type="submit">Enviar link</button>
  </form>
  <?php endif; ?>
  <a class="btn sec" href="/guest/">Voltar</a>
</div>
