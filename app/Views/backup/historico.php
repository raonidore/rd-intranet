<?php

use App\Components\Alert;
use App\Components\Badge;
use App\Services\BackupService;

ob_start();

$formatarBytes = function (int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($unidades) - 1);
    return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
};

$rotuloProvider = fn(?string $p) => match ($p) {
    'b2' => 'Backblaze B2',
    's3' => 'Amazon S3',
    'drive' => 'Google Drive',
    default => '—',
};
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-clock-history me-1"></i> Histórico de Backups</h4>
        <small class="text-muted">Últimas execuções do módulo Backup em Nuvem.</small>
    </div>
    <a href="<?= url('/backup/configuracao') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-gear"></i> Configuração
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($execucoes)): ?>
            <div class="text-center text-muted py-5">Nenhum backup executado ainda.</div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Destino</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Arquivos</th>
                        <th>Enviado</th>
                        <th>Versões</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($execucoes as $e): ?>
                        <tr <?= $e['status'] === 'executando' ? 'class="linha-execucao" data-execucao-id="' . (int)$e['id'] . '"' : '' ?>>
                            <td class="small"><?= htmlspecialchars(data_br($e['iniciado_em'])) ?></td>
                            <td>
                                <?= htmlspecialchars($e['destino_nome'] ?? '(destino excluído)') ?>
                                <?php if ($e['destino_provider']): ?>
                                    <br><span class="small text-muted"><?= $rotuloProvider($e['destino_provider']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= $e['tipo'] === 'agendada' ? 'Agendada' : 'Manual' ?></td>
                            <td class="celula-status">
                                <?php if ($e['status'] === 'concluida'): ?>
                                    <?= Badge::make('Concluída', 'success') ?>
                                <?php elseif ($e['status'] === 'erro'): ?>
                                    <?= Badge::make('Erro', 'danger') ?>
                                <?php else: ?>
                                    <?= Badge::make('Executando', 'secondary') ?>
                                    <div class="progress mt-1" style="height:6px; width:120px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated barra-execucao" style="width:0%"></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($e['status'] === 'erro' && $e['mensagem_erro']): ?>
                                    <div class="small text-danger mt-1">
                                        <?= htmlspecialchars(BackupService::mensagemAmigavel($e['mensagem_erro'])) ?>
                                    </div>
                                    <details class="small text-muted mt-1">
                                        <summary style="cursor:pointer">Detalhe técnico</summary>
                                        <?= htmlspecialchars($e['mensagem_erro']) ?>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="celula-arquivos"><?= (int)$e['arquivos_enviados'] ?></td>
                            <td class="celula-enviado"><?= htmlspecialchars($formatarBytes((int)$e['bytes_enviados'])) ?></td>
                            <td class="celula-versoes">
                                <?= (int)$e['versoes_criadas'] > 0
                                    ? Badge::make((string)(int)$e['versoes_criadas'], 'warning')
                                    : Badge::make('0', 'secondary') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const urlStatus = <?= json_encode(url('/backup/status')) ?>;

    function formatarBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), unidades.length - 1);
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + unidades[i];
    }

    document.querySelectorAll('.linha-execucao').forEach(function (linha) {
        const execucaoId = linha.dataset.execucaoId;
        const barra = linha.querySelector('.barra-execucao');

        const intervalo = setInterval(async function () {
            try {
                const res = await fetch(urlStatus + '?execucao_id=' + encodeURIComponent(execucaoId));
                const dados = await res.json();

                if (dados.status === 'rodando') {
                    barra.style.width = Math.max(0, Math.min(100, dados.percentual || 0)) + '%';
                    linha.querySelector('.celula-arquivos').textContent = dados.arquivos_enviados || 0;
                    linha.querySelector('.celula-enviado').textContent = formatarBytes(dados.bytes_enviados);
                    return;
                }

                // terminou (concluida/erro) -- recarrega pra mostrar o
                // estado final direto do banco (badge, mensagem de erro, etc)
                if (dados.status === 'concluida' || dados.status === 'erro') {
                    clearInterval(intervalo);
                    location.reload();
                }
            } catch (e) {
                // falha de rede pontual -- tenta de novo no proximo tick
            }
        }, 2000);
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Backup - Histórico';

require __DIR__ . '/../layouts/main.php';
