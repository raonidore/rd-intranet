<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;

$rotuloAcao = ['renomeado' => 'Renomeado/Movido', 'excluido' => 'Excluído', 'gravado' => 'Conteúdo gravado', 'pasta_criada' => 'Pasta criada', 'arquivo_criado' => 'Arquivo criado'];
$corAcao = ['renomeado' => 'primary', 'excluido' => 'danger', 'gravado' => 'success', 'pasta_criada' => 'info', 'arquivo_criado' => 'success'];
$iconeAcao = ['renomeado' => 'bi-arrow-left-right', 'excluido' => 'bi-trash3', 'gravado' => 'bi-pencil-square', 'pasta_criada' => 'bi-folder-plus', 'arquivo_criado' => 'bi-file-earmark-plus'];
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
            <li>Registra, por usuário e por compartilhamento: <strong>criar pasta</strong>, <strong>criar arquivo</strong>, <strong>renomear/mover</strong>, <strong>excluir</strong> e <strong>gravar conteúdo</strong> (modificar um arquivo já existente, inclusive copiar de outro lugar da rede).</li>
            <li>Copiar arquivos pela rede (Explorer, "Copiar e Colar") costuma usar um atalho do próprio Windows/Samba que não informa o nome do arquivo -- aparece como "Conteúdo gravado" com uma observação em vez do caminho.</li>
            <li>
                Como a <a href="<?= url('/samba/lixeira') ?>">Lixeira Administrativa</a> fica sempre ativa, uma
                exclusão de usuário nunca some de verdade na hora -- ela move o arquivo pra dentro de
                <code>.recycle/</code>, e é esse movimento que aparece aqui como "Excluído".
            </li>
            <li>Não registra leitura/abertura de arquivo (só o que muda algo), pra não lotar a tela com ruído.</li>
            <li>O histórico completo fica gravado em <code>/var/log/samba/audit.log</code> no servidor (rotacionado conforme a retenção configurada abaixo); esta tela mostra as últimas 5.000 entradas.</li>
            <li>Isso é um arquivo <strong>diferente</strong> do <code>log file</code> (<code>/var/log/samba/%m.log</code>) que aparece em <a href="<?= url('/samba/configuracao') ?>">Samba &gt; Configuração</a> -- aquele é o log geral de conexão/protocolo do Samba (um arquivo por máquina que conecta), não registra quem apagou ou renomeou um arquivo. <strong>Pra investigar quem apagou/moveu/gravou um arquivo, o arquivo certo é o desta tela.</strong></li>
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
            <strong><i class="bi bi-clock-history"></i> Retenção do histórico</strong>
            <p class="small text-muted mt-1 mb-3">Por quanto tempo o <code>audit.log</code> completo (não só as 5.000 entradas mostradas abaixo) fica guardado no servidor antes de ser descartado. Um arquivo apagado/renomeado só pode ser rastreado enquanto o dia dele ainda estiver dentro desse prazo.</p>
            <?php if (!$retencao || empty($retencao['success'])): ?>
                <div class="text-danger small"><i class="bi bi-exclamation-triangle"></i> Não foi possível ler a configuração de retenção no servidor<?= !empty($retencao['message']) ? ': ' . htmlspecialchars($retencao['message']) : '.' ?></div>
            <?php else: ?>
                <?php
                    $tamanhoMb = round(($retencao['tamanho_bytes'] ?? 0) / 1048576, 1);
                    $maisAntiga = $retencao['data_mais_antiga'] ?? '';
                ?>
                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <div class="text-muted small">Guardando há</div>
                        <div class="fw-semibold"><?= (int) ($retencao['dias'] ?? 0) ?> dias</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Espaço ocupado hoje</div>
                        <div class="fw-semibold"><?= number_format($tamanhoMb, 1, ',', '.') ?> MB</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Registro mais antigo em disco</div>
                        <div class="fw-semibold"><?= $maisAntiga ? htmlspecialchars(data_br($maisAntiga, 'd/m/Y')) : '<span class="text-muted">ainda não rotacionou</span>' ?></div>
                    </div>
                </div>
                <form method="post" action="<?= url('/samba/auditoria/retencao') ?>" class="d-flex align-items-end gap-2">
                    <div>
                        <label class="form-label small text-muted mb-1">Guardar por quantos dias</label>
                        <input type="number" name="dias" min="1" max="3650" class="form-control form-control-sm" style="width:110px" value="<?= (int) ($retencao['dias'] ?? 30) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm">Salvar retenção</button>
                </form>
                <p class="small text-muted mt-2 mb-0">Referência: <?= number_format($tamanhoMb, 1, ',', '.') ?> MB acumulados em <?= (int) ($retencao['dias'] ?? 0) ?> dias -- use essa média pra estimar quanto espaço um prazo maior vai ocupar (ex: dobrar os dias tende a dobrar o espaço).</p>
            <?php endif; ?>
        </div>
    </div>

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
