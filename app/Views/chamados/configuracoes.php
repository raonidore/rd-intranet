<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-gear me-1"></i> Chamados - Configurações</h4>
</div>

<div class="card border-0 shadow-sm" style="max-width:560px">
    <div class="card-header bg-white"><strong>Horário de expediente</strong></div>
    <div class="card-body">
        <div class="form-text mb-3">Horário próprio do módulo Chamados (pode ser diferente do WhatsApp). Usado como referência do horário de atendimento -- prazos de SLA hoje são calculados de forma corrida (ver observação abaixo).</div>

        <form method="post" action="<?= url('/chamados/configuracoes/expediente') ?>">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="ativo" id="campoExpedienteAtivo" <?= $expedienteAtivo ? 'checked' : '' ?>>
                <label class="form-check-label" for="campoExpedienteAtivo">Ter um horário de expediente definido</label>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">Início</label>
                    <input type="time" name="inicio" class="form-control" value="<?= htmlspecialchars($expedienteInicio) ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Fim</label>
                    <input type="time" name="fim" class="form-control" value="<?= htmlspecialchars($expedienteFim) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:560px">
    <div class="card-header bg-white"><strong>Distribuição automática da fila</strong></div>
    <div class="card-body">
        <div class="form-text mb-3">Chamado parado na fila (sem ninguém assumir) há mais tempo que o prazo abaixo é atribuído sozinho ao atendente com menos chamados em atendimento no setor -- só funciona pra chamado que já tem um setor definido.</div>

        <form method="post" action="<?= url('/chamados/configuracoes/distribuicao') ?>">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="ativo" id="campoDistribuicaoAtiva" <?= $distribuicaoAtiva ? 'checked' : '' ?>>
                <label class="form-check-label" for="campoDistribuicaoAtiva">Distribuir automaticamente</label>
            </div>
            <div class="mb-3">
                <label class="form-label">Depois de quantos minutos na fila</label>
                <input type="number" name="minutos" class="form-control" style="max-width:120px" min="1" value="<?= (int)$distribuicaoMinutos ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chamados - Configurações';

require __DIR__ . '/../layouts/main.php';
