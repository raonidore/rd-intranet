<?php
ob_start();

use App\Components\Alert;
use App\Services\EntraService;

$valores = [];
foreach (EntraService::CAMPOS_RESTRICOES as $campo => $info) {
    $valores[$campo] = $perfil[$campo] ?? null;
}

$wallpaperUrlsPorCampo = [
    'personalizationDesktopImageUrl' => $wallpaperDesktopFileUrl ?? null,
    'personalizationLockScreenImageUrl' => $wallpaperLockscreenFileUrl ?? null,
];
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-sliders me-1"></i> <?= $modoEdicao ? 'Editar' : 'Novo' ?> Perfil de Configuração</h4>
    <small class="text-muted"><a href="<?= url('/entra/perfis-configuracao') ?>"><i class="bi bi-arrow-left"></i> Perfis de Configuração</a></small>
</div>

<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-body">
        <form method="post" action="<?= url($modoEdicao ? '/entra/perfis-configuracao/editar' : '/entra/perfis-configuracao/novo') ?>">
            <?php if ($modoEdicao): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($perfil['id'] ?? '') ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Nome do perfil</label>
                <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($perfil['displayName'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Descrição (opcional)</label>
                <textarea name="descricao" class="form-control" rows="2"><?= htmlspecialchars($perfil['description'] ?? '') ?></textarea>
            </div>

            <hr>
            <p class="text-muted small">Deixe em branco/desmarcado o que não quiser configurar por esse perfil.</p>

            <?php foreach (EntraService::CAMPOS_RESTRICOES as $campo => $info): ?>
                <div class="mb-3">
                    <?php if ($info['tipo'] === 'bool'): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="<?= htmlspecialchars($campo) ?>" id="campo-<?= htmlspecialchars($campo) ?>" <?= !empty($valores[$campo]) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="campo-<?= htmlspecialchars($campo) ?>"><?= htmlspecialchars($info['label']) ?></label>
                        </div>
                    <?php else: ?>
                        <label class="form-label" for="campo-<?= htmlspecialchars($campo) ?>"><?= htmlspecialchars($info['label']) ?></label>
                        <div class="d-flex gap-2">
                            <input type="<?= $info['tipo'] === 'numero' ? 'number' : 'text' ?>" name="<?= htmlspecialchars($campo) ?>" id="campo-<?= htmlspecialchars($campo) ?>" class="form-control"
                                   value="<?= htmlspecialchars((string)($valores[$campo] ?? '')) ?>">
                            <?php if (!empty($wallpaperUrlsPorCampo[$campo])): ?>
                                <button type="button" class="btn btn-outline-secondary text-nowrap botao-usar-imagem-enviada" data-campo="campo-<?= htmlspecialchars($campo) ?>" data-url="<?= htmlspecialchars($wallpaperUrlsPorCampo[$campo]) ?>">
                                    <i class="bi bi-image"></i> Usar imagem enviada
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if (array_key_exists($campo, $wallpaperUrlsPorCampo) && empty($wallpaperUrlsPorCampo[$campo])): ?>
                            <div class="form-text">Nenhuma imagem enviada ainda pra esse campo -- veja "Imagens de papel de parede" na listagem.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            <a href="<?= url('/entra/perfis-configuracao') ?>" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.botao-usar-imagem-enviada').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const campo = document.getElementById(botao.dataset.campo);
            if (campo) {
                campo.value = botao.dataset.url;
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - ' . ($modoEdicao ? 'Editar' : 'Novo') . ' Perfil de Configuração';

require __DIR__ . '/../layouts/main.php';
