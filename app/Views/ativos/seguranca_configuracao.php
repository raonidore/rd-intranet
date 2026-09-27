<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-3">
    <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Central de Segurança</h4>
    <small class="text-muted">Módulo anti-ransomware de todas as máquinas.</small>
</div>

<ul class="nav nav-pills mb-4">
    <li class="nav-item"><a class="nav-link" href="<?= url('/ativos/seguranca') ?>"><i class="bi bi-speedometer2"></i> Visão geral</a></li>
    <li class="nav-item"><a class="nav-link" href="<?= url('/ativos/seguranca/excecoes') ?>"><i class="bi bi-funnel"></i> Exceções e falsos positivos</a></li>
    <li class="nav-item"><a class="nav-link active" href="<?= url('/ativos/seguranca/configuracao') ?>"><i class="bi bi-gear"></i> Configuração</a></li>
</ul>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <p class="text-muted small mb-3">
            <strong>Padrão para todas as máquinas com agente Windows</strong> (1.0.30 ou mais novo; os ajustes contra falso positivo exigem 1.0.35). Cada máquina pode
            sobrescrever isso na aba <em>Segurança</em> da própria ficha -- ex: deixar um servidor de produção só em
            alerta, sem isolamento automático. As máquinas aplicam a mudança no próximo check-in, sem reinstalar nada.
        </p>
        <form method="post" action="<?= url('/ativos/seguranca/padrao') ?>">
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label small fw-semibold mb-1">Detecções ligadas por padrão</label>
                    <?php
                        $descricoesModulos = [
                            'canary' => 'Cria 2 arquivos-isca ocultos em Documentos, Área de Trabalho e Documentos Públicos. Alterar, apagar ou renomear um deles = alerta crítico. Custo desprezível.',
            'shadow_copy' => 'Conta as shadow copies (pontos de restauração) a cada minuto. Crítico só quando somem 2 ou mais que o próprio Windows não explica: descontadas as limpezas registradas pelo Windows (Volsnap), as cópias temporárias de backup (menos de 2h) e os pontos expirados (mais de 55 dias). Não identifica o processo nem bloqueia nada.',
                            'fim' => 'Conta alterações de arquivo nas pastas do usuário; dispara quando passa do limiar abaixo. Arquivo novo não conta. Pode gerar aviso em sincronização grande (OneDrive, backup).',
                        ];
                    ?>
                    <?php foreach (\App\Services\SegurancaModuloService::MODULOS as $chave => $rotulo): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="<?= $chave ?>" value="1" id="padrao_<?= $chave ?>" <?= !empty($padraoSeguranca[$chave]) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="padrao_<?= $chave ?>">
                                <strong><?= htmlspecialchars($rotulo) ?></strong><br>
                                <span class="text-muted"><?= htmlspecialchars($descricoesModulos[$chave]) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-lg-6">
                    <label class="form-label small fw-semibold mb-1" for="padrao_isolamento_modo">Resposta a evento crítico</label>
                    <select name="isolamento_modo" id="padrao_isolamento_modo" class="form-select form-select-sm mb-2">
                        <?php foreach (\App\Services\SegurancaModuloService::MODOS_ISOLAMENTO as $modo => $rotulo): ?>
                            <option value="<?= $modo ?>" <?= $padraoSeguranca['isolamento_modo'] === $modo ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <label class="small text-muted mb-0" for="padrao_confirmacao_minutos">Prazo do modo confirmação (min)</label>
                        <input type="number" name="confirmacao_minutos" id="padrao_confirmacao_minutos" class="form-control form-control-sm" style="width:80px" min="1" max="60" value="<?= (int)$padraoSeguranca['confirmacao_minutos'] ?>">
                    </div>

                    <label class="form-label small fw-semibold mb-1">Limiar do FIM</label>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 small">
                        <input type="number" name="fim_limiar_critico" aria-label="Arquivos para crítico" class="form-control form-control-sm" style="width:80px" min="5" value="<?= (int)$padraoSeguranca['fim_limiar_critico'] ?>"> arquivos = crítico,
                        <input type="number" name="fim_limiar_aviso" aria-label="Arquivos para aviso" class="form-control form-control-sm" style="width:80px" min="1" value="<?= (int)$padraoSeguranca['fim_limiar_aviso'] ?>"> = aviso, em
                        <input type="number" name="fim_janela_segundos" aria-label="Janela em segundos" class="form-control form-control-sm" style="width:80px" min="5" max="300" value="<?= (int)$padraoSeguranca['fim_janela_segundos'] ?>"> segundos
                    </div>

                    <label class="form-label small fw-semibold mb-1" for="padrao_alerta_emails">Avisar por e-mail</label>
                    <input type="text" name="alerta_emails" id="padrao_alerta_emails" class="form-control form-control-sm mb-2" placeholder="ti@empresa.com.br, outro@empresa.com.br" value="<?= htmlspecialchars($padraoSeguranca['alerta_emails']) ?>">
                    <label class="form-label small fw-semibold mb-1" for="padrao_alerta_whatsapp">Avisar por WhatsApp</label>
                    <input type="text" name="alerta_whatsapp" id="padrao_alerta_whatsapp" class="form-control form-control-sm mb-3" placeholder="5581999999999, 5581988888888" value="<?= htmlspecialchars($padraoSeguranca['alerta_whatsapp']) ?>">

                    <label class="form-label small fw-semibold mb-1" for="padrao_isolamento_liberados">Continuam com rede durante o isolamento</label>
                    <textarea name="isolamento_liberados" id="padrao_isolamento_liberados" class="form-control form-control-sm font-monospace" rows="3"
                              placeholder="C:\Program Files\Sistema\cliente.exe&#10;NomeDoServico"><?= htmlspecialchars($padraoSeguranca['isolamento_liberados'] ?? '') ?></textarea>
                    <div class="form-text small">
                        O agente RD Intranet e o MeshAgent (MeshCentral) sempre ficam liberados, pra dar acesso remoto à máquina isolada.
                        Aqui vão extras: um caminho de .exe ou nome de serviço do Windows por linha. Cada item liberado continua acessando a rede
                        numa máquina possivelmente comprometida -- evite AnyDesk/TeamViewer, que atacantes também usam.
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-sm btn-primary">Salvar padrão</button>
            </div>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Central de Segurança - Configuração';

require __DIR__ . '/../layouts/main.php';
