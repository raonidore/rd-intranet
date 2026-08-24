<?php
/** @var array|null $contrato */
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Número do contrato</label>
        <input type="text" name="numero" class="form-control" maxlength="100"
               value="<?= htmlspecialchars($contrato['numero'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Valor negociado (R$)</label>
        <input type="number" step="0.01" min="0" name="valor" class="form-control"
               value="<?= $contrato['valor'] ?? '' ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Data de início</label>
        <input type="date" name="data_inicio" class="form-control"
               value="<?= htmlspecialchars($contrato['data_inicio'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Data de término</label>
        <input type="date" name="data_termino" class="form-control"
               value="<?= htmlspecialchars($contrato['data_termino'] ?? '') ?>">
    </div>
    <div class="col-12">
        <label class="form-label">Descrição</label>
        <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($contrato['descricao'] ?? '') ?></textarea>
    </div>
</div>
