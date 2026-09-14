<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\ChamadoService;

$corPrioridade = ['baixa' => 'secondary', 'media' => 'primary', 'alta' => 'warning', 'urgente' => 'danger'];
$corStatus = ['fila' => 'secondary', 'em_atendimento' => 'primary', 'aguardando_cliente' => 'warning', 'resolvido' => 'success', 'fechado' => 'dark'];
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <small class="text-muted"><a href="<?= url('/chamados/meus') ?>"><i class="bi bi-arrow-left"></i> Meus Chamados</a></small>
    <h4 class="mb-1 mt-1">
        <span class="font-monospace text-muted">#<?= htmlspecialchars($chamado['numero_controle'] ?? $chamado['id']) ?></span>
        <?= htmlspecialchars($chamado['titulo']) ?>
    </h4>
    <?= Badge::make(htmlspecialchars(ChamadoService::STATUS[$chamado['status']]), $corStatus[$chamado['status']] ?? 'secondary') ?>
    <?= Badge::make(htmlspecialchars(ChamadoService::PRIORIDADES[$chamado['prioridade']]), $corPrioridade[$chamado['prioridade']] ?? 'secondary') ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="text-muted small mb-1">Descrição</div>
                <div style="white-space:pre-wrap"><?= htmlspecialchars($chamado['descricao']) ?></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Conversa</strong></div>
            <div class="card-body">
                <?php if (empty($comentarios)): ?>
                    <p class="text-muted small mb-0">Nenhuma resposta ainda -- assim que a equipe responder, aparece aqui.</p>
                <?php else: ?>
                    <?php foreach ($comentarios as $c): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between">
                                <strong><?= $c['usuario_nome'] ? htmlspecialchars($c['usuario_nome']) . ' (equipe)' : 'Você' ?></strong>
                                <small class="text-muted"><?= data_br($c['criado_em'], 'd/m/Y H:i') ?></small>
                            </div>
                            <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($c['conteudo']) ?></div>
                            <?php foreach ($anexos as $anexo): if ((int)($anexo['comentario_id'] ?? 0) !== (int)$c['id']) continue; ?>
                                <div class="mt-1">
                                    <a href="<?= url('/chamados/meus/anexo?id=' . (int)$anexo['id']) ?>" target="_blank" class="small">
                                        <i class="bi bi-paperclip"></i> <?= htmlspecialchars($anexo['nome_original']) ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!$somenteLeitura): ?>
                    <form method="post" action="<?= url('/chamados/meus/responder') ?>" enctype="multipart/form-data" class="mt-3">
                        <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                        <textarea name="conteudo" class="form-control mb-2" rows="3" required placeholder="Escreva sua resposta..."></textarea>
                        <input type="file" name="arquivo" class="form-control form-control-sm mb-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0 mt-3">Esse chamado já foi encerrado.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Detalhes</strong></div>
            <div class="card-body small">
                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">Categoria</span><span class="fw-semibold"><?= htmlspecialchars($chamado['categoria_nome']) ?></span></div>
                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">Unidade</span><span class="fw-semibold"><?= htmlspecialchars($chamado['unidade_nome']) ?></span></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Aberto em</span><span class="fw-semibold"><?= data_br($chamado['aberto_em'], 'd/m/Y H:i') ?></span></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Anexos</strong></div>
            <div class="card-body small">
                <?php $anexosAvulsos = array_filter($anexos, fn (array $a) => $a['comentario_id'] === null); ?>
                <?php if (empty($anexosAvulsos)): ?>
                    <p class="text-muted mb-2"><?= empty($anexos) ? 'Nenhum anexo ainda.' : 'Nenhum anexo avulso -- veja os anexados junto com respostas na conversa.' ?></p>
                <?php else: ?>
                    <ul class="list-unstyled mb-2">
                        <?php foreach ($anexosAvulsos as $anexo): ?>
                            <li class="mb-1">
                                <a href="<?= url('/chamados/meus/anexo?id=' . (int)$anexo['id']) ?>" target="_blank">
                                    <i class="bi bi-paperclip"></i> <?= htmlspecialchars($anexo['nome_original']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!$somenteLeitura): ?>
                    <form method="post" action="<?= url('/chamados/meus/anexo') ?>" enctype="multipart/form-data" class="d-flex gap-2">
                        <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                        <input type="file" name="arquivo" class="form-control form-control-sm" required>
                        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chamado #' . ($chamado['numero_controle'] ?? $chamado['id']);

require __DIR__ . '/../layouts/main.php';
