<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;

$corStatus = [
    'bot' => 'secondary',
    'fila' => 'warning',
    'em_atendimento' => 'primary',
    'aguardando_nps_atendente' => 'info',
    'aguardando_nps_resolucao' => 'info',
    'encerrado' => 'dark',
];
$rotuloStatus = [
    'bot' => 'Com o bot',
    'fila' => 'Na fila',
    'em_atendimento' => 'Em atendimento',
    'aguardando_nps_atendente' => 'Aguardando avaliação',
    'aguardando_nps_resolucao' => 'Aguardando avaliação',
    'encerrado' => 'Encerrado',
];

$totalPaginas = max(1, (int)ceil($total / $porPagina));
$inicio = $total > 0 ? (($pagina - 1) * $porPagina) + 1 : 0;
$fim = min($total, $pagina * $porPagina);

function urlPaginaContatos(string $busca, int $pagina): string
{
    $params = ['pagina' => $pagina];
    if ($busca !== '') {
        $params['busca'] = $busca;
    }

    return url('/whatsapp/contatos') . '?' . http_build_query($params);
}
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-lines-fill me-1"></i> WhatsApp - Contatos</h4>
        <small class="text-muted">Quem já falou com a empresa -- <?= $total ?> registro<?= $total === 1 ? '' : 's' ?>.</small>
    </div>
    <form method="get" action="<?= url('/whatsapp/contatos') ?>" class="d-flex gap-2">
        <input type="text" name="busca" class="form-control form-control-sm" placeholder="Nome ou telefone..." value="<?= htmlspecialchars($busca) ?>" style="min-width:220px">
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        <?php if ($busca !== ''): ?>
            <a href="<?= url('/whatsapp/contatos') ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($contatos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-person" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2"><?= $busca !== '' ? 'Nenhum contato encontrado pra essa busca.' : 'Nenhum contato ainda -- aparecem aqui assim que alguém mandar mensagem.' ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0" style="overflow-x:auto">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Atendente responsável</th>
                        <th>Status</th>
                        <th>Contato desde</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contatos as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nome'] ?: '(sem nome)') ?></td>
                            <td><?= htmlspecialchars(telefone_br($c['numero'])) ?></td>
                            <td><?= $c['atendente_responsavel'] ? htmlspecialchars($c['atendente_responsavel']) : '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <?php if ($c['status_atual']): ?>
                                    <?= Badge::make(htmlspecialchars($rotuloStatus[$c['status_atual']] ?? $c['status_atual']), $corStatus[$c['status_atual']] ?? 'secondary') ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= data_br($c['criado_em']) ?></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= url('/whatsapp/contatos/historico?id=' . (int)$c['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Ver histórico">
                                    <i class="bi bi-chat-square-text"></i>
                                </a>
                                <form method="post" action="<?= url('/whatsapp/contatos/reabrir') ?>" class="d-inline">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Reabrir atendimento">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                                <form method="post" action="<?= url('/whatsapp/contatos/excluir') ?>" class="d-inline"
                                      onsubmit="return confirm('Excluir <?= htmlspecialchars(addslashes($c['nome'] ?: $c['numero'])) ?>? <?= (int)$c['total_atendimentos'] ?> atendimento(s) e <?= (int)$c['total_mensagens'] ?> mensagem(ns) vão junto, sem volta.');">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir contato">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
        <small class="text-muted">Mostrando <?= $inicio ?>–<?= $fim ?> de <?= $total ?></small>
        <div class="btn-group">
            <a href="<?= urlPaginaContatos($busca, max(1, $pagina - 1)) ?>" class="btn btn-sm btn-outline-secondary <?= $pagina <= 1 ? 'disabled' : '' ?>">
                <i class="bi bi-chevron-left"></i> Anterior
            </a>
            <a href="<?= urlPaginaContatos($busca, min($totalPaginas, $pagina + 1)) ?>" class="btn btn-sm btn-outline-secondary <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                Próxima <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Contatos';

require __DIR__ . '/../layouts/main.php';
