<?php
/**
 * Paleta de cor do cartão (Nova tarefa e janela da tarefa). Espera
 * $corAtual (chave pronta, "#rrggbb" ou vazio), $estiloAtual ('lateral' |
 * 'inteiro') e $coresPersonalizadas (cores livres já usadas no projeto).
 */
use App\Services\ProjetoTarefaService;

$corAtual = (string)($corAtual ?? '');
$estiloAtual = ($estiloAtual ?? 'lateral') === 'inteiro' ? 'inteiro' : 'lateral';
$livres = $coresPersonalizadas ?? [];
if (str_starts_with($corAtual, '#') && !in_array(strtolower($corAtual), $livres, true)) {
    array_unshift($livres, strtolower($corAtual));
}
$idPaleta = 'paleta' . bin2hex(random_bytes(4));
?>
<div class="paleta-cor">
    <input type="hidden" name="cor" class="campo-cor-tarefa" value="<?= htmlspecialchars($corAtual) ?>">
    <div class="cor-swatches">
        <span class="cor-swatch cor-nenhuma <?= $corAtual === '' ? 'selecionada' : '' ?>" data-cor="" title="Sem cor"></span>
        <?php foreach (ProjetoTarefaService::CORES as $corChave => $corHex): ?>
            <span class="cor-swatch <?= $corAtual === $corChave ? 'selecionada' : '' ?>" data-cor="<?= $corChave ?>" style="background:<?= $corHex ?>" title="<?= ucfirst($corChave) ?>"></span>
        <?php endforeach; ?>
        <?php foreach ($livres as $hex): ?>
            <span class="cor-swatch <?= strtolower($corAtual) === $hex ? 'selecionada' : '' ?>" data-cor="<?= htmlspecialchars($hex) ?>" style="background:<?= htmlspecialchars($hex) ?>" title="Cor personalizada <?= htmlspecialchars($hex) ?>"></span>
        <?php endforeach; ?>
        <label class="cor-swatch cor-livre mb-0" title="Escolher outra cor">
            <i class="bi bi-plus-lg"></i>
            <input type="color" class="seletor-cor-livre" value="<?= str_starts_with($corAtual, '#') ? htmlspecialchars($corAtual) : '#4f8fe8' ?>">
        </label>
    </div>
    <div class="btn-group btn-group-sm mt-2" role="group" aria-label="Como pintar o cartão">
        <?php foreach (ProjetoTarefaService::ESTILOS_COR as $valor => $rotulo): ?>
            <input type="radio" class="btn-check" name="cor_estilo" value="<?= $valor ?>" id="<?= $idPaleta . $valor ?>" <?= $estiloAtual === $valor ? 'checked' : '' ?>>
            <label class="btn btn-outline-secondary" for="<?= $idPaleta . $valor ?>">
                <i class="bi <?= $valor === 'lateral' ? 'bi-layout-sidebar' : 'bi-square-fill' ?>"></i> <?= $rotulo ?>
            </label>
        <?php endforeach; ?>
    </div>
</div>
