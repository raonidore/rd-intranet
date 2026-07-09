<?php

use App\Components\Alert;

ob_start();
?>

<div class="db-layout">
    <?php require __DIR__ . '/_console_sidebar.php'; ?>

    <div class="db-content">
        <?= Alert::flash() ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-list-columns me-1"></i> <?= htmlspecialchars($tabela) ?></h4>
                <small class="text-muted"><?= htmlspecialchars($conexao['nome']) ?> — <?= htmlspecialchars($banco) ?></small>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('/banco-dados/console/estrutura/coluna/nova?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela)) ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i> Nova coluna
                </a>
                <a href="<?= url('/banco-dados/console/dados?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela)) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-table"></i> Ver dados
                </a>
                <a href="<?= url('/banco-dados/console/tabelas?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco)) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Tabelas
                </a>
            </div>
        </div>

        <?php require __DIR__ . '/_console_sql_rapido.php'; ?>

        <?php if ($erro): ?>
            <div class="alert alert-danger"><i class="bi bi-x-circle"></i> Não foi possível ler a estrutura: <?= htmlspecialchars($erro) ?></div>
        <?php else: ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Coluna</th>
                                <th>Tipo</th>
                                <th>Nulo</th>
                                <th>Chave</th>
                                <th>Padrão</th>
                                <th>Extra</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estrutura['colunas'] as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['Field'] ?? '') ?></td>
                                    <td><code><?= htmlspecialchars($c['Type'] ?? '') ?></code></td>
                                    <td><?= htmlspecialchars($c['Null'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($c['Key'] ?? '') ?></td>
                                    <td><?= htmlspecialchars((string)($c['Default'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($c['Extra'] ?? '') ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= url('/banco-dados/console/estrutura/coluna/editar?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela) . '&coluna=' . urlencode($c['Field'])) ?>"
                                               class="btn btn-outline-primary" title="Editar coluna">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger botao-remover-coluna" data-nome="<?= htmlspecialchars($c['Field']) ?>" title="Remover coluna">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <i class="bi bi-terminal"></i> SHOW CREATE TABLE
                </div>
                <div class="card-body">
                    <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($estrutura['create_table']) ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const CONEXAO_ID = <?= (int)$conexao['id'] ?>;
    const BANCO = <?= json_encode($banco) ?>;
    const TABELA = <?= json_encode($tabela) ?>;
    const REMOVER_URL = <?= json_encode(url('/banco-dados/console/estrutura/coluna/remover')) ?>;

    document.querySelectorAll('.botao-remover-coluna').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            const nome = botao.dataset.nome;
            if (!confirm('Remover a coluna "' + nome + '"? Os dados dessa coluna serão perdidos permanentemente.')) return;

            botao.disabled = true;
            try {
                const fd = new FormData();
                fd.append('conexao', CONEXAO_ID);
                fd.append('banco', BANCO);
                fd.append('tabela', TABELA);
                fd.append('nome', nome);
                const res = await fetch(REMOVER_URL, { method: 'POST', body: fd });
                const dados = await res.json();
                if (dados.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + dados.mensagem);
                    botao.disabled = false;
                }
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
                botao.disabled = false;
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Estrutura - ' . $tabela;

require __DIR__ . '/../layouts/main.php';
