<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-display me-1"></i> Acesso Remoto</h4>
    <small class="text-muted">
        <a href="<?= url('/ativos') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a> ·
        Powered by <a href="https://github.com/Ylianst/MeshCentral" target="_blank">MeshCentral</a> (open source, Apache 2.0) -- não é uma ferramenta construída do zero.
    </small>
</div>

<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong>Status</strong>
            <?php if (!$instalado): ?>
                <?= Badge::make('Não instalado', 'secondary') ?>
            <?php elseif ($rodando): ?>
                <?= Badge::make('Rodando', 'success') ?>
            <?php else: ?>
                <?= Badge::make('Instalado, mas parado', 'warning') ?>
            <?php endif; ?>
        </div>

        <?php if (!$instalado): ?>
            <p class="text-muted small">
                Instala o MeshCentral como serviço próprio (Node.js + systemd), numa porta dedicada
                (<?= (int)$porta ?>), separado do Apache/PHP. Não expõe nada na internet sozinho -- a porta só
                fica alcançável de onde o Firewall permitir.
            </p>
            <button type="button" class="btn btn-primary" id="botaoInstalar">
                <i class="bi bi-download"></i> Instalar
            </button>
        <?php else: ?>
            <p class="text-muted small">
                Console próprio do MeshCentral (criação da conta de administrador, instalador do
                MeshAgent pra baixar e rodar em cada máquina Windows, e configuração de tokens de
                automação usados pela integração com a ficha do ativo):
            </p>
            <a href="<?= htmlspecialchars($urlConsole) ?>" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-box-arrow-up-right"></i> Abrir console do MeshCentral
            </a>
            <p class="text-muted small mt-3 mb-0">
                Na primeira vez, a conta que você criar lá vira automaticamente administrador do
                MeshCentral. Depois de criar, desative o cadastro público de novas contas nas
                configurações dele (não é gerenciado por aqui ainda).
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const botao = document.getElementById('botaoInstalar');
    if (!botao) return;

    botao.addEventListener('click', async function () {
        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Instalando (pode levar um minuto)...';

        try {
            const res = await fetch(<?= json_encode(url('/ativos/acesso-remoto/instalar')) ?>, { method: 'POST' });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Instalado.' : 'Falha ao instalar.'));
            if (resultado.success) location.reload();
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            botao.disabled = false;
            botao.innerHTML = '<i class="bi bi-download"></i> Instalar';
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Acesso Remoto';

require __DIR__ . '/../layouts/main.php';
