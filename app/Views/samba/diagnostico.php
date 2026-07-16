<?php

ob_start();

function statusBadgeDiag(string $status): string
{
    return match ($status) {
        'active', 'OK' => '<span class="badge bg-success">OK</span>',
        'inactive' => '<span class="badge bg-secondary">Parado</span>',
        'failed', 'ERRO' => '<span class="badge bg-danger">Erro</span>',
        default => '<span class="badge bg-warning text-dark">'.htmlspecialchars($status).'</span>',
    };
}

$comparacao = $diagnostico['comparacao'];
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-activity"></i> Diagnóstico Samba
            </h5>
            <small class="text-muted">
                Verificação automática de serviços, configuração, banco, Linux, permissões e logs.
            </small>
        </div>

        <a href="<?= url('/samba/diagnostico') ?>" class="btn btn-primary">
            <i class="bi bi-arrow-clockwise"></i> Atualizar diagnóstico
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted">SMBD</div>
                <h4><?= statusBadgeDiag($diagnostico['servicos']['smbd']) ?></h4>
                <small>Inicialização: <?= htmlspecialchars($diagnostico['servicos']['smbd_enabled']) ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted">NMBD</div>
                <h4><?= statusBadgeDiag($diagnostico['servicos']['nmbd']) ?></h4>
                <small>Inicialização: <?= htmlspecialchars($diagnostico['servicos']['nmbd_enabled']) ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted">testparm</div>
                <h4><?= statusBadgeDiag($diagnostico['testparm']['status']) ?></h4>
                <small>Validação da configuração Samba</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted">Consistência</div>
                <h4>
                    <?php if (empty($comparacao['orfaos_linux']) && empty($comparacao['ausentes_linux'])): ?>
                        <span class="badge bg-success">OK</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Atenção</span>
                    <?php endif; ?>
                </h4>
                <small>Banco x Linux</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong><i class="bi bi-diagram-3"></i> Consistência Banco x Linux</strong>
    </div>

    <div class="card-body">
        <div class="row text-center mb-4">
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <div class="text-muted">Banco</div>
                    <h3><?= (int)$comparacao['banco_total'] ?></h3>
                    <small>cadastrados na RD Intranet</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="border rounded p-3">
                    <div class="text-muted">Linux</div>
                    <h3><?= (int)$comparacao['linux_total'] ?></h3>
                    <small>pastas físicas encontradas</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="border rounded p-3">
                    <div class="text-muted">Sincronizados</div>
                    <h3><?= count($comparacao['sincronizados']) ?></h3>
                    <small>existem nos dois lados</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="border rounded p-3">
                    <div class="text-muted">Inconsistências</div>
                    <h3><?= count($comparacao['orfaos_linux']) + count($comparacao['ausentes_linux']) ?></h3>
                    <small>precisam de revisão</small>
                </div>
            </div>
        </div>

        <?php if (!empty($comparacao['orfaos_linux'])): ?>
            <div class="alert alert-warning">
                <strong>Pastas órfãs no Linux</strong><br>
                Existem pastas físicas em <code>/srv/samba/Compartilhamentos</code> que não estão cadastradas na RD Intranet.
            </div>

            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Pasta</th>
                        <th>Caminho</th>
                        <th>Dono</th>
                        <th>Grupo</th>
                        <th>Modo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparacao['orfaos_linux'] as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nome']) ?></td>
                            <td><code><?= htmlspecialchars($p['path']) ?></code></td>
                            <td><?= htmlspecialchars($p['owner']) ?></td>
                            <td><?= htmlspecialchars($p['grupo']) ?></td>
                            <td><code><?= htmlspecialchars($p['modo']) ?></code></td>
                            <td>
                                <form method="post"
                                      action="<?= url('/samba/actions/importar-compartilhamento') ?>"
                                      class="d-inline">

                                    <input type="hidden" name="nome" value="<?= htmlspecialchars($p['nome']) ?>">
                                    <input type="hidden" name="grupo" value="<?= htmlspecialchars($p['grupo']) ?>">
                                    <input type="hidden" name="caminho" value="<?= htmlspecialchars($p['path']) ?>">

                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="return confirm('Importar esta pasta para a RD Intranet?')">
                                        <i class="bi bi-box-arrow-in-down"></i> Importar
                                    </button>
                                </form>

                                <form method="post"
                                      action="<?= url('/samba/actions/mover-pasta-lixeira') ?>"
                                      class="d-inline">

                                    <input type="hidden" name="nome" value="<?= htmlspecialchars($p['nome']) ?>">

                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Mover esta pasta para a lixeira administrativa? Nenhum arquivo será apagado definitivamente.')">
                                        <i class="bi bi-trash"></i> Lixeira
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($comparacao['ausentes_linux'])): ?>
            <div class="alert alert-danger">
                <strong>Compartilhamentos cadastrados sem pasta física</strong><br>
                Existem registros no banco que não possuem pasta correspondente no Linux.
            </div>

            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Compartilhamento</th>
                        <th>Caminho esperado</th>
                        <th>Grupo</th>
                        <th>Recomendação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparacao['ausentes_linux'] as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nome']) ?></td>
                            <td><code><?= htmlspecialchars($c['caminho']) ?></code></td>
                            <td><?= htmlspecialchars($c['grupo']) ?></td>
                            <td>
                                <span class="badge bg-danger">Criar pasta ou remover cadastro</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (empty($comparacao['orfaos_linux']) && empty($comparacao['ausentes_linux'])): ?>
            <div class="alert alert-success mb-0">
                <strong>Banco e Linux estão sincronizados.</strong><br>
                Todos os compartilhamentos cadastrados possuem pasta física correspondente.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong><i class="bi bi-folder-check"></i> Pastas e permissões Linux</strong>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Caminho</th>
                    <th>Dono</th>
                    <th>Grupo</th>
                    <th>Modo</th>
                    <th>Leitura humana</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($diagnostico['pastas'] as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nome']) ?></td>
                        <td><code><?= htmlspecialchars($p['path']) ?></code></td>
                        <td><?= htmlspecialchars($p['owner']) ?></td>
                        <td><?= htmlspecialchars($p['grupo']) ?></td>
                        <td><code><?= htmlspecialchars($p['modo']) ?></code></td>
                        <td>
                            <?php if ($p['owner'] === 'root' && $p['modo'] === '2770'): ?>
                                <span class="badge bg-success">Padrão recomendado</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Revisar permissões</span>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted">
                                Grupo <?= htmlspecialchars($p['grupo']) ?> deve ter leitura/escrita. Outros não devem acessar.
                            </small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong><i class="bi bi-journal-medical"></i> Interpretação dos logs recentes</strong>
    </div>

    <div class="card-body">
        <?php foreach ($achadosLogs as $a): ?>
            <div class="alert alert-<?= htmlspecialchars($a['nivel']) ?> mb-3">
                <strong><?= htmlspecialchars($a['titulo']) ?></strong><br>
                <?= htmlspecialchars($a['descricao']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong><i class="bi bi-terminal"></i> Sessões e arquivos abertos</strong>
    </div>

    <div class="card-body">
        <pre class="bg-dark text-light p-3 rounded" style="max-height:350px;overflow:auto;"><?= htmlspecialchars($diagnostico['smbstatus']) ?></pre>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <strong><i class="bi bi-code-square"></i> Detalhes técnicos</strong>
    </div>

    <div class="card-body">
        <pre class="bg-dark text-light p-3 rounded" style="max-height:500px;overflow:auto;"><?= htmlspecialchars($diagnostico['raw']) ?></pre>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-journal-text"></i> Logs completos</strong>
        <button type="button" class="btn btn-sm btn-outline-primary" id="botaoVerLogsCompletos">
            <i class="bi bi-search"></i> Buscar logs completos
        </button>
    </div>

    <div class="card-body">
        <p class="text-muted small mb-3">
            Pra investigar um incidente pontual (ex: uma operação que caiu no meio) sem precisar de
            SSH -- journalctl do smbd (janela maior), erro do Apache desta aplicação, indício de crash
            real (core dump) e o log individual de cada máquina que já conectou no compartilhamento.
        </p>

        <div class="text-center text-muted small d-none" id="carregandoLogsCompletos">
            <div class="spinner-border spinner-border-sm"></div> Buscando...
        </div>

        <div class="d-none" id="blocoLogsCompletos">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabJournalctl" type="button">journalctl smbd</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabApache" type="button">Erro Apache</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCores" type="button">Core dumps</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabClientes" type="button">Por cliente</button></li>
            </ul>
            <div class="tab-content border border-top-0 p-2">
                <div class="tab-pane fade show active" id="tabJournalctl"><pre class="bg-dark text-light p-3 rounded mb-0" style="max-height:450px;overflow:auto;" id="conteudoJournalctl"></pre></div>
                <div class="tab-pane fade" id="tabApache"><pre class="bg-dark text-light p-3 rounded mb-0" style="max-height:450px;overflow:auto;" id="conteudoApache"></pre></div>
                <div class="tab-pane fade" id="tabCores"><pre class="bg-dark text-light p-3 rounded mb-0" style="max-height:450px;overflow:auto;" id="conteudoCores"></pre></div>
                <div class="tab-pane fade" id="tabClientes"><pre class="bg-dark text-light p-3 rounded mb-0" style="max-height:450px;overflow:auto;" id="conteudoClientes"></pre></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const botao = document.getElementById('botaoVerLogsCompletos');
    if (!botao) return;

    const carregando = document.getElementById('carregandoLogsCompletos');
    const bloco = document.getElementById('blocoLogsCompletos');

    botao.addEventListener('click', async function () {
        botao.disabled = true;
        bloco.classList.add('d-none');
        carregando.classList.remove('d-none');

        try {
            const res = await fetch(<?= json_encode(url('/samba/diagnostico/logs-completos')) ?>);
            const dados = await res.json();

            document.getElementById('conteudoJournalctl').textContent = dados.journalctl_smbd || '(vazio)';
            document.getElementById('conteudoApache').textContent = dados.apache_error || '(vazio)';
            document.getElementById('conteudoCores').textContent = dados.core_dumps || '(vazio)';
            document.getElementById('conteudoClientes').textContent = dados.logs_por_cliente || '(vazio)';

            bloco.classList.remove('d-none');
        } catch (e) {
            alert('Erro ao buscar os logs.');
        } finally {
            carregando.classList.add('d-none');
            botao.disabled = false;
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Diagnóstico Samba';

require __DIR__ . '/../layouts/main.php';
