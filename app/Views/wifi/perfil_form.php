<?php
ob_start();

use App\Components\Alert;
use App\Services\WifiPerfilService;

$p = $perfil ?? [];
$v = fn ($k, $padrao = '') => htmlspecialchars((string)($p[$k] ?? $padrao));
$campos = json_decode((string)($p['campos'] ?? ''), true) ?: [];
$camposPorChave = [];
$extras = [];
foreach ($campos as $c) {
    if (isset(WifiPerfilService::CAMPOS_CONHECIDOS[$c['campo']])) {
        $camposPorChave[$c['campo']] = $c;
    } else {
        $extras[] = $c['rotulo'] . (!empty($c['obrigatorio']) ? ' *' : '');
    }
}
$h = json_decode((string)($p['horarios'] ?? ''), true) ?: [];
$filtroAtual = json_decode((string)($p['filtro'] ?? ''), true) ?: [];
$sessao = (int)($p['sessao_minutos'] ?? 480);
$mbps = fn ($k) => !empty($p[$k]) ? rtrim(rtrim(number_format($p[$k] / 1000, 1, '.', ''), '0'), '.') : '';
$chk = fn ($k, $padrao = 0) => !empty($p ? ($p[$k] ?? 0) : $padrao) ? 'checked' : '';
?>

<?= Alert::flash() ?>

<div class="mb-3">
    <a href="<?= url('/wifi/perfis') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Perfis</a>
    <h4 class="mb-1 mt-1"><?= $p ? 'Perfil: ' . $v('nome') : 'Novo perfil' ?></h4>
</div>

