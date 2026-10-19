<?php
ob_start();

use App\Components\Alert;
use App\Services\EntraService;

$def = EntraService::TIPOS_PERFIL[$tipo];
$valores = EntraService::valoresDoPerfil($tipo, $perfil);

$wallpaperUrlsPorCampo = [
    'personalizationDesktopImageUrl' => $wallpaperDesktopFileUrl ?? null,
    'personalizationLockScreenImageUrl' => $wallpaperLockscreenFileUrl ?? null,
];
?>

<?= Alert::flash() ?>

<div class="mb-3">
    <h4 class="mb-1"><i class="bi <?= $def['icone'] ?> me-1"></i> <?= $modoEdicao ? 'Editar' : 'Novo' ?> perfil -- <?= htmlspecialchars($def['nome']) ?></h4>
    <small class="text-muted"><a href="<?= url('/entra/perfis-configuracao') ?>"><i class="bi bi-arrow-left"></i> Perfis de Configuração</a></small>
</div>

<form method="post" action="<?= url($modoEdicao ? '/entra/perfis-configuracao/editar' : '/entra/perfis-configuracao/novo') ?>" style="max-width:980px">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <?php if ($modoEdicao): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($perfil['id'] ?? '') ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <p class="text-muted small"><?= htmlspecialchars($def['resumo']) ?></p>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Nome do perfil</label>
                    <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($perfil['displayName'] ?? '') ?>"
                           placeholder="Ex.: RD.Intranet - <?= htmlspecialchars($def['nome']) ?>">
                </div>
                <div class="col-md-7">
                    <label class="form-label">Descrição <span class="text-muted small">(opcional)</span></label>
                    <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($perfil['description'] ?? '') ?>">
                </div>
            </div>
            <div class="form-text mt-2">O que ficar desmarcado ou em branco não é configurado -- o Windows segue como está. Vale para as máquinas no Intune depois de "Aplicar a todos os dispositivos".</div>
        </div>
    </div>

    <?php foreach ($def['secoes'] as $secao => $campos): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong><?= htmlspecialchars($secao) ?></strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($campos as $campo => $info): ?>
                        <?php $id = 'campo-' . $campo; $valor = $valores[$campo] ?? null; ?>
                        <div class="<?= $info['tipo'] === 'bool' || $info['tipo'] === 'textarea' || $info['tipo'] === 'url' ? 'col-12' : 'col-md-6' ?>">
                            <?php if ($info['tipo'] === 'bool'): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="<?= $campo ?>" id="<?= $id ?>" <?= !empty($valor) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $id ?>"><?= htmlspecialchars($info['label']) ?></label>
                                </div>
                            <?php else: ?>
                                <label class="form-label small" for="<?= $id ?>"><?= htmlspecialchars($info['label']) ?></label>
                                <?php if ($info['tipo'] === 'opcao'): ?>
                                    <select name="<?= $campo ?>" id="<?= $id ?>" class="form-select form-select-sm">
                                        <?php foreach ($info['opcoes'] as $chave => $rotulo): ?>
                                            <option value="<?= htmlspecialchars((string)$chave) ?>" <?= (string)$valor === (string)$chave ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($info['tipo'] === 'textarea'): ?>
                                    <textarea name="<?= $campo ?>" id="<?= $id ?>" class="form-control form-control-sm" rows="3"><?= htmlspecialchars((string)$valor) ?></textarea>
                                <?php else: ?>
                                    <div class="d-flex gap-2">
                                        <input type="<?= ['numero' => 'number', 'hora' => 'time', 'url' => 'url'][$info['tipo']] ?? 'text' ?>" name="<?= $campo ?>" id="<?= $id ?>"
                                               class="form-control form-control-sm" value="<?= htmlspecialchars((string)$valor) ?>"
                                               <?= isset($info['min']) ? 'min="' . $info['min'] . '"' : '' ?> <?= isset($info['max']) ? 'max="' . $info['max'] . '"' : '' ?>>
                                        <?php if (!empty($wallpaperUrlsPorCampo[$campo])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap botao-usar-imagem-enviada" data-campo="<?= $id ?>" data-url="<?= htmlspecialchars($wallpaperUrlsPorCampo[$campo]) ?>">
                                                <i class="bi bi-image"></i> Usar imagem enviada
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($info['ajuda'])): ?>
                                <div class="form-text"><?= htmlspecialchars($info['ajuda']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="<?= url('/entra/perfis-configuracao') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
(function () {
    document.querySelectorAll('.botao-usar-imagem-enviada').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const campo = document.getElementById(botao.dataset.campo);
            if (campo) campo.value = botao.dataset.url;
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - ' . ($modoEdicao ? 'Editar' : 'Novo') . ' Perfil de Configuração';

require __DIR__ . '/../layouts/main.php';
