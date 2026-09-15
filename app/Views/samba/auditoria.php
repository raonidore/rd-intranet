<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;

$rotuloAcao = ['renomeado' => 'Renomeado/Movido', 'excluido' => 'Excluído', 'gravado' => 'Conteúdo gravado'];
$corAcao = ['renomeado' => 'primary', 'excluido' => 'danger', 'gravado' => 'success'];
$iconeAcao = ['renomeado' => 'bi-arrow-left-right', 'excluido' => 'bi-trash3', 'gravado' => 'bi-pencil-square'];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-eye me-1"></i> Auditoria de Arquivos</h4>
        <small class="text-muted">Quem renomeou, excluiu ou gravou conteúdo nos compartilhamentos Samba.</small>
    </div>
    <?php if ($ativa): ?>
        <form method="post" action="<?= url('/samba/auditoria/desativar') ?>" onsubmit="return confirm('Desativar a auditoria de arquivos? Nenhum registro novo será gravado até ligar de novo -- o histórico já coletado continua salvo.');">
            <button type="submit" class="btn btn-outline-danger">Desativar auditoria</button>
        </form>
    <?php else: ?>
        <form method="post" action="<?= url('/samba/auditoria/ativar') ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill"></i> Ativar auditoria</button>
        </form>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <strong><i class="bi bi-question-circle"></i> Como funciona</strong>
        <ul class="small text-muted mb-0 mt-2 ps-3">
            <li>Registra, por usuário e por compartilhamento: <strong>renomear/mover</strong>, <strong>excluir</strong> e <strong>gravar conteúdo</strong> (criar ou modificar um arquivo).</li>
            <li>
                Como a <a href="<?= url('/samba/lixeira') ?>">Lixeira Administrativa</a> fica sempre ativa, uma
                exclusão de usuário nunca some de verdade na hora -- ela move o arquivo pra dentro de
                <code>.recycle/</code>, e é esse movimento que aparece aqui como "Excluído".
            </li>
            <li>Não registra leitura/abertura de arquivo (só o que muda algo), pra não lotar a tela com ruído.</li>
            <li>O histórico completo fica gravado em <code>/var/log/samba/audit.log</code> no servidor (rotacionado automaticamente); esta tela mostra as últimas 5.000 entradas.</li>
        </ul>
    </div>
</div>

<?php if (!$ativa): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-eye-slash" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Auditoria desativada -- clique em "Ativar auditoria" acima para começar a registrar.</p>
        </div>
    </div>
<?php else: ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Usuário</label>
                    <input type="text" name="usuario" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['usuario']) ?>" placeholder="login do usuário">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Compartilhamento</label>
                    <select name="compartilhamento" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($compartilhamentos as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $filtros['compartilhamento'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Ação</label>
                    <select name="acao" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($rotuloAcao as $valor => $label): ?>
                            <option value="<?= $valor ?>" <?= $filtros['acao'] === $valor ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Arquivo (busca)</label>
                    <input type="text" name="busca" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['busca']) ?>" placeholder="parte do nome/caminho">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($registros)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size:2rem;"></i>
                    <p class="mb-0 mt-2">Nenhum registro encontrado<?= array_filter($filtros) ? ' com esses filtros' : ' ainda' ?>.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Usuário</th>
                                <th>Máquina</th>
                                <th>Compartilhamento</th>
                                <th>Ação</th>
                                <th>Arquivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $r): ?>
                                <tr>
                                    <td class="small text-nowrap"><?= htmlspecialchars(data_br($r['data_hora'], 'd/m/Y H:i:s')) ?></td>
                                    <td class="small"><?= htmlspecialchars($r['usuario']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($r['maquina']) ?></td>
                                    <td class="small"><?= htmlspecialchars($r['compartilhamento']) ?></td>
                                    <td>
                                        <?= Badge::make('<i class="bi ' . $iconeAcao[$r['acao']] . '"></i> ' . $rotuloAcao[$r['acao']], $corAcao[$r['acao']]) ?>
                                    </td>
                                    <td class="small font-monospace" style="word-break:break-all">
                                        <?= htmlspecialchars($r['arquivo']) ?>
                                        <?php if ($r['arquivo_destino']): ?>
                                            <i class="bi bi-arrow-right text-muted mx-1"></i><?= htmlspecialchars($r['arquivo_destino']) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Auditoria de Arquivos';

require __DIR__ . '/../layouts/main.php';
