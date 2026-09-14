<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-plus-circle me-1"></i> Abrir Chamado</h4>
        <small class="text-muted">Precisa de ajuda com algo? Abra um chamado e nossa equipe entra em contato.</small>
    </div>
    <button type="button" class="btn btn-outline-dark text-nowrap" data-bs-toggle="modal" data-bs-target="#modalPopChamados">
        <i class="bi bi-broadcast"></i> POP - Chamados
    </button>
</div>

<!-- POP -- Procedimento Operacional Padrão de abertura de chamado (mesmo conteúdo de chamados/atendimentos.php) -->
<div class="modal fade pop-modal" id="modalPopChamados" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="pop-topbar">
                <span class="pop-breadcrumb"><i class="bi bi-broadcast"></i> POP -- Abertura de Chamado</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="pop-body">
                <p class="pop-intro">Passo a passo de cada campo do formulário "Abrir Chamado" -- preenchendo nessa ordem, nada fica pra trás.</p>

                <div class="pop-step">
                    <div class="pop-step-num">1</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-card-text"></i> Título</div>
                        <div class="pop-step-text">Resuma o problema numa linha, específico o bastante pra identificar sem precisar abrir o chamado. <strong>"Impressora não imprime -- 2º andar financeiro"</strong> é melhor que só "Impressora com problema".</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">2</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-flag"></i> Prioridade</div>
                        <div class="pop-step-text">Baixa, Média, Alta ou Urgente -- define o <strong>prazo de SLA</strong> (1ª resposta e resolução) que se aplica ao chamado, configurado por categoria.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">3</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-tags"></i> Categoria</div>
                        <div class="pop-step-text">Obrigatória -- além de classificar o chamado, define o <strong>setor responsável padrão</strong> (Chamados &gt; Categorias) e o SLA que vale pra ele.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">4</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-diagram-3"></i> Setor responsável <span class="text-muted small fw-normal">(opcional)</span></div>
                        <div class="pop-step-text">Só preencha se quiser <strong>desviar</strong> do setor padrão da categoria escolhida -- deixando em branco, usa o padrão configurado nela.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">5</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-signpost-2"></i> Unidade</div>
                        <div class="pop-step-text">Obrigatória -- a filial/site ao qual o chamado se refere.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">6</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-hdd-network"></i> Ativo relacionado <span class="text-muted small fw-normal">(opcional)</span></div>
                        <div class="pop-step-text">Se o chamado for sobre um equipamento cadastrado, busque por <strong>código de patrimônio, nome ou número de série</strong> -- vincula o chamado direto na ficha do Ativo.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">7</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-file-text"></i> Descrição</div>
                        <div class="pop-step-text">Quanto mais detalhe, melhor pra quem for atender. A partir de 8 caracteres, o sistema já sugere artigos da <strong>Base de Conhecimento</strong> que podem ter a solução, em tempo real enquanto você digita.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">8</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-person-lines-fill"></i> Solicitante</div>
                        <div class="pop-step-text">Busque um <strong>usuário já cadastrado</strong> no sistema (preenche nome e e-mail sozinho) ou digite manualmente. É obrigatório informar <strong>e-mail ou telefone</strong> -- é por ali que o solicitante recebe as atualizações do chamado (por e-mail e/ou WhatsApp, conforme o que estiver configurado).</div>
                        <div class="pop-callout pop-callout-info">
                            <i class="bi bi-info-circle"></i>
                            <div>Sem e-mail nem telefone, o formulário recusa o envio e destaca os dois campos em vermelho.</div>
                        </div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">9</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-send"></i> Abrir chamado</div>
                        <div class="pop-step-text">Registra o chamado -- a partir daí, respostas públicas e mudanças de status já notificam o solicitante automaticamente.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pop-modal .modal-content { background:#0d1117; color:#c9d1d9; border:1px solid #30363d; border-radius:14px; }
.pop-topbar { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; background:#161b22; border-bottom:1px solid #30363d; border-radius:14px 14px 0 0; }
.pop-topbar .pop-breadcrumb { font-weight:600; color:#58a6ff; display:flex; align-items:center; gap:8px; font-size:1rem; }
.pop-body { padding:1.3rem 1.6rem; max-height:72vh; overflow-y:auto; }
.pop-intro { color:#8b949e; font-size:.88rem; margin-bottom:1.2rem; padding-bottom:1rem; border-bottom:1px dashed #30363d; }
.pop-step { display:flex; gap:1rem; padding:.85rem 0; border-bottom:1px solid #21262d; }
.pop-step:last-child { border-bottom:0; padding-bottom:0; }
.pop-step-num { flex:0 0 auto; width:34px; height:34px; border-radius:9px; background:#132030; color:#58a6ff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.95rem; border:1px solid #1f3b57; }
.pop-step-title { font-weight:600; color:#e6edf3; font-size:.92rem; display:flex; align-items:center; gap:6px; }
.pop-step-text { color:#8b949e; font-size:.83rem; margin-top:3px; line-height:1.55; }
.pop-step-text strong { color:#c9d1d9; }
.pop-step-text code { background:#1c2733; color:#7ee787; padding:1px 5px; border-radius:4px; font-size:.78rem; }
.pop-callout { display:flex; gap:.6rem; padding:.7rem .9rem; border-radius:8px; font-size:.82rem; margin-top:.6rem; }
.pop-callout-info { background:rgba(12,45,74,.35); border:1px solid rgba(31,111,235,.35); color:#79c0ff; }
.pop-callout-warning { background:rgba(59,47,0,.2); border:1px solid rgba(125,90,0,.35); color:#e3b341; }
.pop-callout i { flex:0 0 auto; }

.abrir-card { border:1px solid #e2e8f0; border-radius:16px; padding:3rem 2rem; text-align:center; }
.abrir-card i.bi-ticket-perforated { font-size:3rem; color:#0d6efd; }
</style>

<div class="card border-0 shadow-sm">
    <div class="card-body abrir-card">
        <i class="bi bi-ticket-perforated d-block mb-3"></i>
        <h5 class="mb-2">Está com algum problema ou precisa de suporte?</h5>
        <p class="text-muted mb-4">
            Abra um chamado contando o que está acontecendo -- ele vai direto pra fila do setor responsável,
            e você recebe as atualizações por e-mail e/ou WhatsApp conforme for respondido.
        </p>
        <a href="<?= url('/chamados/atendimentos/novo') ?>" class="btn btn-primary btn-lg">
            <i class="bi bi-plus-lg"></i> Abrir chamado
        </a>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Abrir Chamado';

require __DIR__ . '/../layouts/main.php';
