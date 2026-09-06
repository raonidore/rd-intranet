<?php
$mensagem = $_SESSION['flash_msg'] ?? null;
$tipoMensagem = $_SESSION['flash_tipo'] ?? 'error';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

$colunaLabels = [
    'a_fazer' => 'A fazer',
    'em_andamento' => 'Em andamento',
    'aguardando_terceiro' => 'Aguardando terceiro',
    'concluido' => 'Concluído',
];
$colunaCor = ['a_fazer' => 'secondary', 'em_andamento' => 'primary', 'aguardando_terceiro' => 'warning', 'concluido' => 'success'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Minhas Tarefas</title>
    <link rel="icon" href="<?= url('/favicon.ico') ?>" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php require __DIR__ . '/_topo.php'; ?>

<div class="portal-container">
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <?php if (empty($tarefas)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-kanban" style="font-size:2rem;"></i>
                <p class="mb-0 mt-2">Nenhuma tarefa atribuída a você ainda.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($tarefas as $tarefa): ?>
            <a href="<?= url('/projetos/portal/tarefa?id=' . (int)$tarefa['id']) ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm mb-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div style="min-width:0">
                            <strong><?= htmlspecialchars($tarefa['titulo']) ?></strong>
                            <div class="text-muted small"><?= htmlspecialchars($tarefa['projeto_titulo']) ?></div>
                        </div>
                        <div class="text-end">
                            <span class="badge text-bg-<?= $colunaCor[$tarefa['coluna']] ?? 'secondary' ?>"><?= $colunaLabels[$tarefa['coluna']] ?? $tarefa['coluna'] ?></span>
                            <?php if ($tarefa['prazo']): ?>
                                <div class="text-muted small mt-1"><?= date('d/m/Y', strtotime($tarefa['prazo'])) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
