<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-wifi me-1"></i> UniFi Network Controller</h4>
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
            Usada pela Ficha do Ativo (botão "Coletar dados UniFi") pra buscar modelo, firmware, status,
            rádios e clientes conectados de pontos de acesso UniFi cadastrados em Ativos -- sem depender de
            SNMP, que não funciona nessa linha de equipamentos.
        </p>

        <form method="post" action="<?= url('/administracao/integracoes/unifi/salvar') ?>" class="row g-3">
            <div class="col-12">
                <label class="form-label">URL do Controller</label>
                <input type="text" name="url" class="form-control font-monospace" required
                       value="<?= htmlspecialchars($urlAtual) ?>" placeholder="https://192.168.10.1">
            </div>
            <div class="col-12">
                <label class="form-label">API Key</label>
                <input type="password" name="api_key" class="form-control font-monospace"
                       placeholder="<?= $configurado ? '••••••••  (deixe em branco pra manter)' : 'chave gerada em Configurações > Integrações, no Controller' ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                <?php if ($configurado): ?>
                    <button type="button" class="btn btn-outline-secondary" id="botaoTestarConexaoUnifi"><i class="bi bi-plug"></i> Testar conexão</button>
                    <button type="button" class="btn btn-outline-danger" id="botaoRemoverConfigUnifi"><i class="bi bi-trash"></i> Remover configuração</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= url('/administracao/integracoes/unifi/remover') ?>" id="formRemoverConfigUnifi" class="d-none"></form>

        <div class="alert alert-success mt-3 d-none" id="unifiTesteOk"></div>
        <div class="alert alert-danger mt-3 d-none" id="unifiTesteErro"></div>

        <?php if ($configurado && $siteId): ?>
            <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle"></i> Site identificado: <code><?= htmlspecialchars($siteId) ?></code></p>
        <?php elseif ($configurado): ?>
            <p class="text-muted small mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> Site ainda não identificado -- clique em "Testar conexão".</p>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-body">
        <strong><i class="bi bi-list-ol"></i> Passo a passo completo</strong>
        <ol class="small text-muted mt-2 mb-0">
            <li class="mb-2">
                <strong>Código atualizado</strong> -- se esta tela é nova pra você, é sinal de que este
                RD.Intranet ainda pode estar em uma versão anterior à da integração. Atualize primeiro
                (via SSH, no servidor):
                <br><code>cd /var/www/rd.intranet &amp;&amp; git pull --ff-only &amp;&amp; php rd migrate</code>
                <br>A migration cria o tipo de ativo "Roteador/Gateway" -- sem ela, esse tipo não aparece
                no cadastro de Ativos.
            </li>
            <li class="mb-2">
                <strong>Gere a API Key no Controller</strong> -- no próprio UniFi Controller (Cloud
                Gateway/Dream Machine), abra <strong>Configurações do Sistema &gt; Integração/Integrations</strong>,
                crie uma chave nova (ex: "RD.Intranet") e copie o valor mostrado -- ele não é exibido de
                novo depois.
            </li>
            <li class="mb-2">
                <strong>Configure aqui em cima</strong> -- cole a URL do Controller (ex:
                <code>https://192.168.10.1</code>) e a API Key, salve, e clique em <strong>"Testar
                conexão"</strong> -- identifica e grava o site sozinho.
            </li>
            <li class="mb-2">
                <strong>Cadastre o equipamento como Ativo</strong> -- em Ativos &gt; Novo Ativo, informe o
                IP de gerenciamento na LAN (não o IP da WAN, no caso de gateway) e clique em
                <strong>"Detectar"</strong> ao lado do campo IP: o sistema já identifica sozinho se é
                Access Point, Switch ou Roteador/Gateway, e preenche marca/modelo/firmware.
            </li>
            <li class="mb-2">
                <strong>Colete os dados completos</strong> -- na ficha do ativo já salvo, clique em
                <strong>"Detectar e coletar automaticamente"</strong>. Pra pontos de acesso, preenche a
                aba "Wi-Fi" (rádios/clientes); pra gateways, a aba "Rede/WAN" (conexões, diagnóstico de
                24h e o seletor failover/balanceamento).
            </li>
            <li>
                <strong>(Opcional) Ative a coleta periódica</strong> -- no Dashboard de Ativos, aba
                "Integrações de rede", botão "Ativar coleta" no card UniFi, pra não precisar clicar
                manualmente a cada equipamento.
            </li>
        </ol>
    </div>
</div>

<script>
(function () {
    const botaoTestar = document.getElementById('botaoTestarConexaoUnifi');
    const okBox = document.getElementById('unifiTesteOk');
    const erroBox = document.getElementById('unifiTesteErro');

    if (botaoTestar) {
        botaoTestar.addEventListener('click', async function () {
            okBox.classList.add('d-none');
            erroBox.classList.add('d-none');
            botaoTestar.disabled = true;

            try {
                const res = await fetch(<?= json_encode(url('/administracao/integracoes/unifi/testar')) ?>, { method: 'POST' });
                const resultado = await res.json();

                if (resultado.success) {
                    okBox.textContent = resultado.message;
                    okBox.classList.remove('d-none');
                    // O site identificado é gravado no servidor, mas o parágrafo "Site
                    // identificado: ..." abaixo é renderizado no carregamento da página --
                    // sem recarregar, ficava mostrando o aviso antigo mesmo com o teste
                    // já tendo dado certo.
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

    const botaoRemover = document.getElementById('botaoRemoverConfigUnifi');
    if (botaoRemover) {
        botaoRemover.addEventListener('click', function () {
            if (confirm('Remover a configuração do UniFi Controller? A coleta de dados dos pontos de acesso para de funcionar até ser configurada de novo.')) {
                document.getElementById('formRemoverConfigUnifi').submit();
            }
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - UniFi Network Controller';

require __DIR__ . '/../layouts/main.php';
