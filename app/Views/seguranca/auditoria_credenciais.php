<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<style>
.aud-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); }
.aud-card .card-header { background: #f8fafc; border-bottom: 1px solid #e9ecef; border-radius: 14px 14px 0 0; }

.pop-modal .modal-content { background:#0d1117; color:#c9d1d9; border:1px solid #30363d; border-radius:14px; }
.pop-topbar { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; background:#161b22; border-bottom:1px solid #30363d; border-radius:14px 14px 0 0; }
.pop-topbar .pop-breadcrumb { font-weight:600; color:#58a6ff; display:flex; align-items:center; gap:8px; font-size:1rem; }
.pop-body { padding:1.3rem 1.6rem; max-height:72vh; overflow-y:auto; }
.pop-intro { color:#8b949e; font-size:.88rem; margin-bottom:1.2rem; padding-bottom:1rem; border-bottom:1px dashed #30363d; }
.pop-step { display:flex; gap:1rem; padding:.85rem 0; border-bottom:1px solid #21262d; }
.pop-step:last-child { border-bottom:0; padding-bottom:0; }
.pop-step-num { flex:0 0 auto; width:34px; height:34px; border-radius:9px; background:#132030; color:#58a6ff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.95rem; border:1px solid #1f3b57; }
.pop-step-title { font-weight:600; color:#e6edf3; font-size:.92rem; display:flex; align-items:center; gap:6px; }
.pop-step-text { color:#8b949e; font-size:.83rem; margin-top:3px; line-height:1.55; }
.pop-step-text strong { color:#c9d1d9; }
.pop-step-text code { background:#1c2733; color:#7ee787; padding:1px 5px; border-radius:4px; font-size:.78rem; }
.pop-callout { display:flex; gap:.6rem; padding:.7rem .9rem; border-radius:8px; font-size:.82rem; margin-top:.6rem; }
.pop-callout-info { background:rgba(12,45,74,.35); border:1px solid rgba(31,111,235,.35); color:#79c0ff; }
.pop-callout-warning { background:rgba(59,47,0,.2); border:1px solid rgba(125,90,0,.35); color:#e3b341; }
.pop-callout i { flex:0 0 auto; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Auditoria de Credenciais</h4>
        <small class="text-muted">Diagnóstico de senha fraca/padrão -- módulo restrito, uso registrado em auditoria.</small>
    </div>

    <button type="button" class="btn btn-outline-dark text-nowrap" data-bs-toggle="modal" data-bs-target="#modalPopAuditoriaCredenciais">
        <i class="bi bi-broadcast"></i> POP - Auditoria de Credenciais
    </button>
</div>

<!-- POP -- Procedimento Operacional Padrão da tela de Auditoria de Credenciais -->
<div class="modal fade pop-modal" id="modalPopAuditoriaCredenciais" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="pop-topbar">
                <span class="pop-breadcrumb"><i class="bi bi-broadcast"></i> POP -- Auditoria de Credenciais</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="pop-body">
                <p class="pop-intro">O que é essa tela, quando usar cada ferramenta, e o que fica registrado a cada teste.</p>

                <div class="pop-step">
                    <div class="pop-step-num">1</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-shield-lock"></i> Pra que serve</div>
                        <div class="pop-step-text">
                            Testa se contas de servidor ou dispositivos de rede estão com senha fraca (de uma wordlist comum)
                            ou com a senha de fábrica ainda ativa. É um módulo <strong>restrito</strong>: só quem tem concessão
                            explícita em Usuários acessa esta tela -- nem administrador entra por padrão.
                        </div>
                        <div class="pop-callout pop-callout-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <div>Use só em servidores/dispositivos que você tem autorização pra testar. Testar credencial de algo que não é seu é ilegal.</div>
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">2</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-hdd"></i> Auditoria Local</div>
                        <div class="pop-step-text">
                            Testa as contas <strong>deste próprio servidor</strong> (onde o rd.intranet roda) contra uma wordlist
                            de senhas comuns -- é offline, não toca em rede, só lê o <code>/etc/shadow</code> que o processo já
                            tem acesso. Clique em "Auditar contas locais"; se achar alguma senha fraca, mostra o usuário e a
                            senha em texto claro, pra você trocar na hora.
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">3</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-router"></i> Credenciais Padrão</div>
                        <div class="pop-step-text">
                            Pra dispositivo de rede (roteador, câmera, NAS, DVR/NVR) -- informe o <strong>IP</strong> e o
                            <strong>serviço</strong> (a maioria expõe a senha de fábrica no próprio painel web, então
                            <strong>HTTP</strong>/<strong>HTTPS</strong> é o ponto de partida certo pra esses casos; SSH/FTP/Telnet
                            cobrem servidor e equipamento mais antigo) e a ferramenta testa uma lista curta de credenciais
                            de fábrica conhecidas (<code>admin/admin</code>, <code>root/root</code> etc). Bom ponto de partida
                            pra qualquer coisa que o IP Scanner acabou de descobrir na rede.
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">4</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-terminal"></i> Teste de Senha SSH</div>
                        <div class="pop-step-text">
                            Diferente da anterior: aqui você já sabe qual <strong>conta</strong> existe (ex: <code>root</code>)
                            e quer saber só se a senha dela é fraca, contra uma wordlist curada -- não tenta adivinhar usuários.
                            Pode demorar mais (vários testes em sequência), por isso mostra uma barra de progresso enquanto roda.
                        </div>
                        <div class="pop-callout pop-callout-info">
                            <i class="bi bi-info-circle"></i>
                            <div>"Credenciais Padrão" e "Teste de Senha SSH" precisam do <code>THC-Hydra</code> instalado no servidor, e "Auditoria Local" precisa do <code>John the Ripper</code> -- confira em Infraestrutura &gt; Dependências.</div>
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">5</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-clock-history"></i> Histórico</div>
                        <div class="pop-step-text">
                            Toda execução das três ferramentas fica registrada na tabela abaixo (tipo, alvo, resultado, quem
                            rodou e quando) e também na Auditoria geral do sistema -- inclusive quando encontra uma senha
                            fraca, fica um registro permanente de que aquele teste foi feito e por quem.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-warning border-0 shadow-sm mb-4">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Use apenas em servidores/dispositivos que você tem autorização para testar. Todo teste fica registrado no histórico abaixo e na auditoria geral do sistema.
</div>

<?= Alert::flash() ?>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card aud-card h-100">
            <div class="card-header"><i class="bi bi-hdd me-1"></i> Auditoria Local</div>
            <div class="card-body d-flex flex-column">
                <p class="small text-muted flex-grow-1">Testa as contas do próprio servidor contra senhas comuns (offline, sem tocar em rede -- lê o <code>/etc/shadow</code> que este processo já tem acesso total).</p>
                <button type="button" class="btn btn-outline-primary" id="btn-auditar-local">
                    <i class="bi bi-play-fill"></i> Auditar contas locais
                </button>
                <div id="resultado-local" class="mt-3"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card aud-card h-100">
            <div class="card-header"><i class="bi bi-router me-1"></i> Credenciais Padrão</div>
            <div class="card-body d-flex flex-column">
                <p class="small text-muted">Testa uma lista curta de credenciais de fábrica conhecidas (admin/admin, root/root, etc.) num dispositivo -- útil pra roteador, câmera, NAS descoberto pelo IP Scanner.</p>
                <form id="form-credenciais-padrao" class="mb-2">
                    <div class="mb-2">
                        <input type="text" name="ip" class="form-control form-control-sm font-monospace" placeholder="IP do dispositivo" required>
                    </div>
                    <div class="mb-2">
                        <select name="servico" class="form-select form-select-sm">
                            <option value="http">HTTP (painel web, porta 80)</option>
                            <option value="https">HTTPS (painel web, porta 443)</option>
                            <option value="ssh">SSH</option>
                            <option value="ftp">FTP</option>
                            <option value="telnet">Telnet</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-play-fill"></i> Testar
                    </button>
                </form>
                <div id="resultado-credenciais-padrao"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card aud-card h-100">
            <div class="card-header"><i class="bi bi-terminal me-1"></i> Teste de Senha SSH</div>
            <div class="card-body d-flex flex-column">
                <p class="small text-muted">Testa se UMA conta específica que você já conhece tem senha fraca, contra uma wordlist curada -- não tenta adivinhar usuários.</p>
                <form id="form-ssh" class="mb-2">
                    <div class="mb-2">
                        <input type="text" name="ip" class="form-control form-control-sm font-monospace" placeholder="IP do host" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="usuario" class="form-control form-control-sm" placeholder="Conta (ex: root)" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100" id="btn-ssh-iniciar">
                        <i class="bi bi-play-fill"></i> Testar
                    </button>
                </form>
                <div id="progresso-ssh" class="d-none">
                    <div class="progress mb-2" style="height:18px">
                        <div id="progresso-ssh-barra" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
                    </div>
                    <div id="progresso-ssh-msg" class="small text-muted"></div>
                </div>
                <div id="resultado-ssh"></div>
            </div>
        </div>
    </div>
</div>

<div class="card aud-card">
    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Histórico</div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Tipo</th><th>Alvo</th><th>Resultado</th><th>Quando</th><th>Quem</th></tr>
            </thead>
            <tbody>
                <?php if (empty($historico)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma auditoria registrada ainda.</td></tr>
                <?php endif; ?>
                <?php foreach ($historico as $h): ?>
                    <?php
                        $resultado = json_decode($h['resultado'], true) ?: [];
                        $tipoLabel = ['local' => 'Auditoria Local', 'credenciais_padrao' => 'Credenciais Padrão', 'forca_bruta_ssh' => 'Teste SSH'][$h['tipo']] ?? $h['tipo'];

                        if ($h['tipo'] === 'local') {
                            $qtd = count($resultado['contas_fracas'] ?? []);
                            $resumo = $qtd > 0 ? Badge::make("{$qtd} conta(s) fraca(s)", 'danger') : Badge::make('Nenhuma conta fraca', 'success');
                        } elseif ($h['tipo'] === 'credenciais_padrao') {
                            $resumo = !empty($resultado['encontrado']) ? Badge::make('Credencial padrão ativa', 'danger') : Badge::make('Nenhuma credencial padrão', 'success');
                        } else {
                            $resumo = !empty($resultado['encontrado']) ? Badge::make('Senha fraca', 'danger') : Badge::make('Senha não encontrada na lista', 'success');
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($tipoLabel) ?></td>
                        <td class="font-monospace small"><?= htmlspecialchars($h['alvo'] ?? '-') ?></td>
                        <td><?= $resumo ?></td>
                        <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($h['executado_em'])) ?></td>
                        <td class="small"><?= htmlspecialchars($h['executado_por_nome'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const URL_LOCAL = <?= json_encode(url('/seguranca/auditoria-credenciais/local')) ?>;
    const URL_CRED_PADRAO = <?= json_encode(url('/seguranca/auditoria-credenciais/credenciais-padrao')) ?>;
    const URL_SSH_INICIAR = <?= json_encode(url('/seguranca/auditoria-credenciais/ssh/iniciar')) ?>;
    const URL_SSH_STATUS = <?= json_encode(url('/seguranca/auditoria-credenciais/ssh/status')) ?>;
    const URL_SSH_FINALIZAR = <?= json_encode(url('/seguranca/auditoria-credenciais/ssh/finalizar')) ?>;

    function badge(texto, cor) {
        return '<span class="badge text-bg-' + cor + '">' + texto + '</span>';
    }

    document.getElementById('btn-auditar-local').addEventListener('click', async function () {
        const btn = this;
        const div = document.getElementById('resultado-local');
        btn.disabled = true;
        div.innerHTML = '<div class="text-muted small"><i class="bi bi-hourglass-split"></i> Auditando...</div>';

        try {
            const res = await fetch(URL_LOCAL, { method: 'POST' });
            const dados = await res.json();

            if (!dados.success) {
                div.innerHTML = '<div class="alert alert-danger small mb-0">' + (dados.message || 'Falha na auditoria.') + '</div>';
                return;
            }

            const contas = dados.contas_fracas || [];
            if (contas.length === 0) {
                div.innerHTML = '<div class="alert alert-success small mb-0">Nenhuma conta local com senha da wordlist.</div>';
            } else {
                let html = '<div class="alert alert-danger small mb-0"><strong>' + contas.length + ' conta(s) fraca(s):</strong><ul class="mb-0 mt-1">';
                contas.forEach(c => { html += '<li><code>' + c.usuario + '</code> -- senha: <code>' + c.senha + '</code></li>'; });
                html += '</ul></div>';
                div.innerHTML = html;
            }
        } catch (err) {
            div.innerHTML = '<div class="alert alert-danger small mb-0">Erro ao comunicar com o servidor.</div>';
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('form-credenciais-padrao').addEventListener('submit', async function (e) {
        e.preventDefault();
        const div = document.getElementById('resultado-credenciais-padrao');
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        div.innerHTML = '<div class="text-muted small"><i class="bi bi-hourglass-split"></i> Testando...</div>';

        try {
            const res = await fetch(URL_CRED_PADRAO, { method: 'POST', body: new FormData(this) });
            const dados = await res.json();

            if (!dados.success) {
                div.innerHTML = '<div class="alert alert-danger small mb-0">' + (dados.message || 'Falha no teste.') + '</div>';
            } else if (dados.encontrado) {
                div.innerHTML = '<div class="alert alert-danger small mb-0">Credencial padrão ativa: <code>' + dados.usuario + '</code> / <code>' + dados.senha + '</code></div>';
            } else {
                div.innerHTML = '<div class="alert alert-success small mb-0">Nenhuma credencial padrão da lista funcionou.</div>';
            }
        } catch (err) {
            div.innerHTML = '<div class="alert alert-danger small mb-0">Erro ao comunicar com o servidor.</div>';
        } finally {
            btn.disabled = false;
        }
    });

    let pollSsh = null;
    function pararPollSsh() { if (pollSsh) { clearInterval(pollSsh); pollSsh = null; } }

    document.getElementById('form-ssh').addEventListener('submit', async function (e) {
        e.preventDefault();
        const ip = this.querySelector('[name="ip"]').value.trim();
        const usuario = this.querySelector('[name="usuario"]').value.trim();
        const btn = document.getElementById('btn-ssh-iniciar');
        const divResultado = document.getElementById('resultado-ssh');
        const painelProgresso = document.getElementById('progresso-ssh');

        btn.disabled = true;
        divResultado.innerHTML = '';
        painelProgresso.classList.remove('d-none');
        document.getElementById('progresso-ssh-barra').style.width = '0%';
        document.getElementById('progresso-ssh-barra').textContent = '0%';

        try {
            const res = await fetch(URL_SSH_INICIAR, { method: 'POST', body: new FormData(this) });
            const dados = await res.json();

            if (!dados.success) {
                painelProgresso.classList.add('d-none');
                divResultado.innerHTML = '<div class="alert alert-danger small mb-0">' + (dados.message || 'Falha ao iniciar.') + '</div>';
                btn.disabled = false;
                return;
            }

            acompanharSsh(dados.execucao_id, ip, usuario);
        } catch (err) {
            painelProgresso.classList.add('d-none');
            divResultado.innerHTML = '<div class="alert alert-danger small mb-0">Erro ao comunicar com o servidor.</div>';
            btn.disabled = false;
        }
    });

    function acompanharSsh(execucaoId, ip, usuario) {
        pararPollSsh();
        pollSsh = setInterval(async function () {
            try {
                const res = await fetch(URL_SSH_STATUS + '?id=' + encodeURIComponent(execucaoId));
                const dados = await res.json();

                if (dados.status === 'rodando') {
                    const pct = dados.percentual || 0;
                    document.getElementById('progresso-ssh-barra').style.width = pct + '%';
                    document.getElementById('progresso-ssh-barra').textContent = pct + '%';
                    document.getElementById('progresso-ssh-msg').textContent = dados.mensagem || '';
                    return;
                }

                if (dados.status === 'concluido') {
                    pararPollSsh();
                    document.getElementById('btn-ssh-iniciar').disabled = false;
                    document.getElementById('progresso-ssh').classList.add('d-none');

                    const div = document.getElementById('resultado-ssh');
                    if (dados.encontrado) {
                        div.innerHTML = '<div class="alert alert-danger small mb-0">Senha fraca: <code>' + dados.encontrado.senha + '</code></div>';
                    } else {
                        div.innerHTML = '<div class="alert alert-success small mb-0">' + (dados.mensagem || 'Nenhuma senha da wordlist funcionou.') + '</div>';
                    }

                    await fetch(URL_SSH_FINALIZAR, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + encodeURIComponent(execucaoId) + '&ip=' + encodeURIComponent(ip) + '&usuario=' + encodeURIComponent(usuario)
                    });
                    return;
                }

                if (dados.status === 'erro') {
                    pararPollSsh();
                    document.getElementById('btn-ssh-iniciar').disabled = false;
                    document.getElementById('progresso-ssh').classList.add('d-none');
                    document.getElementById('resultado-ssh').innerHTML = '<div class="alert alert-danger small mb-0">' + (dados.mensagem || 'Falha no teste.') + '</div>';
                }
                // "desconhecido" -- job ainda nao escreveu o primeiro status
            } catch (err) {
                // falha de rede pontual -- tenta de novo no proximo tick
            }
        }, 1500);
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Segurança - Auditoria de Credenciais';
require __DIR__ . '/../layouts/main.php';
