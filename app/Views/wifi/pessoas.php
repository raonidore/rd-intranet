<?php
ob_start();

use App\Components\Alert;
use App\Services\PermissionService;

require __DIR__ . '/_util.php';
$rotulos = ['' => 'Todos', 'pendente' => 'Aguardando', 'ativo' => 'Ativos', 'expirado' => 'Vencidos', 'bloqueado' => 'Bloqueados', 'recusado' => 'Recusados'];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-people me-1"></i> Cadastros do Wi-Fi</h4>
        <small class="text-muted">Colaboradores, fornecedores, visitantes e demais perfis.</small>
    </div>
    <?php if (PermissionService::temAcesso('wifi_pessoas')): ?>
    <a href="<?= url('/wifi/pessoas/nova') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo cadastro</a>
    <?php endif; ?>
</div>

<form class="card border-0 shadow-sm mb-3" method="get" action="<?= url('/wifi/pessoas') ?>">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <div class="btn-group flex-wrap" role="group">
            <?php foreach ($rotulos as $st => $rot): ?>
            <input type="radio" class="btn-check" name="status" id="st_<?= $st ?: 'todos' ?>" value="<?= $st ?>" <?= ($filtro['status'] ?? '') === $st ? 'checked' : '' ?> onchange="this.form.submit()">
            <label class="btn btn-outline-secondary btn-sm" for="st_<?= $st ?: 'todos' ?>"><?= $rot ?><?= $st && !empty($contagem[$st]) ? ' <span class="badge text-bg-light">' . (int)$contagem[$st] . '</span>' : '' ?></label>
            <?php endforeach; ?>
        </div>
        <select name="perfil_id" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()">
            <option value="">Todos os perfis</option>
            <?php foreach ($perfis as $pf): ?><option value="<?= (int)$pf['id'] ?>" <?= (string)$filtro['perfil_id'] === (string)$pf['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pf['nome']) ?></option><?php endforeach; ?>
        </select>
        <input type="search" name="busca" value="<?= htmlspecialchars($filtro['busca']) ?>" class="form-control form-control-sm" style="max-width:260px" placeholder="Nome, e-mail, empresa, celular">
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
    <?php if (!$pessoas): ?>
        <div class="text-center text-muted py-5">Nenhum cadastro encontrado.</div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Nome</th><th>Perfil</th><th>Situação</th><th>Válido até</th><th>Último acesso</th><th class="text-center">Online</th></tr></thead>
            <tbody>
            <?php foreach ($pessoas as $p): ?>
                <tr style="cursor:pointer" onclick="location.href='<?= url('/wifi/pessoas/ver?id=' . (int)$p['id']) ?>'">
                    <td><strong><?= htmlspecialchars($p['nome']) ?></strong><div class="small text-muted"><?= htmlspecialchars(implode(' · ', array_filter([$p['email'], $p['empresa']]))) ?></div></td>
                    <td><?= wifi_perfil($p['perfil_nome'], $p['perfil_cor']) ?></td>
                    <td><?= wifi_status($p['status']) ?></td>
                    <td class="small"><?= $p['valido_ate'] ? date('d/m/Y', strtotime($p['valido_ate'])) : '<span class="text-muted">sem vencimento</span>' ?></td>
                    <td class="small"><?= $p['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($p['ultimo_acesso'])) : '<span class="text-muted">nunca</span>' ?></td>
                    <td class="text-center"><?= $p['sessoes_ativas'] ? '<span class="badge text-bg-success">' . (int)$p['sessoes_ativas'] . '</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - Cadastros';

require __DIR__ . '/../layouts/main.php';
