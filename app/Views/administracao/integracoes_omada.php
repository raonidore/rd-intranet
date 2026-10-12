<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-hdd-network me-1"></i> TP-Link Omada</h4>
    <small class="text-muted">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-3" style="max-width:720px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong>Status</strong>
            <?php if ($configurado): ?>
                <?= Badge::make('Configurado -- ' . \App\Services\OmadaService::rotuloModo($modo), 'success') ?>
            <?php else: ?>
                <?= Badge::make('Não configurado', 'secondary') ?>
            <?php endif; ?>
        </div>

        <p class="text-muted small mb-3">
            Usada pela Ficha do Ativo ("Detectar e coletar automaticamente") e pela coleta periódica pra buscar
            dados dos switches TP-Link cadastrados em Ativos. Escolha abaixo <strong>como</strong> o sistema fala
            com eles: o Controller informa modelo, firmware, status e uptime; o SSH traz também as portas
            (quem está em cada uma), VLANs, CPU e memória -- dá pra usar os dois juntos.
        </p>

        <form method="post" action="<?= url('/administracao/integracoes/omada/salvar') ?>" class="row g-3">
            <div class="col-12">
                <label class="form-label">Tipo de integração</label>
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="radio" class="btn-check" name="modo" id="modoController" value="controller" <?= $modo === 'controller' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary w-100 h-100 text-start p-3" for="modoController">
                            <strong><i class="bi bi-diagram-3"></i> Omada Controller</strong>
                            <div class="small mt-1">Open API de um Omada Controller com os switches adotados. Só status, modelo, firmware e uptime.</div>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <input type="radio" class="btn-check" name="modo" id="modoSsh" value="ssh" <?= $modo === 'ssh' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary w-100 h-100 text-start p-3" for="modoSsh">
                            <strong><i class="bi bi-terminal"></i> SSH direto no switch</strong>
                            <div class="small mt-1">Sem Controller: lê os dados pela linha de comando do switch, só com comandos de consulta.</div>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <input type="radio" class="btn-check" name="modo" id="modoAmbos" value="ambos" <?= $modo === 'ambos' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary w-100 h-100 text-start p-3" for="modoAmbos">
                            <strong><i class="bi bi-layers"></i> Controller + SSH</strong>
                            <div class="small mt-1">Switches adotados no Controller e com SSH liberado: status pelo Controller, portas/VLANs pelo SSH.</div>
                        </label>
                    </div>
                </div>
                <?php if ($modo === 'ssh' && $controllerConfigurado): ?>
                    <div class="form-text">A configuração do Controller continua salva -- volta a valer se você trocar o tipo de novo.</div>
                <?php elseif ($modo === 'controller' && $sshConfigurado): ?>
                    <div class="form-text">A credencial SSH continua salva -- volta a valer se você trocar o tipo de novo.</div>
                <?php endif; ?>
            </div>

            <div class="col-12 bloco-modo" data-modo="controller">
                <div class="row g-3">
                    <div class="col-12 bloco-titulo-ambos"><strong class="small text-uppercase text-muted"><i class="bi bi-diagram-3"></i> Omada Controller</strong></div>
                    <div class="col-12">
                        <label class="form-label">URL do Controller</label>
                        <input type="text" name="url" class="form-control font-monospace"
                               value="<?= htmlspecialchars($urlAtual) ?>" placeholder="https://192.168.10.17:8043">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Omada ID</label>
                        <input type="text" name="omadac_id" class="form-control font-monospace"
                               value="<?= htmlspecialchars($omadacIdAtual) ?>" placeholder="identificador fixo desta instalação do Controller">
                        <div class="form-text">Não tem uma tela própria pra isso no Controller -- peça esse valor a quem configurou a integração pela primeira vez.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Client ID</label>
                        <input type="text" name="client_id" class="form-control font-monospace"
                               value="<?= htmlspecialchars($clientIdAtual) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Client Secret</label>
                        <input type="password" name="client_secret" class="form-control font-monospace" autocomplete="new-password"
                               placeholder="<?= $controllerConfigurado ? '••••••••  (deixe em branco pra manter)' : 'gerado em Configurações > Integração de Plataforma > Open API' ?>">
                    </div>
                </div>
            </div>

            <div class="col-12 bloco-modo" data-modo="ssh">
                <div class="row g-3">
                    <div class="col-12 bloco-titulo-ambos"><strong class="small text-uppercase text-muted"><i class="bi bi-terminal"></i> SSH nos switches</strong></div>
                    <div class="col-md-8">
                        <label class="form-label">Usuário SSH</label>
                        <input type="text" name="ssh_usuario" class="form-control font-monospace" autocomplete="off"
                               value="<?= htmlspecialchars($sshUsuarioAtual) ?>" placeholder="ex: rdcoleta">
                        <div class="form-text">Use um usuário de privilégio <strong>user</strong> (só leitura) criado no switch -- não o admin.
                            <span class="bloco-titulo-ambos">Em switch <strong>adotado pelo Controller</strong>, o login é o da <strong>Conta do Dispositivo</strong> do Controller.</span></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Porta</label>
                        <input type="number" name="ssh_porta" class="form-control font-monospace" min="1" max="65535"
                               value="<?= (int)$sshPortaAtual ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Senha SSH</label>
                        <input type="password" name="ssh_senha" class="form-control font-monospace" autocomplete="new-password"
                               placeholder="<?= $sshConfigurado ? '••••••••  (deixe em branco pra manter)' : 'senha do usuário de leitura' ?>">
                        <div class="form-text">Guardada cifrada. A mesma credencial vale pra todos os switches TP-Link cadastrados em Ativos.</div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                <?php if ($configurado): ?>
                    <button type="button" class="btn btn-outline-danger" id="botaoRemoverConfigOmada"><i class="bi bi-trash"></i> Remover configuração</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= url('/administracao/integracoes/omada/remover') ?>" id="formRemoverConfigOmada" class="d-none"></form>

        <?php if ($configurado): ?>
            <hr>
            <div class="d-flex align-items-end gap-2 flex-wrap">
                <?php if ($modo !== 'controller'): ?>
                    <div>
                        <label class="form-label small mb-1" for="campoIpTesteOmada">IP de um switch pra testar<?= $modo === 'ambos' ? ' (SSH, opcional)' : '' ?></label>
                        <input type="text" id="campoIpTesteOmada" class="form-control form-control-sm font-monospace" placeholder="192.168.0.245" style="width:180px">
                    </div>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="botaoTestarConexaoOmada"><i class="bi bi-plug"></i> Testar conexão</button>
            </div>
        <?php endif; ?>

        <div class="alert alert-success mt-3 d-none" id="omadaTesteOk"></div>
        <div class="alert alert-danger mt-3 d-none" id="omadaTesteErro"></div>

        <?php if ($configurado && $modo !== 'ssh' && $siteId): ?>
            <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle"></i> Site identificado: <code><?= htmlspecialchars($siteId) ?></code></p>
        <?php elseif ($configurado && $modo !== 'ssh'): ?>
            <p class="text-muted small mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> Site ainda não identificado -- clique em "Testar conexão".</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Passos finais iguais nos dois tipos de integração.
