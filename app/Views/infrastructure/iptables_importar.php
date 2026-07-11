<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-upload"></i> Importar regras de firewall</h5>
    </div>

    <div class="card-body">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle"></i>
            Aceita o mesmo arquivo JSON gerado pelo botão <strong>Exportar</strong>. É aditivo — soma às regras já
            cadastradas, não apaga nenhuma existente. Todas as regras do arquivo são validadas antes de qualquer uma
            ser salva (se uma for inválida, nada é importado).
        </div>

        <form method="post" action="<?= url('/infraestrutura/iptables/importar') ?>" enctype="multipart/form-data"
              onsubmit="return confirm('Importar as regras deste arquivo? Você poderá confirmar ou reverter em seguida, como em qualquer alteração.');">
            <div class="mb-3">
                <label class="form-label">Arquivo JSON</label>
                <input type="file" name="arquivo" accept="application/json,.json" class="form-control" required>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/infraestrutura/iptables') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload"></i> Importar e aplicar
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Importar Regras de Firewall';

require __DIR__ . '/../layouts/main.php';
