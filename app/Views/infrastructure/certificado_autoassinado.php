<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-pen"></i> Gerar certificado autoassinado</h5>
        <small class="text-muted">Fica pronto na hora. Ideal para uso apenas em rede local.</small>
    </div>

    <div class="card-body">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle"></i>
            Como o certificado é autoassinado (não emitido por uma autoridade reconhecida), o navegador vai mostrar um
            aviso de "conexão não segura"/"certificado não confiável" na primeira vez que alguém acessar via HTTPS —
            isso é esperado e normal; basta confirmar/avançar para continuar. Se quiser um certificado sem esse aviso,
            use Let's Encrypt (precisa de domínio público) ou importe um certificado comprado.
        </div>

        <form method="post" action="<?= url('/infraestrutura/certificado/autoassinado') ?>"
              onsubmit="return confirm('Gerar novo certificado autoassinado e ativar HTTPS?');">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome (hostname) do servidor</label>
                    <input type="text" name="cn" class="form-control font-monospace" required
                           placeholder="rd.intranet" value="rd.intranet">
                    <small class="text-muted">Nome que os usuários digitam no navegador pra acessar.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">IP adicional (opcional)</label>
                    <select name="ip_extra" class="form-select">
                        <option value="">(nenhum)</option>
                        <?php foreach ($ips as $ip): ?>
                            <option value="<?= htmlspecialchars($ip) ?>"><?= htmlspecialchars($ip) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Cobre acesso direto por IP também (ex: https://192.168.1.15).</small>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/infraestrutura/certificado') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Gerar e ativar HTTPS
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Gerar Certificado Autoassinado';

require __DIR__ . '/../layouts/main.php';
