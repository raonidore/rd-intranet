<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-1">
            <i class="bi bi-ui-checks"></i> Configurar Serviços Gerenciados
        </h5>
        <small class="text-muted">
            Escolha quais serviços do sistema aparecem na tela de Serviços, com opção de reiniciar, recarregar e ver logs.
        </small>
    </div>
</div>

<form method="POST" action="<?= url('/infraestrutura/servicos/configurar') ?>">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <input type="text" class="form-control" id="filtro" placeholder="Filtrar por nome da unidade ou descrição...">
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-0" style="max-height:500px; overflow-y:auto">
            <table class="table table-hover align-middle mb-0" id="tabela-servicos">
                <thead>
                    <tr>
                        <th style="width:40px"></th>
                        <th>Unidade</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catalogo as $item): ?>
                        <tr class="linha-servico" data-busca="<?= htmlspecialchars(strtolower($item['unidade'] . ' ' . $item['nome'])) ?>">
                            <td>
                                <input type="checkbox" class="form-check-input" name="unidades[]"
                                       value="<?= htmlspecialchars($item['unidade']) ?>"
                                       <?= $item['gerenciado'] ? 'checked' : '' ?>>
                            </td>
                            <td><code><?= htmlspecialchars($item['unidade']) ?></code></td>
                            <td><?= htmlspecialchars($item['nome']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="<?= url('/infraestrutura/servicos') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Salvar Seleção
        </button>
    </div>
</form>

<script>
document.getElementById('filtro').addEventListener('input', function (e) {
    const termo = e.target.value.toLowerCase();
    document.querySelectorAll('.linha-servico').forEach(function (linha) {
        linha.style.display = linha.dataset.busca.includes(termo) ? '' : 'none';
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Configurar Serviços';

require __DIR__ . '/../layouts/main.php';