<form method="post" action="<?= url('/wifi/perfis/salvar') ?>">
<input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent fw-semibold">Identificação e acesso</div><div class="card-body row g-2">
            <div class="col-8"><label class="form-label small">Nome</label><input name="nome" class="form-control" value="<?= $v('nome') ?>" required maxlength="60"></div>
            <div class="col-2"><label class="form-label small">Cor</label><input type="color" name="cor" class="form-control form-control-color w-100" value="<?= $v('cor', '#0d7b83') ?>"></div>
            <div class="col-2"><label class="form-label small">Ordem</label><input type="number" name="ordem" class="form-control" value="<?= $v('ordem', '10') ?>"></div>
            <div class="col-8"><label class="form-label small">Descrição (aparece no portal)</label><input name="descricao" class="form-control" value="<?= $v('descricao') ?>" maxlength="160"></div>
            <div class="col-4"><label class="form-label small">Ícone</label><input name="icone" class="form-control" value="<?= $v('icone', 'bi-person') ?>" placeholder="bi-person"></div>
            <div class="col-12 mt-3">
                <label class="form-label small fw-semibold">Como a pessoa entra</label>
                <div class="form-check"><input class="form-check-input" type="radio" name="tipo_acesso" value="senha" id="taSenha" <?= ($p['tipo_acesso'] ?? 'senha') === 'senha' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="taSenha">Login (e-mail) e senha -- a senha chega por e-mail, com "esqueci a senha"</label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="tipo_acesso" value="cadastro" id="taCad" <?= ($p['tipo_acesso'] ?? '') === 'cadastro' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="taCad">Cadastro rápido no portal, sem senha (visitante)</label></div>
            </div>
            <div class="col-12">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="autocadastro" value="1" id="autocad" <?= $chk('autocadastro', 1) ?>><label class="form-check-label" for="autocad">A pessoa pode se cadastrar pelo portal</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="exige_liberacao" value="1" id="exlib" <?= $chk('exige_liberacao') ?>><label class="form-check-label" for="exlib">Cadastro pelo portal precisa ser liberado por alguém (Wi-Fi &gt; Liberações)</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativo" <?= $chk('ativo', 1) ?>><label class="form-check-label" for="ativo">Perfil ativo</label></div>
            </div>
            <div class="col-12"><label class="form-label small">Só aceitar e-mails destes domínios <small class="text-muted">(vazio = qualquer)</small></label>
                <input name="dominios_email" class="form-control" value="<?= $v('dominios_email') ?>" placeholder="empresa.com.br, empresa.com"></div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent fw-semibold">Tempo, velocidade e aparelhos</div><div class="card-body row g-2">
            <div class="col-6"><label class="form-label small">Cadastro vale (dias) <small class="text-muted">vazio = sem vencimento</small></label><input type="number" min="1" name="validade_dias" class="form-control" value="<?= $v('validade_dias') ?>"></div>
            <div class="col-3"><label class="form-label small">Sessão (h)</label><input type="number" min="0" name="sessao_horas" class="form-control" value="<?= intdiv($sessao, 60) ?>"></div>
            <div class="col-3"><label class="form-label small">+ min</label><input type="number" min="0" max="59" name="sessao_min" class="form-control" value="<?= $sessao % 60 ?>"></div>
            <div class="col-12"><div class="form-text mt-0 mb-2">Ao fim da sessão a pessoa entra de novo pelo portal (ou automaticamente, se o aparelho estiver lembrado).</div></div>
            <div class="col-6"><label class="form-label small">Download (Mbps)</label><input name="banda_down_mbps" class="form-control" value="<?= $mbps('banda_down_kbps') ?>" placeholder="sem limite"></div>
            <div class="col-6"><label class="form-label small">Upload (Mbps)</label><input name="banda_up_mbps" class="form-control" value="<?= $mbps('banda_up_kbps') ?>" placeholder="sem limite"></div>
            <div class="col-4"><label class="form-label small">Cota por sessão (MB)</label><input type="number" min="1" name="cota_mb" class="form-control" value="<?= $v('cota_mb') ?>" placeholder="sem cota"></div>
            <div class="col-4"><label class="form-label small">Aparelhos ao mesmo tempo</label><input type="number" min="1" name="max_aparelhos" class="form-control" value="<?= $v('max_aparelhos', '1') ?>"></div>
            <div class="col-4"><label class="form-label small">Lembrar aparelho (dias)</label><input type="number" min="0" name="lembrar_dias" class="form-control" value="<?= $v('lembrar_dias', '0') ?>"></div>
            <div class="col-12 mt-3">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="horario_ativo" value="1" id="horAtivo" <?= !empty($h['dias']) ? 'checked' : '' ?>><label class="form-check-label" for="horAtivo">Restringir dias e horário de uso</label></div>
                <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
                    <?php foreach (WifiPerfilService::DIAS as $n => $rot): ?>
                    <input type="checkbox" class="btn-check" name="horario_dias[]" value="<?= $n ?>" id="dia<?= $n ?>" <?= in_array($n, array_map('intval', $h['dias'] ?? [1, 2, 3, 4, 5]), true) ? 'checked' : '' ?>>
                    <label class="btn btn-sm btn-outline-secondary" for="dia<?= $n ?>"><?= $rot ?></label>
                    <?php endforeach; ?>
                    <input type="time" name="horario_inicio" class="form-control form-control-sm" style="width:auto" value="<?= htmlspecialchars($h['inicio'] ?? '07:00') ?>">
                    <span class="small">às</span>
                    <input type="time" name="horario_fim" class="form-control form-control-sm" style="width:auto" value="<?= htmlspecialchars($h['fim'] ?? '19:00') ?>">
                </div>
            </div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent fw-semibold">Campos do cadastro <small class="text-muted fw-normal">(nome e e-mail sempre)</small></div><div class="card-body">
            <table class="table table-sm align-middle mb-2">
                <thead><tr><th>Pedir</th><th>Campo</th><th>Rótulo no portal</th><th class="text-center">Obrigatório</th></tr></thead>
                <?php foreach (WifiPerfilService::CAMPOS_CONHECIDOS as $campo => $rotulo): $c = $camposPorChave[$campo] ?? null; ?>
                <tr>
                    <td><input class="form-check-input" type="checkbox" name="campo[<?= $campo ?>][usar]" value="1" <?= $c ? 'checked' : '' ?>></td>
                    <td class="small text-muted"><?= $rotulo ?></td>
                    <td><input class="form-control form-control-sm" name="campo[<?= $campo ?>][rotulo]" value="<?= htmlspecialchars($c['rotulo'] ?? $rotulo) ?>"></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" name="campo[<?= $campo ?>][obrigatorio]" value="1" <?= !empty($c['obrigatorio']) ? 'checked' : '' ?>></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <label class="form-label small">Outros campos <small class="text-muted">(um por linha; termine com * para obrigatório)</small></label>
            <textarea name="campos_extras" class="form-control" rows="3" placeholder="Placa do veículo&#10;Motivo da visita *"><?= htmlspecialchars(implode("\n", $extras)) ?></textarea>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent fw-semibold">Termos de uso <?= $termo ? '<small class="text-muted fw-normal">(versão ' . (int)$termo['versao'] . ')</small>' : '' ?></div><div class="card-body">
            <textarea name="termo" class="form-control small" rows="12"><?= htmlspecialchars($termo['texto'] ?? '') ?></textarea>
            <div class="form-text">Ao alterar o texto, nasce uma versão nova e cada pessoa aceita de novo no próximo acesso. Os aceites antigos ficam guardados.</div>
        </div></div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Filtro de sites deste perfil</div><div class="card-body">
            <?php if (!$filtroAtivo): ?><div class="alert alert-warning small py-2">O filtro de sites ainda não está ativo (Wi-Fi &gt; Configurações &gt; Filtro). As regras ficam salvas e passam a valer quando ele for ligado.</div><?php endif; ?>
            <?php require __DIR__ . '/_filtro_campos.php'; ?>
        </div></div>
    </div>
</div>

<div class="d-flex gap-2 mt-3 mb-4">
    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar perfil</button>
    <a href="<?= url('/wifi/perfis') ?>" class="btn btn-outline-secondary">Cancelar</a>
</div>
</form>

<?php if ($p): ?>
<form method="post" action="<?= url('/wifi/perfis/excluir') ?>" onsubmit="return confirm('Excluir este perfil?')" class="mb-4">
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <button class="btn btn-link text-danger p-0 small">Excluir perfil (só se não tiver cadastros)</button>
</form>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - Perfil';

require __DIR__ . '/../layouts/main.php';
