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
                    <label class="form-label small fw-semibold mb-1" for="padrao_alerta_url_portal">Endereço do portal nos alertas</label>
        <input type="url" name="alerta_url_portal" id="padrao_alerta_url_portal" class="form-control form-control-sm mb-1"
               placeholder="https://cliente.ddns.net:83/rd.intranet" value="<?= htmlspecialchars($padraoSeguranca['alerta_url_portal'] ?? '') ?>">
        <div class="form-text small mb-2">Usado nos botões do e-mail. Em branco, usa o endereço pelo qual o agente chega ao servidor (costuma ser o IP interno, que só abre de dentro da rede do cliente).</div>
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
            <div class="border-top mt-4 pt-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div>
                        <label class="form-label small fw-semibold mb-0">Modelo do e-mail de alerta</label>
                        <div class="form-text small mt-0">Todos levam o logo e o nome da empresa (Administração &rsaquo; Empresa), identificam o servidor e trazem os links da máquina e da Central.</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoAlertaTeste">
                        <i class="bi bi-envelope"></i> Enviar e-mail de teste
                    </button>
                </div>
                <div class="row g-3">
                    <?php foreach (\App\Services\SegurancaAlertaService::MODELOS as $modelo => [$rotuloModelo, $descricaoModelo]): ?>
                        <div class="col-xl-4">
                            <label class="card h-100 border <?= ($padraoSeguranca['alerta_modelo'] ?? 'sistema') === $modelo ? 'border-primary border-2' : '' ?>" style="cursor:pointer">
                                <div class="card-body p-2">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input js-modelo-alerta" type="radio" name="alerta_modelo" value="<?= $modelo ?>" id="modelo_<?= $modelo ?>"
                                               <?= ($padraoSeguranca['alerta_modelo'] ?? 'sistema') === $modelo ? 'checked' : '' ?>>
                                        <span class="form-check-label small"><strong><?= htmlspecialchars($rotuloModelo) ?></strong>
                                            <span class="text-muted d-block"><?= htmlspecialchars($descricaoModelo) ?></span></span>
                                    </div>
                                    <iframe title="Prévia do modelo <?= htmlspecialchars($rotuloModelo) ?>" srcdoc="<?= htmlspecialchars($previasAlerta[$modelo] ?? '') ?>"
                                            style="width:100%;height:420px;border:1px solid #e5e7eb;border-radius:6px;pointer-events:none" loading="lazy" sandbox></iframe>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-sm btn-primary">Salvar padrão</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // Destaca o cartão escolhido sem precisar salvar pra ver.
    document.querySelectorAll('.js-modelo-alerta').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.js-modelo-alerta').forEach(function (r) {
                r.closest('label.card').classList.toggle('border-primary', r.checked);
                r.closest('label.card').classList.toggle('border-2', r.checked);
            });
        });
    });

    const botao = document.getElementById('botaoAlertaTeste');
    botao.addEventListener('click', async function () {
        const escolhido = document.querySelector('.js-modelo-alerta:checked');
        botao.disabled = true;
        try {
            const res = await fetch(<?= json_encode(url('/ativos/seguranca/alerta-teste')) ?>, {
                method: 'POST',
                body: new URLSearchParams({ modelo: escolhido ? escolhido.value : 'sistema' })
            });
            const dados = await res.json();
            alert(dados.message || (dados.success ? 'Enviado.' : 'Falha ao enviar.'));
        } catch (e) {
            alert('Falha de comunicação com o servidor.');
        } finally {
            botao.disabled = false;
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Central de Segurança - Configuração';

require __DIR__ . '/../layouts/main.php';
