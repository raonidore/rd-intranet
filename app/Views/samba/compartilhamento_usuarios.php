<?php
ob_start();

$mapa = [];
foreach ($autorizados as $a) {
    $mapa[$a['usuario_id']] = $a;
}
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-people"></i> Usuários do Compartilhamento</h5>
        <small class="text-muted"><?= htmlspecialchars($compartilhamento['nome']) ?></small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/samba/compartilhamentos/usuarios') ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($compartilhamento['id']) ?>">

            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Login</th>
                        <th>Leitura</th>
                        <th>Escrita</th>
                        <th>Exclusão</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <?php $perm = $mapa[$u['id']] ?? null; ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nome']) ?></td>
                            <td><?= htmlspecialchars($u['login']) ?></td>

                            <td>
                                <input type="checkbox" name="usuarios[<?= $u['id'] ?>][leitura]" <?= $perm && (int)$perm['leitura'] ? 'checked' : '' ?>>
                            </td>

                            <td>
                                <input type="checkbox" name="usuarios[<?= $u['id'] ?>][escrita]" <?= $perm && (int)$perm['escrita'] ? 'checked' : '' ?>>
                            </td>

                            <td>
                                <input type="checkbox" name="usuarios[<?= $u['id'] ?>][exclusao]" <?= $perm && (int)$perm['exclusao'] ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button class="btn btn-primary"><i class="bi bi-save"></i> Salvar usuários</button>
            <a href="<?= url('/samba/compartilhamentos') ?>" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Usuários do Compartilhamento';
require __DIR__ . '/../layouts/main.php';
