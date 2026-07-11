<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

function iptablesAcaoCor(string $acao): string
{
    return match ($acao) {
        'ACCEPT' => 'success',
        'DROP', 'REJECT' => 'danger',
        'MASQUERADE', 'DNAT', 'SNAT' => 'info',
        'LOG' => 'secondary',
        default => 'warning',
    };
}

$porGrupo = [];
foreach ($regras as $r) {
    $porGrupo[$r['tabela']][$r['cadeia']][] = $r;
}
?>

<style>
.fw-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
.fw-card .card-header { background: #f8fafc; border-bottom: 1px solid #e9ecef; border-radius: 14px 14px 0 0; padding: 14px 20px; }
.regra-desativada { opacity: .55; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-hdd-network me-1"></i> Firewall (iptables)</h4>
        <small class="text-muted">
            Toda alteração é aplicada com reversão automática caso não seja confirmada — seguro para testar sem risco de perder o acesso.
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/infraestrutura/iptables/ao-vivo') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-eye"></i> Ver ao vivo
        </a>
        <a href="<?= url('/infraestrutura/iptables/templates') ?>" class="btn btn-outline-primary">
            <i class="bi bi-magic"></i> Regras prontas
        </a>
        <a href="<?= url('/infraestrutura/iptables/exportar') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download"></i> Exportar
        </a>
        <a href="<?= url('/infraestrutura/iptables/importar') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-upload"></i> Importar
        </a>
        <a href="<?= url('/infraestrutura/iptables/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nova regra
        </a>
        <?php if (!$panicoAtivo): ?>
            <button type="button" class="btn btn-danger" id="btn-panico">
                <i class="bi bi-exclamation-octagon"></i> Modo Pânico
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($panicoAtivo): ?>
    <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong><i class="bi bi-exclamation-octagon"></i> Modo Pânico ativo.</strong>
            Só SSH, este painel web e conexões já estabelecidas estão liberados — todo o resto do tráfego está bloqueado.
        </div>
        <button type="button" class="btn btn-light" id="btn-panico-desativar">
            <i class="bi bi-shield-check"></i> Desativar modo pânico
        </button>
    </div>
<?php endif; ?>

<div id="alerta-pendente" class="alert alert-warning d-none">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong><i class="bi bi-exclamation-triangle"></i> Alteração aplicada, aguardando confirmação.</strong><br>
            Se não for confirmada, o firewall anterior será restaurado automaticamente em
            <strong id="segundos-restantes">--</strong> segundo(s).
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-danger" id="btn-reverter">
                <i class="bi bi-arrow-counterclockwise"></i> Reverter agora
            </button>
            <button class="btn btn-success" id="btn-confirmar">
                <i class="bi bi-check-lg"></i> Confirmar Alteração
            </button>
        </div>
    </div>
</div>

<?php if (!empty($sombreadas)): ?>
    <div class="alert alert-warning">
        <strong><i class="bi bi-eye-slash"></i> <?= count($sombreadas) ?> regra(s) nunca alcançada(s):</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($sombreadas as $s): ?>
                <li class="small"><?= htmlspecialchars($s['mensagem']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($ultimoErro): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-x-circle"></i> Última aplicação falhou.</strong> <?= htmlspecialchars($ultimoErro) ?>
    </div>
<?php elseif ($ultimoApplyEm): ?>
    <div class="text-muted small mb-3">
        <i class="bi bi-check-circle text-success"></i> Último ruleset aplicado com sucesso em <?= htmlspecialchars($ultimoApplyEm) ?>.
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_iptables_fluxo.php'; ?>
<?php require __DIR__ . '/_iptables_mapa_calor.php'; ?>

<div class="card fw-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-sliders me-1"></i> Política padrão das cadeias</span>
    </div>
    <div class="card-body">
        <form method="post" action="<?= url('/infraestrutura/iptables/politica') ?>" class="row g-3 align-items-end"
              onsubmit="return confirm('Mudar a política padrão pode afetar todo o tráfego. Você terá tempo para confirmar antes da reversão automática. Continuar?');">
            <?php foreach (['INPUT' => 'Entrada (INPUT)', 'FORWARD' => 'Roteado (FORWARD)', 'OUTPUT' => 'Saída (OUTPUT)'] as $cadeia => $label): ?>
                <div class="col-md-4">
                    <label class="form-label"><?= $label ?></label>
                    <select name="<?= $cadeia ?>" class="form-select">
                        <option value="ACCEPT" <?= $politicas[$cadeia] === 'ACCEPT' ? 'selected' : '' ?>>ACCEPT (permitir por padrão)</option>
                        <option value="DROP" <?= $politicas[$cadeia] === 'DROP' ? 'selected' : '' ?>>DROP (bloquear por padrão)</option>
                    </select>
                </div>
            <?php endforeach; ?>
            <div class="col-12">
                <small class="text-muted d-block mb-2">
                    Recomendado: manter ACCEPT e usar regras explícitas de DROP/REJECT no fim da lista — assim, se as regras forem
                    zeradas por acidente, o servidor não fica instantaneamente inacessível.
                    Porta(s) SSH detectada(s) (<?= htmlspecialchars(implode(', ', $sshPortas)) ?>), porta(s) deste painel web
                    (<?= htmlspecialchars(implode(', ', $painelPortas)) ?>), conexões já estabelecidas e a interface
                    local (loopback) estão sempre liberadas automaticamente, mesmo com política DROP.
                </small>
                <button type="submit" class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-check2-circle"></i> Aplicar políticas
                </button>
            </div>
        </form>
    </div>
</div>

<div class="mb-3">
    <input type="search" id="busca-regras" class="form-control" placeholder="Buscar por nome, porta, IP, interface ou ação...">
</div>

<?php foreach (['filter' => 'Tabela filter (firewall)', 'nat' => 'Tabela nat (NAT/redirecionamento)'] as $tabela => $tituloTabela): ?>
    <div class="card fw-card" id="tabela-<?= $tabela ?>">
        <div class="card-header"><i class="bi bi-table me-1"></i> <?= $tituloTabela ?></div>
        <div class="card-body p-0">
            <?php if (empty($porGrupo[$tabela])): ?>
                <p class="text-muted text-center py-4 mb-0">Nenhuma regra cadastrada nesta tabela.</p>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Cadeia</th>
                            <th>Regra</th>
                            <th>Explicação</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porGrupo[$tabela] as $cadeia => $lista): ?>
                            <?php foreach ($lista as $r): ?>
                                <?php
                                $busca = mb_strtolower(implode(' ', array_filter([
                                    $r['nome'], $r['acao'], $cadeia, $r['protocolo'] ?? '',
                                    $r['porta_destino'] ?? '', $r['porta_origem'] ?? '',
                                    $r['ip_origem'] ?? '', $r['ip_destino'] ?? '',
                                    $r['interface_entrada'] ?? '', $r['interface_saida'] ?? '',
                                ])));
                                ?>
                                <tr class="linha-regra <?= (int)$r['ativo'] === 1 ? '' : 'regra-desativada' ?>" data-busca="<?= htmlspecialchars($busca) ?>">
                                    <td><code><?= htmlspecialchars($cadeia) ?></code></td>
                                    <td>
                                        <?= htmlspecialchars($r['nome']) ?>
                                        <span class="badge text-bg-<?= iptablesAcaoCor($r['acao']) ?> ms-1"><?= htmlspecialchars($r['acao']) ?></span>
                                        <?php if ($r['origem_template']): ?>
                                            <br><small class="text-muted"><i class="bi bi-magic"></i> gerada por template</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($r['explicacao']) ?></td>
                                    <td><?= (int)$r['ativo'] === 1 ? Badge::make('Ativa', 'success') : Badge::make('Desativada', 'secondary') ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ((int)($r['registrar_log'] ?? 0) === 1): ?>
                                                <button type="button" class="btn btn-outline-dark botao-ver-ips" data-id="<?= $r['id'] ?>" data-nome="<?= htmlspecialchars($r['nome']) ?>" title="Ver IPs registrados">
                                                    <i class="bi bi-binoculars"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="<?= url('/infraestrutura/iptables/mover?id=' . $r['id'] . '&direcao=cima') ?>" class="btn btn-outline-secondary" title="Mover para cima">
                                                <i class="bi bi-arrow-up"></i>
                                            </a>
                                            <a href="<?= url('/infraestrutura/iptables/mover?id=' . $r['id'] . '&direcao=baixo') ?>" class="btn btn-outline-secondary" title="Mover para baixo">
                                                <i class="bi bi-arrow-down"></i>
                                            </a>
                                            <a href="<?= url('/infraestrutura/iptables/editar?id=' . $r['id']) ?>" class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ((int)$r['ativo'] === 1): ?>
                                                <a href="<?= url('/infraestrutura/iptables/desativar?id=' . $r['id']) ?>" class="btn btn-outline-warning" title="Desativar">
                                                    <i class="bi bi-toggle2-on"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= url('/infraestrutura/iptables/ativar?id=' . $r['id']) ?>" class="btn btn-outline-success" title="Ativar">
                                                    <i class="bi bi-toggle2-off"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= url('/infraestrutura/iptables/excluir?id=' . $r['id']) ?>" class="btn btn-outline-danger" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="modalIps" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-binoculars"></i> IPs registrados: <span id="modalIpsNome"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <small class="text-muted d-block mb-2">Atualiza a cada 3s enquanto esta janela estiver aberta.</small>
                <div id="modalIpsCorpo">
                    <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Carregando...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const STATUS_URL = <?= json_encode(url('/infraestrutura/iptables/status')) ?>;
    const CONFIRMAR_URL = <?= json_encode(url('/infraestrutura/iptables/confirmar')) ?>;
    const REVERTER_URL = <?= json_encode(url('/infraestrutura/iptables/reverter')) ?>;
    const LOGS_URL = <?= json_encode(url('/infraestrutura/iptables/logs')) ?>;
    const PANICO_ATIVAR_URL = <?= json_encode(url('/infraestrutura/iptables/panico/ativar')) ?>;
    const PANICO_DESATIVAR_URL = <?= json_encode(url('/infraestrutura/iptables/panico/desativar')) ?>;

    const alertaPendente = document.getElementById('alerta-pendente');
    const segundosEl = document.getElementById('segundos-restantes');
    const btnConfirmar = document.getElementById('btn-confirmar');
    const btnReverter = document.getElementById('btn-reverter');

    let poll = null;
    let pollIps = null;

    function montarTabelaIps(itens) {
        if (!itens.length) {
            return '<p class="text-muted text-center py-3 mb-0">Nenhum IP registrado ainda por esta regra.</p>';
        }
        const linhas = itens.map(function (i) {
            return '<tr>' +
                '<td class="small font-monospace">' + (i.quando || '-') + '</td>' +
                '<td class="font-monospace">' + i.origem + '</td>' +
                '<td class="font-monospace">' + i.destino + '</td>' +
                '<td>' + i.protocolo + '</td>' +
                '<td>' + (i.porta_destino || '-') + '</td>' +
                '</tr>';
        }).join('');
        return '<table class="table table-sm table-hover mb-0">' +
            '<thead><tr><th>Quando</th><th>Origem</th><th>Destino</th><th>Protocolo</th><th>Porta</th></tr></thead>' +
            '<tbody>' + linhas + '</tbody></table>';
    }

    async function carregarIps(id) {
        try {
            const res = await fetch(LOGS_URL + '?id=' + encodeURIComponent(id));
            const dados = await res.json();
            document.getElementById('modalIpsCorpo').innerHTML = montarTabelaIps(dados.itens || []);
        } catch (e) {
            document.getElementById('modalIpsCorpo').innerHTML = '<p class="text-danger text-center py-3 mb-0">Erro ao carregar.</p>';
        }
    }

    document.querySelectorAll('.botao-ver-ips').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const id = botao.dataset.id;
            const modalEl = document.getElementById('modalIps');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

            document.getElementById('modalIpsNome').textContent = botao.dataset.nome;
            document.getElementById('modalIpsCorpo').innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Carregando...</div>';
            modal.show();

            carregarIps(id);
            if (pollIps) clearInterval(pollIps);
            pollIps = setInterval(function () { carregarIps(id); }, 3000);

            modalEl.addEventListener('hidden.bs.modal', function () {
                if (pollIps) { clearInterval(pollIps); pollIps = null; }
            }, { once: true });
        });
    });

    async function verificarStatus() {
        try {
            const res = await fetch(STATUS_URL);
            const data = await res.json();
            if (data.pendente) {
                alertaPendente.classList.remove('d-none');
                segundosEl.textContent = data.segundos_restantes;
                if (!poll) poll = setInterval(verificarStatus, 3000);
                if (data.segundos_restantes <= 0) {
                    clearInterval(poll);
                    poll = null;
                    alertaPendente.classList.add('d-none');
                }
            } else {
                alertaPendente.classList.add('d-none');
                if (poll) { clearInterval(poll); poll = null; }
            }
        } catch (e) {
            console.warn('Falha ao verificar status do firewall:', e);
        }
    }

    btnConfirmar.addEventListener('click', async function () {
        btnConfirmar.disabled = true;
        try {
            const res = await fetch(CONFIRMAR_URL, { method: 'POST' });
            const data = await res.json();
            alert(data.message);
            if (data.success) {
                alertaPendente.classList.add('d-none');
                if (poll) { clearInterval(poll); poll = null; }
            }
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            btnConfirmar.disabled = false;
        }
    });

    btnReverter.addEventListener('click', async function () {
        if (!confirm('Reverter agora para o firewall anterior a esta alteração?')) return;
        btnReverter.disabled = true;
        try {
            const res = await fetch(REVERTER_URL, { method: 'POST' });
            const data = await res.json();
            alert(data.message);
            location.reload();
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            btnReverter.disabled = false;
        }
    });

    const btnPanico = document.getElementById('btn-panico');
    if (btnPanico) {
        btnPanico.addEventListener('click', async function () {
            if (!confirm('Isso vai bloquear TODO o tráfego de entrada, exceto SSH, este painel web e conexões já estabelecidas. Continuar?')) return;
            const digitado = prompt('Digite BLOQUEAR (em maiúsculas) para confirmar:');
            if (digitado !== 'BLOQUEAR') {
                alert('Confirmação incorreta, nada foi alterado.');
                return;
            }
            btnPanico.disabled = true;
            try {
                const res = await fetch(PANICO_ATIVAR_URL, { method: 'POST' });
                const data = await res.json();
                alert(data.message);
                location.reload();
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            } finally {
                btnPanico.disabled = false;
            }
        });
    }

    const btnPanicoDesativar = document.getElementById('btn-panico-desativar');
    if (btnPanicoDesativar) {
        btnPanicoDesativar.addEventListener('click', async function () {
            if (!confirm('Desativar o modo pânico e restaurar as regras anteriores?')) return;
            btnPanicoDesativar.disabled = true;
            try {
                const res = await fetch(PANICO_DESATIVAR_URL, { method: 'POST' });
                const data = await res.json();
                alert(data.message);
                location.reload();
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            } finally {
                btnPanicoDesativar.disabled = false;
            }
        });
    }

    verificarStatus();
})();

(function () {
    const campoBusca = document.getElementById('busca-regras');
    if (!campoBusca) return;

    const linhas = document.querySelectorAll('.linha-regra');

    campoBusca.addEventListener('input', function () {
        const termo = campoBusca.value.trim().toLowerCase();
        linhas.forEach(function (linha) {
            const bate = termo === '' || (linha.dataset.busca || '').includes(termo);
            linha.style.display = bate ? '' : 'none';
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Firewall';

require __DIR__ . '/../layouts/main.php';
