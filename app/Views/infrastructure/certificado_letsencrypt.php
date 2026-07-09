<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-award"></i> Certificado Let's Encrypt</h5>
        <small class="text-muted">Grátis, reconhecido pelos navegadores — sem avisos de "não confiável".</small>
    </div>

    <div class="card-body">
        <?php if (!$status['certbot_instalado']): ?>
            <div class="alert alert-danger">
                <strong><i class="bi bi-x-circle"></i> O certbot não está instalado neste servidor.</strong>
                Instale pelo <a href="<?= url('/infraestrutura/dependencias') ?>">checklist de dependências</a> antes de continuar.
            </div>
        <?php endif; ?>

        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle"></i>
            Só funciona se este servidor tiver um <strong>domínio público de verdade</strong> (ex: <code>painel.suaempresa.com.br</code>)
            já apontando pro IP deste servidor, com a <strong>porta 80 acessível pela internet</strong> — o Let's Encrypt precisa
            conseguir acessar <code>http://SEU-DOMINIO/.well-known/acme-challenge/...</code> de fora pra confirmar que você é dono
            do domínio. Servidor só em rede local (LAN)? Use o certificado autoassinado em vez deste.
        </div>

        <form method="post" action="<?= url('/infraestrutura/certificado/letsencrypt') ?>"
              onsubmit="return confirm('Solicitar certificado Let\'s Encrypt para este domínio agora?');">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Domínio público</label>
                    <input type="text" name="dominio" class="form-control font-monospace" required
                           placeholder="painel.suaempresa.com.br">
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail (avisos de expiração/renovação)</label>
                    <input type="email" name="email" class="form-control" required
                           placeholder="ti@suaempresa.com.br">
                </div>
            </div>

            <p class="text-muted small">A renovação automática é configurada junto — o certificado se renova sozinho antes de vencer.</p>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/infraestrutura/certificado') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary" <?= !$status['certbot_instalado'] ? 'disabled' : '' ?>>
                    <i class="bi bi-check-lg"></i> Obter certificado
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = "Certificado Let's Encrypt";

require __DIR__ . '/../layouts/main.php';
