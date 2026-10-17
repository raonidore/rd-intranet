<?php
use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-shield-fill-check me-1" style="color:#ED1C24"></i> Bitdefender GravityZone</h4>
    <small class="text-muted">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-3" style="max-width:900px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong><i class="bi bi-cloud-check"></i> Conexão com o console</strong>
            <?= $configurado ? Badge::make('Configurado', 'success') : Badge::make('Não configurado', 'secondary') ?>
        </div>
        <p class="text-muted small mb-3">
            Uma chave de API do console do GravityZone (corporativo -- o Bitdefender doméstico não tem API). Com a integração ativa,
            aparece <strong>Segurança &gt; Bitdefender</strong> com as máquinas, a quarentena e ações (verificar, restaurar, isolar), e o
            que o Bitdefender coloca em quarentena vira alerta na Central de Segurança.
        </p>

        <form method="post" action="<?= url('/administracao/integracoes/bitdefender/salvar') ?>" class="row g-3">
            <div class="col-12">
                <label class="form-label">Endereço da API</label>
                <input type="text" name="url" class="form-control font-monospace" required value="<?= htmlspecialchars($urlAtual) ?>">
                <div class="form-text">Aparece em <em>Minha conta &gt; Chaves de API</em> no console. Para a nuvem: <code>https://cloud.gravityzone.bitdefender.com/api</code>.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Chave de API</label>
                <input type="password" name="api_key" class="form-control font-monospace" autocomplete="off"
                       placeholder="<?= $configurado ? '••••••••  (deixe em branco para manter)' : 'gerada em Minha conta > Chaves de API' ?>">
                <div class="form-text">Guardada cifrada. Permissões necessárias: <strong>Rede, Quarentena, Licenciamento</strong> (e <strong>Incidentes</strong> para isolar).</div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                <?php if ($configurado): ?>
                    <button type="button" class="btn btn-outline-secondary" id="botaoTestarBd"><i class="bi bi-plug"></i> Testar conexão</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/seguranca/bitdefender') ?>"><i class="bi bi-box-arrow-up-right"></i> Abrir Segurança &gt; Bitdefender</a>
                    <button type="button" class="btn btn-outline-danger" onclick="if (confirm('Remover a integração com o Bitdefender?')) document.getElementById('formRemoverBd').submit()"><i class="bi bi-trash"></i> Remover</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= url('/administracao/integracoes/bitdefender/remover') ?>" id="formRemoverBd" class="d-none"></form>

        <div class="alert alert-success mt-3 d-none" id="bdTesteOk"></div>
        <div class="alert alert-danger mt-3 d-none" id="bdTesteErro"></div>

        <?php if ($configurado): ?>
            <p class="small text-muted mt-3 mb-0">
                <i class="bi bi-arrow-repeat"></i> Sincroniza a cada 15 minutos (cron "Sincronizar Bitdefender GravityZone").
                Última: <?= $ultimaSincronizacao ? htmlspecialchars(data_br($ultimaSincronizacao, 'd/m/Y H:i')) : 'ainda não rodou' ?>.
                <?php if ($ultimoErro): ?><br><span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Último erro: <?= htmlspecialchars($ultimoErro) ?></span><?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:900px">
    <div class="card-body small">
        <strong><i class="bi bi-exclamation-diamond text-warning"></i> Exclusões obrigatórias na política do GravityZone</strong>
        <p class="mt-2 mb-2">O Controle Avançado de Ameaças (ATC) do Bitdefender pode achar suspeito o que o agente RD.Intranet faz (regras de firewall,
            comandos elevados, monitor de conexões) e colocar os arquivos dele em quarentena -- o agente para de funcionar (aconteceu no Enzilab NTO em 16/10/2026).
            Em <em>Políticas &gt; (a política em uso) &gt; Antimalware &gt; Configurações &gt; Exclusões personalizadas</em>, adicione:</p>
        <table class="table table-sm mb-2">
            <thead><tr><th>Tipo</th><th>Exclusão</th><th>Módulos</th></tr></thead>
            <tbody>
                <tr><td>Pasta</td><td><code>C:\RDTecnologia\</code></td><td>On-access, On-demand, ATC/IDS</td></tr>
                <tr><td>Pasta</td><td><code>%ProgramData%\RDIntranetAgent\</code></td><td>On-access, On-demand, ATC/IDS</td></tr>
                <tr><td>Pasta</td><td><code>%LocalAppData%\RDIntranetAgent\</code></td><td>On-access, On-demand, ATC/IDS</td></tr>
                <tr><td>Pasta</td><td><code>%windir%\Temp\RDIntranetAgenteExec\</code></td><td>On-access, ATC/IDS</td></tr>
                <tr><td>Processo</td><td><code>C:\RDTecnologia\RdIntranetAgente.exe</code></td><td>ATC/IDS</td></tr>
            </tbody>
        </table>
        <p class="mb-0 text-muted">Depois, restaure os arquivos do agente em Segurança &gt; Bitdefender &gt; Quarentena (botão "Restaurar").</p>
    </div>
</div>

<script>
(function () {
    const botao = document.getElementById('botaoTestarBd');
    if (!botao) return;
    const ok = document.getElementById('bdTesteOk');
    const erro = document.getElementById('bdTesteErro');
    botao.addEventListener('click', async function () {
        ok.classList.add('d-none'); erro.classList.add('d-none'); botao.disabled = true;
        try {
            const r = await (await fetch(<?= json_encode(url('/administracao/integracoes/bitdefender/testar')) ?>, { method: 'POST' })).json();
            (r.success ? ok : erro).textContent = r.message;
            (r.success ? ok : erro).classList.remove('d-none');
        } catch (e) {
            erro.textContent = 'Falha ao testar: ' + e.message; erro.classList.remove('d-none');
        }
        botao.disabled = false;
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - Bitdefender GravityZone';

require __DIR__ . '/../layouts/main.php';
