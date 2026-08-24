<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\ChamadoService;

$corPrioridade = ['baixa' => 'secondary', 'media' => 'primary', 'alta' => 'warning', 'urgente' => 'danger'];
$corStatus = ['fila' => 'secondary', 'em_atendimento' => 'primary', 'aguardando_cliente' => 'warning', 'resolvido' => 'success', 'fechado' => 'dark'];
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <small class="text-muted"><a href="<?= url('/chamados/atendimentos') ?>"><i class="bi bi-arrow-left"></i> Chamados</a></small>
        <h4 class="mb-1 mt-1">
            <span class="font-monospace text-muted">#<?= (int)$chamado['id'] ?></span>
            <?= htmlspecialchars($chamado['titulo']) ?>
        </h4>
        <?= Badge::make(htmlspecialchars(ChamadoService::STATUS[$chamado['status']]), $corStatus[$chamado['status']] ?? 'secondary') ?>
        <?= Badge::make(htmlspecialchars(ChamadoService::PRIORIDADES[$chamado['prioridade']]), $corPrioridade[$chamado['prioridade']] ?? 'secondary') ?>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($chamado['status'] === 'fila'): ?>
            <form method="post" action="<?= url('/chamados/fila/assumir') ?>">
                <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-hand-index-thumb"></i> Assumir</button>
            </form>
        <?php endif; ?>

        <?php if (!in_array($chamado['status'], ['resolvido', 'fechado'], true)): ?>
            <form method="post" action="<?= url('/chamados/atendimentos/status') ?>">
                <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                <input type="hidden" name="status" value="aguardando_cliente">
                <button type="submit" class="btn btn-outline-warning" <?= $chamado['status'] === 'aguardando_cliente' ? 'disabled' : '' ?>>
                    <i class="bi bi-hourglass-split"></i> Aguardando cliente
                </button>
            </form>
            <form method="post" action="<?= url('/chamados/atendimentos/status') ?>">
                <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                <input type="hidden" name="status" value="resolvido">
                <button type="submit" class="btn btn-outline-success"><i class="bi bi-check2-circle"></i> Marcar resolvido</button>
            </form>
        <?php endif; ?>

        <?php if ($chamado['status'] === 'resolvido'): ?>
            <form method="post" action="<?= url('/chamados/atendimentos/status') ?>">
                <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                <input type="hidden" name="status" value="fechado">
                <button type="submit" class="btn btn-dark"><i class="bi bi-lock"></i> Fechar</button>
            </form>
        <?php endif; ?>

        <?php if (in_array($chamado['status'], ['resolvido', 'fechado'], true)): ?>
            <form method="post" action="<?= url('/chamados/atendimentos/status') ?>">
                <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                <input type="hidden" name="status" value="em_atendimento">
                <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reabrir</button>
            </form>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCriarArtigoKb">
                <i class="bi bi-journal-plus"></i> Criar artigo da Base de Conhecimento
            </button>
        <?php endif; ?>
    </div>
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
                    <p class="text-muted small mb-0">Nenhuma resposta ainda.</p>
                <?php else: ?>
                    <?php foreach ($comentarios as $c): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between">
                                <span>
                                    <?php if ($c['tipo'] === 'interna'): ?>
                                        <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle">Nota interna</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-light border">Resposta pública</span>
                                    <?php endif; ?>
                                    <strong class="ms-1"><?= htmlspecialchars($c['usuario_nome'] ?? 'Sistema') ?></strong>
                                </span>
                                <small class="text-muted"><?= data_br($c['criado_em'], 'd/m/Y H:i') ?></small>
                            </div>
                            <div class="mt-1" style="white-space:pre-wrap; <?= $c['tipo'] === 'interna' ? 'background:#fdf3d6;border:1px dashed #e6cf82;border-radius:8px;padding:8px 12px' : '' ?>">
                                <?= htmlspecialchars($c['conteudo']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!$somenteLeitura): ?>
                    <form method="post" action="<?= url('/chamados/atendimentos/responder') ?>" class="mt-3">
                        <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                        <textarea name="conteudo" class="form-control mb-2" rows="3" required placeholder="Escreva sua resposta..."></textarea>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input type="checkbox" name="tipo" value="interna" class="form-check-input" id="campoNotaInterna">
                                <label class="form-check-label small" for="campoNotaInterna">Nota interna (o solicitante nunca vê isso)</label>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0 mt-3">Chamado encerrado -- reabra pra continuar a conversa.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Solicitante</strong></div>
            <div class="card-body small">
                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">Nome</span><span class="fw-semibold"><?= htmlspecialchars($chamado['solicitante_nome']) ?></span></div>
                <?php if ($chamado['solicitante_email']): ?>
                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">E-mail</span><span class="fw-semibold"><?= htmlspecialchars($chamado['solicitante_email']) ?></span></div>
                <?php endif; ?>
                <?php if ($chamado['solicitante_telefone']): ?>
                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">Telefone</span><span class="fw-semibold"><?= htmlspecialchars(telefone_br($chamado['solicitante_telefone'])) ?></span></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Unidade</span><span class="fw-semibold"><?= htmlspecialchars($chamado['unidade_nome']) ?></span></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Anexos</strong></div>
            <div class="card-body small">
                <?php if (empty($anexos)): ?>
                    <p class="text-muted mb-2">Nenhum anexo ainda.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-2">
                        <?php foreach ($anexos as $anexo): ?>
                            <li class="mb-1">
                                <a href="<?= url('/chamados/atendimentos/anexo?id=' . (int)$anexo['id']) ?>" target="_blank">
                                    <i class="bi bi-paperclip"></i> <?= htmlspecialchars($anexo['nome_original']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!$somenteLeitura): ?>
                    <form method="post" action="<?= url('/chamados/atendimentos/anexo') ?>" enctype="multipart/form-data" class="d-flex gap-2">
                        <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                        <input type="file" name="arquivo" class="form-control form-control-sm" required>
                        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($chamado['ativo_id']): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Ativo vinculado</strong></div>
            <div class="card-body small">
                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">Código</span><span class="fw-semibold font-monospace"><?= htmlspecialchars($chamado['ativo_codigo']) ?></span></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Nome</span><span class="fw-semibold"><?= htmlspecialchars($chamado['ativo_nome']) ?></span></div>
                <a href="<?= url('/ativos/ver?id=' . (int)$chamado['ativo_id']) ?>" class="small" target="_blank">Ver ficha completa →</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Prazo</strong></div>
            <div class="card-body small">
                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">Categoria</span><span class="fw-semibold"><?= htmlspecialchars($chamado['categoria_nome']) ?></span></div>
                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">Setor</span><span class="fw-semibold"><?= htmlspecialchars($chamado['setor_nome'] ?? '—') ?></span></div>
                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">Atendente</span><span class="fw-semibold"><?= htmlspecialchars($chamado['usuario_nome'] ?? '— na fila —') ?></span></div>
                <?php if ($chamado['sla_resolucao_prazo']): ?>
                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">1ª resposta</span><span class="fw-semibold"><?= $chamado['primeira_resposta_em'] ? data_br($chamado['primeira_resposta_em'], 'd/m H:i') : data_br($chamado['sla_resposta_prazo'], 'd/m H:i') . ' (prazo)' ?></span></div>
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">SLA resolução</span><span class="fw-semibold"><?= data_br($chamado['sla_resolucao_prazo'], 'd/m H:i') ?></span></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between py-1 pt-2 border-top mt-1"><span class="text-muted">Aberto em</span><span class="fw-semibold"><?= data_br($chamado['aberto_em'], 'd/m/Y H:i') ?></span></div>
            </div>
        </div>
    </div>
</div>

<?php if (in_array($chamado['status'], ['resolvido', 'fechado'], true)): ?>
<?php
    $ultimaPublica = '';
    foreach (array_reverse($comentarios) as $c) {
        if ($c['tipo'] === 'publica') { $ultimaPublica = $c['conteudo']; break; }
    }
?>
<div class="modal fade" id="modalCriarArtigoKb" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= url('/chamados/atendimentos/kb-criar') ?>">
                <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-journal-plus"></i> Criar artigo da Base de Conhecimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">O problema (descrição do chamado) já entra preenchido; revise a solução antes de salvar -- vira um artigo privado desta instalação.</p>
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($chamado['titulo']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Solução</label>
                        <textarea name="solucao" class="form-control" rows="6" required><?= htmlspecialchars($ultimaPublica) ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Criar artigo</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chamado #' . (int)$chamado['id'];

require __DIR__ . '/../layouts/main.php';
