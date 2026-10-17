<?php
ob_start();

use App\Components\Alert;
use App\Services\AcessoMaquinaService;

$query = http_build_query(array_filter($filtros, fn($v) => $v !== null && $v !== ''));
$coresEvento = [
    'login' => 'success', 'desbloqueio' => 'info', 'bloqueio' => 'secondary',
    'logoff' => 'dark', 'falha' => 'warning', 'negado' => 'danger',
];
$comTrilhaPorId = array_column($comTrilha, null, 'id');
$semTrilha = array_values(array_filter($maquinas, fn($m) => ($m['origem'] ?? '') === 'agente' && !isset($comTrilhaPorId[$m['id']])));
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-badge me-1"></i> Trilha de acesso às máquinas</h4>
        <small class="text-muted">Quem entrou em qual computador e quando -- para evidência de auditoria. Guardado por <?= (int)$retencaoAnos ?> ano(s).</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/seguranca/trilha-acesso/exportar') . '?' . $query . '&formato=pdf' ?>" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-filetype-pdf"></i> PDF</a>
        <a href="<?= url('/seguranca/trilha-acesso/exportar') . '?' . $query . '&formato=xlsx' ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
    </div>
</div>

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">De</label>
            <input type="date" name="de" value="<?= htmlspecialchars($filtros['de']) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Até</label>
            <input type="date" name="ate" value="<?= htmlspecialchars($filtros['ate']) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small mb-1">Máquina</label>
            <select name="ativo_id" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($maquinas as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)$filtros['ativo_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small mb-1">Usuário</label>
            <input type="text" name="usuario" value="<?= htmlspecialchars($filtros['usuario']) ?>" class="form-control form-control-sm" placeholder="nome ou e-mail">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Evento</label>
            <select name="evento" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (AcessoMaquinaService::EVENTOS as $chave => $rotulo): ?>
                    <option value="<?= $chave ?>" <?= $filtros['evento'] === $chave ? 'selected' : '' ?>><?= $rotulo ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label small mb-1">Origem</label>
            <select name="origem" class="form-select form-select-sm">
                <option value="">Todas</option>
                <option value="windows" <?= $filtros['origem'] === 'windows' ? 'selected' : '' ?>>Windows</option>
                <option value="entra" <?= $filtros['origem'] === 'entra' ? 'selected' : '' ?>>Entra ID</option>
            </select>
        </div>
        <div class="col-12 col-md-1">
            <button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button>
        </div>
    </div>
</form>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach (AcessoMaquinaService::EVENTOS as $chave => $rotulo): ?>
        <a href="<?= url('/seguranca/trilha-acesso') . '?' . http_build_query(['evento' => $chave] + $filtros) ?>" class="text-decoration-none">
            <span class="badge text-bg-<?= $coresEvento[$chave] ?> fs-6 fw-normal"><?= $rotulo ?>: <strong><?= (int)($totais[$chave] ?? 0) ?></strong></span>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!empty($totais['negado'])): ?>
    <div class="alert alert-danger small py-2">
        <i class="bi bi-shield-x"></i> <strong><?= (int)$totais['negado'] ?> tentativa(s) negada(s)</strong> no período -- contas sem
        permissão para entrar na máquina (fora do grupo em Entra &gt; Acesso às Máquinas). Mostra que a restrição está funcionando.
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($linhas)): ?>
            <p class="text-muted p-3 mb-0">Nenhum registro no período.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Data/hora</th>
                            <th>Máquina</th>
                            <th>Usuário</th>
                            <th>Evento</th>
                            <th>Forma / detalhe</th>
                            <th>IP</th>
                            <th>Origem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas as $l): ?>
                            <tr class="<?= $l['evento'] === 'negado' ? 'table-danger' : '' ?>">
                                <td class="text-nowrap"><?= date('d/m/Y H:i:s', strtotime($l['ocorrido_em'])) ?></td>
                                <td><?= htmlspecialchars((string)$l['maquina_nome']) ?></td>
                                <td><?= htmlspecialchars(AcessoMaquinaService::rotuloUsuario($l)) ?></td>
                                <td><span class="badge text-bg-<?= $coresEvento[$l['evento']] ?? 'light' ?>"><?= AcessoMaquinaService::EVENTOS[$l['evento']] ?? htmlspecialchars($l['evento']) ?></span></td>
                                <td>
                                    <?= htmlspecialchars((string)$l['forma']) ?>
                                    <?php if ($l['detalhe']): ?><div class="text-muted"><?= htmlspecialchars($l['detalhe']) ?></div><?php endif; ?>
                                </td>
                                <td class="font-monospace"><?= htmlspecialchars((string)$l['ip']) ?></td>
                                <td><?= $l['origem'] === 'entra' ? '<span class="badge text-bg-primary">Entra ID</span>' : '<span class="badge text-bg-light border">Windows</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($linhas) >= 2000): ?>
                <p class="text-muted small p-2 mb-0">Mostrando os 2.000 mais recentes -- refine o filtro ou exporte para ver tudo.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong>Máquinas na trilha</strong></div>
            <div class="card-body small">
                <?php if (empty($comTrilha)): ?>
                    <p class="text-muted mb-0">Nenhuma ainda. A coleta começa sozinha quando o agente da máquina estiver na versão 1.0.71 ou mais nova.</p>
                <?php else: ?>
                    <?php foreach ($comTrilha as $m): ?>
                        <div><?= htmlspecialchars($m['nome']) ?> <span class="text-muted">-- último envio <?= date('d/m H:i', strtotime($m['ultimo'])) ?></span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($semTrilha): ?>
                    <div class="text-warning mt-2"><i class="bi bi-exclamation-triangle"></i> Ainda sem trilha (agente antigo, desligada ou sem permissão de administrador):
                        <?= htmlspecialchars(implode(', ', array_column($semTrilha, 'nome'))) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong>De onde vêm os registros</strong></div>
            <div class="card-body small text-muted">
                <p class="mb-2"><span class="badge text-bg-light border">Windows</span> Log de segurança de cada máquina, lido pelo agente a cada 5 minutos:
                    login, logoff, bloqueio e desbloqueio de tela, senha errada e acesso negado -- inclusive login sem internet. O agente também
                    liga a auditoria de bloqueio de tela e aumenta o log do Windows para não perder eventos.</p>
                <p class="mb-0"><span class="badge text-bg-primary">Entra ID</span>
                    <?php if ($entraConfigurado): ?>
                        Registro de entradas da Microsoft (login no Windows autenticado na nuvem), importado a cada 15 minutos -- confirma o registro do Windows.
                    <?php else: ?>
                        Integração com o Entra não configurada neste portal.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Trilha de acesso às máquinas';

require __DIR__ . '/../layouts/main.php';
