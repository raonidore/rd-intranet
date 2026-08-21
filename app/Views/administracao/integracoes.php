<?php

ob_start();
?>

<style>
.metodo-card { text-decoration: none; color: inherit; display: block; height: 100%; }
.metodo-card .card { transition: transform .15s ease, box-shadow .15s ease; }
.metodo-card:hover .card { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.1); }
</style>

<!-- Padrão pra todo novo card de integração: a tela de destino do card
     precisa ter um link "<- Integrações" pra cá no topo (ver
     app/Views/administracao/email.php e
     app/Views/administracao/integracoes_base_conhecimento.php como
     referência) -- sem isso vira beco sem saída, só dá pra voltar pelo
     menu lateral. -->
<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-plug me-1"></i> Integrações</h4>
    <small class="text-muted">Configurações de integração com serviços externos -- só administradores acessam.</small>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <a href="<?= url('/administracao/email') ?>" class="metodo-card">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-2" style="font-size:28px; color:#0d6efd;"><i class="bi bi-envelope"></i></div>
                    <h6 class="mb-1">E-mail</h6>
                    <p class="text-muted small mb-0">
                        Configuração do SMTP usado pelas notificações do sistema (backup, alerta de certificado etc.).
                    </p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url('/administracao/integracoes/base-conhecimento') ?>" class="metodo-card">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-2" style="font-size:28px; color:#6366f1;"><i class="bi bi-journal-text"></i></div>
                    <h6 class="mb-1">Base de Conhecimento</h6>
                    <p class="text-muted small mb-0">
                        URL e chave de API da base central onde os artigos públicos são moderados e distribuídos entre todas as instalações.
                    </p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url('/administracao/integracoes/whatsapp') ?>" class="metodo-card">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-2" style="font-size:28px; color:#25D366;"><i class="bi bi-whatsapp"></i></div>
                    <h6 class="mb-1">WhatsApp</h6>
                    <p class="text-muted small mb-0">
                        Conexão do módulo de Atendimento com o WhatsApp -- QR Code, API Oficial ou Twilio.
                    </p>
                </div>
            </div>
        </a>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações';

require __DIR__ . '/../layouts/main.php';
