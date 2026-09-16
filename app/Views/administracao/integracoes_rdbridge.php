<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-diagram-3 me-1"></i> RD.Bridge</h4>
    <small class="text-muted">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-3" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-question-circle"></i> Como funciona</strong>
        <ul class="small text-muted mb-0 mt-2 ps-3">
            <li>Um coletor RD.Bridge instalado numa unidade remota (atrás de NAT) fala com este servidor por conexão de saída -- nunca precisa de porta aberta nem configuração no roteador do cliente.</li>
            <li>Ele coleta, na própria rede local dele, os ativos com SNMP habilitado cadastrados nessa unidade -- mesmo dado que a tela de Ativos já coletaria direto, se alcançasse a rede.</li>
            <li>Cada coletor tem um <strong>token próprio</strong>, gerado uma vez só (não fica salvo em texto puro depois) -- revogue e crie outro se precisar trocar.</li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:960px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong><i class="bi bi-hdd-network"></i> Coletores</strong>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoBridge">
                <i class="bi bi-plus-lg"></i> Novo coletor
            </button>
        </div>

        <?php if (empty($coletores)): ?>
            <p class="text-muted small mb-0">Nenhum coletor cadastrado ainda.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Unidade</th>
                            <th>Modo</th>
                            <th>Último checkin</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coletores as $c): ?>
                            <?php
                                $online = $c['ultimo_checkin_em'] && (strtotime($c['ultimo_checkin_em']) > time() - 300);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($c['nome']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($c['unidade_nome']) ?></td>
                                <td><?= Badge::make(strtoupper($c['modo']), $c['modo'] === 'vpn' ? 'success' : 'secondary') ?></td>
                                <td class="text-muted small">
                                    <?= $c['ultimo_checkin_em'] ? htmlspecialchars(data_br($c['ultimo_checkin_em'], 'd/m/Y H:i:s')) . ' (' . htmlspecialchars($c['ip_ultimo_checkin']) . ')' : 'nunca' ?>
                                    <?= $c['versao'] ? ' -- v' . htmlspecialchars($c['versao']) : '' ?>
                                </td>
                                <td>
                                    <?php if (!$c['ativo']): ?>
                                        <?= Badge::make('Revogado', 'secondary') ?>
                                    <?php elseif ($online): ?>
                                        <?= Badge::make('<i class="bi bi-circle-fill" style="font-size:8px"></i> Online', 'success') ?>
                                    <?php else: ?>
                                        <?= Badge::make('Offline', 'warning') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($c['ativo']): ?>
                                        <form method="post" action="<?= url('/administracao/integracoes/rd-bridge/revogar') ?>" onsubmit="return confirm('Revogar este coletor? O token dele para de funcionar imediatamente.');">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Revogar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Novo Coletor -->
<div class="modal fade" id="modalNovoBridge" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Novo coletor RD.Bridge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" id="campoNomeBridge" placeholder="Ex: Fábrica -- Coletor Principal">
                </div>
                <div class="mb-3">
                    <label class="form-label">Unidade</label>
                    <select class="form-select" id="campoUnidadeBridge">
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Modo</label>
                    <select class="form-select" id="campoModoBridge">
                        <option value="http">HTTP (padrão -- sempre funciona, sem configuração de rede)</option>
                        <option value="vpn">VPN (quando o cliente autoriza -- ver Infraestrutura &gt; VPN)</option>
                    </select>
                </div>
                <div id="resultadoNovoBridge" class="d-none">
                    <div class="alert alert-success small mb-0">
                        <strong>Token gerado -- copie agora, ele não aparece de novo:</strong>
                        <div class="input-group mt-2">
                            <input type="text" class="form-control form-control-sm font-monospace" id="campoTokenGerado" readonly>
                            <button class="btn btn-sm btn-outline-secondary" type="button" id="botaoCopiarToken"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary btn-sm" id="botaoCriarBridge">
                    <i class="bi bi-plus-lg"></i> Criar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const botao = document.getElementById('botaoCriarBridge');
    const resultado = document.getElementById('resultadoNovoBridge');
    const campoToken = document.getElementById('campoTokenGerado');

    botao.addEventListener('click', async function () {
        const nome = document.getElementById('campoNomeBridge').value.trim();
        if (!nome) {
            alert('Informe um nome pro coletor.');
            return;
        }

        botao.disabled = true;
        try {
            const body = new URLSearchParams({
                nome: nome,
                unidade_id: document.getElementById('campoUnidadeBridge').value,
                modo: document.getElementById('campoModoBridge').value,
            });
            const res = await fetch(<?= json_encode(url('/administracao/integracoes/rd-bridge/criar')) ?>, { method: 'POST', body });
            const dados = await res.json();

            if (!dados.success) {
                alert(dados.message || 'Falha ao criar o coletor.');
                return;
            }

            campoToken.value = dados.token;
            resultado.classList.remove('d-none');
            botao.classList.add('d-none');
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            botao.disabled = false;
        }
    });

    document.getElementById('botaoCopiarToken').addEventListener('click', function () {
        campoToken.select();
        navigator.clipboard.writeText(campoToken.value).catch(function () {});
    });

    document.getElementById('modalNovoBridge').addEventListener('hidden.bs.modal', function () {
        if (!resultado.classList.contains('d-none')) {
            location.reload();
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - RD.Bridge';

require __DIR__ . '/../layouts/main.php';
