<?php
ob_start();

use App\Components\Alert;

/** @var array $itens @var array $tipos @var array $setores @var array $aplicacoes @var bool $entraConfigurado */

$descricao = function (array $i): string {
    $d = $i['dados'];
    return match ($i['tipo']) {
        'atalho_site' => $d['url'] ?? '',
        'inicializar', 'atalho_programa' => trim(($d['caminho'] ?? '') . ' ' . ($d['argumentos'] ?? '')),
        'unidade_mapeada' => ($d['letra'] ?? '?') . ': → ' . ($d['unc'] ?? '') . (!empty($d['usuario']) ? '  (usuário ' . $d['usuario'] . ')' : ''),
        'impressora' => $d['unc'] ?? '',
        default => '',
    };
};
$porTipo = [];
foreach ($itens as $i) {
    $porTipo[$i['tipo']][] = $i;
}
?>

<?= Alert::flash() ?>

<style>
.amb-item { display: grid; grid-template-columns: 36px 1fr auto; gap: 12px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--bs-border-color-translucent); }
.amb-item:last-child { border-bottom: 0; }
.amb-item.pausado { opacity: .55; }
.amb-item .amb-ico { width: 36px; height: 36px; border-radius: 8px; display: grid; place-items: center; background: rgba(37,99,235,.08); color: #2563eb; font-size: 18px; }
.amb-item .amb-det { font-family: var(--bs-font-monospace); font-size: 12.5px; color: var(--bs-secondary-color); word-break: break-all; }
.amb-item > div { min-width: 0; }
.amb-acoes { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.amb-tipo-titulo { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-secondary-color); font-weight: 600; }
.amb-tipos .btn-check + label { display: flex; align-items: center; gap: 8px; justify-content: flex-start; text-align: left; }
@media (max-width: 576px) { .amb-item { grid-template-columns: 36px 1fr; } .amb-acoes { grid-column: 1 / -1; justify-content: flex-start; } }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-window-desktop me-1"></i> Ambiente padrão</h4>
        <small class="text-muted">Atalhos, pastas mapeadas, impressoras e programas que iniciam com o Windows -- configurados uma vez, aplicados em toda máquina e todo usuário.</small>
    </div>
    <button class="btn btn-primary" type="button" onclick="ambNovo()"><i class="bi bi-plus-lg"></i> Novo item</button>
</div>

<div class="alert alert-light border small mb-3">
    <div class="row g-3">
        <div class="col-md-4"><strong><i class="bi bi-arrow-repeat text-primary"></i> Sempre conferido</strong><br>
            O agente confere ao entrar no Windows e a cada 30 minutos. O que faltar é recriado; o que você excluir aqui é removido das máquinas.</div>
        <div class="col-md-4"><strong><i class="bi bi-people text-primary"></i> Para quem vale</strong><br>
            <em>Todos</em>: toda máquina com o agente. <em>Setor</em>: máquinas daquele setor no cadastro de ativos.
            <em>Grupo do Entra</em>: só as pessoas do grupo, em qualquer máquina.</div>
        <div class="col-md-4"><strong><i class="bi bi-info-circle text-primary"></i> Como é aplicado</strong><br>
            Programas e atalhos para todos vão para o Windows inteiro (Área de Trabalho pública). Pastas e impressoras vão para o perfil de cada pessoa. Precisa do agente 1.0.89 ou mais novo.</div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <?php if (!$itens): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-window-desktop display-6 d-block mb-2"></i>
                Nenhum item ainda. Comece, por exemplo, com o sistema que precisa abrir junto com o Windows.<br>
                <button class="btn btn-sm btn-outline-primary mt-3" type="button" onclick="ambNovo()"><i class="bi bi-plus-lg"></i> Adicionar o primeiro item</button>
            </div>
        <?php endif; ?>
        <?php foreach ($tipos as $tipo => $info): if (empty($porTipo[$tipo])) continue; ?>
            <div class="px-3 pt-3 pb-1 amb-tipo-titulo"><i class="bi <?= $info['icone'] ?>"></i> <?= htmlspecialchars($info['rotulo']) ?></div>
            <?php foreach ($porTipo[$tipo] as $i): ?>
                <div class="amb-item <?= (int)$i['ativo'] ? '' : 'pausado' ?>">
                    <div class="amb-ico"><i class="bi <?= $info['icone'] ?>"></i></div>
                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($i['nome']) ?>
                            <?php if (!(int)$i['ativo']): ?><span class="badge text-bg-secondary ms-1">Pausado</span><?php endif; ?>
                        </div>
                        <div class="amb-det"><?= htmlspecialchars($descricao($i)) ?></div>
                        <div class="small mt-1">
                            <?php if ($i['escopo'] === 'todos'): ?>
                                <span class="badge text-bg-light border"><i class="bi bi-globe"></i> Todos</span>
                            <?php elseif ($i['escopo'] === 'setor'): ?>
                                <span class="badge text-bg-light border"><i class="bi bi-diagram-3"></i> Setor <?= htmlspecialchars($i['escopo_nome'] ?? '') ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-light border"><i class="bi bi-people"></i> Grupo <?= htmlspecialchars($i['escopo_nome'] ?? '') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($i['tem_senha'])): ?><span class="badge text-bg-light border"><i class="bi bi-key"></i> com senha</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="amb-acoes">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick='ambEditar(<?= json_encode($i, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-pencil"></i> Editar</button>
                        <form method="post" action="<?= url('/ativos/ambiente-padrao/alternar') ?>">
                            <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary"><?= (int)$i['ativo'] ? '<i class="bi bi-pause"></i> Pausar' : '<i class="bi bi-play"></i> Reativar' ?></button>
                        </form>
                        <form method="post" action="<?= url('/ativos/ambiente-padrao/excluir') ?>" onsubmit="return confirm('Excluir &quot;<?= htmlspecialchars($i['nome'], ENT_QUOTES) ?>&quot;? O agente remove das máquinas o que ele tinha criado.');">
                            <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white"><strong><i class="bi bi-pc-display"></i> Aplicação nas máquinas</strong>
        <small class="text-muted ms-2">última conferência de cada máquina e de cada pessoa</small></div>
    <div class="card-body p-0">
        <?php if (!$aplicacoes): ?>
            <p class="text-muted small p-3 mb-0">Nenhuma máquina conferiu ainda. Depois de criar um item, aguarde até 30 minutos (ou peça à pessoa para sair e entrar de novo no Windows).</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th class="ps-3">Máquina</th><th>Para</th><th>Quando</th><th>Resultado</th></tr></thead>
                <tbody>
                <?php foreach ($aplicacoes as $ap): ?>
                    <tr>
                        <td class="ps-3"><a href="<?= url('/ativos/ver?id=' . (int)$ap['ativo_id']) ?>"><?= htmlspecialchars($ap['codigo_patrimonio']) ?></a>
                            <span class="small text-muted"><?= htmlspecialchars($ap['ativo_nome']) ?></span></td>
                        <td class="small"><?= $ap['contexto'] === 'maquina' ? 'Todos os usuários' : htmlspecialchars($ap['usuario']) ?></td>
                        <td class="small text-nowrap"><?= htmlspecialchars(data_br($ap['aplicado_em'])) ?></td>
                        <td class="small">
                            <?php if (!$ap['itens']): ?>
                                <span class="text-muted">nada a aplicar</span>
                            <?php elseif ((int)$ap['falhas'] === 0): ?>
                                <span class="text-success"><i class="bi bi-check-circle"></i> <?= count($ap['itens']) ?> item(ns) em dia</span>
                            <?php else: ?>
                                <span class="text-danger"><i class="bi bi-exclamation-triangle"></i> <?= (int)$ap['falhas'] ?> com falha</span>
                                <?php foreach ($ap['itens'] as $it): if (!empty($it['ok'])) continue; ?>
                                    <div class="text-danger-emphasis"><?= htmlspecialchars($it['nome']) ?>: <?= htmlspecialchars($it['mensagem']) ?></div>
                                <?php endforeach; ?>
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

<!-- Modal novo/editar -->
<div class="modal fade" id="ambModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" method="post" action="<?= url('/ativos/ambiente-padrao/salvar') ?>" id="ambForm">
            <div class="modal-header">
                <h5 class="modal-title" id="ambTitulo">Novo item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="ambId">
                <label class="form-label fw-semibold">O que você quer configurar?</label>
                <div class="row g-2 mb-3 amb-tipos">
                    <?php foreach ($tipos as $tipo => $info): ?>
                        <div class="col-6 col-md-4">
                            <input type="radio" class="btn-check" name="tipo" id="ambTipo_<?= $tipo ?>" value="<?= $tipo ?>" autocomplete="off">
                            <label class="btn btn-outline-primary w-100" for="ambTipo_<?= $tipo ?>"><i class="bi <?= $info['icone'] ?>"></i> <?= htmlspecialchars($info['rotulo']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="ambNome">Nome</label>
                    <input type="text" class="form-control" name="nome" id="ambNome" maxlength="80" placeholder="Como vai aparecer para o usuário">
                </div>

                <div class="amb-campos" data-tipos="atalho_site">
                    <label class="form-label" for="ambUrl">Endereço do site</label>
                    <input type="text" class="form-control mb-3" name="url" id="ambUrl" placeholder="https://sistema.exemplo.com.br">
                </div>

                <div class="amb-campos" data-tipos="inicializar atalho_programa">
                    <label class="form-label" for="ambCaminho">Caminho do programa</label>
                    <input type="text" class="form-control font-monospace" name="caminho" id="ambCaminho" placeholder="C:\Program Files (x86)\ShiftApp\ShiftApp.exe">
                    <div class="form-text mb-2">O programa precisa já estar instalado nas máquinas. Pode ser um caminho de rede (\\servidor\pasta\programa.exe).</div>
                    <label class="form-label" for="ambArgs">Parâmetros <span class="text-muted">(opcional)</span></label>
                    <input type="text" class="form-control font-monospace mb-3" name="argumentos" id="ambArgs">
                </div>
                <div class="amb-campos" data-tipos="atalho_programa">
                    <label class="form-label" for="ambPasta">Pasta de trabalho <span class="text-muted">(opcional)</span></label>
                    <input type="text" class="form-control font-monospace mb-3" name="pasta" id="ambPasta" placeholder="Em branco: a pasta do programa">
                </div>

                <div class="amb-campos" data-tipos="unidade_mapeada">
                    <div class="row g-2">
                        <div class="col-4 col-md-2">
                            <label class="form-label" for="ambLetra">Letra</label>
                            <select class="form-select" name="letra" id="ambLetra">
                                <?php foreach (range('D', 'Z') as $l): ?><option value="<?= $l ?>" <?= $l === 'P' ? 'selected' : '' ?>><?= $l ?>:</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-8 col-md-10">
                            <label class="form-label" for="ambUnc">Pasta de rede</label>
                            <input type="text" class="form-control font-monospace" name="unc" id="ambUnc" placeholder="\\192.168.1.15\Producao">
                        </div>
                    </div>
                    <div class="row g-2 mt-1 mb-1">
                        <div class="col-md-6">
                            <label class="form-label" for="ambUsuario">Usuário da pasta <span class="text-muted">(opcional)</span></label>
                            <input type="text" class="form-control" name="usuario" id="ambUsuario" autocomplete="off" placeholder="Em branco: entra com a conta da pessoa">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ambSenha">Senha</label>
                            <input type="password" class="form-control" name="senha" id="ambSenha" autocomplete="new-password">
                            <div class="form-text" id="ambSenhaDica">Fica guardada cifrada no portal e no Gerenciador de Credenciais do Windows de cada pessoa.</div>
                        </div>
                    </div>
                    <div class="mb-3"></div>
                </div>

                <div class="amb-campos" data-tipos="impressora">
                    <label class="form-label" for="ambFila">Impressora compartilhada</label>
                    <input type="text" class="form-control font-monospace mb-1" id="ambFila" placeholder="\\servidor\impressora" oninput="document.getElementById('ambUnc').value = this.value">
                    <div class="form-text mb-3">A impressora precisa estar compartilhada num servidor Windows; o driver vem de lá.</div>
                </div>

                <label class="form-label fw-semibold">Para quem vale</label>
                <div class="row g-2">
                    <div class="col-md-4">
                        <select class="form-select" name="escopo" id="ambEscopo" onchange="ambEscopoMudou()">
                            <option value="todos">Todas as máquinas e pessoas</option>
                            <option value="setor">Máquinas de um setor</option>
                            <option value="grupo" <?= $entraConfigurado ? '' : 'disabled' ?>>Pessoas de um grupo do Entra<?= $entraConfigurado ? '' : ' (Entra não configurado)' ?></option>
                        </select>
                    </div>
                    <div class="col-md-8" id="ambEscopoSetor" hidden>
                        <select class="form-select" name="setor_id" id="ambSetor">
                            <?php foreach ($setores as $s): ?><option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8" id="ambEscopoGrupo" hidden>
                        <select class="form-select" name="grupo_id" id="ambGrupo" onchange="document.getElementById('ambGrupoNome').value = this.options[this.selectedIndex]?.text || ''">
                            <option value="">Carregando grupos...</option>
                        </select>
                        <input type="hidden" name="grupo_nome" id="ambGrupoNome">
                    </div>
                </div>
                <div class="form-text" id="ambEscopoDica"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
const URL_GRUPOS = <?= json_encode(url('/ativos/ambiente-padrao/grupos')) ?>;
let gruposCarregados = false, grupoPendente = null;

function ambTipoAtual() { return (document.querySelector('input[name="tipo"]:checked') || {}).value || ''; }

function ambMostrarCampos() {
    const tipo = ambTipoAtual();
    document.querySelectorAll('.amb-campos').forEach(el => { el.hidden = !el.dataset.tipos.split(' ').includes(tipo); });
    // Impressora usa o mesmo campo "unc" da pasta mapeada (um só name no formulário).
    if (tipo === 'impressora') document.getElementById('ambUnc').value = document.getElementById('ambFila').value;
    ambEscopoMudou();
}

function ambEscopoMudou() {
    const escopo = document.getElementById('ambEscopo').value;
    const tipo = ambTipoAtual();
    document.getElementById('ambEscopoSetor').hidden = escopo !== 'setor';
    document.getElementById('ambEscopoGrupo').hidden = escopo !== 'grupo';
    if (escopo === 'grupo') ambCarregarGrupos();
    const doUsuario = tipo === 'unidade_mapeada' || tipo === 'impressora';
    document.getElementById('ambEscopoDica').textContent = escopo === 'grupo'
        ? 'Vale para essas pessoas em qualquer máquina com o agente.'
        : (doUsuario ? 'Aplicado no perfil de cada pessoa que entrar nessas máquinas.' : 'Aplicado no Windows inteiro: vale para todos que entrarem nessas máquinas.');
}

async function ambCarregarGrupos() {
    if (gruposCarregados) return;
    const sel = document.getElementById('ambGrupo');
    try {
        const grupos = await (await fetch(URL_GRUPOS)).json();
        sel.innerHTML = '<option value="">Escolha o grupo</option>' + grupos.map(g => `<option value="${g.id}">${g.nome.replace(/</g, '&lt;')}</option>`).join('');
        gruposCarregados = true;
        if (grupoPendente) { sel.value = grupoPendente; sel.dispatchEvent(new Event('change')); }
    } catch (e) { sel.innerHTML = '<option value="">Não foi possível carregar os grupos do Entra</option>'; }
}

function ambAbrir(titulo) {
    document.getElementById('ambTitulo').textContent = titulo;
    ambMostrarCampos();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ambModal')).show();
}

function ambNovo() {
    document.getElementById('ambForm').reset();
    document.getElementById('ambId').value = '';
    document.getElementById('ambTipo_inicializar').checked = true;
    document.getElementById('ambSenhaDica').textContent = 'Fica guardada cifrada no portal e no Gerenciador de Credenciais do Windows de cada pessoa.';
    grupoPendente = null;
    ambAbrir('Novo item');
}

function ambEditar(i) {
    document.getElementById('ambForm').reset();
    const d = i.dados || {};
    document.getElementById('ambId').value = i.id;
    document.getElementById('ambTipo_' + i.tipo).checked = true;
    document.getElementById('ambNome').value = i.nome;
    document.getElementById('ambUrl').value = d.url || '';
    document.getElementById('ambCaminho').value = d.caminho || '';
    document.getElementById('ambArgs').value = d.argumentos || '';
    document.getElementById('ambPasta').value = d.pasta || '';
    document.getElementById('ambLetra').value = d.letra || 'P';
    document.getElementById('ambUnc').value = d.unc || '';
    document.getElementById('ambFila').value = i.tipo === 'impressora' ? (d.unc || '') : '';
    document.getElementById('ambUsuario').value = d.usuario || '';
    document.getElementById('ambSenhaDica').textContent = i.tem_senha ? 'Em branco mantém a senha atual.' : 'Fica guardada cifrada no portal e no Gerenciador de Credenciais do Windows de cada pessoa.';
    document.getElementById('ambEscopo').value = i.escopo;
    if (i.escopo === 'setor') document.getElementById('ambSetor').value = i.escopo_ref;
    grupoPendente = i.escopo === 'grupo' ? i.escopo_ref : null;
    document.getElementById('ambGrupoNome').value = i.escopo_nome || '';
    if (grupoPendente && gruposCarregados) document.getElementById('ambGrupo').value = grupoPendente;
    ambAbrir('Editar item');
}

document.querySelectorAll('input[name="tipo"]').forEach(r => r.addEventListener('change', ambMostrarCampos));
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Ambiente padrão';
require __DIR__ . '/../layouts/main.php';
