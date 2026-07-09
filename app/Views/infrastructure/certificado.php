<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

$cert = $status['certificado'];
$tipoLabel = [
    'autoassinado' => 'Autoassinado',
    'letsencrypt' => "Let's Encrypt",
    'importado' => 'Importado',
    'nenhum' => 'Nenhum',
][$status['tipo']] ?? $status['tipo'];
?>

<style>
.fw-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
.fw-card .card-header { background: #f8fafc; border-bottom: 1px solid #e9ecef; border-radius: 14px 14px 0 0; padding: 14px 20px; }
.metodo-card { text-decoration: none; color: inherit; display: block; height: 100%; }
.metodo-card .card { transition: transform .15s ease, box-shadow .15s ease; }
.metodo-card:hover .card { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.1); }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Certificado Digital (HTTPS)</h4>
        <small class="text-muted">Deixa o acesso ao painel criptografado — hoje o sistema roda em HTTP puro.</small>
    </div>
</div>

<div class="card fw-card">
    <div class="card-header"><i class="bi bi-info-circle me-1"></i> Situação atual</div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="text-muted small">HTTPS ativo</div>
                <div class="fs-5 fw-bold"><?= $status['mod_ssl'] && $status['site_ssl'] ? Badge::make('Sim', 'success') : Badge::make('Não', 'secondary') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Tipo de certificado</div>
                <div class="fs-5 fw-bold"><?= htmlspecialchars($tipoLabel) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">certbot instalado</div>
                <div class="fs-5 fw-bold"><?= $status['certbot_instalado'] ? Badge::make('Sim', 'success') : Badge::make('Não', 'secondary') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Domínio/CN configurado</div>
                <div class="fs-5 fw-bold"><?= htmlspecialchars($status['dominio'] ?: '-') ?></div>
            </div>
        </div>

        <?php if ($cert): ?>
            <?php if ($cert['expirado']): ?>
                <div class="alert alert-danger">
                    <strong><i class="bi bi-exclamation-octagon"></i> Certificado expirado!</strong>
                    Venceu em <?= htmlspecialchars($cert['nao_depois']) ?>. Gere um novo o quanto antes.
                </div>
            <?php elseif ($cert['expirando']): ?>
                <div class="alert alert-warning">
                    <strong><i class="bi bi-exclamation-triangle"></i> Certificado expirando em breve.</strong>
                    Faltam <?= $cert['dias_restantes'] ?> dia(s) (<?= htmlspecialchars($cert['nao_depois']) ?>).
                </div>
            <?php endif; ?>

            <table class="table table-sm mb-0">
                <tr><th style="width:200px">Titular (subject)</th><td class="font-monospace"><?= htmlspecialchars($cert['subject']) ?></td></tr>
                <tr><th>Emissor (issuer)</th><td class="font-monospace"><?= htmlspecialchars($cert['issuer']) ?></td></tr>
                <tr><th>Válido de</th><td class="font-monospace"><?= htmlspecialchars($cert['nao_antes']) ?></td></tr>
                <tr><th>Válido até</th><td class="font-monospace"><?= htmlspecialchars($cert['nao_depois']) ?> (<?= $cert['dias_restantes'] ?> dias)</td></tr>
                <tr><th>Fingerprint (SHA-256)</th><td class="font-monospace small"><?= htmlspecialchars($cert['fingerprint']) ?></td></tr>
            </table>
        <?php else: ?>
            <p class="text-muted mb-0">Nenhum certificado configurado ainda. Escolha uma opção abaixo.</p>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <a href="<?= url('/infraestrutura/certificado/autoassinado') ?>" class="metodo-card">
            <div class="card fw-card h-100">
                <div class="card-body">
                    <div class="mb-2" style="font-size:28px; color:#0d6efd;"><i class="bi bi-pen"></i></div>
                    <h6 class="mb-1">Gerar autoassinado</h6>
                    <p class="text-muted small mb-0">
                        Pronto na hora, sem depender de nada externo. Ideal pra uso só em rede local (LAN) —
                        os navegadores vão mostrar um aviso de "não confiável" na primeira vez, o que é esperado.
                    </p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url('/infraestrutura/certificado/letsencrypt') ?>" class="metodo-card">
            <div class="card fw-card h-100">
                <div class="card-body">
                    <div class="mb-2" style="font-size:28px; color:#22c55e;"><i class="bi bi-award"></i></div>
                    <h6 class="mb-1">Let's Encrypt (grátis, confiável)</h6>
                    <p class="text-muted small mb-0">
                        Certificado real, reconhecido pelos navegadores, sem custo. Só funciona se este servidor tiver um
                        <strong>domínio público</strong> apontando pra ele (não funciona só com IP de rede local).
                    </p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url('/infraestrutura/certificado/importar') ?>" class="metodo-card">
            <div class="card fw-card h-100">
                <div class="card-body">
                    <div class="mb-2" style="font-size:28px; color:#6366f1;"><i class="bi bi-upload"></i></div>
                    <h6 class="mb-1">Importar certificado próprio</h6>
                    <p class="text-muted small mb-0">
                        Já tem um certificado comprado ou emitido pela CA da sua empresa? Envie o arquivo (.crt/.pem) e a chave (.key).
                    </p>
                </div>
            </div>
        </a>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Certificado Digital';

require __DIR__ . '/../layouts/main.php';
