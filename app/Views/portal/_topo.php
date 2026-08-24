<style>
body { background: #f4f6f9; }
.portal-topo {
    background: #111827;
    color: #fff;
    padding: 14px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.portal-topo a { color: #d1d5db; text-decoration: none; }
.portal-topo a:hover { color: #fff; }
.portal-container { max-width: 760px; margin: 0 auto; padding: 28px 16px; }
</style>
<div class="portal-topo">
    <span><i class="bi bi-ticket-perforated me-1"></i> <strong>Meus Chamados</strong></span>
    <span>
        <?= htmlspecialchars($solicitante['nome']) ?>
        <a href="<?= url('/portal/chamados/sair') ?>" class="ms-3"><i class="bi bi-box-arrow-right"></i> Sair</a>
    </span>
</div>
