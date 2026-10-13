<?php
$e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$v = fn ($k) => $e($dados[$k] ?? '');
$comSenha = $perfil['tipo_acesso'] === 'senha';
$temTelefone = (bool)array_filter($campos, fn ($c) => $c['campo'] === 'telefone');
?>
<div class="cartao">
  <h2>Cadastro: <?= $e($perfil['nome']) ?></h2>
  <?php if ($perfil['exige_liberacao']): ?>
  <p class="suave">Depois de enviar, alguém da equipe confere e libera o acesso. Você recebe um e-mail com o resultado<?= $comSenha ? ' e sua senha' : '' ?>.</p>
  <?php elseif ($comSenha): ?>
  <p class="suave">Sua senha chega por e-mail.</p>
  <?php endif; ?>
  <?php if (!empty($erro)): ?><div class="erro"><?= $e($erro) ?></div><?php endif; ?>
  <form method="post" action="/guest/cadastro">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="perfil" value="<?= $e($perfil['slug']) ?>">
    <label for="nome">Nome completo</label>
    <input type="text" id="nome" name="nome" value="<?= $v('nome') ?>" autocomplete="name" required>
    <label for="email">E-mail<?= $comSenha ? '' : ' (ou celular abaixo)' ?></label>
    <input type="email" id="email" name="email" value="<?= $v('email') ?>" autocomplete="email" <?= $comSenha ? 'required' : '' ?>>
    <?php if ($perfil['dominios_email']): ?><p class="suave" style="margin-top:4px">Use o e-mail da empresa.</p><?php endif; ?>
    <?php if (!$temTelefone && !$comSenha): ?>
    <label for="telefone">Celular</label>
    <input type="tel" id="telefone" name="telefone" value="<?= $v('telefone') ?>" autocomplete="tel">
    <?php endif; ?>
    <?php foreach ($campos as $c): $id = 'c_' . preg_replace('/\W/', '', $c['campo']); ?>
    <label for="<?= $id ?>"><?= $e($c['rotulo']) ?><?= empty($c['obrigatorio']) ? ' <span class="suave">(opcional)</span>' : '' ?></label>
    <input type="<?= $c['campo'] === 'telefone' ? 'tel' : 'text' ?>" id="<?= $id ?>" name="<?= $e($c['campo']) ?>" value="<?= $v($c['campo']) ?>" <?= !empty($c['obrigatorio']) ? 'required' : '' ?>>
    <?php endforeach; ?>
    <?php if ($termo): ?>
    <label>Termos de uso</label>
    <div class="termo"><?= $e($termo['texto']) ?></div>
    <?php endif; ?>
    <label class="check"><input type="checkbox" name="aceite" value="1" required> Li e aceito os termos de uso e o tratamento dos meus dados para liberar o acesso.</label>
    <button class="btn" type="submit"><?= $perfil['exige_liberacao'] ? 'Enviar cadastro' : 'Continuar' ?></button>
  </form>
  <a class="btn sec" href="/guest/">Voltar</a>
</div>
