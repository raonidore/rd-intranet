<?php
/**
 * Campos do filtro de sites (perfil, exceção da pessoa e filtro global).
 * @var array $filtroAtual  ['categorias','servicos','bloquear','liberar']
 * @var array $servicos     id => nome (do AdGuard)
 */
use App\Services\WifiFiltroService;

$filtroAtual = array_merge(['categorias' => [], 'servicos' => [], 'bloquear' => [], 'liberar' => []], $filtroAtual ?? []);
$idCampo = 'f' . substr(md5(uniqid('', true)), 0, 6);
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Bloquear categorias</label>
        <?php foreach (WifiFiltroService::CATEGORIAS as $k => $rot): ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="filtro_categorias[]" value="<?= $k ?>" id="<?= $idCampo . $k ?>" <?= in_array($k, $filtroAtual['categorias'], true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= $idCampo . $k ?>"><?= $rot ?></label>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Bloquear serviços</label>
        <select name="filtro_servicos[]" class="form-select" multiple size="7">
            <?php foreach ($servicos as $id => $nome): ?>
            <option value="<?= htmlspecialchars($id) ?>" <?= in_array($id, $filtroAtual['servicos'], true) ? 'selected' : '' ?>><?= htmlspecialchars($nome) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Ctrl/Cmd + clique para marcar vários (redes sociais, streaming, jogos...).</div>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Sempre bloquear estes sites</label>
        <textarea name="filtro_bloquear" class="form-control font-monospace small" rows="3" placeholder="exemplo.com.br (um por linha; vale para os subdomínios)"><?= htmlspecialchars(implode("\n", $filtroAtual['bloquear'])) ?></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Sempre liberar estes sites</label>
        <textarea name="filtro_liberar" class="form-control font-monospace small" rows="3" placeholder="sistema.empresa.com.br (vence qualquer bloqueio)"><?= htmlspecialchars(implode("\n", $filtroAtual['liberar'])) ?></textarea>
    </div>
</div>
