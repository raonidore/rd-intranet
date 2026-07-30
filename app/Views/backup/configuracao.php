<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

$rotuloProvider = fn(string $p) => match ($p) {
    'b2' => 'Backblaze B2',
    's3' => 'Amazon S3',
    'drive' => 'Google Drive',
    default => $p,
};

$corProvider = fn(string $p) => match ($p) {
    'b2' => 'info',
    's3' => 'warning',
    'drive' => 'success',
    default => 'secondary',
};

$formatarBytes = function (int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($unidades) - 1);
    return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
};

$jobsPorNomeDestino = [];
foreach ($jobsCron as $job) {
    if (str_starts_with($job['nome'], 'Backup em nuvem - ')) {
        $jobsPorNomeDestino[substr($job['nome'], strlen('Backup em nuvem - '))] = $job;
    }
}
?>

<style>
/* mesmo "cron builder" visual de infraestrutura/cron/novo (cron_form.php),
   reaproveitado aqui pro agendamento do backup -- duplicado (nao
   compartilhado) porque cada view deste projeto carrega seu proprio
   <style> junto do conteudo, sem folha de estilo global por modulo. */
.cron-builder-tabs { display: inline-flex; gap: 4px; background: #f1f3f5; border-radius: 10px; padding: 4px; }
.cron-tab {
    border: 0; background: transparent; padding: 6px 16px; border-radius: 8px;
    font-size: 14px; color: #495057; cursor: pointer; transition: all .15s ease;
}
.cron-tab.active { background: #fff; color: #0d6efd; box-shadow: 0 1px 3px rgba(0,0,0,.12); font-weight: 600; }
.cron-dias { display: flex; gap: 6px; flex-wrap: wrap; }
.cron-dia-btn { position: relative; cursor: pointer; user-select: none; }
.cron-dia-btn input { position: absolute; opacity: 0; width: 0; height: 0; }
.cron-dia-btn span {
    display: inline-flex; align-items: center; justify-content: center;
    width: 44px; height: 38px; border-radius: 8px; border: 1px solid #ced4da;
    font-size: 13px; color: #495057; transition: all .15s ease;
}
.cron-dia-btn input:checked + span { background: #0d6efd; border-color: #0d6efd; color: #fff; font-weight: 600; }
.cron-preview {
    background: #eef6ff; border: 1px solid #cfe4fd; border-radius: 8px;
    padding: 10px 14px; font-size: 14px; color: #0b5ed7;
}
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-cloud-arrow-up me-1"></i> Backup em Nuvem</h4>
        <small class="text-muted">Espelha os compartilhamentos do Samba para Backblaze B2, Amazon S3 ou Google Drive.</small>
    </div>
    <button type="button" class="btn btn-primary" id="botaoNovoDestino">
        <i class="bi bi-plus-lg"></i> Novo destino
    </button>
</div>

<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <i class="bi bi-info-circle"></i>
        Cada destino abaixo é só a credencial de nuvem. <strong>O que</strong> é enviado é definido em
        <a href="<?= url('/samba/compartilhamentos') ?>">Samba &gt; Compartilhamentos</a>, marcando o botão
        "Backup nuvem" em cada compartilhamento -- dá pra escolher vários diretórios específicos.
    </div>
    <span class="badge text-bg-<?= empty($compartilhamentosAtivos) ? 'secondary' : 'success' ?>">
        <?= count($compartilhamentosAtivos) ?> compartilhamento(s) ativo(s)
    </span>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <strong><i class="bi bi-question-circle"></i> Como funciona o envio</strong>
        <ul class="small text-muted mb-0 mt-2 ps-3">
            <li>
                <strong>É incremental, não reenvia tudo toda vez:</strong> a cada execução, o sistema compara
                cada arquivo local com o que já está na nuvem (tamanho e data de modificação) e só transfere
                o que é novo ou foi alterado desde o último backup. Um agendamento diário, por exemplo, depois
                do primeiro envio completo, passa a subir só as mudanças do dia.
            </li>
            <li class="mt-1">
                <strong>Nada é apagado da nuvem por engano:</strong> se um arquivo for removido ou sobrescrito
                localmente, a versão anterior não é excluída no destino -- ela é movida para uma pasta datada
                (<code>.versoes/</code>), preservada pelo prazo de retenção configurado em cada destino. Isso
                protege contra exclusão acidental ou um eventual ataque de ransomware que criptografe os
                arquivos locais.
            </li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong><i class="bi bi-hdd-rack"></i> Destinos configurados</strong>
    </div>
    <div class="card-body p-0">
        <?php if (empty($destinos)): ?>
            <div class="text-center text-muted py-5">
                Nenhum destino de backup cadastrado ainda. Clique em "Novo destino" para começar.
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Provedor</th>
                        <th>Retenção</th>
                        <th>Ativo</th>
                        <th>Agendamento</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($destinos as $d): ?>
                        <?php $job = $jobsPorNomeDestino[$d['nome']] ?? null; ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($d['nome']) ?>
                                <?php if ($d['relatorio_diario_ativo'] || $d['alerta_falha_ativo']): ?>
                                    <i class="bi bi-envelope-check text-primary ms-1"
                                       title="Notifica <?= htmlspecialchars($d['email_notificacao'] ?? '') ?>
                                           <?= $d['relatorio_diario_ativo'] ? '-- relatório diário' : '' ?>
                                           <?= $d['alerta_falha_ativo'] ? '-- alerta de falha' : '' ?>"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= Badge::make($rotuloProvider($d['provider']), $corProvider($d['provider'])) ?></td>
                            <td><?= (int)$d['retencao_dias'] ?> dias</td>
                            <td>
                                <?= $d['ativo'] ? Badge::make('Ativo', 'success') : Badge::make('Inativo', 'secondary') ?>
                            </td>
                            <td class="small">
                                <?php if ($job): ?>
                                    <i class="bi bi-check-circle text-success"></i> <?= htmlspecialchars($job['expressao']) ?>
                                    <a href="<?= url('/infraestrutura/cron/editar?id=' . (int)$job['id']) ?>" class="ms-1" title="Editar em Infraestrutura > Cron">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Não agendado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary botao-rodar-agora" data-id="<?= (int)$d['id'] ?>" data-nome="<?= htmlspecialchars($d['nome']) ?>">
                                    <i class="bi bi-play-fill"></i> Rodar agora
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary botao-editar" data-destino='<?= json_encode($d) ?>'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary botao-agendar"
                                        data-id="<?= (int)$d['id'] ?>" data-nome="<?= htmlspecialchars($d['nome']) ?>"
                                        data-expressao="<?= htmlspecialchars($job['expressao'] ?? '') ?>"
                                        title="<?= $job ? 'Editar agendamento' : 'Agendar' ?>">
                                    <i class="bi <?= $job ? 'bi-calendar-check' : 'bi-calendar-plus' ?>"></i>
                                </button>
                                <?php if (!$d['ativo']): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary botao-ativar" data-id="<?= (int)$d['id'] ?>">
                                    <i class="bi bi-star"></i>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-danger botao-excluir" data-id="<?= (int)$d['id'] ?>" data-nome="<?= htmlspecialchars($d['nome']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4 d-none" id="painelProgresso">
    <div class="card-header bg-white">
        <strong><i class="bi bi-arrow-repeat"></i> Backup em andamento -- <span id="progressoNomeDestino"></span></strong>
    </div>
    <div class="card-body">
        <p class="mb-2 small text-muted" id="progressoTexto">
            Enviando arquivos (isso roda em segundo plano -- pode navegar para outra tela, o backup continua mesmo assim)...
        </p>
        <div class="progress" style="height:22px">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressoBarra" role="progressbar" style="width:0%">0%</div>
        </div>
        <div class="alert alert-danger mt-3 d-none" id="progressoErro">
            <div id="progressoErroAmigavel"></div>
            <div class="small text-muted mt-1" id="progressoErroTecnico"></div>
        </div>
        <div class="alert alert-success mt-3 d-none" id="progressoSucesso"></div>
    </div>
</div>

<a href="<?= url('/backup/historico') ?>" class="btn btn-outline-secondary">
    <i class="bi bi-clock-history"></i> Ver histórico de execuções
</a>

<!-- Modal criar/editar destino -->
<div class="modal fade" id="modalDestino" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formDestino">
                <input type="hidden" name="id" id="campoId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDestinoTitulo">Novo destino de backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" name="nome" id="campoNome" required placeholder="Ex.: Cliente XPTO - Backblaze">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Provedor</label>
                            <select class="form-select" name="provider" id="campoProvider">
                                <option value="b2">Backblaze B2</option>
                                <option value="s3">Amazon S3</option>
                                <option value="drive">Google Drive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Retenção de versões (dias)</label>
                            <input type="number" min="1" class="form-control" name="retencao_dias" id="campoRetencaoDias" value="30">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ativo" id="campoAtivo" value="1" checked>
                                <label class="form-check-label" for="campoAtivo">
                                    Marcar como destino ativo (usado pelo agendamento automático)
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <?php if (!$emailConfigurado): ?>
                    <div class="form-text mb-2">
                        <i class="bi bi-info-circle"></i>
                        Pra usar os avisos por e-mail abaixo, configure o SMTP em
                        <a href="<?= url('/administracao/email') ?>" target="_blank">Sistema &gt; E-mail</a> primeiro.
                    </div>
                    <?php endif; ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input campo-notificacao-flag" type="checkbox" name="relatorio_diario_ativo" id="campoRelatorioDiario" value="1">
                                <label class="form-check-label" for="campoRelatorioDiario">Enviar relatório por e-mail a cada backup concluído</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input campo-notificacao-flag" type="checkbox" name="alerta_falha_ativo" id="campoAlertaFalha" value="1">
                                <label class="form-check-label" for="campoAlertaFalha">Enviar alerta por e-mail em caso de falha</label>
                            </div>
                        </div>
                        <div class="col-12 d-none" id="grupoEmailNotificacao">
                            <label class="form-label">E-mail(s) de notificação</label>
                            <div id="listaEmailsNotificacao" class="d-flex flex-column gap-2 mb-2"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoAdicionarEmail">
                                <i class="bi bi-plus-lg"></i> Adicionar e-mail
                            </button>
                            <div class="form-text">Cada e-mail adicionado recebe o mesmo relatório/alerta.</div>
                        </div>
                    </div>

                    <hr>

                    <div id="grupoB2" class="row g-3">
                        <div class="col-12">
                            <div class="form-text mb-2">
                                Em <a href="https://secure.backblaze.com/b2_buckets.htm" target="_blank" rel="noopener">secure.backblaze.com</a>:
                                (1) crie um <strong>Bucket</strong> como <strong>Private</strong>;
                                (2) em <strong>Application Keys &gt; Add a New Application Key</strong>, restrinja "Allow access to Bucket(s)"
                                a esse bucket e marque <strong>Read and Write</strong>;
                                (3) copie o <code>keyID</code> e o <code>applicationKey</code> gerados (o applicationKey só aparece uma vez)
                                e cole abaixo, junto com o nome exato do bucket.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Key ID</label>
                            <input type="text" class="form-control" name="b2_key_id" id="campoB2KeyId">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Application Key</label>
                            <input type="password" class="form-control" name="b2_application_key" id="campoB2AppKey" placeholder="deixe em branco para manter a atual">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Bucket</label>
                            <input type="text" class="form-control" name="b2_bucket" id="campoB2Bucket">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Prefixo (opcional)</label>
                            <input type="text" class="form-control" name="b2_prefixo" id="campoB2Prefixo" placeholder="ex.: rd-backup">
                        </div>
                    </div>

                    <div id="grupoS3" class="row g-3 d-none">
                        <div class="col-md-6">
                            <label class="form-label">Access Key ID</label>
                            <input type="text" class="form-control" name="s3_access_key_id" id="campoS3AccessKey">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Secret Access Key</label>
                            <input type="password" class="form-control" name="s3_secret_access_key" id="campoS3SecretKey" placeholder="deixe em branco para manter a atual">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Bucket</label>
                            <input type="text" class="form-control" name="s3_bucket" id="campoS3Bucket">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Região</label>
                            <input type="text" class="form-control" name="s3_regiao" id="campoS3Regiao" placeholder="ex.: us-east-1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Endpoint (opcional)</label>
                            <input type="text" class="form-control" name="s3_endpoint" id="campoS3Endpoint" placeholder="s3-compatível não-AWS">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Prefixo (opcional)</label>
                            <input type="text" class="form-control" name="s3_prefixo" id="campoS3Prefixo" placeholder="ex.: rd-backup">
                        </div>
                    </div>

                    <div id="grupoDrive" class="row g-3 d-none">
                        <div class="col-12">
                            <div class="form-text mb-2">
                                O Google Drive usa OAuth: numa máquina com navegador, rode
                                <code>rclone authorize "drive"</code>, autorize a conta que vai receber o backup e cole
                                abaixo o token JSON gerado (isso só precisa ser feito uma vez).
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client ID (opcional)</label>
                            <input type="text" class="form-control" name="drive_client_id" id="campoDriveClientId">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Secret (opcional)</label>
                            <input type="password" class="form-control" name="drive_client_secret" id="campoDriveClientSecret" placeholder="deixe em branco para manter a atual">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Token (rclone authorize drive)</label>
                            <textarea class="form-control font-monospace" name="drive_token" id="campoDriveToken" rows="3" placeholder="deixe em branco para manter o atual"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">ID da pasta no Drive (opcional)</label>
                            <input type="text" class="form-control" name="drive_pasta_id" id="campoDrivePastaId" placeholder="restringe o backup a uma pasta específica">
                        </div>
                    </div>

                    <div class="alert alert-danger mt-3 d-none" id="destinoErro"></div>
                    <div class="alert alert-success mt-3 d-none" id="destinoTesteOk"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="botaoTestarConexao">
                        <i class="bi bi-plug"></i> Testar conexão
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="botaoSalvarDestino">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal agendar -->
<div class="modal fade" id="modalAgendar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAgendarTitulo">Agendar backup automático</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Destino: <strong id="agendarNomeDestino"></strong></p>

                <div class="cron-builder-tabs mb-3" role="tablist">
                    <button type="button" class="cron-tab active" data-alvo="visual">
                        <i class="bi bi-mouse2"></i> Visual
                    </button>
                    <button type="button" class="cron-tab" data-alvo="manual">
                        <i class="bi bi-code-slash"></i> Manual
                    </button>
                </div>

                <div id="agendarPainelVisual" class="cron-painel">
                    <select id="agendarFrequencia" class="form-select mb-3">
                        <option value="n-minutos">A cada X minutos</option>
                        <option value="hora">A cada hora, num minuto fixo</option>
                        <option value="diario" selected>Todo dia, num horário</option>
                        <option value="semanal">Toda semana, em dias específicos</option>
                        <option value="mensal">Todo mês, num dia específico</option>
                    </select>

                    <div data-painel="n-minutos" class="cron-subpainel row g-2 align-items-center" style="display:none">
                        <div class="col-auto">A cada</div>
                        <div class="col-auto">
                            <input type="number" id="agendarNMinutos" class="form-control" style="width:90px" min="2" max="59" value="30">
                        </div>
                        <div class="col-auto">minutos</div>
                    </div>

                    <div data-painel="hora" class="cron-subpainel row g-2 align-items-center" style="display:none">
                        <div class="col-auto">No minuto</div>
                        <div class="col-auto">
                            <input type="number" id="agendarMinutoHora" class="form-control" style="width:90px" min="0" max="59" value="0">
                        </div>
                        <div class="col-auto">de cada hora</div>
                    </div>

                    <div data-painel="diario" class="cron-subpainel row g-2 align-items-center">
                        <div class="col-auto">Às</div>
                        <div class="col-auto">
                            <input type="time" id="agendarHorarioDiario" class="form-control" value="03:00">
                        </div>
                    </div>

                    <div data-painel="semanal" class="cron-subpainel" style="display:none">
                        <div class="cron-dias mb-2">
                            <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $i => $dia): ?>
                                <label class="cron-dia-btn">
                                    <input type="checkbox" class="cron-dia-semana" value="<?= $i ?>" <?= $i >= 1 && $i <= 5 ? 'checked' : '' ?>>
                                    <span><?= $dia ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">Às</div>
                            <div class="col-auto">
                                <input type="time" id="agendarHorarioSemanal" class="form-control" value="03:00">
                            </div>
                        </div>
                    </div>

                    <div data-painel="mensal" class="cron-subpainel row g-2 align-items-center" style="display:none">
                        <div class="col-auto">No dia</div>
                        <div class="col-auto">
                            <input type="number" id="agendarDiaMes" class="form-control" style="width:90px" min="1" max="31" value="1">
                        </div>
                        <div class="col-auto">de cada mês, às</div>
                        <div class="col-auto">
                            <input type="time" id="agendarHorarioMensal" class="form-control" value="03:00">
                        </div>
                    </div>

                    <div class="cron-preview mt-3">
                        <i class="bi bi-info-circle"></i> <span id="agendarPreviewTexto">Executa todo dia às 03:00.</span>
                    </div>
                </div>

                <div id="agendarPainelManual" class="cron-painel" style="display:none">
                    <input type="text" class="form-control font-monospace" id="agendarManualInput" placeholder="0 3 * * *">
                    <small class="text-muted d-block mt-1">
                        5 campos (min hora dia mês dia-semana) ou um atalho como <code>@daily</code>.
                    </small>
                </div>

                <input type="hidden" id="campoExpressaoCron" value="0 3 * * *">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="botaoConfirmarAgendar">Agendar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URLS = {
        salvar: <?= json_encode(url('/backup/configuracao/salvar')) ?>,
        excluir: <?= json_encode(url('/backup/configuracao/excluir')) ?>,
        ativar: <?= json_encode(url('/backup/configuracao/ativar')) ?>,
        testar: <?= json_encode(url('/backup/configuracao/testar')) ?>,
        agendar: <?= json_encode(url('/backup/configuracao/agendar')) ?>,
        executar: <?= json_encode(url('/backup/executar')) ?>,
        status: <?= json_encode(url('/backup/status')) ?>,
    };

    // bootstrap.bundle.min.js so carrega no rodape do layout, depois deste
    // <script> -- instanciar o Modal aqui em cima (no parse do script)
    // lancaria "bootstrap is not defined" e travaria toda a IIFE, inclusive
    // os addEventListener dos botoes. Por isso getOrCreateInstance() e
    // chamado soo dentro dos handlers de clique (mesmo padrao ja usado em
    // antivirus.php/compartilhamento_usuarios.php).
    function modal(id) {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
    }

    const form = document.getElementById('formDestino');
    const erroBox = document.getElementById('destinoErro');
    const testeOkBox = document.getElementById('destinoTesteOk');

    const grupos = { b2: document.getElementById('grupoB2'), s3: document.getElementById('grupoS3'), drive: document.getElementById('grupoDrive') };

    function mostrarGrupo(provider) {
        Object.keys(grupos).forEach(function (p) {
            grupos[p].classList.toggle('d-none', p !== provider);
        });
    }

    document.getElementById('campoProvider').addEventListener('change', function () {
        mostrarGrupo(this.value);
    });

    const grupoEmailNotificacao = document.getElementById('grupoEmailNotificacao');
    const listaEmailsNotificacao = document.getElementById('listaEmailsNotificacao');

    function atualizarGrupoEmailNotificacao() {
        const algumaFlagAtiva = document.getElementById('campoRelatorioDiario').checked || document.getElementById('campoAlertaFalha').checked;
        grupoEmailNotificacao.classList.toggle('d-none', !algumaFlagAtiva);
    }

    document.querySelectorAll('.campo-notificacao-flag').forEach(function (campo) {
        campo.addEventListener('change', atualizarGrupoEmailNotificacao);
    });

    function adicionarLinhaEmail(valor) {
        const linha = document.createElement('div');
        linha.className = 'input-group input-group-sm linha-email-notificacao';
        linha.innerHTML =
            '<input type="email" class="form-control" name="email_notificacao[]" placeholder="contato@cliente.com.br">' +
            '<button type="button" class="btn btn-outline-danger botao-remover-email" title="Remover"><i class="bi bi-x-lg"></i></button>';

        linha.querySelector('input').value = valor || '';

        linha.querySelector('.botao-remover-email').addEventListener('click', function () {
            // sempre mantem pelo menos 1 linha visivel -- se so sobrar essa, so limpa o valor em vez de remover
            if (listaEmailsNotificacao.querySelectorAll('.linha-email-notificacao').length <= 1) {
                linha.querySelector('input').value = '';
                return;
            }
            linha.remove();
        });

        listaEmailsNotificacao.appendChild(linha);
    }

    function definirEmailsNotificacao(emailsCsv) {
        listaEmailsNotificacao.innerHTML = '';
        const emails = (emailsCsv || '').split(',').map(function (e) { return e.trim(); }).filter(Boolean);

        if (emails.length === 0) {
            adicionarLinhaEmail('');
        } else {
            emails.forEach(adicionarLinhaEmail);
        }
    }

    document.getElementById('botaoAdicionarEmail').addEventListener('click', function () {
        adicionarLinhaEmail('');
    });

    function limparForm() {
        form.reset();
        document.getElementById('campoId').value = '';
        mostrarGrupo('b2');
        definirEmailsNotificacao('');
        atualizarGrupoEmailNotificacao();
        erroBox.classList.add('d-none');
        testeOkBox.classList.add('d-none');
    }

    document.getElementById('botaoNovoDestino').addEventListener('click', function () {
        limparForm();
        document.getElementById('modalDestinoTitulo').textContent = 'Novo destino de backup';
        modal('modalDestino').show();
    });

    document.querySelectorAll('.botao-editar').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const d = JSON.parse(botao.dataset.destino);
            limparForm();
            document.getElementById('modalDestinoTitulo').textContent = 'Editar destino de backup';
            document.getElementById('campoId').value = d.id;
            document.getElementById('campoNome').value = d.nome;
            document.getElementById('campoProvider').value = d.provider;
            document.getElementById('campoRetencaoDias').value = d.retencao_dias;
            document.getElementById('campoAtivo').checked = !!Number(d.ativo);
            document.getElementById('campoB2KeyId').value = d.b2_key_id || '';
            document.getElementById('campoB2Bucket').value = d.b2_bucket || '';
            document.getElementById('campoB2Prefixo').value = d.b2_prefixo || '';
            document.getElementById('campoS3AccessKey').value = d.s3_access_key_id || '';
            document.getElementById('campoS3Bucket').value = d.s3_bucket || '';
            document.getElementById('campoS3Regiao').value = d.s3_regiao || '';
            document.getElementById('campoS3Endpoint').value = d.s3_endpoint || '';
            document.getElementById('campoS3Prefixo').value = d.s3_prefixo || '';
            document.getElementById('campoDriveClientId').value = d.drive_client_id || '';
            document.getElementById('campoDrivePastaId').value = d.drive_pasta_id || '';
            document.getElementById('campoRelatorioDiario').checked = !!Number(d.relatorio_diario_ativo);
            document.getElementById('campoAlertaFalha').checked = !!Number(d.alerta_falha_ativo);
            definirEmailsNotificacao(d.email_notificacao || '');
            atualizarGrupoEmailNotificacao();
            mostrarGrupo(d.provider);
            modal('modalDestino').show();
        });
    });

    document.getElementById('botaoTestarConexao').addEventListener('click', async function () {
        erroBox.classList.add('d-none');
        testeOkBox.classList.add('d-none');

        const botao = this;
        botao.disabled = true;
        try {
            const res = await fetch(URLS.testar, { method: 'POST', body: new FormData(form) });
            const dados = await res.json();

            if (dados.success) {
                testeOkBox.textContent = dados.message;
                testeOkBox.classList.remove('d-none');
            } else {
                erroBox.textContent = dados.message;
                erroBox.classList.remove('d-none');
            }
        } catch (e) {
            erroBox.textContent = 'Erro de rede ao testar a conexão.';
            erroBox.classList.remove('d-none');
        } finally {
            botao.disabled = false;
        }
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        erroBox.classList.add('d-none');
        testeOkBox.classList.add('d-none');

        const botao = document.getElementById('botaoSalvarDestino');
        botao.disabled = true;
        try {
            const res = await fetch(URLS.salvar, { method: 'POST', body: new FormData(form) });
            const dados = await res.json();

            if (!dados.success) {
                erroBox.textContent = dados.message;
                erroBox.classList.remove('d-none');
                return;
            }

            location.reload();
        } catch (e) {
            erroBox.textContent = 'Erro de rede ao salvar o destino.';
            erroBox.classList.remove('d-none');
        } finally {
            botao.disabled = false;
        }
    });

    document.querySelectorAll('.botao-excluir').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            if (!confirm('Excluir o destino "' + botao.dataset.nome + '"? O histórico de execuções é mantido.')) return;

            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            await fetch(URLS.excluir, { method: 'POST', body: dados });
            location.reload();
        });
    });

    document.querySelectorAll('.botao-ativar').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            await fetch(URLS.ativar, { method: 'POST', body: dados });
            location.reload();
        });
    });

    // --- Construtor visual do agendamento (mesmo mecanismo do "cron
    // builder" de infraestrutura/cron/novo, ver cron_form.php) -- SEM bloco
    // (nem IIFE) proprio de proposito, diferente de cron_form.php: fica no
    // MESMO escopo do handler do botao "Agendar" mais abaixo, que precisa
    // chamar preencherAgendamentoExistente() pra reabrir o modal ja
    // preenchido quando o destino ja tem agendamento.
    const DIAS_NOME = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    const expressaoFinal = document.getElementById('campoExpressaoCron');
        const frequencia = document.getElementById('agendarFrequencia');
        const manualInput = document.getElementById('agendarManualInput');
        const preview = document.getElementById('agendarPreviewTexto');
        const abas = document.querySelectorAll('#modalAgendar .cron-tab');
        const painelVisual = document.getElementById('agendarPainelVisual');
        const painelManual = document.getElementById('agendarPainelManual');

        function pad(n) { return String(n).padStart(2, '0'); }

        function horaMinuto(inputId) {
            const [h, m] = (document.getElementById(inputId).value || '00:00').split(':');
            return { h: parseInt(h, 10) || 0, m: parseInt(m, 10) || 0 };
        }

        function diasSelecionados() {
            return Array.from(document.querySelectorAll('#modalAgendar .cron-dia-semana:checked')).map(function (c) { return parseInt(c.value, 10); });
        }

        function construirExpressao() {
            switch (frequencia.value) {
                case 'n-minutos': {
                    const n = Math.min(59, Math.max(2, parseInt(document.getElementById('agendarNMinutos').value, 10) || 5));
                    return { expr: `*/${n} * * * *`, texto: `Executa a cada ${n} minutos.` };
                }
                case 'hora': {
                    const m = Math.min(59, Math.max(0, parseInt(document.getElementById('agendarMinutoHora').value, 10) || 0));
                    return { expr: `${m} * * * *`, texto: `Executa a cada hora, no minuto ${pad(m)}.` };
                }
                case 'diario': {
                    const { h, m } = horaMinuto('agendarHorarioDiario');
                    return { expr: `${m} ${h} * * *`, texto: `Executa todo dia às ${pad(h)}:${pad(m)}.` };
                }
                case 'semanal': {
                    const { h, m } = horaMinuto('agendarHorarioSemanal');
                    const dias = diasSelecionados();
                    if (dias.length === 0) {
                        return { expr: `${m} ${h} * * *`, texto: 'Selecione ao menos um dia da semana.', invalido: true };
                    }
                    const nomes = dias.slice().sort().map(function (d) { return DIAS_NOME[d]; }).join(', ');
                    return { expr: `${m} ${h} * * ${dias.join(',')}`, texto: `Executa às ${pad(h)}:${pad(m)}, toda(s): ${nomes}.` };
                }
                case 'mensal': {
                    const { h, m } = horaMinuto('agendarHorarioMensal');
                    const dia = Math.min(31, Math.max(1, parseInt(document.getElementById('agendarDiaMes').value, 10) || 1));
                    return { expr: `${m} ${h} ${dia} * *`, texto: `Executa todo mês no dia ${dia}, às ${pad(h)}:${pad(m)}.` };
                }
            }
        }

        function atualizarPreview() {
            const resultado = construirExpressao();
            expressaoFinal.value = resultado.expr;
            preview.textContent = resultado.texto + '  →  ' + resultado.expr;
            preview.closest('.cron-preview').classList.toggle('border-danger', !!resultado.invalido);
        }

        frequencia.addEventListener('change', function () {
            document.querySelectorAll('#modalAgendar [data-painel]').forEach(function (painel) { painel.style.display = 'none'; });
            const alvo = document.querySelector('#modalAgendar [data-painel="' + frequencia.value + '"]');
            if (alvo) alvo.style.display = '';
            atualizarPreview();
        });

        painelVisual.addEventListener('input', atualizarPreview);
        painelVisual.addEventListener('change', atualizarPreview);

        manualInput.addEventListener('input', function () {
            expressaoFinal.value = manualInput.value;
        });

        abas.forEach(function (aba) {
            aba.addEventListener('click', function () {
                abas.forEach(function (a) { a.classList.remove('active'); });
                aba.classList.add('active');

                if (aba.dataset.alvo === 'manual') {
                    painelVisual.style.display = 'none';
                    painelManual.style.display = '';
                    manualInput.value = expressaoFinal.value;
                } else {
                    painelManual.style.display = 'none';
                    painelVisual.style.display = '';
                    atualizarPreview();
                }
            });
        });

        atualizarPreview();

    // Ao editar um agendamento existente, tenta reconhecer a expressao
    // atual num dos modos visuais (mesmo raciocinio de cron_form.php); se
    // nao bater com nenhum padrao conhecido, abre direto no modo Manual.
    // Sem argumento (destino ainda sem agendamento), so restaura o padrao.
    function preencherAgendamentoExistente(expressaoAtual) {
        abas.forEach(function (a) { a.classList.remove('active'); });
        document.querySelector('#modalAgendar .cron-tab[data-alvo="visual"]').classList.add('active');
        painelManual.style.display = 'none';
        painelVisual.style.display = '';

        const atual = (expressaoAtual || '').trim();
        const campos = atual.split(/\s+/);
        const ehNumero = function (v) { return /^\d+$/.test(v); };

        function abrirManualCom(valor) {
            document.querySelector('#modalAgendar .cron-tab[data-alvo="manual"]').click();
            manualInput.value = valor;
            expressaoFinal.value = valor;
        }

        if (atual === '') {
            frequencia.value = 'diario';
            document.getElementById('agendarHorarioDiario').value = '03:00';
        } else if (campos.length !== 5 || campos.some(function (c) { return c === ''; })) {
            abrirManualCom(atual);
            return;
        } else {
            const [min, hora, dom, mes, dow] = campos;

            if (/^\*\/\d+$/.test(min) && hora === '*' && dom === '*' && mes === '*' && dow === '*') {
                frequencia.value = 'n-minutos';
                document.getElementById('agendarNMinutos').value = min.slice(2);
            } else if (ehNumero(min) && hora === '*' && dom === '*' && mes === '*' && dow === '*') {
                frequencia.value = 'hora';
                document.getElementById('agendarMinutoHora').value = min;
            } else if (ehNumero(min) && ehNumero(hora) && dom === '*' && mes === '*' && dow === '*') {
                frequencia.value = 'diario';
                document.getElementById('agendarHorarioDiario').value = pad(hora) + ':' + pad(min);
            } else if (ehNumero(min) && ehNumero(hora) && dom === '*' && mes === '*' && /^[0-6](,[0-6])*$/.test(dow)) {
                frequencia.value = 'semanal';
                document.getElementById('agendarHorarioSemanal').value = pad(hora) + ':' + pad(min);
                document.querySelectorAll('#modalAgendar .cron-dia-semana').forEach(function (c) { c.checked = false; });
                dow.split(',').forEach(function (d) {
                    const cb = document.querySelector('#modalAgendar .cron-dia-semana[value="' + d + '"]');
                    if (cb) cb.checked = true;
                });
            } else if (ehNumero(min) && ehNumero(hora) && ehNumero(dom) && mes === '*' && dow === '*') {
                frequencia.value = 'mensal';
                document.getElementById('agendarHorarioMensal').value = pad(hora) + ':' + pad(min);
                document.getElementById('agendarDiaMes').value = dom;
            } else {
                abrirManualCom(atual);
                return;
            }
        }

        frequencia.dispatchEvent(new Event('change'));
    }

    let destinoAgendarId = null;
    document.querySelectorAll('.botao-agendar').forEach(function (botao) {
        botao.addEventListener('click', function () {
            destinoAgendarId = botao.dataset.id;
            document.getElementById('agendarNomeDestino').textContent = botao.dataset.nome;
            document.getElementById('modalAgendarTitulo').textContent = botao.dataset.expressao ? 'Editar agendamento' : 'Agendar backup automático';
            preencherAgendamentoExistente(botao.dataset.expressao || '');
            modal('modalAgendar').show();
        });
    });

    document.getElementById('botaoConfirmarAgendar').addEventListener('click', async function () {
        const dados = new URLSearchParams();
        dados.set('destino_id', destinoAgendarId);
        dados.set('expressao', document.getElementById('campoExpressaoCron').value.trim());
        await fetch(URLS.agendar, { method: 'POST', body: dados });
        location.reload();
    });

    // --- Rodar agora / progresso (mesmo padrão de polling da tela Samba > Usuários) ---
    const painel = document.getElementById('painelProgresso');
    const nomeDestinoSpan = document.getElementById('progressoNomeDestino');
    const textoProgresso = document.getElementById('progressoTexto');
    const barra = document.getElementById('progressoBarra');
    const progressoErro = document.getElementById('progressoErro');
    const progressoSucesso = document.getElementById('progressoSucesso');

    let intervaloPoll = null;

    function pararPoll() {
        if (intervaloPoll) {
            clearInterval(intervaloPoll);
            intervaloPoll = null;
        }
    }

    function atualizarBarra(pct) {
        const p = Math.max(0, Math.min(100, pct || 0));
        barra.style.width = p + '%';
        barra.textContent = p + '%';
    }

    function formatarBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), unidades.length - 1);
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + unidades[i];
    }

    function formatarDuracao(segundos) {
        if (segundos < 60) return segundos + 's';
        if (segundos < 3600) return Math.round(segundos / 60) + 'min';
        return Math.floor(segundos / 3600) + 'h' + Math.round((segundos % 3600) / 60) + 'min';
    }

    function consultarStatus(execucaoId) {
        intervaloPoll = setInterval(async function () {
            try {
                const res = await fetch(URLS.status + '?execucao_id=' + encodeURIComponent(execucaoId));
                const dados = await res.json();

                if (dados.status === 'rodando') {
                    atualizarBarra(dados.percentual);
                    let texto = 'Enviando arquivos (' + formatarBytes(dados.bytes_enviados) +
                        (dados.bytes_totais ? ' de ' + formatarBytes(dados.bytes_totais) : '') +
                        ', ' + (dados.arquivos_enviados || 0) + ' arquivo(s))';
                    if (dados.velocidade_bytes_seg > 0) {
                        texto += ' -- ' + formatarBytes(dados.velocidade_bytes_seg) + '/s';
                    }
                    if (dados.eta_segundos > 0) {
                        texto += ', tempo estimado: ' + formatarDuracao(dados.eta_segundos);
                    }
                    textoProgresso.textContent = texto + '...';
                    return;
                }

                if (dados.status === 'concluida') {
                    pararPoll();
                    atualizarBarra(100);
                    barra.classList.remove('progress-bar-animated');
                    textoProgresso.classList.add('d-none');
                    progressoSucesso.textContent = 'Backup concluído: ' + formatarBytes(dados.bytes_enviados) +
                        ' enviados, ' + (dados.arquivos_enviados || 0) + ' arquivo(s)' +
                        (dados.versoes_criadas > 0 ? ', ' + dados.versoes_criadas + ' versão(ões) preservada(s) em .versoes/' : '') + '.';
                    progressoSucesso.classList.remove('d-none');
                    return;
                }

                if (dados.status === 'erro') {
                    pararPoll();
                    barra.classList.remove('progress-bar-animated');
                    textoProgresso.classList.add('d-none');
                    document.getElementById('progressoErroAmigavel').textContent = dados.mensagem_amigavel || dados.mensagem || 'Erro ao executar o backup.';
                    document.getElementById('progressoErroTecnico').textContent = dados.mensagem_amigavel ? (dados.mensagem || '') : '';
                    progressoErro.classList.remove('d-none');
                    return;
                }
                // "desconhecido" -- job ainda nao escreveu o primeiro status, continua tentando
            } catch (e) {
                // falha de rede pontual -- tenta de novo no proximo tick
            }
        }, 2000);
    }

    document.querySelectorAll('.botao-rodar-agora').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            progressoErro.classList.add('d-none');
            progressoSucesso.classList.add('d-none');
            textoProgresso.classList.remove('d-none');
            barra.classList.add('progress-bar-animated');
            atualizarBarra(0);
            nomeDestinoSpan.textContent = botao.dataset.nome;
            painel.classList.remove('d-none');
            painel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);

            try {
                const res = await fetch(URLS.executar, { method: 'POST', body: dados });
                const resposta = await res.json();

                if (!resposta.success) {
                    textoProgresso.classList.add('d-none');
                    document.getElementById('progressoErroAmigavel').textContent = resposta.message || 'Erro ao iniciar o backup.';
                    document.getElementById('progressoErroTecnico').textContent = '';
                    progressoErro.classList.remove('d-none');
                    return;
                }

                consultarStatus(resposta.execucao_id);
            } catch (e) {
                textoProgresso.classList.add('d-none');
                document.getElementById('progressoErroAmigavel').textContent = 'Erro de rede ao iniciar o backup.';
                document.getElementById('progressoErroTecnico').textContent = '';
                progressoErro.classList.remove('d-none');
            }
        });
    });

    <?php if ($execucaoEmAndamento): ?>
    // Ha uma execucao em andamento (disparada por este ou outro navegador,
    // ou pelo agendamento/cron) -- retoma o acompanhamento ao vivo sem
    // precisar clicar em "Rodar agora" de novo.
    textoProgresso.classList.remove('d-none');
    barra.classList.add('progress-bar-animated');
    atualizarBarra(0);
    nomeDestinoSpan.textContent = <?= json_encode($execucaoEmAndamento['destino_nome'] ?? '') ?>;
    painel.classList.remove('d-none');
    consultarStatus(<?= (int)$execucaoEmAndamento['id'] ?>);
    <?php endif; ?>
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Backup em Nuvem';

require __DIR__ . '/../layouts/main.php';