$passosAtivos = <<<'HTML'
<li class="mb-2">
    <strong>Cadastre o switch como Ativo</strong> -- em Ativos &gt; Novo Ativo, informe o IP
    de gerenciamento e clique em <strong>"Detectar"</strong> ao lado do campo IP: o sistema
    identifica sozinho e preenche marca/modelo/firmware.
</li>
<li class="mb-2">
    <strong>Colete os dados</strong> -- na ficha do ativo já salvo, clique em <strong>"Detectar
    e coletar automaticamente"</strong> pra trazer modelo, firmware, status e uptime.
</li>
<li>
    <strong>(Opcional) Ative a coleta periódica</strong> -- no Dashboard de Ativos, aba
    "Integrações de rede", botão "Ativar coleta" no card Omada.
</li>
HTML;
?>

<div class="card border-0 shadow-sm mb-3 bloco-modo" data-modo="controller" style="max-width:720px">
    <div class="card-body">
        <strong><i class="bi bi-list-ol"></i> Passo a passo -- Omada Controller</strong>
        <ol class="small text-muted mt-2 mb-0">
            <li class="mb-2">
                <strong>Código atualizado</strong> -- se esta tela é nova pra você, atualize o servidor
                primeiro (via SSH):
                <br><code>cd /var/www/rd.intranet &amp;&amp; git pull --ff-only &amp;&amp; php rd migrate</code>
            </li>
            <li class="mb-2">
                <strong>Omada Controller instalado e com o switch adotado</strong> -- diferente da UniFi
                (Controller já embutido no gateway), precisa existir um Omada Network Application rodando em
                algum servidor (instalação nativa via <code>.deb</code>) com o switch <strong>já adotado</strong>
                nele -- não basta aparecer como "descoberto"/pendente. Atenção: adotar um switch que estava em
                modo standalone faz o Controller aplicar a configuração dele por cima (VLANs, portas) -- faça numa
                janela de manutenção. Se um switch específico não trouxer dado nenhum, confira o status dele dentro
                do próprio Omada (deve estar "Connected", não "Pending"/"Adopt Failed").
            </li>
            <li class="mb-2">
                <strong>Gere o Client ID/Secret</strong> -- no Omada Controller, abra
                <strong>Configurações (engrenagem) &gt; Integração de Plataforma &gt; Open API</strong>,
                clique em <strong>Add New App</strong>, dê um nome (ex: "RD.Intranet"), escolha o modo
                <strong>Client</strong> (não "Authorization Code") e função <strong>Super Admin</strong>,
                e copie o Client ID e o Client Secret gerados -- o segredo não é exibido de novo depois.
            </li>
            <li class="mb-2">
                <strong>Descubra o Omada ID</strong> -- é o único dado que o Controller não expõe em
                nenhuma tela: peça pra quem configurou a integração pela primeira vez, ou confira a
                documentação interna deste projeto.
            </li>
            <li class="mb-2">
                <strong>Configure aqui em cima</strong> -- escolha "Omada Controller", cole URL, Omada ID,
                Client ID e Client Secret, salve, e clique em <strong>"Testar conexão"</strong> -- identifica
                e grava o site sozinho.
            </li>
            <?= $passosAtivos ?>
        </ol>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3 bloco-modo" data-modo="ssh" style="max-width:720px">
    <div class="card-body">
        <strong><i class="bi bi-list-ol"></i> Passo a passo -- SSH direto no switch</strong>
        <ol class="small text-muted mt-2 mb-0">
            <li class="mb-2">
                <strong>Crie um usuário só de leitura no switch</strong> -- pela interface web do switch
                (System &gt; User Management) ou pela linha de comando, logado como admin:
                <br><code>enable</code> &rarr; <code>configure</code> &rarr;
                <code>user name rdcoleta privilege user password &lt;senha&gt;</code>
                <br>O nível <strong>user</strong> não tem <code>configure</code>, <code>copy</code>,
                <code>reboot</code> nem <code>reset</code> -- só consulta.
            </li>
            <li class="mb-2">
                <strong>Feche a elevação de privilégio (importante)</strong> -- de fábrica o switch deixa qualquer
                usuário virar admin pelo comando <code>enable-admin</code> <strong>sem pedir senha</strong>.
                Ainda como admin, no modo <code>configure</code>:
                <br><code>enable admin password &lt;outra senha forte&gt;</code>
                <br><code>aaa authentication enable default local</code>
                <br><code>service password-encryption</code>
                <br>e salve com <code>end</code> e <code>copy running-config startup-config</code>. O login do
                admin não muda. Sem o <code>service password-encryption</code>, o usuário de leitura vê a senha de
                elevação em texto puro no <code>show running-config</code>. Essa senha não aceita <code>@</code>.
            </li>
            <li class="mb-2">
                <strong>SSH ligado no switch</strong> -- vem ligado de fábrica nos SG3428 (<code>show ip ssh</code>
                mostra "SSH Server: Enabled"). O servidor do rd.intranet precisa alcançar o IP do switch na porta 22.
            </li>
            <li class="mb-2">
                <strong>Configure aqui em cima</strong> -- escolha "SSH direto no switch", informe usuário e senha,
                salve, e use <strong>"Testar conexão"</strong> com o IP de um switch.
            </li>
            <?= $passosAtivos ?>
        </ol>
        <div class="alert alert-light border small mt-3 mb-0 bloco-titulo-ambos">
            <i class="bi bi-layers"></i> <strong>Switch adotado pelo Omada Controller</strong> -- o Controller assume o
            SSH do switch: a porta 22 até aceita a conexão, mas fecha na hora enquanto o SSH estiver desligado no
            Controller. Ligue em <strong>Configurações do site &gt; Serviços &gt; SSH</strong> (SSH Login) e use como
            usuário/senha a <strong>Conta do Dispositivo</strong> (Configurações do site &gt; Conta do Dispositivo).
            Os comandos de criar usuário acima não se aplicam: quem manda na configuração é o Controller.
        </div>
        <p class="small text-muted mt-3 mb-0">
            <i class="bi bi-shield-check"></i> O rd.intranet só sabe mandar uma lista fixa de comandos de consulta
            (<code>show system-info</code>, <code>show interface status</code>, <code>show cpu-utilization</code>,
            <code>show memory-utilization</code>, <code>show mac address-table count</code>,
            <code>show vlan brief</code>, <code>show mac address-table dynamic</code>, <code>show arp</code>) -- não existe caminho no código pra mandar outro comando.
        </p>
    </div>
