<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\SegurancaAssinaturaService;
use App\Services\SegurancaEventoService;

$totalFp = (int)($totaisResolucao['falso_positivo'] ?? 0);
$totalConfirmado = (int)($totaisResolucao['ataque_confirmado'] ?? 0);
$totalGeral = array_sum(array_map('intval', $totaisResolucao));
$taxaFp = $totalGeral > 0 ? round($totalFp * 100 / $totalGeral) : 0;
?>

<?= Alert::flash() ?>

<div class="mb-3">
    <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Central de Segurança</h4>
    <small class="text-muted">Módulo anti-ransomware de todas as máquinas.</small>
</div>

<ul class="nav nav-pills mb-4">
    <li class="nav-item"><a class="nav-link" href="<?= url('/ativos/seguranca') ?>"><i class="bi bi-speedometer2"></i> Visão geral</a></li>
    <li class="nav-item"><a class="nav-link active" href="<?= url('/ativos/seguranca/excecoes') ?>"><i class="bi bi-funnel"></i> Exceções e falsos positivos</a></li>
    <li class="nav-item"><a class="nav-link" href="<?= url('/ativos/seguranca/configuracao') ?>"><i class="bi bi-gear"></i> Configuração</a></li>
</ul>

<div class="row g-3 mb-4">
    <?php
        $cartoes = [
            ['Detecções em 90 dias', $totalGeral, 'bi-activity', 'primary'],
            ['Marcadas como falso positivo', $totalFp . ($totalGeral ? " ({$taxaFp}%)" : ''), 'bi-funnel', $totalFp ? 'warning' : 'secondary'],
            ['Ataques confirmados', $totalConfirmado, 'bi-bug', $totalConfirmado ? 'danger' : 'secondary'],
            ['Ainda sem análise', (int)($totaisResolucao['aberto'] ?? 0), 'bi-hourglass-split', ($totaisResolucao['aberto'] ?? 0) ? 'danger' : 'secondary'],
        ];
    ?>
    <?php foreach ($cartoes as [$rotulo, $valor, $icone, $cor]): ?>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1"><i class="bi <?= $icone ?> text-<?= $cor ?>"></i> <?= htmlspecialchars($rotulo) ?></div>
                    <div class="fs-4 fw-semibold"><?= htmlspecialchars((string)$valor) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <strong><i class="bi bi-funnel"></i> Onde os detectores erraram</strong>
                <small class="text-muted ms-1">falsos positivos dos últimos 90 dias</small>
            </div>
            <div class="card-body p-0">
                <?php if (!$resumoFalsosPositivos): ?>
                    <p class="text-muted small p-3 mb-0">
                        Nenhum evento marcado como falso positivo. Quando um alerta não for ataque, use o botão
                        <strong>Falso positivo</strong> no evento (aqui na Central ou na aba Segurança da máquina) e conte a causa --
                        é isso que mostra onde criar uma exceção.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Detector</th><th>Máquina</th><th class="text-end">Vezes</th><th>Último</th></tr></thead>
                            <tbody>
                                <?php foreach ($resumoFalsosPositivos as $linha): ?>
                                    <tr>
                                        <td class="small"><?= htmlspecialchars(SegurancaEventoService::rotuloTipo($linha['tipo'])) ?></td>
                                        <td class="small">
                                            <a href="<?= url('/ativos/ver?id=' . (int)$linha['ativo_id'] . '#abaSeguranca') ?>"><?= htmlspecialchars($linha['codigo_patrimonio']) ?></a>
                                            <span class="text-muted"><?= htmlspecialchars($linha['ativo_nome']) ?></span>
                                        </td>
                                        <td class="small text-end fw-semibold"><?= (int)$linha['total'] ?></td>
                                        <td class="small text-nowrap"><?= htmlspecialchars(data_br($linha['ultimo'], 'd/m/Y H:i')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($falsosPositivos): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><strong><i class="bi bi-clock-history"></i> Falsos positivos recentes</strong></div>
                <div class="card-body p-0">
                    <?php
                        $eventosTabela = $falsosPositivos;
                        $mostrarMaquina = true;
                        $podeEditarTabela = false;
                        require __DIR__ . '/_eventos_seguranca.php';
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <strong><i class="bi bi-check2-circle"></i> Causas conhecidas que o agente já trata sozinho</strong>
                <small class="text-muted ms-1">agente 1.0.35 ou mais novo</small>
            </div>
            <div class="card-body small">
                <p class="text-muted">Levantadas em documentação da Microsoft, fabricantes e incidentes reais destes clientes. Não precisam de exceção.</p>
                <strong>Shadow copies</strong>
                <ul>
                    <li>Windows apagando cópias por limite de espaço (Volsnap 33) ou descartando todas (Volsnap 25, 32, 35, 36) -- caso da IMUNOQUIMICA em 27/09.</li>
                    <li>Pontos de restauração expirados: o Windows 11 24H2 apaga sozinho os que têm mais de 60 dias.</li>
                    <li>Cópias temporárias de backup e ferramentas de fabricante (ex.: Dell SupportAssist) criadas e apagadas em menos de 2 horas -- caso da CL-TRIAGEM em 26/09.</li>
                </ul>
                <strong>Mudança em massa</strong>
                <ul>
                    <li>Office salvando: grava num <code>.tmp</code> e renomeia pra <code>.docx</code>/<code>.xlsx</code>. Download do navegador: <code>.crdownload</code> → <code>.pdf</code>. Troca pra extensão comum não conta.</li>
                    <li>Sincronização (OneDrive, Google Drive, Dropbox), troca de branch no git, restauração de backup: alteram muitos arquivos, mas o conteúdo continua válido. Crítico só quando a amostra mostra arquivos corrompidos (cabeçalho trocado ou conteúdo embaralhado) ou extensões suspeitas.</li>
                    <li>Pastas de desenvolvimento e cache (<code>.git</code>, <code>node_modules</code>, <code>bin</code>/<code>obj</code>, <code>__pycache__</code>, <code>.venv</code>) e temporários de Office, LibreOffice e sincronizadores.</li>
                </ul>
                <strong>Arquivos-isca</strong>
                <ul class="mb-0">
                    <li>Antivírus, indexador e backup só leem -- conta apenas mudança real de conteúdo.</li>
                    <li>Documentos sincronizados pelo OneDrive em mais de um computador: cada máquina usa iscas com o próprio nome e ignora as das outras.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong><i class="bi bi-sliders"></i> Exceções</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/ativos/seguranca/excecoes') ?>">
                    <label class="form-label small fw-semibold mb-1" for="fim_pastas_ignoradas">Pastas ignoradas pela mudança em massa</label>
                    <textarea name="fim_pastas_ignoradas" id="fim_pastas_ignoradas" rows="4" class="form-control form-control-sm font-monospace" <?= $podeEditar ? '' : 'disabled' ?>
                              placeholder="\Documents\Exportacoes&#10;\Desktop\Scanner"><?= htmlspecialchars($padraoSeguranca['fim_pastas_ignoradas']) ?></textarea>
                    <div class="form-text small mb-3">Uma por linha. Vale qualquer caminho que contenha o trecho -- ex.: <code>\Documents\Exportacoes</code> pega essa pasta em todos os usuários. Use pra pastas que um sistema reescreve inteiras de propósito.</div>

                    <label class="form-label small fw-semibold mb-1" for="fim_extensoes_ignoradas">Extensões ignoradas pela mudança em massa</label>
                    <textarea name="fim_extensoes_ignoradas" id="fim_extensoes_ignoradas" rows="2" class="form-control form-control-sm font-monospace" <?= $podeEditar ? '' : 'disabled' ?>
                              placeholder=".dcm&#10;.hl7"><?= htmlspecialchars($padraoSeguranca['fim_extensoes_ignoradas']) ?></textarea>
                    <div class="form-text small mb-3">Uma por linha. Ex.: arquivos de equipamento que o sistema do laboratório gera e regrava em lote.</div>

                    <label class="form-label small fw-semibold mb-1">Janela de manutenção</label>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <input type="time" name="janela_inicio" class="form-control form-control-sm" style="width:120px" value="<?= htmlspecialchars($padraoSeguranca['janela_inicio']) ?>" <?= $podeEditar ? '' : 'disabled' ?> aria-label="Início">
                        <span class="small">até</span>
                        <input type="time" name="janela_fim" class="form-control form-control-sm" style="width:120px" value="<?= htmlspecialchars($padraoSeguranca['janela_fim']) ?>" <?= $podeEditar ? '' : 'disabled' ?> aria-label="Fim">
                    </div>
                    <div class="form-text small mb-3">
                        Horário de backup ou atualização em massa. Dentro dele, shadow copy e mudança em massa só <strong>alertam</strong>, nunca isolam.
                        Arquivo-isca continua isolando. Deixe em branco pra desligar. Pode virar a meia-noite (22:00 até 05:00).
                    </div>

                    <?php if ($podeEditar): ?>
                        <button class="btn btn-sm btn-primary">Salvar exceções</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-cloud-download"></i> Lista pública de extensões de ransomware</strong>
                <?= Badge::make($assinaturas['ativo'] ? 'Ligada' : 'Desligada', $assinaturas['ativo'] ? 'success' : 'secondary') ?>
            </div>
            <div class="card-body small">
                <p>
                    Reforço opcional pra mudança em massa: renomear vários arquivos pra uma extensão conhecida de ransomware,
                    ou criar a mesma nota de resgate em pastas diferentes, vira crítico mesmo com poucos arquivos.
                </p>
                <p class="text-muted">
                    Fonte: <a href="<?= htmlspecialchars(SegurancaAssinaturaService::FONTE_PAGINA) ?>" target="_blank" rel="noopener">dannyroemhild/ransomware-fileext-list</a>
                    (comunidade, atualizada toda semana, licença GPL-3.0 -- baixada em uso, não copiada pro sistema).
                    Padrões genéricos demais (<code>.lock</code>, <code>.tmp</code>, só números) são descartados. Ligada, o servidor renova no máximo 1x por dia.
                </p>
                <dl class="row mb-3">
                    <dt class="col-5 fw-normal text-muted">Extensões em uso</dt><dd class="col-7 mb-1"><?= number_format($assinaturas['extensoes'], 0, ',', '.') ?></dd>
                    <dt class="col-5 fw-normal text-muted">Notas de resgate</dt><dd class="col-7 mb-1"><?= number_format($assinaturas['notas'], 0, ',', '.') ?></dd>
                    <dt class="col-5 fw-normal text-muted">Padrões na fonte</dt><dd class="col-7 mb-1"><?= number_format($assinaturas['total_fonte'], 0, ',', '.') ?></dd>
                    <dt class="col-5 fw-normal text-muted">Última tentativa</dt><dd class="col-7 mb-1"><?= $assinaturas['atualizado_em'] ? htmlspecialchars(date('d/m/Y H:i', $assinaturas['atualizado_em'])) : 'nunca' ?></dd>
                </dl>
                <?php if ($assinaturas['mensagem'] !== ''): ?>
                    <p class="text-muted"><?= htmlspecialchars($assinaturas['mensagem']) ?></p>
                <?php endif; ?>
                <?php if ($podeEditar): ?>
                    <form method="post" action="<?= url('/ativos/seguranca/assinaturas') ?>" class="d-flex flex-wrap gap-2">
                        <?php if ($assinaturas['ativo']): ?>
                            <button name="acao" value="desligar" class="btn btn-sm btn-outline-secondary">Desligar</button>
                            <button name="acao" value="atualizar" class="btn btn-sm btn-outline-primary">Atualizar agora</button>
                        <?php else: ?>
                            <button name="acao" value="ligar" class="btn btn-sm btn-primary">Ligar</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Central de Segurança - Exceções';

require __DIR__ . '/../layouts/main.php';
