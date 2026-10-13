<?php $e = fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>
<div class="cartao">
  <h2>Privacidade e uso dos dados</h2>
  <p>Para liberar o Wi-Fi coletamos os dados do cadastro (nome, e-mail e o que mais o formulário pedir) e registramos as conexões: aparelho (endereço MAC), endereço IP, início e fim de cada uso e volume de dados.</p>
  <p><strong>Por quê:</strong> controlar quem usa a rede e cumprir o Marco Civil da Internet (Lei 12.965/2014), que exige guardar os registros de conexão. Base legal: cumprimento de obrigação legal e legítimo interesse (LGPD, art. 7º).</p>
  <p><strong>Por quanto tempo:</strong> os registros de conexão ficam <?= (int)$retencao ?> dias e depois são anonimizados.<?php if ($filtroDias): ?> Os endereços de sites acessados ficam no máximo <?= (int)$filtroDias ?> dias, só para diagnóstico e segurança da rede.<?php endif; ?></p>
  <p><strong>Seus direitos:</strong> você pode pedir acesso, correção ou exclusão dos seus dados (respeitado o prazo legal dos registros de conexão).</p>
  <?php if ($encarregadoNome || $encarregadoEmail): ?>
  <p><strong>Encarregado (DPO):</strong> <?= $e($encarregadoNome) ?><?php if ($encarregadoEmail): ?> -- <a class="link" href="mailto:<?= $e($encarregadoEmail) ?>"><?= $e($encarregadoEmail) ?></a><?php endif; ?></p>
  <?php endif; ?>
  <a class="btn sec" href="/guest/">Voltar</a>
</div>