</div>

<script>
(function () {
    // Mostra só os campos e o passo a passo do tipo escolhido.
    function mostrarModo() {
        const modo = document.querySelector('input[name="modo"]:checked').value;
        document.querySelectorAll('.bloco-modo').forEach(function (bloco) {
            bloco.classList.toggle('d-none', modo !== 'ambos' && bloco.dataset.modo !== modo);
        });
        document.querySelectorAll('.bloco-titulo-ambos').forEach(function (el) {
            el.classList.toggle('d-none', modo !== 'ambos');
        });
    }
    document.querySelectorAll('input[name="modo"]').forEach(r => r.addEventListener('change', mostrarModo));
    mostrarModo();

    const botaoTestar = document.getElementById('botaoTestarConexaoOmada');
    const campoIp = document.getElementById('campoIpTesteOmada');
    const okBox = document.getElementById('omadaTesteOk');
    const erroBox = document.getElementById('omadaTesteErro');

    if (botaoTestar) {
        botaoTestar.addEventListener('click', async function () {
            okBox.classList.add('d-none');
            erroBox.classList.add('d-none');
            botaoTestar.disabled = true;

            const dados = new URLSearchParams();
            if (campoIp) dados.set('ip', campoIp.value.trim());

            try {
                const res = await fetch(<?= json_encode(url('/administracao/integracoes/omada/testar')) ?>, { method: 'POST', body: dados });
                const resultado = await res.json();

                if (resultado.success) {
                    okBox.textContent = resultado.message;
                    okBox.classList.remove('d-none');
                    // Com Controller o teste grava o site -- recarrega pra mostrar.
                    if (!campoIp || <?= json_encode($modo === 'ambos') ?>) setTimeout(() => location.reload(), 1500);
                } else {
                    erroBox.textContent = resultado.message;
                    erroBox.classList.remove('d-none');
                }
            } catch (e) {
                erroBox.textContent = 'Erro ao comunicar com o servidor.';
                erroBox.classList.remove('d-none');
            } finally {
                botaoTestar.disabled = false;
            }
        });
    }

    const botaoRemover = document.getElementById('botaoRemoverConfigOmada');
    if (botaoRemover) {
        botaoRemover.addEventListener('click', function () {
            if (confirm('Remover a configuração da integração TP-Link/Omada (Controller e SSH)? A coleta de dados dos switches TP-Link para de funcionar até ser configurada de novo.')) {
                document.getElementById('formRemoverConfigOmada').submit();
            }
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - TP-Link Omada';

require __DIR__ . '/../layouts/main.php';
