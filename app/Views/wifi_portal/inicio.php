<?php $e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>
<?php if ($semAparelho): ?>
<div class="cartao"><p class="suave">Para usar a internet, conecte no Wi-Fi e aguarde esta página abrir sozinha. Se não abrir, acesse qualquer site pelo navegador.</p></div>
<?php endif; ?>
<?php if ($temSenha): ?>
<div class="cartao">
  <h2>Entrar</h2>
  <p class="suave"><?= $e($boasVindas) ?></p>
  <?php if (!empty($erro)): ?><div class="erro"><?= $e($erro) ?></div><?php endif; ?>
  <form method="post" action="/guest/entrar">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" value="<?= $e($email ?? '') ?>" autocomplete="username" required>
    <label for="senha">Senha</label>
    <input type="password" id="senha" name="senha" autocomplete="current-password" required>
    <label class="check"><input type="checkbox" name="lembrar" value="1"> Lembrar este aparelho</label>
    <button class="btn" type="submit">Entrar</button>
  </form>
  <p style="margin:14px 0 0;text-align:center"><a class="link" href="/guest/esqueci">Esqueci a senha</a></p>
</div>
<?php endif; ?>
<?php if ($perfisCadastro): ?>
<div class="cartao perfis">
  <h2><?= $temSenha ? 'Primeira vez?' : 'Acessar o Wi-Fi' ?></h2>
  <?php foreach ($perfisCadastro as $p): ?>
  <a href="/guest/cadastro?perfil=<?= $e($p['slug']) ?>">
    <div><strong><?= $e($p['nome']) ?></strong><span><?= $e($p['descricao'] ?: ($p['tipo_acesso'] === 'cadastro' ? 'Cadastro rápido' : 'Cadastro com liberação')) ?></span></div>
    <span aria-hidden="true">&rsaquo;</span>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
