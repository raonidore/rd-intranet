<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

$badgeSimNao = fn(bool $v, string $simTexto = 'Ativo', string $naoTexto = 'Inativo') =>
    $v ? Badge::make($simTexto, 'success') : Badge::make($naoTexto, 'secondary');
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-virus me-1"></i> Antivírus</h4>
        <small class="text-muted">Escaneamento (ClamAV) dos arquivos dos compartilhamentos do Samba.</small>
    </div>
    <?php if ($status['instalado']): ?>
    <button type="button" class="btn btn-outline-primary" id="botaoVerificarAgora">
        <i class="bi bi-search"></i> Verificar agora
    </button>
    <?php endif; ?>
</div>

<?php if (!$status['instalado']): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-5">
            <i class="bi bi-shield-exclamation display-5 text-muted"></i>
            <h5 class="mt-3">ClamAV não está instalado neste servidor</h5>
            <p class="text-muted">
                Instala o serviço de antivírus (clamav-daemon + freshclam) e prepara a pasta de quarentena.
                O primeiro download das assinaturas de vírus acontece em segundo plano e pode levar alguns minutos.
            </p>
            <button type="button" class="btn btn-primary" id="botaoInstalar">
                <i class="bi bi-download"></i> Instalar ClamAV
            </button>
        </div>
    </div>
