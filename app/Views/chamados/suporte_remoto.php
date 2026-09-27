<?php
ob_start();

use App\Components\Alert;

$onlineCadastradas = count(array_filter($ativos, fn ($a) => (int)$a['online'] === 1));
$semCadastroOnline = count(array_filter($semCadastro, fn ($d) => $d['online']));
$esperaTexto = static function (int $minutos): string {
    if ($minutos < 1) return 'agora';
    if ($minutos < 60) return "há {$minutos} min";
    $horas = intdiv($minutos, 60);
    return $horas < 24 ? "há {$horas} h" : 'há ' . intdiv($horas, 24) . ' dia(s)';
};
?>

<?= Alert::flash() ?>

<style>
    .sr-cabecalho { background: linear-gradient(135deg, #111827 0%, #1e3a8a 100%); border-radius: 16px; color: #fff; }
    .sr-icone { width: 56px; height: 56px; border-radius: 14px; background: rgba(255,255,255,.12); display: flex; align-items: center; justify-content: center; font-size: 28px; }
    .sr-metrica { background: rgba(255,255,255,.08); border-radius: 12px; padding: 10px 16px; min-width: 130px; }
    .sr-metrica .valor { font-size: 22px; font-weight: 700; line-height: 1.1; }
    .sr-cartao { border: 1px solid #e5e7eb; border-radius: 14px; background: #fff; transition: box-shadow .15s, transform .15s; }
    .sr-cartao:hover { box-shadow: 0 6px 20px rgba(17,24,39,.08); transform: translateY(-1px); }
    .sr-pedido { border-left: 4px solid #f59e0b; }
    .sr-ponto { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
    .sr-ponto.on { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.18); }
    .sr-ponto.off { background: #9ca3af; }
    .sr-maquina-icone { width: 40px; height: 40px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .sr-btn-conectar { border-radius: 10px; font-weight: 600; }
    .sr-titulo-secao { font-size: 13px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; color: #6b7280; }
    .sr-progresso-mesh { min-width: 100%; }
</style>

<div class="sr-cabecalho p-4 mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="sr-icone"><i class="bi bi-headset"></i></div>
        <div class="me-auto">
            <h4 class="mb-1 fw-bold">Suporte Remoto</h4>
            <div class="small text-white-50">Quem pediu ajuda pelo agente e conexão rápida a qualquer máquina no MeshCentral.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <div class="sr-metrica"><div class="small text-white-50">Pedindo ajuda</div><div class="valor <?= $pedidos ? 'text-warning' : '' ?>"><?= count($pedidos) ?></div></div>
            <div class="sr-metrica"><div class="small text-white-50">Máquinas online</div><div class="valor"><?= $onlineCadastradas ?><span class="fs-6 text-white-50"> / <?= count($ativos) ?></span></div></div>
            <div class="sr-metrica"><div class="small text-white-50">Sem cadastro</div><div class="valor"><?= $semCadastroOnline ?><span class="fs-6 text-white-50"> / <?= count($semCadastro) ?></span></div></div>
        </div>
    </div>
</div>

<div class="sr-titulo-secao mb-2"><i class="bi bi-life-preserver"></i> Pedindo ajuda agora</div>
<?php if (!$pedidos): ?>
    <div class="sr-cartao p-4 mb-4 text-center text-muted small">
        <i class="bi bi-check2-circle fs-3 d-block mb-1 text-success"></i>
        Nenhum pedido aberto. O usuário pede ajuda pelo botão <strong>Pedir ajuda</strong> do agente RD Intranet (ícone perto do relógio).
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($pedidos as $p): ?>
            <?php $online = $p['ultimo_heartbeat'] && strtotime($p['ultimo_heartbeat']) > time() - 180; ?>
            <div class="col-md-6 col-xl-4">
                <div class="sr-cartao sr-pedido p-3 h-100 d-flex flex-column">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <div class="sr-maquina-icone" style="background:#fffbeb;color:#d97706"><i class="bi bi-person-raised-hand"></i></div>
                        <div class="me-auto">
                            <div class="fw-semibold"><?= htmlspecialchars($p['usuario'] ?: 'Usuário') ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($p['codigo_patrimonio']) ?> &middot; <?= htmlspecialchars($p['ativo_nome']) ?><?= $p['ip'] ? ' &middot; ' . htmlspecialchars($p['ip']) : '' ?></div>
                        </div>
                        <span class="badge rounded-pill text-bg-warning"><?= $esperaTexto((int)$p['minutos_esperando']) ?></span>
                    </div>
                    <div class="small bg-light rounded-3 p-2 mb-3 flex-grow-1" style="white-space:pre-line"><?= $p['mensagem'] ? htmlspecialchars($p['mensagem']) : '<span class="text-muted">Sem descrição.</span>' ?></div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php if (!empty($p['mesh_device_id'])): ?>
                            <button type="button" class="btn btn-primary btn-sm sr-btn-conectar js-conectar" data-ativo="<?= (int)$p['ativo_id'] ?>" data-nome="<?= htmlspecialchars($p['ativo_nome']) ?>">
                                <i class="bi bi-display"></i> Conectar
                            </button>
                        <?php elseif ($meshAgentDisponivel): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm sr-btn-conectar js-instalar-mesh" data-pedido="<?= (int)$p['id'] ?>">
                                <i class="bi bi-cloud-download"></i> Instalar acesso remoto
                            </button>
                            <div class="sr-progresso-mesh d-none" data-mesh-progresso aria-live="polite">
                                <div class="small text-muted mb-1" data-mesh-mensagem></div>
                                <div class="progress" style="height:8px"><div class="progress-bar progress-bar-striped progress-bar-animated" data-mesh-barra style="width:4%"></div></div>
                            </div>
                        <?php else: ?>
                            <span class="small text-danger"><i class="bi bi-exclamation-circle"></i> Sem MeshCentral; instalador x64/ARM64 não configurado</span>
                        <?php endif; ?>
                        <a class="btn btn-outline-secondary btn-sm sr-btn-conectar" href="<?= url('/ativos/ver?id=' . (int)$p['ativo_id']) ?>"><i class="bi bi-pc-display"></i> Ficha</a>
                        <button type="button" class="btn btn-outline-success btn-sm sr-btn-conectar ms-auto js-atender" data-id="<?= (int)$p['id'] ?>"><i class="bi bi-check2"></i> Atendido</button>
                    </div>
                    <div class="small mt-2 <?= $online ? 'text-success' : 'text-muted' ?>"><span class="sr-ponto <?= $online ? 'on' : 'off' ?>"></span> agente <?= $online ? 'online' : 'sem sinal agora' ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <div class="sr-titulo-secao me-auto"><i class="bi bi-pc-display-horizontal"></i> Máquinas cadastradas</div>
    <div class="input-group input-group-sm" style="max-width:320px">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control" id="buscaMaquinas" placeholder="Buscar por código, nome, usuário ou IP">
    </div>
</div>
<?php if (!$ativos): ?>
    <div class="sr-cartao p-4 mb-4 text-center text-muted small">Nenhum ativo vinculado ao MeshCentral ainda (Ativos &rsaquo; Acesso Remoto).</div>
<?php else: ?>
    <div class="row g-3 mb-4" id="gradeMaquinas">
        <?php foreach ($ativos as $a): ?>
            <div class="col-sm-6 col-lg-4 col-xxl-3 js-maquina" data-busca="<?= htmlspecialchars(mb_strtolower($a['codigo_patrimonio'] . ' ' . $a['nome'] . ' ' . ($a['usuario_logado'] ?? '') . ' ' . ($a['ip'] ?? ''))) ?>">
                <div class="sr-cartao p-3 h-100 d-flex align-items-center gap-3">
                    <div class="sr-maquina-icone"><i class="bi bi-display"></i></div>
                    <div class="flex-grow-1 text-truncate">
                        <div class="fw-semibold text-truncate"><span class="sr-ponto <?= (int)$a['online'] === 1 ? 'on' : 'off' ?> me-1"></span><?= htmlspecialchars($a['nome']) ?></div>
                        <div class="small text-muted text-truncate"><?= htmlspecialchars($a['codigo_patrimonio']) ?><?= $a['ip'] ? ' &middot; ' . htmlspecialchars($a['ip']) : '' ?></div>
                        <?php if (!empty($a['usuario_logado'])): ?><div class="small text-muted text-truncate"><i class="bi bi-person"></i> <?= htmlspecialchars($a['usuario_logado']) ?></div><?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm sr-btn-conectar js-conectar" data-ativo="<?= (int)$a['id'] ?>" data-nome="<?= htmlspecialchars($a['nome']) ?>" title="Abrir tela remota">
                        <i class="bi bi-box-arrow-in-up-right"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-muted small mb-4 d-none" id="semResultado">Nenhuma máquina encontrada.</div>
<?php endif; ?>

<div class="sr-titulo-secao mb-2"><i class="bi bi-question-circle"></i> No MeshCentral, sem cadastro</div>
<?php if (!$semCadastro): ?>
    <div class="sr-cartao p-4 text-center text-muted small">
        Nenhum dispositivo do MeshCentral fora do cadastro de ativos. Quem não tem o agente RD Intranet aparece aqui ao conectar pelo MeshCentral Assistant ou MeshAgent.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($semCadastro as $d): ?>
            <div class="col-sm-6 col-lg-4 col-xxl-3">
                <div class="sr-cartao p-3 h-100 d-flex align-items-center gap-3">
                    <div class="sr-maquina-icone" style="background:#f3f4f6;color:#4b5563"><i class="bi bi-laptop"></i></div>
                    <div class="flex-grow-1 text-truncate">
                        <div class="fw-semibold text-truncate"><span class="sr-ponto <?= $d['online'] ? 'on' : 'off' ?> me-1"></span><?= htmlspecialchars($d['nome']) ?></div>
                        <div class="small text-muted text-truncate"><?= htmlspecialchars($d['grupo']) ?><?= $d['ip'] ? ' &middot; ' . htmlspecialchars($d['ip']) : '' ?></div>
                        <?php if ($d['usuarios'] !== ''): ?><div class="small text-muted text-truncate"><i class="bi bi-person"></i> <?= htmlspecialchars($d['usuarios']) ?></div><?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm sr-btn-conectar js-conectar" data-mesh="<?= htmlspecialchars($d['id']) ?>" data-nome="<?= htmlspecialchars($d['nome']) ?>" <?= $d['online'] ? '' : 'disabled' ?> title="Abrir tela remota">
                        <i class="bi bi-box-arrow-in-up-right"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Mesma tela cheia da "Tela remota" da ficha do ativo -->
<div class="modal fade" id="modalTelaRemota" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-display"></i> Tela remota -- <span id="nomeTelaRemota"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 d-flex align-items-center justify-content-center bg-dark" id="corpoTelaRemota"></div>
        </div>
    </div>
</div>

<script>
(function () {
    const carregando = '<div class="text-white-50"><i class="bi bi-hourglass-split"></i> Abrindo sessão remota via MeshCentral...</div>';
    const corpo = document.getElementById('corpoTelaRemota');
    const modalEl = document.getElementById('modalTelaRemota');
    let instalacaoMeshEmAndamento = false;

    document.querySelectorAll('.js-conectar').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            document.getElementById('nomeTelaRemota').textContent = botao.dataset.nome || '';
            corpo.innerHTML = carregando;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();

            const dados = new URLSearchParams();
            let rota;
            if (botao.dataset.ativo) {
                dados.set('ativo_id', botao.dataset.ativo);
                rota = <?= json_encode(url('/ativos/acesso-remoto/compartilhar')) ?>;
            } else {
                dados.set('mesh_device_id', botao.dataset.mesh);
                rota = <?= json_encode(url('/chamados/suporte-remoto/conectar')) ?>;
            }

            try {
                const res = await fetch(rota, { method: 'POST', body: dados });
                const resultado = await res.json();
                if (!resultado.success) {
                    corpo.innerHTML = '<div class="text-white-50 text-center p-4"></div>';
                    corpo.firstChild.textContent = resultado.message || 'Falha ao abrir a tela remota.';
                    return;
                }
                const iframe = document.createElement('iframe');
                iframe.src = resultado.url;
                iframe.style.cssText = 'width:100%;height:100%;border:0';
                corpo.innerHTML = '';
                corpo.appendChild(iframe);
            } catch (e) {
                corpo.innerHTML = '<div class="text-white-50 text-center p-4">Erro ao comunicar com o servidor.</div>';
            }
        });
    });

    document.querySelectorAll('.js-instalar-mesh').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            if (!confirm('Instalar o MeshAgent de acesso remoto nesta máquina? O agente verificará a arquitetura, ignorará instalações existentes e tentará vincular o dispositivo pelo hostname se houver uma única correspondência.')) return;

            const cartao = botao.closest('.sr-pedido');
            const painel = cartao.querySelector('[data-mesh-progresso]');
            const mensagem = painel.querySelector('[data-mesh-mensagem]');
            const barra = painel.querySelector('[data-mesh-barra]');
            botao.disabled = true;
            painel.classList.remove('d-none');
            instalacaoMeshEmAndamento = true;

            function atualizar(texto, percentual, classe = 'bg-primary') {
                mensagem.textContent = texto;
                barra.style.width = percentual + '%';
                barra.className = 'progress-bar' + (percentual < 100 ? ' progress-bar-striped progress-bar-animated' : '') + ' ' + classe;
            }

            try {
                atualizar('Enviando solicitação ao agente...', 8);
                const inicio = await fetch(<?= json_encode(url('/chamados/suporte-remoto/instalar-meshagent')) ?>, {
                    method: 'POST',
                    body: new URLSearchParams({ pedido_id: botao.dataset.pedido })
                });
                const pedido = await inicio.json();
                if (!pedido.success) throw new Error(pedido.message || 'Não foi possível solicitar a instalação.');

                atualizar('O agente está verificando a arquitetura e se o MeshAgent já existe...', 20);
                const limite = Date.now() + 180000;
                while (Date.now() < limite) {
                    await new Promise(resolve => setTimeout(resolve, 3500));
                    const url = <?= json_encode(url('/chamados/suporte-remoto/instalacao-meshagent/status')) ?>
                        + '?pedido_id=' + encodeURIComponent(botao.dataset.pedido)
                        + '&solicitacao_id=' + encodeURIComponent(pedido.solicitacao_id);
                    const resposta = await fetch(url);
                    const estado = await resposta.json();
                    if (!estado.success) throw new Error(estado.message || 'Falha ao consultar o progresso.');

                    if (estado.status === 'pendente') {
                        const vinculando = estado.etapa === 'aguardando_meshcentral';
                        atualizar(
                            vinculando ? (estado.message || 'MeshAgent instalado; aguardando o MeshCentral...') : 'Instalando o serviço MeshAgent na máquina...',
                            vinculando ? 88 : Math.min(78, 25 + Math.floor((Date.now() % 90000) / 6000)),
                            vinculando ? 'bg-info' : 'bg-primary'
                        );
                        continue;
                    }

                    if (estado.status === 'erro') throw new Error(estado.mensagem || 'O agente não conseguiu instalar o MeshAgent.');
                    const resultado = estado.resultado || {};
                    const texto = resultado.mensagem || pedido.message || 'Instalação finalizada.';
                    if (resultado.vinculado) {
                        atualizar(texto, 100, 'bg-success');
                        setTimeout(() => location.reload(), 1800);
                    } else {
                        atualizar(texto, 100, resultado.instalado || resultado.ja_instalado ? 'bg-warning' : 'bg-danger');
                        botao.disabled = false;
                    }
                    return;
                }

                atualizar('A instalação continua no agente. Atualize a página em alguns instantes para verificar o vínculo.', 90, 'bg-warning');
                botao.disabled = false;
            } catch (erro) {
                atualizar(erro.message, 100, 'bg-danger');
                botao.disabled = false;
            } finally {
                instalacaoMeshEmAndamento = false;
            }
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function () { corpo.innerHTML = ''; });

    document.querySelectorAll('.js-atender').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            botao.disabled = true;
            const res = await fetch(<?= json_encode(url('/chamados/suporte-remoto/atender')) ?>, { method: 'POST', body: new URLSearchParams({ id: botao.dataset.id }) });
            const dados = await res.json().catch(() => ({ success: false }));
            if (dados.success) { location.reload(); } else { alert(dados.message || 'Não foi possível marcar como atendido.'); botao.disabled = false; }
        });
    });

    const busca = document.getElementById('buscaMaquinas');
    if (busca) {
        busca.addEventListener('input', function () {
            const termo = busca.value.trim().toLowerCase();
            let visiveis = 0;
            document.querySelectorAll('.js-maquina').forEach(function (cartao) {
                const mostra = termo === '' || cartao.dataset.busca.includes(termo);
                cartao.classList.toggle('d-none', !mostra);
                if (mostra) visiveis++;
            });
            document.getElementById('semResultado').classList.toggle('d-none', visiveis > 0);
        });
    }

    // Pedido novo aparece sozinho: recarrega a cada 60s se nenhuma tela remota estiver aberta.
    setInterval(function () {
        if (!instalacaoMeshEmAndamento && !modalEl.classList.contains('show') && document.activeElement !== busca) location.reload();
    }, 60000);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chamados - Suporte Remoto';

require __DIR__ . '/../layouts/main.php';
