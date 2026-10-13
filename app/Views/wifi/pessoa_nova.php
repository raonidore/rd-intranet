<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-person-plus me-1"></i> Novo cadastro do Wi-Fi</h4>
    <small class="text-muted">Cadastro feito pela TI/RH já nasce liberado. Em perfis com senha, a pessoa recebe login e senha provisória por e-mail.</small>
</div>

<form method="post" action="<?= url('/wifi/pessoas/nova') ?>" class="card border-0 shadow-sm" style="max-width:760px">
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Perfil</label>
            <select name="perfil_id" class="form-select" required>
                <?php foreach ($perfis as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nome']) ?><?= $p['tipo_acesso'] === 'senha' ? ' (login e senha)' : ' (cadastro rápido)' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Válido até <small class="text-muted">(vazio = padrão do perfil)</small></label>
            <input type="date" name="valido_ate" class="form-control" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-6"><label class="form-label">Nome completo</label><input type="text" name="nome" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Celular</label><input type="tel" name="telefone" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">CPF / documento</label><input type="text" name="documento" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Setor</label><input type="text" name="setor" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Empresa</label><input type="text" name="empresa" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">CNPJ</label><input type="text" name="cnpj" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Responsável / anfitrião</label><input type="text" name="anfitriao" class="form-control"></div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Cadastrar e enviar acesso</button>
        <a href="<?= url('/wifi/pessoas') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - Novo cadastro';

require __DIR__ . '/../layouts/main.php';
