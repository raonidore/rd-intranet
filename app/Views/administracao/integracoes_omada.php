<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-hdd-network me-1"></i> Omada SDN Controller (TP-Link)</h4>
    <small class="text-muted">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-3" style="max-width:720px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong>Status</strong>
            <?= $configurado ? Badge::make('Configurado', 'success') : Badge::make('Não configurado', 'secondary') ?>
        </div>

        <p class="text-muted small mb-3">
            Usada pela Ficha do Ativo (botão "Coletar dados Omada") pra buscar modelo, firmware, status e uptime
            de switches TP-Link cadastrados em Ativos -- os switches TP-Link (ex: TL-SG3428) não têm nenhuma API
            própria em modo standalone, o caminho oficial é sempre via este Controller.
        </p>

        <form method="post" action="<?= url('/administracao/integracoes/omada/salvar') ?>" class="row g-3">
            <div class="col-12">
                <label class="form-label">URL do Controller</label>
                <input type="text" name="url" class="form-control font-monospace" required
                       value="<?= htmlspecialchars($urlAtual) ?>" placeholder="https://192.168.10.17:8043">
            </div>
            <div class="col-12">
                <label class="form-label">Omada ID</label>
                <input type="text" name="omadac_id" class="form-control font-monospace" required
                       value="<?= htmlspecialchars($omadacIdAtual) ?>" placeholder="identificador fixo desta instalação do Controller">
                <div class="form-text">Não tem uma tela própria pra isso no Controller -- peça esse valor a quem configurou a integração pela primeira vez.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Client ID</label>
                <input type="text" name="client_id" class="form-control font-monospace" required
                       value="<?= htmlspecialchars($clientIdAtual) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Client Secret</label>
                <input type="password" name="client_secret" class="form-control font-monospace"
                       placeholder="<?= $configurado ? '••••••••  (deixe em branco pra manter)' : 'gerado em Configurações > Integração de Plataforma > Open API' ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                <?php if ($configurado): ?>
                    <button type="button" class="btn btn-outline-secondary" id="botaoTestarConexaoOmada"><i class="bi bi-plug"></i> Testar conexão</button>
                    <button type="button" class="btn btn-outline-danger" id="botaoRemoverConfigOmada"><i class="bi bi-trash"></i> Remover configuração</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= url('/administracao/integracoes/omada/remover') ?>" id="formRemoverConfigOmada" class="d-none"></form>

        <div class="alert alert-success mt-3 d-none" id="omadaTesteOk"></div>
        <div class="alert alert-danger mt-3 d-none" id="omadaTesteErro"></div>

        <?php if ($configurado && $siteId): ?>
            <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle"></i> Site identificado: <code><?= htmlspecialchars($siteId) ?></code></p>
        <?php elseif ($configurado): ?>
            <p class="text-muted small mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> Site ainda não identificado -- clique em "Testar conexão".</p>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:720px">
    <div class="card-body">
        <strong><i class="bi bi-list-ol"></i> Passo a passo completo</strong>
        <ol class="small text-muted mt-2 mb-0">
            <li class="mb-2">
                <strong>Código atualizado</strong> -- se esta tela é nova pra você, atualize o servidor
                primeiro (via SSH):
                <br><code>cd /var/www/rd.intranet &amp;&amp; git pull --ff-only &amp;&amp; php rd migrate</code>
            </li>
            <li class="mb-2">
                <strong>Omada Controller instalado e com o switch adotado</strong> -- diferente da UniFi
                (Controller já embutido no gateway), switches TP-Link em modo standalone não têm API
                própria nenhuma: precisa existir um Omada Network Application rodando em algum servidor
                (instalação nativa via <code>.deb</code>) com o switch <strong>já adotado</strong> nele --
                não basta aparecer como "descoberto"/pendente. Se um switch específico não trouxer dado
                nenhum de porta/VLAN mesmo com tudo configurado certo, o motivo mais provável é esse:
                confira o status dele dentro do próprio Omada (deve estar "Connected", não "Pending"/
                "Adopt Failed").
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
                <strong>Configure aqui em cima</strong> -- cole URL, Omada ID, Client ID e Client Secret,
                salve, e clique em <strong>"Testar conexão"</strong> -- identifica e grava o site sozinho.
            </li>
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
        </ol>
    </div>
</div>

<script>
(function () {
    const botaoTestar = document.getElementById('botaoTestarConexaoOmada');
    const okBox = document.getElementById('omadaTesteOk');
    const erroBox = document.getElementById('omadaTesteErro');

    if (botaoTestar) {
        botaoTestar.addEventListener('click', async function () {
            okBox.classList.add('d-none');
            erroBox.classList.add('d-none');
            botaoTestar.disabled = true;

            try {
                const res = await fetch(<?= json_encode(url('/administracao/integracoes/omada/testar')) ?>, { method: 'POST' });
                const resultado = await res.json();

                if (resultado.success) {
                    okBox.textContent = resultado.message;
                    okBox.classList.remove('d-none');
                    setTimeout(() => location.reload(), 1200);
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
            if (confirm('Remover a configuração do Omada Controller? A coleta de dados dos switches TP-Link para de funcionar até ser configurada de novo.')) {
                document.getElementById('formRemoverConfigOmada').submit();
            }
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - Omada SDN Controller';

require __DIR__ . '/../layouts/main.php';
