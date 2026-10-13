<?php
ob_start();

use App\Components\Alert;
use App\Services\PermissionService;

require __DIR__ . '/_util.php';
$podeEditar = PermissionService::temAcesso('wifi_pessoas');
$podeLiberar = PermissionService::temAcesso('wifi_liberar');
$podeLgpd = PermissionService::temAcesso('wifi_relatorios');
$extra = json_decode((string)$pessoa['dados_extra'], true) ?: [];
$voltar = '/wifi/pessoas/ver?id=' . (int)$pessoa['id'];
$ex = fn ($k) => $excecoes[$k] ?? '';
?>

<?= Alert::flash() ?>

<div class="mb-3 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <a href="<?= url('/wifi/pessoas') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Cadastros</a>
        <h4 class="mb-1 mt-1"><?= htmlspecialchars($pessoa['nome']) ?> <?= wifi_status($pessoa['status']) ?></h4>
        <small class="text-muted"><?= wifi_perfil($pessoa['perfil_nome'], $pessoa['perfil_cor']) ?> &middot; cadastrado <?= $pessoa['origem'] === 'portal' ? 'pelo portal' : 'pela equipe' ?> em <?= date('d/m/Y H:i', strtotime($pessoa['criado_em'])) ?>
            <?= $pessoa['motivo'] ? ' &middot; ' . htmlspecialchars($pessoa['motivo']) : '' ?></small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($podeLiberar && in_array($pessoa['status'], ['pendente', 'recusado', 'bloqueado', 'expirado'], true)): ?>
        <form method="post" action="<?= url('/wifi/liberar') ?>"><input type="hidden" name="id" value="<?= (int)$pessoa['id'] ?>"><input type="hidden" name="voltar" value="<?= $voltar ?>">
            <button class="btn btn-success"><i class="bi bi-check-lg"></i> <?= $pessoa['status'] === 'pendente' ? 'Liberar' : 'Reativar' ?></button></form>
        <?php endif; ?>
        <?php if ($podeLiberar && $pessoa['status'] === 'pendente'): ?>
        <button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#formRecusar"><i class="bi bi-x-lg"></i> Recusar</button>
        <?php endif; ?>
        <?php if ($podeLiberar && $pessoa['status'] === 'ativo'): ?>
        <button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#formBloquear"><i class="bi bi-slash-circle"></i> Bloquear</button>
        <?php endif; ?>
        <?php if ($podeEditar && $pessoa['email'] && $perfil['tipo_acesso'] === 'senha' && $pessoa['status'] === 'ativo'): ?>
        <form method="post" action="<?= url('/wifi/pessoas/senha') ?>" onsubmit="return confirm('Gerar uma senha provisória nova e enviar por e-mail?')"><input type="hidden" name="id" value="<?= (int)$pessoa['id'] ?>">
            <button class="btn btn-outline-secondary"><i class="bi bi-key"></i> Enviar nova senha</button></form>
        <?php endif; ?>
    </div>
</div>

<?php if ($podeLiberar): ?>
<form method="post" action="<?= url('/wifi/recusar') ?>" id="formRecusar" class="collapse card card-body border-0 shadow-sm mb-3">
    <input type="hidden" name="id" value="<?= (int)$pessoa['id'] ?>"><input type="hidden" name="voltar" value="<?= $voltar ?>">
    <div class="d-flex gap-2"><input type="text" name="motivo" class="form-control" placeholder="Motivo (vai no e-mail, opcional)"><button class="btn btn-danger">Confirmar recusa</button></div>
</form>
<form method="post" action="<?= url('/wifi/bloquear') ?>" id="formBloquear" class="collapse card card-body border-0 shadow-sm mb-3">
    <input type="hidden" name="id" value="<?= (int)$pessoa['id'] ?>"><input type="hidden" name="voltar" value="<?= $voltar ?>">
    <div class="d-flex gap-2"><input type="text" name="motivo" class="form-control" placeholder="Motivo do bloqueio"><button class="btn btn-danger">Bloquear e desconectar</button></div>
