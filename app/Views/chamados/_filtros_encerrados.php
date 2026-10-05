<?php
/**
 * Pesquisa da aba Encerrados de Atendimentos (GET, então o link da busca
 * pode ser salvo/compartilhado). Espera $filtrosEncerrados, $opcoesFiltro,
 * $encerrados, $limiteEncerrados e $podeVerEquipe (ChamadoController::index).
 */
use App\Services\ChamadoService;

$f = $filtrosEncerrados;
$avancados = array_diff_key($f, ['q' => 1]);
$opcao = fn (string $campo, $valor) => (string)($f[$campo] ?? '') === (string)$valor ? 'selected' : '';
?>
<form method="get" action="<?= url('/chamados/atendimentos') ?>" class="card border-0 shadow-sm mb-3">
    <input type="hidden" name="aba" value="encerrados">
    <div class="card-body pb-2">
        <div class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control" value="<?= htmlspecialchars($f['q'] ?? '') ?>" autofocus
                       placeholder="Nº do chamado, título, descrição, solicitante ou equipamento...">
            </div>
            <button type="button" class="btn btn-outline-secondary text-nowrap" data-bs-toggle="collapse" data-bs-target="#filtrosEncerrados">
                <i class="bi bi-funnel"></i> Filtros<?= $avancados ? ' <span class="badge text-bg-primary">' . count($avancados) . '</span>' : '' ?>
            </button>
            <button type="submit" class="btn btn-primary text-nowrap">Pesquisar</button>
        </div>

        <div class="collapse <?= $avancados ? 'show' : '' ?>" id="filtrosEncerrados">
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label small mb-0">Categoria</label>
                    <select name="categoria_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($opcoesFiltro['categorias'] as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $opcao('categoria_id', $c['id']) ?>><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Setor de atendimento</label>
                    <select name="setor_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($opcoesFiltro['setores'] as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= $opcao('setor_id', $s['id']) ?>><?= htmlspecialchars($s['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($opcoesFiltro['setoresSolicitantes']): ?>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Setor do solicitante</label>
                    <select name="setor_solicitante_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($opcoesFiltro['setoresSolicitantes'] as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= $opcao('setor_solicitante_id', $s['id']) ?>><?= htmlspecialchars($s['nome'] . ($s['unidade_nome'] ? ' (' . $s['unidade_nome'] . ')' : '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Unidade</label>
                    <select name="unidade_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($opcoesFiltro['unidades'] as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= $opcao('unidade_id', $u['id']) ?>><?= htmlspecialchars($u['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($podeVerEquipe): ?>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Atendente</label>
                    <select name="usuario_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($opcoesFiltro['atendentes'] as $a): ?>
                            <option value="<?= (int)$a['id'] ?>" <?= $opcao('usuario_id', $a['id']) ?>><?= htmlspecialchars($a['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Prioridade</label>
                    <select name="prioridade" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach (ChamadoService::PRIORIDADES as $chave => $rotulo): ?>
                            <option value="<?= $chave ?>" <?= $opcao('prioridade', $chave) ?>><?= htmlspecialchars($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Situação</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Resolvido ou fechado</option>
                        <option value="resolvido" <?= $opcao('status', 'resolvido') ?>>Resolvido</option>
                        <option value="fechado" <?= $opcao('status', 'fechado') ?>>Fechado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Encerrado de</label>
                    <input type="date" name="de" class="form-control form-control-sm" value="<?= htmlspecialchars($f['de'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">até</label>
                    <input type="date" name="ate" class="form-control form-control-sm" value="<?= htmlspecialchars($f['ate'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
            <span>
                <?php if ($f): ?>
                    <?= count($encerrados) >= $limiteEncerrados ? "Mostrando os {$limiteEncerrados} mais recentes -- refine a busca para ver outros." : count($encerrados) . ' chamado(s) encontrado(s).' ?>
                <?php else: ?>
                    <?= count($encerrados) >= $limiteEncerrados ? "Mostrando os {$limiteEncerrados} encerrados mais recentes. Use a pesquisa para achar os mais antigos." : count($encerrados) . ' chamado(s) encerrado(s).' ?>
                <?php endif; ?>
            </span>
            <?php if ($f): ?>
                <a href="<?= url('/chamados/atendimentos?aba=encerrados') ?>"><i class="bi bi-x-circle"></i> Limpar pesquisa</a>
            <?php endif; ?>
        </div>
    </div>
</form>
