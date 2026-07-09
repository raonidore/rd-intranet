<?php

use App\Components\Alert;

ob_start();

$pks = $resultado['chaves_primarias'] ?? [];
$temPk = !empty($pks);

$nulavelPorColuna = [];
foreach ($resultado['colunas_info'] ?? [] as $c) {
    $nulavelPorColuna[$c['Field']] = ($c['Null'] ?? 'NO') === 'YES';
}

function urlDados($conexao, $banco, $tabela, $extra = [])
{
    $base = [
        'conexao' => $conexao['id'],
        'banco' => $banco,
        'tabela' => $tabela,
    ];
    return url('/banco-dados/console/dados?' . http_build_query(array_merge($base, $extra)));
}
?>

<style>
.celula-editavel { cursor: cell; }
.celula-editavel:hover { background: #f8f9fa; outline: 1px dashed #ced4da; outline-offset: -1px; }
.celula-editavel.celula-salvando { opacity: .5; }
.celula-edicao-wrapper input { min-width: 80px; }
</style>

<div class="db-layout">
    <?php require __DIR__ . '/_console_sidebar.php'; ?>

    <div class="db-content">
        <?= Alert::flash() ?>

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-1"><i class="bi bi-table me-1"></i> <?= htmlspecialchars($tabela) ?></h4>
                <small class="text-muted"><?= htmlspecialchars($conexao['nome']) ?> — <?= htmlspecialchars($banco) ?></small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('/banco-dados/console/dados/inserir?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela)) ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i> Inserir
                </a>
                <a href="<?= url('/banco-dados/console/dados/exportar?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela) . '&busca=' . urlencode($resultado['busca'] ?? '')) ?>" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-download"></i> Exportar CSV
                </a>
                <a href="<?= url('/banco-dados/console/estrutura?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco) . '&tabela=' . urlencode($tabela)) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-list-columns"></i> Estrutura
                </a>
                <a href="<?= url('/banco-dados/console/tabelas?conexao=' . $conexao['id'] . '&banco=' . urlencode($banco)) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Tabelas
                </a>
            </div>
        </div>

        <?php require __DIR__ . '/_console_sql_rapido.php'; ?>

        <form method="get" action="<?= url('/banco-dados/console/dados') ?>" class="mb-3">
            <input type="hidden" name="conexao" value="<?= (int)$conexao['id'] ?>">
            <input type="hidden" name="banco" value="<?= htmlspecialchars($banco) ?>">
            <input type="hidden" name="tabela" value="<?= htmlspecialchars($tabela) ?>">
            <div class="input-group input-group-sm" style="max-width:420px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="busca" class="form-control" placeholder="Procurar em qualquer coluna..."
                       value="<?= htmlspecialchars($resultado['busca'] ?? '') ?>">
                <button type="submit" class="btn btn-outline-secondary">Buscar</button>
                <?php if (!empty($resultado['busca'])): ?>
                    <a href="<?= urlDados($conexao, $banco, $tabela) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$temPk): ?>
            <div class="alert alert-warning small">
                <i class="bi bi-exclamation-triangle"></i>
                Esta tabela não tem chave primária — editar (inclusive clicando 2x na célula), duplicar e excluir por
                linha não estão disponíveis (não há como identificar uma linha específica com segurança). Use o
                Console SQL para alterações.
            </div>
        <?php elseif (empty($erro)): ?>
            <small class="text-muted d-block mb-2"><i class="bi bi-info-circle"></i> Dica: clique duas vezes numa célula pra editar direto.</small>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert alert-danger"><i class="bi bi-x-circle"></i> Não foi possível ler os dados: <?= htmlspecialchars($erro) ?></div>
        <?php else: ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-0" style="overflow-x:auto">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <?php foreach ($resultado['colunas'] as $col): ?>
                                    <th><?= htmlspecialchars($col) ?><?= in_array($col, $pks, true) ? ' <i class="bi bi-key-fill text-warning" title="Chave primária"></i>' : '' ?></th>
                                <?php endforeach; ?>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultado['linhas'] as $linha): ?>
                                <?php
                                    $pkValores = [];
                                    foreach ($pks as $colPk) {
                                        $pkValores[$colPk] = $linha[$colPk] ?? null;
                                    }
                                    $pkQuery = [];
                                    foreach ($pkValores as $k => $v) {
                                        $pkQuery['pk[' . $k . ']'] = $v;
                                    }
                                ?>
                                <tr data-pk='<?= htmlspecialchars(json_encode($pkValores), ENT_QUOTES) ?>'>
                                    <?php foreach ($linha as $col => $valor): ?>
                                        <td<?= $temPk ? ' class="celula-editavel"' : '' ?>
                                            data-coluna="<?= htmlspecialchars($col) ?>"
                                            data-nulavel="<?= !empty($nulavelPorColuna[$col]) ? '1' : '0' ?>"
                                            data-valor="<?= htmlspecialchars($valor === null ? '' : (string)$valor) ?>"
                                            data-nulo="<?= $valor === null ? '1' : '0' ?>">
                                            <?= $valor === null ? '<span class="text-muted fst-italic">NULL</span>' : htmlspecialchars((string)$valor) ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-end">
                                        <?php if ($temPk): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="<?= url('/banco-dados/console/dados/editar?' . http_build_query(['conexao' => $conexao['id'], 'banco' => $banco, 'tabela' => $tabela] + $pkQuery)) ?>"
                                                   class="btn btn-outline-primary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-secondary botao-duplicar"
                                                        data-pk="<?= htmlspecialchars(json_encode($pkValores)) ?>" title="Duplicar">
                                                    <i class="bi bi-copy"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger botao-remover"
                                                        data-pk="<?= htmlspecialchars(json_encode($pkValores)) ?>" title="Remover">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($resultado['linhas'])): ?>
                                <tr><td colspan="<?= max(1, count($resultado['colunas']) + 1) ?>" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($resultado['total_paginas'] > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($p = 1; $p <= $resultado['total_paginas']; $p++): ?>
                            <li class="page-item <?= $p === $resultado['pagina'] ? 'active' : '' ?>">
                                <a class="page-link" href="<?= urlDados($conexao, $banco, $tabela, ['pagina' => $p, 'busca' => $resultado['busca'] ?? '']) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            <div class="text-center text-muted small">
                <?= number_format($resultado['total'], 0, ',', '.') ?> linha(s) no total.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const CONEXAO_ID = <?= (int)$conexao['id'] ?>;
    const BANCO = <?= json_encode($banco) ?>;
    const TABELA = <?= json_encode($tabela) ?>;
    const EXCLUIR_URL = <?= json_encode(url('/banco-dados/console/dados/excluir')) ?>;
    const DUPLICAR_URL = <?= json_encode(url('/banco-dados/console/dados/duplicar')) ?>;
    const CELULA_URL = <?= json_encode(url('/banco-dados/console/dados/celula')) ?>;

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function iniciarEdicaoCelula(td) {
        if (td.querySelector('input')) return;

        const valorOriginal = td.dataset.valor;
        const nuloOriginal = td.dataset.nulo === '1';
        const nulavel = td.dataset.nulavel === '1';
        const htmlOriginal = td.innerHTML;
        let jaResolvido = false;

        td.innerHTML = '';
        const wrapper = document.createElement('div');
        wrapper.className = 'celula-edicao-wrapper d-flex align-items-center gap-1';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm';
        input.value = nuloOriginal ? '' : valorOriginal;
        if (nuloOriginal) input.placeholder = 'NULL';
        wrapper.appendChild(input);

        let forcarNulo = false;

        if (nulavel) {
            const btnNulo = document.createElement('button');
            btnNulo.type = 'button';
            btnNulo.className = 'btn btn-sm btn-outline-secondary';
            btnNulo.title = 'Definir como NULL';
            btnNulo.textContent = 'N';
            btnNulo.addEventListener('mousedown', function (e) {
                e.preventDefault();
                forcarNulo = true;
                salvar();
            });
            wrapper.appendChild(btnNulo);
        }

        td.appendChild(wrapper);
        input.focus();
        input.select();

        function cancelar() {
            if (jaResolvido) return;
            jaResolvido = true;
            td.innerHTML = htmlOriginal;
        }

        async function salvar() {
            if (jaResolvido) return;

            const novoValor = input.value;
            const nuloNovo = forcarNulo;

            if (nuloNovo === nuloOriginal && (nuloNovo || novoValor === valorOriginal)) {
                cancelar();
                return;
            }

            jaResolvido = true;

            const pk = JSON.parse(td.closest('tr').dataset.pk);
            const fd = new FormData();
            fd.append('conexao', CONEXAO_ID);
            fd.append('banco', BANCO);
            fd.append('tabela', TABELA);
            fd.append('coluna', td.dataset.coluna);
            fd.append('valor', nuloNovo ? '' : novoValor);
            if (nuloNovo) fd.append('nulo', '1');
            Object.keys(pk).forEach(function (k) {
                fd.append('pk[' + k + ']', pk[k] === null ? '' : pk[k]);
            });

            td.classList.add('celula-salvando');
            try {
                const res = await fetch(CELULA_URL, { method: 'POST', body: fd });
                const dados = await res.json();
                if (dados.success) {
                    td.dataset.valor = nuloNovo ? '' : novoValor;
                    td.dataset.nulo = nuloNovo ? '1' : '0';
                    td.innerHTML = nuloNovo ? '<span class="text-muted fst-italic">NULL</span>' : escapeHtml(novoValor);
                } else {
                    alert('Erro ao salvar: ' + dados.mensagem);
                    td.innerHTML = htmlOriginal;
                }
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
                td.innerHTML = htmlOriginal;
            } finally {
                td.classList.remove('celula-salvando');
            }
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); salvar(); }
            if (e.key === 'Escape') { e.preventDefault(); cancelar(); }
        });
        input.addEventListener('blur', function () {
            setTimeout(salvar, 150);
        });
    }

    document.querySelectorAll('.celula-editavel').forEach(function (td) {
        td.addEventListener('dblclick', function () {
            iniciarEdicaoCelula(td);
        });
    });

    function corpoPk(pk) {
        const fd = new FormData();
        fd.append('conexao', CONEXAO_ID);
        fd.append('banco', BANCO);
        fd.append('tabela', TABELA);
        Object.keys(pk).forEach(function (k) {
            fd.append('pk[' + k + ']', pk[k] === null ? '' : pk[k]);
        });
        return fd;
    }

    document.querySelectorAll('.botao-remover').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            if (!confirm('Remover este registro? Esta ação não pode ser desfeita.')) return;
            const pk = JSON.parse(botao.dataset.pk);
            botao.disabled = true;
            try {
                const res = await fetch(EXCLUIR_URL, { method: 'POST', body: corpoPk(pk) });
                const dados = await res.json();
                if (dados.success) {
                    botao.closest('tr').remove();
                } else {
                    alert('Erro: ' + dados.mensagem);
                }
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            } finally {
                botao.disabled = false;
            }
        });
    });

    document.querySelectorAll('.botao-duplicar').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            if (!confirm('Duplicar este registro?')) return;
            const pk = JSON.parse(botao.dataset.pk);
            botao.disabled = true;
            try {
                const res = await fetch(DUPLICAR_URL, { method: 'POST', body: corpoPk(pk) });
                const dados = await res.json();
                if (dados.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + dados.mensagem);
                }
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            } finally {
                botao.disabled = false;
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Dados - ' . $tabela;

require __DIR__ . '/../layouts/main.php';