<?php else: ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small mb-1">Serviço de escaneamento (clamd)</div>
                    <div><?= $badgeSimNao($status['clamd_ativo']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small mb-1">Atualização de assinaturas (freshclam)</div>
                    <div><?= $badgeSimNao($status['freshclam_ativo']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small mb-1">Versão / base de vírus</div>
                    <div><?= $status['versao'] ? htmlspecialchars($status['versao']) : '—' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small mb-1">Escaneamento em tempo real</div>
                    <div><?= $badgeSimNao($status['tempo_real_ativo'], 'Ativo', 'Desativado') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <strong><i class="bi bi-calendar-check"></i> Verificação periódica</strong>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Escaneia diariamente todos os arquivos dos compartilhamentos
                        (<code><?= htmlspecialchars($caminhoPadrao) ?></code>). Modo recomendado: mais leve para o
                        servidor e para quem está copiando arquivos, sem exigir configuração adicional no Samba.
                    </p>
                    <?php if ($verificacaoPeriodicaAtiva): ?>
                        <div class="text-success"><i class="bi bi-check-circle"></i> Verificação diária ativa.</div>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoVerificacaoPeriodica">
                            Ativar verificação diária
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <strong><i class="bi bi-lightning-charge"></i> Escaneamento em tempo real</strong>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Escaneia cada arquivo no momento em que é aberto pelo Samba (<code>vfs_virusfilter</code>).
                        Mais imediato, porém adiciona uma verificação a cada acesso a arquivo nos compartilhamentos,
                        o que pode deixar cópias grandes um pouco mais lentas.
                    </p>
                    <?php if ($status['tempo_real_ativo']): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="botaoTempoRealDesativar">
                            Desativar tempo real
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="botaoTempoRealAtivar">
                            Ativar tempo real
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <strong><i class="bi bi-clock-history"></i> Histórico de verificações</strong>
        </div>
        <div class="card-body p-0">
            <?php if (empty($historico)): ?>
                <div class="text-center text-muted py-4">Nenhuma verificação executada ainda.</div>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Tipo</th>
                            <th>Caminho</th>
                            <th>Status</th>
                            <th>Arquivos</th>
                            <th>Ameaças</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $h): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($h['iniciado_em']) ?></td>
                                <td><?= $h['tipo'] === 'agendada' ? 'Agendada' : 'Manual' ?></td>
                                <td class="small font-monospace"><?= htmlspecialchars($h['caminho']) ?></td>
                                <td>
                                    <?php if ($h['status'] === 'concluida'): ?>
                                        <?= Badge::make('Concluída', 'success') ?>
                                    <?php elseif ($h['status'] === 'erro'): ?>
                                        <?= Badge::make('Erro', 'danger') ?>
                                    <?php else: ?>
                                        <?= Badge::make('Executando', 'secondary') ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$h['arquivos_verificados'] ?></td>
                                <td>
                                    <?= (int)$h['ameacas_encontradas'] > 0
                                        ? Badge::make((string)(int)$h['ameacas_encontradas'], 'danger')
                                        : Badge::make('0', 'secondary') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <strong><i class="bi bi-file-earmark-lock"></i> Quarentena</strong>
        </div>
        <div class="card-body p-0">
            <?php if (empty($quarentena)): ?>
                <div class="text-center text-muted py-4">Nenhum arquivo em quarentena.</div>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Arquivo original</th>
                            <th>Ameaça</th>
                            <th>Detectado em</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quarentena as $q): ?>
                            <tr>
                                <td class="small font-monospace"><?= htmlspecialchars($q['caminho_original']) ?></td>
                                <td><?= htmlspecialchars($q['assinatura']) ?></td>
                                <td class="small"><?= htmlspecialchars($q['detectado_em']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger botao-excluir-quarentena" data-id="<?= (int)$q['id'] ?>">
                                        <i class="bi bi-trash"></i> Excluir
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<div class="modal fade" id="modalVerificar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verificar agora</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Caminho a verificar</label>
                <input type="text" class="form-control" id="campoCaminhoVerificar" value="<?= htmlspecialchars($caminhoPadrao ?? '') ?>">
                <div class="form-text">Precisa estar dentro dos compartilhamentos do Samba.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="botaoConfirmarVerificar">Verificar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAcao" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAcaoTitulo">Processando</h5>
            </div>
            <div class="modal-body" id="modalAcaoCorpo">
                <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde, pode levar alguns instantes...</div>
            </div>
            <div class="modal-footer" id="modalAcaoRodape" style="display:none">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="location.reload()">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URLS = {
        instalar: <?= json_encode(url('/seguranca/antivirus/instalar')) ?>,
        verificarAgora: <?= json_encode(url('/seguranca/antivirus/verificar-agora')) ?>,
        tempoRealAtivar: <?= json_encode(url('/seguranca/antivirus/tempo-real/ativar')) ?>,
        tempoRealDesativar: <?= json_encode(url('/seguranca/antivirus/tempo-real/desativar')) ?>,
        verificacaoPeriodica: <?= json_encode(url('/seguranca/antivirus/verificacao-periodica')) ?>,
        quarentenaExcluir: <?= json_encode(url('/seguranca/antivirus/quarentena/excluir')) ?>,
    };

    async function executar(url, titulo, corpoPost) {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcao'));
        const corpo = document.getElementById('modalAcaoCorpo');
        const rodape = document.getElementById('modalAcaoRodape');

        document.getElementById('modalAcaoTitulo').textContent = titulo;
        corpo.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde, pode levar alguns instantes...</div>';
        rodape.style.display = 'none';
        modal.show();

        try {
            const res = await fetch(url, { method: 'POST', body: corpoPost });
            const dados = await res.json();

            const cor = dados.success ? 'success' : 'danger';
            const icone = dados.success ? 'check-circle' : 'x-circle';
            corpo.innerHTML = '<div class="alert alert-' + cor + '"><i class="bi bi-' + icone + '"></i> ' +
                String(dados.message || '').replace(/</g, '&lt;') + '</div>';
        } catch (e) {
            corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao comunicar com o servidor.</div>';
        } finally {
            rodape.style.display = '';
        }
    }

    const botaoInstalar = document.getElementById('botaoInstalar');
    if (botaoInstalar) {
        botaoInstalar.addEventListener('click', function () {
            if (!confirm('Instalar o ClamAV neste servidor?')) return;
            executar(URLS.instalar, 'Instalando ClamAV');
        });
    }

    const botaoVerificarAgora = document.getElementById('botaoVerificarAgora');
    if (botaoVerificarAgora) {
        botaoVerificarAgora.addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVerificar')).show();
        });
    }

    const botaoConfirmarVerificar = document.getElementById('botaoConfirmarVerificar');
    if (botaoConfirmarVerificar) {
        botaoConfirmarVerificar.addEventListener('click', function () {
            const caminho = document.getElementById('campoCaminhoVerificar').value.trim();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVerificar')).hide();

            const dados = new URLSearchParams();
            dados.set('caminho', caminho);
            executar(URLS.verificarAgora, 'Verificando arquivos', dados);
        });
    }

    const botaoTempoRealAtivar = document.getElementById('botaoTempoRealAtivar');
    if (botaoTempoRealAtivar) {
        botaoTempoRealAtivar.addEventListener('click', function () {
            if (!confirm('Ativar o escaneamento em tempo real nos compartilhamentos do Samba?')) return;
            executar(URLS.tempoRealAtivar, 'Ativando tempo real');
        });
    }

    const botaoTempoRealDesativar = document.getElementById('botaoTempoRealDesativar');
    if (botaoTempoRealDesativar) {
        botaoTempoRealDesativar.addEventListener('click', function () {
            if (!confirm('Desativar o escaneamento em tempo real?')) return;
            executar(URLS.tempoRealDesativar, 'Desativando tempo real');
        });
    }

    const botaoVerificacaoPeriodica = document.getElementById('botaoVerificacaoPeriodica');
    if (botaoVerificacaoPeriodica) {
        botaoVerificacaoPeriodica.addEventListener('click', function () {
            executar(URLS.verificacaoPeriodica, 'Ativando verificação diária');
        });
    }

    document.querySelectorAll('.botao-excluir-quarentena').forEach(function (botao) {
        botao.addEventListener('click', function () {
            if (!confirm('Excluir definitivamente este arquivo da quarentena?')) return;

            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            executar(URLS.quarentenaExcluir, 'Excluindo da quarentena', dados);
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Segurança - Antivírus';

require __DIR__ . '/../layouts/main.php';
