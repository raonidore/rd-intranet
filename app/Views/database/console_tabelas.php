<?php

ob_start();

$tabela = '';
?>

<div class="db-layout">
    <?php require __DIR__ . '/_console_sidebar.php'; ?>

    <div class="db-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-table me-1"></i> <?= htmlspecialchars($banco) ?></h4>
                <small class="text-muted"><?= htmlspecialchars($conexao['nome']) ?> — <?= htmlspecialchars($conexao['host']) ?></small>
            </div>
            <div>
                <a href="<?= url('/banco-dados/console/sql?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco)) ?>" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-code-slash"></i> Console SQL
                </a>
                <a href="<?= url('/banco-dados/console?conexao=' . $conexao['id']) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Bancos
                </a>
            </div>
        </div>

        <?php require __DIR__ . '/_console_sql_rapido.php'; ?>

        <?php if ($erro): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle"></i> Não foi possível listar as tabelas: <?= htmlspecialchars($erro) ?></div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tabela</th>
                        <th>Engine</th>
                        <th>Linhas (aprox.)</th>
                        <th>Tamanho</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tabelas as $t): ?>
                        <?php
                            $nomeTabela = $t['Name'] ?? '';
                            $tamanho = ((int)($t['Data_length'] ?? 0) + (int)($t['Index_length'] ?? 0));
                            $tamanhoMb = round($tamanho / 1048576, 2);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($nomeTabela) ?></td>
                            <td><?= htmlspecialchars($t['Engine'] ?? '-') ?></td>
                            <td><?= number_format((int)($t['Rows'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= $tamanhoMb ?> MB</td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="<?= url('/banco-dados/console/estrutura?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($nomeTabela)) ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Estrutura">
                                        <i class="bi bi-list-columns"></i>
                                    </a>
                                    <a href="<?= url('/banco-dados/console/dados?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($nomeTabela)) ?>"
                                       class="btn btn-sm btn-outline-primary" title="Ver dados">
                                        <i class="bi bi-table"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tabelas)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma tabela encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
        <?php endif; ?>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Console - ' . $banco;

require __DIR__ . '/../layouts/main.php';
