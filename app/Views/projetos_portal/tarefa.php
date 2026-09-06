<?php
$mensagem = $_SESSION['flash_msg'] ?? null;
$tipoMensagem = $_SESSION['flash_tipo'] ?? 'error';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($tarefa['titulo']) ?></title>
    <link rel="icon" href="<?= url('/favicon.ico') ?>" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php require __DIR__ . '/_topo.php'; ?>

<div class="portal-container">
    <small class="text-muted"><a href="<?= url('/projetos/portal') ?>"><i class="bi bi-arrow-left"></i> Minhas tarefas</a></small>

    <div class="mb-3 mt-1">
        <h4 class="mb-1"><?= htmlspecialchars($tarefa['titulo']) ?></h4>
        <span class="text-muted small"><?= htmlspecialchars($tarefa['projeto_titulo']) ?></span>
        <?php if ($tarefa['prazo']): ?>
            <span class="text-muted small ms-2"><i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($tarefa['prazo'])) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <?php if (!empty($tarefa['descricao'])): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div style="white-space:pre-wrap"><?= htmlspecialchars($tarefa['descricao']) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($anexos)): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="card-title mb-2">Anexos</h6>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($anexos as $a): ?>
                        <li class="small d-flex justify-content-between">
                            <span><?= htmlspecialchars($a['anexo_nome_original']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Conversa</strong></div>
        <div class="card-body">
            <?php if (empty($timeline)): ?>
                <p class="text-muted small mb-0">Nenhuma mensagem ainda.</p>
            <?php else: ?>
                <?php foreach ($timeline as $c): ?>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between">
                            <strong><?= $c['usuario_nome'] ? htmlspecialchars($c['usuario_nome']) . ' (equipe)' : htmlspecialchars($c['participante_nome'] ?? 'Você') ?></strong>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></small>
                        </div>
                        <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($c['conteudo']) ?></div>
                        <?php if (!empty($c['latitude'])): ?>
                            <a class="small" target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= $c['latitude'] ?>,<?= $c['longitude'] ?>">
                                <i class="bi bi-geo-alt"></i> Ver no mapa
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="post" action="<?= url('/projetos/portal/comentar') ?>" enctype="multipart/form-data" class="mt-3">
                <input type="hidden" name="tarefa_id" value="<?= (int)$tarefa['id'] ?>">
                <input type="hidden" name="latitude" id="comentarioLatitude">
                <input type="hidden" name="longitude" id="comentarioLongitude">
                <textarea name="conteudo" class="form-control mb-2" rows="3" required placeholder="Escreva sua atualização..."></textarea>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex gap-2 align-items-center">
                        <label class="btn btn-outline-secondary btn-sm mb-0">
                            <i class="bi bi-camera"></i> Foto
                            <input type="file" name="arquivo" accept="image/*,application/pdf" capture="environment" class="d-none" id="comentarioArquivo">
                        </label>
                        <span class="small text-muted" id="comentarioArquivoNome"></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLocalizacao">
                            <i class="bi bi-geo-alt"></i> Localização
                        </button>
                        <span class="small text-success d-none" id="localizacaoOk"><i class="bi bi-check-circle"></i> Anexada</span>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('comentarioArquivo').addEventListener('change', function () {
    document.getElementById('comentarioArquivoNome').textContent = this.files[0] ? this.files[0].name : '';
});
document.getElementById('btnLocalizacao').addEventListener('click', function () {
    if (!navigator.geolocation) { alert('Seu navegador não suporta localização.'); return; }
    navigator.geolocation.getCurrentPosition(function (pos) {
        document.getElementById('comentarioLatitude').value = pos.coords.latitude;
        document.getElementById('comentarioLongitude').value = pos.coords.longitude;
        document.getElementById('localizacaoOk').classList.remove('d-none');
    }, function () {
        alert('Não foi possível obter sua localização.');
    });
});
</script>

</body>
</html>
