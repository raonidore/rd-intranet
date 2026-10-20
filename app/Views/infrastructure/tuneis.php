<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-signpost-split me-1"></i> Túneis</h4>
    <small class="text-muted">Acesse ou exponha este servidor sem depender de VPN de terceiros -- Tailscale (rede privada, acesso direto) e Cloudflare Tunnel (exposição pública protegida).</small>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong><i class="bi bi-diagram-3 me-1"></i> Tailscale</strong></div>
            <div class="card-body">
                <p class="text-muted small">Coloca este servidor numa rede privada (tailnet) -- qualquer dispositivo autorizado te alcança direto, sem VPN de cliente nenhuma no meio.</p>

                <div class="mb-3">
                    <?php if (!$tailscaleStatus['instalado']): ?>
                        <?= Badge::make('Não instalado', 'secondary') ?>
                        <div class="small text-muted mt-1"><i class="bi bi-info-circle"></i> Não precisa instalar à mão: ao clicar em <strong>Conectar</strong> o portal instala o Tailscale sozinho (leva 1 a 2 minutos).</div>
                    <?php elseif ($tailscaleStatus['conectado']): ?>
                        <?= Badge::make('Conectado', 'success') ?>
                        <div class="small text-muted mt-1">
                            <?php if ($tailscaleStatus['ip']): ?>IP: <span class="font-monospace"><?= htmlspecialchars($tailscaleStatus['ip']) ?></span><br><?php endif; ?>
                            <?php if ($tailscaleStatus['hostname_tailnet']): ?>Hostname: <span class="font-monospace"><?= htmlspecialchars($tailscaleStatus['hostname_tailnet']) ?></span><br><?php endif; ?>
                            <?php if ($tailscaleStatus['conectado_desde']): ?>desde <?= htmlspecialchars(data_br($tailscaleStatus['conectado_desde'])) ?><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?= Badge::make('Instalado, desconectado', 'warning') ?>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= url('/infraestrutura/tuneis/tailscale/configurar') ?>" class="mb-3">
                    <div class="mb-2">
                        <label class="form-label small mb-0">Chave do Tailscale</label>
                        <input type="password" name="api_token" class="form-control form-control-sm font-monospace" autocomplete="off"
                               placeholder="<?= $tailscaleConfigurado ? '•••••••• (deixe em branco pra manter)' : 'tskey-auth-... ou tskey-api-...' ?>">
                        <details class="small text-muted mt-1">
                            <summary>Onde pego essa chave?</summary>
                            <ul class="mb-1 mt-1 ps-3">
                                <li><strong>Mais simples -- chave de autenticação</strong> (<code>tskey-auth-</code>): no
                                    <a href="https://login.tailscale.com/admin/machines/new-linux" target="_blank" rel="noopener">console do Tailscale &gt; Add device &gt; Linux</a>,
                                    clique em <em>Generate install script</em> e copie só a chave que vem depois de <code>--auth-key=</code>,
                                    <strong>pelo botão de copiar</strong> -- ela aparece quebrada em duas linhas. Marque "Reusable" se for usar em mais de um servidor.</li>
                                <li><strong>API Token</strong> (<code>tskey-api-</code>): em
                                    <a href="https://login.tailscale.com/admin/settings/keys" target="_blank" rel="noopener">Settings &gt; Keys &gt; Generate access token</a>.
                                    O portal gera sozinho uma chave de uso único a cada conexão.</li>
                            </ul>
                        </details>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small mb-0">Tailnet <span class="text-muted">(pode deixar em branco)</span></label>
                            <input type="text" name="tailnet" class="form-control form-control-sm" placeholder="- (o da conta da chave)" value="<?= htmlspecialchars($tailscaleTailnet === '-' ? '' : $tailscaleTailnet) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-0">Hostname (opcional)</label>
                            <input type="text" name="hostname" class="form-control form-control-sm" placeholder="ex: rd-servidor-x" value="<?= htmlspecialchars($tailscaleHostname) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check-lg"></i> Salvar credenciais</button>
                </form>

                <?php if ($tailscaleStatus['conectado']): ?>
                    <form method="post" action="<?= url('/infraestrutura/tuneis/tailscale/desconectar') ?>" onsubmit="return confirm('Desconectar este servidor do tailnet?');">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-plug"></i> Desconectar</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= url('/infraestrutura/tuneis/tailscale/conectar') ?>" id="formConectarTailscale">
                        <button type="submit" class="btn btn-sm btn-primary" <?= $tailscaleConfigurado ? '' : 'disabled' ?>><i class="bi bi-plug-fill"></i> Conectar</button>
                        <?php if (!$tailscaleConfigurado): ?><span class="small text-muted ms-2">salve a chave primeiro</span><?php endif; ?>
                    </form>
                    <div id="progressoTailscale" class="alert alert-info small mt-3 mb-0 d-none">
                        <div class="d-flex align-items-center gap-2">
                            <div class="spinner-border spinner-border-sm"></div>
                            <div><strong id="progressoTailscaleTitulo">Conectando...</strong>
                                <div id="progressoTailscaleTexto">Não feche esta página.</div></div>
                        </div>
                    </div>
                    <script>
                    (function () {
                        const form = document.getElementById('formConectarTailscale');
                        if (!form) return;
                        const instalado = <?= $tailscaleStatus['instalado'] ? 'true' : 'false' ?>;
                        form.addEventListener('submit', function (e) {
                            const aviso = instalado
                                ? 'Conectar este servidor ao tailnet agora?'
                                : 'O Tailscale ainda não está instalado neste servidor. Instalar (1 a 2 minutos) e conectar agora?';
                            if (!confirm(aviso)) { e.preventDefault(); return; }
                            form.querySelector('button').disabled = true;
                            document.getElementById('progressoTailscale').classList.remove('d-none');
                            const passos = instalado
                                ? [[0, 'Conectando ao tailnet', 'Entrando na rede privada.']]
                                : [[0, 'Instalando o Tailscale', 'Baixando o pacote oficial e instalando -- leva 1 a 2 minutos. Não feche esta página.'],
                                   [70, 'Ainda instalando', 'Em servidor mais lento pode chegar a 3 minutos.'],
                                   [150, 'Quase lá', 'Conectando ao tailnet.']];
                            const inicio = Date.now();
                            const atualizar = function () {
                                const seg = Math.round((Date.now() - inicio) / 1000);
                                const atual = passos.filter(function (p) { return seg >= p[0]; }).pop();
                                document.getElementById('progressoTailscaleTitulo').textContent = atual[1] + '... (' + seg + ' s)';
                                document.getElementById('progressoTailscaleTexto').textContent = atual[2];
                            };
                            atualizar();
                            setInterval(atualizar, 1000);
                        });
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong><i class="bi bi-cloud-arrow-up me-1"></i> Cloudflare Tunnel</strong></div>
            <div class="card-body">
                <p class="text-muted small">Expõe esta intranet publicamente com um hostname estável, através da rede da Cloudflare -- sem abrir porta nenhuma de entrada no firewall.</p>

                <div class="mb-3">
                    <?php if (!$cloudflareStatus['instalado']): ?>
                        <?= Badge::make('Não instalado', 'secondary') ?>
                    <?php elseif ($cloudflareStatus['tunel_criado'] && $cloudflareStatus['ativo']): ?>
                        <?= Badge::make('Túnel ativo', 'success') ?>
                        <div class="small text-muted mt-1">
                            <a href="https://<?= htmlspecialchars($cloudflareStatus['hostname']) ?>" target="_blank" rel="noopener">
                                https://<?= htmlspecialchars($cloudflareStatus['hostname']) ?>
                            </a>
                        </div>
                    <?php elseif ($cloudflareStatus['tunel_criado']): ?>
                        <?= Badge::make('Túnel criado, serviço parado', 'warning') ?>
                    <?php else: ?>
                        <?= Badge::make('Instalado, sem túnel', 'warning') ?>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= url('/infraestrutura/tuneis/cloudflare/configurar') ?>" class="mb-3">
                    <div class="mb-2">
                        <label class="form-label small mb-0">API Token</label>
                        <input type="password" name="api_token" class="form-control form-control-sm"
                               placeholder="<?= $cloudflareConfigurado ? '•••••••• (deixe em branco pra manter)' : 'token com permissão Tunnel + DNS Edit' ?>">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small mb-0">Account ID</label>
                            <input type="text" name="account_id" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($cloudflareAccountId) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-0">Zone ID</label>
                            <input type="text" name="zone_id" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($cloudflareZoneId) ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-0">Hostname público</label>
                        <input type="text" name="hostname" class="form-control form-control-sm" placeholder="ex: intranet.suaempresa.com.br" value="<?= htmlspecialchars($cloudflareHostname) ?>" <?= $cloudflareStatus['tunel_criado'] ? 'disabled' : '' ?>>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary" <?= $cloudflareStatus['tunel_criado'] ? 'disabled' : '' ?>><i class="bi bi-check-lg"></i> Salvar credenciais</button>
                    <?php if ($cloudflareStatus['tunel_criado']): ?><span class="small text-muted ms-2">remova o túnel pra trocar</span><?php endif; ?>
                </form>

                <?php if ($cloudflareStatus['tunel_criado']): ?>
                    <form method="post" action="<?= url('/infraestrutura/tuneis/cloudflare/remover') ?>" onsubmit="return confirm('Remover o túnel? Isso apaga o túnel e o registro DNS na Cloudflare, e para o serviço local.');">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Remover túnel</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= url('/infraestrutura/tuneis/cloudflare/criar') ?>"
                          onsubmit="return confirm('Criar o túnel agora? Isso instala o cloudflared (se preciso) e cria recursos de verdade na sua conta Cloudflare (túnel + registro DNS).');">
                        <button type="submit" class="btn btn-sm btn-primary" <?= $cloudflareConfigurado ? '' : 'disabled' ?>><i class="bi bi-cloud-plus"></i> Criar túnel</button>
                        <?php if (!$cloudflareConfigurado): ?><span class="small text-muted ms-2">salve as credenciais primeiro</span><?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Túneis';

require __DIR__ . '/../layouts/main.php';
