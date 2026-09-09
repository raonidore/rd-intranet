<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-camera-video me-1"></i> DVR/NVR Intelbras</h4>
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
            Usada pela Ficha do Ativo (botão "Detectar e coletar automaticamente") pra buscar modelo,
            número de série, firmware, status do HD e a lista de canais/câmeras de DVR/NVR Intelbras
            cadastrados em Ativos.
        </p>

        <form method="post" action="<?= url('/administracao/integracoes/intelbras-dvr/salvar') ?>" class="row g-3">
            <div class="col-12">
                <label class="form-label">Usuário admin</label>
                <input type="text" name="usuario" class="form-control font-monospace" required
                       value="<?= htmlspecialchars($usuarioAtual) ?>" placeholder="admin">
            </div>
            <div class="col-12">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control font-monospace"
                       placeholder="<?= $configurado ? '••••••••  (deixe em branco pra manter)' : 'senha do usuário acima, a mesma usada pra entrar na interface web do DVR' ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                <?php if ($configurado): ?>
                    <button type="button" class="btn btn-outline-danger" id="botaoRemoverConfigIntelbrasDvr"><i class="bi bi-trash"></i> Remover configuração</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= url('/administracao/integracoes/intelbras-dvr/remover') ?>" id="formRemoverConfigIntelbrasDvr" class="d-none"></form>

        <p class="text-muted small mt-3 mb-0">
            <i class="bi bi-info-circle"></i> Diferente do UniFi/Omada, não existe um "Controller" único
            pra testar a conexão aqui -- essa credencial é aplicada a <strong>todos os DVR/NVR cadastrados
            com IP</strong> (a maioria dos clientes usa a mesma senha admin em todos os equipamentos do
            site). O teste de verdade acontece direto na ficha de cada ativo, ao clicar em "Detectar e
            coletar automaticamente".
        </p>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-body">
        <strong><i class="bi bi-list-ol"></i> Passo a passo completo</strong>
        <ol class="small text-muted mt-2 mb-0">
            <li class="mb-2">
                <strong>Código atualizado</strong> -- se esta tela é nova pra você, atualize o servidor
                primeiro (via SSH):
                <br><code>cd /var/www/rd.intranet &amp;&amp; git pull --ff-only &amp;&amp; php rd migrate</code>
                <br>A migration cria o tipo de ativo "DVR/NVR" -- sem ela, esse tipo não aparece no
                cadastro de Ativos.
            </li>
            <li class="mb-2">
                <strong>Configure aqui em cima</strong> -- usuário e senha admin do DVR (o mesmo login
                usado pra entrar na interface web dele). Salve.
            </li>
            <li class="mb-2">
                <strong>Cadastre o equipamento como Ativo</strong> -- em Ativos &gt; Novo Ativo, informe o
                IP e clique em <strong>"Detectar"</strong> ao lado do campo IP: o sistema identifica
                sozinho e preenche marca/modelo/firmware.
            </li>
            <li class="mb-2">
                <strong>Colete os dados completos</strong> -- na ficha do ativo já salvo, clique em
                <strong>"Detectar e coletar automaticamente"</strong> pra trazer modelo, número de série,
                versão de firmware/hardware, status do HD e a aba "Canais" (nome e status de sinal de cada
                câmera).
            </li>
            <li>
                <strong>(Opcional) Ative a coleta periódica</strong> -- no Dashboard de Ativos, aba
                "Integrações de rede", botão "Ativar coleta" no card DVR/NVR Intelbras.
            </li>
        </ol>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:720px">
    <div class="card-body">
        <strong><i class="bi bi-info-circle"></i> Como isso funciona por baixo dos panos</strong>
        <p class="text-muted small mt-2 mb-0">
            DVR/NVR Intelbras rodam firmware da família Dahua (rebrand) -- confirmado testando ao vivo
            contra um equipamento real: o endpoint <code>/cgi-bin/magicBox.cgi</code> responde
            normalmente, só pedindo autenticação (Digest, com o usuário/senha configurados acima). A
            Intelbras trata a documentação oficial dessa API como confidencial (exige acordo de
            confidencialidade assinado com o CNPJ da empresa pra liberar), mas o protocolo em si é o
            mesmo já documentado publicamente fora dos canais da Intelbras -- por isso construímos essa
            integração testando os comandos direto contra o equipamento, em vez de aguardar
            documentação oficial.
        </p>
    </div>
</div>

<script>
(function () {
    const botaoRemover = document.getElementById('botaoRemoverConfigIntelbrasDvr');
    if (botaoRemover) {
        botaoRemover.addEventListener('click', function () {
            if (confirm('Remover a configuração de DVR/NVR Intelbras? A coleta de dados desses equipamentos para de funcionar até ser configurada de novo.')) {
                document.getElementById('formRemoverConfigIntelbrasDvr').submit();
            }
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - DVR/NVR Intelbras';

require __DIR__ . '/../layouts/main.php';