</form>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="<?= url('/wifi/pessoas/editar') ?>" class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Dados do cadastro</div>
            <div class="card-body row g-2">
                <input type="hidden" name="id" value="<?= (int)$pessoa['id'] ?>">
                <?php $ro = $podeEditar ? '' : 'readonly'; ?>
                <div class="col-12"><label class="form-label small">Nome</label><input name="nome" class="form-control" value="<?= htmlspecialchars($pessoa['nome']) ?>" <?= $ro ?>></div>
                <div class="col-md-6"><label class="form-label small">E-mail (login)</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string)$pessoa['email']) ?>" <?= $ro ?>></div>
                <div class="col-md-6"><label class="form-label small">Celular</label><input name="telefone" class="form-control" value="<?= htmlspecialchars((string)$pessoa['telefone']) ?>" <?= $ro ?>></div>
                <div class="col-md-6"><label class="form-label small">Empresa</label><input name="empresa" class="form-control" value="<?= htmlspecialchars((string)$pessoa['empresa']) ?>" <?= $ro ?>></div>
                <div class="col-md-6"><label class="form-label small">CNPJ</label><input name="cnpj" class="form-control" value="<?= htmlspecialchars((string)$pessoa['cnpj']) ?>" <?= $ro ?>></div>
                <div class="col-md-6"><label class="form-label small">Documento</label><input name="documento" class="form-control" value="<?= htmlspecialchars((string)$pessoa['documento']) ?>" <?= $ro ?>></div>
                <div class="col-md-6"><label class="form-label small">Setor</label><input name="setor" class="form-control" value="<?= htmlspecialchars((string)$pessoa['setor']) ?>" <?= $ro ?>></div>
                <div class="col-md-6"><label class="form-label small">Responsável / anfitrião</label><input name="anfitriao" class="form-control" value="<?= htmlspecialchars((string)$pessoa['anfitriao']) ?>" <?= $ro ?>></div>
                <div class="col-md-6"><label class="form-label small">Válido até</label><input type="date" name="valido_ate" class="form-control" value="<?= $pessoa['valido_ate'] ? date('Y-m-d', strtotime($pessoa['valido_ate'])) : '' ?>" <?= $ro ?>></div>
                <div class="col-md-6"><label class="form-label small">Perfil</label>
                    <select name="perfil_id" class="form-select" <?= $podeEditar ? '' : 'disabled' ?>>
                        <?php foreach ($perfis as $pf): ?><option value="<?= (int)$pf['id'] ?>" <?= (int)$pf['id'] === (int)$pessoa['perfil_id'] ? 'selected' : '' ?>><?= htmlspecialchars($pf['nome']) ?></option><?php endforeach; ?>
                    </select></div>
                <?php foreach ($extra as $rot => $v): ?>
                <div class="col-md-6"><label class="form-label small"><?= htmlspecialchars($rot) ?></label><input class="form-control" value="<?= htmlspecialchars((string)$v) ?>" readonly></div>
                <?php endforeach; ?>
            </div>
            <?php if ($podeEditar): ?><div class="card-footer bg-transparent"><button class="btn btn-primary btn-sm">Salvar dados</button></div><?php endif; ?>
        </form>
    </div>

    <div class="col-lg-6">
        <form method="post" action="<?= url('/wifi/pessoas/excecoes') ?>" class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Regras desta pessoa <small class="text-muted fw-normal">(vazio = segue o perfil)</small></div>
            <div class="card-body row g-2">
                <input type="hidden" name="id" value="<?= (int)$pessoa['id'] ?>">
                <?php $ro = $podeEditar ? '' : 'disabled'; ?>
                <div class="col-6"><label class="form-label small">Download (Mbps)</label><input name="banda_down_mbps" class="form-control" value="<?= $ex('banda_down_kbps') !== '' ? $ex('banda_down_kbps') / 1000 : '' ?>" placeholder="perfil: <?= wifi_banda($perfil['banda_down_kbps']) ?>" <?= $ro ?>></div>
                <div class="col-6"><label class="form-label small">Upload (Mbps)</label><input name="banda_up_mbps" class="form-control" value="<?= $ex('banda_up_kbps') !== '' ? $ex('banda_up_kbps') / 1000 : '' ?>" placeholder="perfil: <?= wifi_banda($perfil['banda_up_kbps']) ?>" <?= $ro ?>></div>
                <div class="col-6"><label class="form-label small">Sessão (horas)</label><input type="number" min="1" name="sessao_horas" class="form-control" value="<?= $ex('sessao_minutos') !== '' ? $ex('sessao_minutos') / 60 : '' ?>" placeholder="perfil: <?= wifi_duracao($perfil['sessao_minutos']) ?>" <?= $ro ?>></div>
                <div class="col-6"><label class="form-label small">Aparelhos ao mesmo tempo</label><input type="number" min="1" name="max_aparelhos" class="form-control" value="<?= $ex('max_aparelhos') ?>" placeholder="perfil: <?= (int)$perfil['max_aparelhos'] ?>" <?= $ro ?>></div>
                <div class="col-6"><label class="form-label small">Cota por sessão (MB)</label><input type="number" min="1" name="cota_mb" class="form-control" value="<?= $ex('cota_mb') ?>" placeholder="perfil: <?= $perfil['cota_mb'] ?: 'sem cota' ?>" <?= $ro ?>></div>
                <div class="col-6"><label class="form-label small">Lembrar aparelho (dias)</label><input type="number" min="0" name="lembrar_dias" class="form-control" value="<?= $ex('lembrar_dias') ?>" placeholder="perfil: <?= (int)$perfil['lembrar_dias'] ?>" <?= $ro ?>></div>
                <div class="col-12 mt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="filtro_proprio" value="1" id="filtroProprio" <?= isset($excecoes['filtro']) ? 'checked' : '' ?> <?= $ro ?>
                               onchange="document.getElementById('filtroPessoa').classList.toggle('d-none', !this.checked)">
                        <label class="form-check-label" for="filtroProprio">Filtro de sites próprio (substitui o do perfil)</label>
                    </div>
                    <div id="filtroPessoa" class="mt-2 <?= isset($excecoes['filtro']) ? '' : 'd-none' ?>">
                        <?php $filtroAtual = isset($excecoes['filtro']) ? (array)$excecoes['filtro'] : $regras['filtro']; require __DIR__ . '/_filtro_campos.php'; ?>
                    </div>
                </div>
            </div>
            <?php if ($podeEditar): ?><div class="card-footer bg-transparent"><button class="btn btn-primary btn-sm">Salvar regras</button></div><?php endif; ?>
        </form>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Aparelhos</div>
            <div class="card-body p-0">
                <?php if (!$aparelhos): ?><p class="text-muted small p-3 mb-0">Nenhum aparelho usado ainda.</p><?php endif; ?>
                <table class="table table-sm align-middle mb-0">
                <?php foreach ($aparelhos as $a): ?>
                    <tr>
                        <td class="ps-3"><code><?= htmlspecialchars(strtoupper($a['mac'])) ?></code>
                            <?= $a['bloqueado'] ? '<span class="badge text-bg-danger">bloqueado</span>' : '' ?>
                            <?= $a['lembrado_ate'] && strtotime($a['lembrado_ate']) > time() ? '<span class="badge text-bg-light border">lembrado até ' . date('d/m', strtotime($a['lembrado_ate'])) . '</span>' : '' ?></td>
                        <td class="small text-muted">usado <?= date('d/m/Y H:i', strtotime($a['ultimo_uso'])) ?></td>
                        <td class="text-end pe-3">
                            <?php if ($podeLiberar): ?>
                            <form method="post" action="<?= url('/wifi/pessoas/aparelho') ?>">
                                <input type="hidden" name="aparelho_id" value="<?= (int)$a['id'] ?>">
                                <input type="hidden" name="acao" value="<?= $a['bloqueado'] ? 'desbloquear' : 'bloquear' ?>">
                                <button class="btn btn-sm <?= $a['bloqueado'] ? 'btn-outline-success' : 'btn-outline-danger' ?>"><?= $a['bloqueado'] ? 'Desbloquear' : 'Bloquear' ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Termos aceitos</div>
            <div class="card-body p-0">
                <?php if (!$aceites): ?><p class="text-muted small p-3 mb-0">Nenhum aceite registrado.</p><?php endif; ?>
                <table class="table table-sm mb-0 small">
                <?php foreach ($aceites as $a): ?>
                    <tr><td class="ps-3"><?= htmlspecialchars($a['perfil_nome']) ?> v<?= (int)$a['versao'] ?></td><td><?= date('d/m/Y H:i', strtotime($a['aceito_em'])) ?></td><td class="text-muted"><?= htmlspecialchars((string)$a['ip']) ?> <?= htmlspecialchars(strtoupper((string)$a['mac'])) ?></td></tr>
                <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Conexões (últimas 50)</div>
            <div class="card-body p-0">
                <?php if (!$sessoes): ?><p class="text-muted small p-3 mb-0">Nenhuma conexão ainda.</p><?php else: ?>
                <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead><tr><th class="ps-3">Início</th><th>Fim</th><th>Aparelho</th><th>IP</th><th class="text-end">Download</th><th class="text-end">Upload</th><th>Encerramento</th><th></th></tr></thead>
                    <?php foreach ($sessoes as $s): ?>
                    <tr>
                        <td class="ps-3"><?= date('d/m/Y H:i', strtotime($s['inicio'])) ?></td>
                        <td><?= $s['fim'] ? date('d/m H:i', strtotime($s['fim'])) : '<span class="badge text-bg-success">conectado</span>' ?></td>
                        <td><code><?= htmlspecialchars(strtoupper((string)$s['mac'])) ?></code></td>
                        <td><?= htmlspecialchars((string)$s['ip']) ?></td>
                        <td class="text-end"><?= wifi_bytes($s['bytes_down']) ?></td>
                        <td class="text-end"><?= wifi_bytes($s['bytes_up']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars((string)$s['motivo_fim']) ?></td>
                        <td class="text-end pe-3">
                            <?php if ($podeLgpd && $s['ip']): ?><a class="btn btn-sm btn-link" href="<?= url('/wifi/relatorios/sites?ip=' . urlencode($s['ip'])) ?>" title="Sites consultados por este IP">sites</a><?php endif; ?>
                            <?php if ($podeLiberar && !$s['fim']): ?>
                            <form method="post" action="<?= url('/wifi/sessao/derrubar') ?>" class="d-inline"><input type="hidden" name="sessao_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="voltar" value="<?= $voltar ?>">
                                <button class="btn btn-sm btn-outline-danger">Desconectar</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($podeLgpd): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm border-start border-3 border-secondary">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div><strong>LGPD -- direitos do titular</strong><div class="small text-muted">Exportar tudo o que existe sobre a pessoa, ou atender um pedido de exclusão.</div></div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('/wifi/pessoas/exportar?id=' . (int)$pessoa['id']) ?>"><i class="bi bi-download"></i> Exportar dados (JSON)</a>
                    <?php if ($pessoa['status'] !== 'anonimizado'): ?>
                    <form method="post" action="<?= url('/wifi/pessoas/anonimizar') ?>" onsubmit="return confirm('Encerrar o acesso e apagar os dados não obrigatórios desta pessoa? Nome e e-mail ficam só até o fim do prazo legal dos registros de conexão. Não dá para desfazer.')">
                        <input type="hidden" name="id" value="<?= (int)$pessoa['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-eraser"></i> Pedido de exclusão</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - ' . $pessoa['nome'];

require __DIR__ . '/../layouts/main.php';
