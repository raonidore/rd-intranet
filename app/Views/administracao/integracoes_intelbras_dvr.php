<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-camera-video me-1"></i> DVR/NVR Intelbras</h4>
    <small class="text-muted">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-3" style="max-width:900px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong><i class="bi bi-hdd-network"></i> Credenciais por IP</strong>
            <?= $configurado ? Badge::make('Configurado', 'success') : Badge::make('Não configurado', 'secondary') ?>
        </div>

        <p class="text-muted small mb-3">
            Cada DVR/NVR tem o próprio login/senha admin -- cadastre um por IP aqui. Usada pela Ficha do
            Ativo (botão "Detectar e coletar automaticamente") pra buscar modelo, número de série,
            firmware, status do HD e a lista de canais/câmeras.
        </p>

        <?php if (empty($credenciais)): ?>
            <p class="text-muted small">Nenhuma credencial por IP cadastrada ainda.</p>
        <?php else: ?>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>IP</th><th>Usuário</th><th>Senha</th><th>Atualizado em</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($credenciais as $cred): ?>
                            <tr>
                                <td class="font-monospace"><?= htmlspecialchars($cred['ip']) ?></td>
                                <td><?= htmlspecialchars($cred['usuario']) ?></td>
                                <td>
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="senha-dvr font-monospace" data-senha="<?= htmlspecialchars($cred['senha']) ?>">••••••••</span>
                                        <button type="button" class="btn btn-sm btn-link p-0 botao-revelar-senha-dvr" title="Mostrar/ocultar"><i class="bi bi-eye"></i></button>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($cred['atualizado_em']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary botao-editar-credencial-dvr"
                                            data-ip="<?= htmlspecialchars($cred['ip']) ?>" data-usuario="<?= htmlspecialchars($cred['usuario']) ?>" data-senha="<?= htmlspecialchars($cred['senha']) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= url('/administracao/integracoes/intelbras-dvr/credenciais/remover') ?>" class="d-inline formRemoverCredencialDvr">
                                        <input type="hidden" name="ip" value="<?= htmlspecialchars($cred['ip']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('/administracao/integracoes/intelbras-dvr/credenciais/salvar') ?>" class="row g-2 align-items-end" id="formCredencialDvr">
            <div class="col-auto">
                <label class="form-label small mb-1">IP</label>
                <input type="text" name="ip" id="campoCredIp" class="form-control form-control-sm font-monospace" required placeholder="192.168.1.36" style="width:150px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Usuário</label>
                <input type="text" name="usuario" id="campoCredUsuario" class="form-control form-control-sm font-monospace" required placeholder="admin" style="width:130px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Senha</label>
                <input type="text" name="senha" id="campoCredSenha" class="form-control form-control-sm font-monospace" required style="width:180px">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary" id="botaoSalvarCredencialDvr"><i class="bi bi-plus-lg"></i> Adicionar</button>
            </div>
        </form>
        <div class="form-text mt-1">Salvar de novo com um IP já cadastrado atualiza a credencial dele (ou clique no lápis pra editar).</div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:900px">
    <div class="card-body">
        <strong>Credencial padrão (fallback)</strong>
        <p class="text-muted small mt-2 mb-3">
            Usada só quando um IP <strong>não</strong> tiver credencial própria cadastrada acima --
            cobre os casos onde vários DVR/NVR do site realmente compartilham a mesma senha admin, sem
            precisar cadastrar cada um.
        </p>

        <form method="post" action="<?= url('/administracao/integracoes/intelbras-dvr/salvar') ?>" class="row g-3">
            <div class="col-12">
                <label class="form-label">Usuário admin</label>
                <input type="text" name="usuario" class="form-control font-monospace"
                       value="<?= htmlspecialchars($usuarioAtual) ?>" placeholder="admin">
            </div>
            <div class="col-12">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control font-monospace"
                       placeholder="<?= $usuarioAtual !== '' ? '••••••••  (deixe em branco pra manter)' : 'senha do usuário acima' ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check-lg"></i> Salvar padrão</button>
                <?php if ($usuarioAtual !== ''): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="botaoRemoverConfigIntelbrasDvr"><i class="bi bi-trash"></i> Remover padrão</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= url('/administracao/integracoes/intelbras-dvr/remover') ?>" id="formRemoverConfigIntelbrasDvr" class="d-none"></form>
    </div>
</div>

<div class="card border-0 shadow-sm dvr-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-rocket-takeoff"></i> Como configurar</strong>
        <div class="dvr-steps mt-3">
            <div class="dvr-step">
                <div class="dvr-step-num">1</div>
                <div class="dvr-step-body">
                    <div class="dvr-step-title">Atualize o servidor</div>
                    <div class="dvr-step-text">Só na primeira vez, via SSH -- a migration cria o tipo de ativo "DVR/NVR".</div>
                    <code class="dvr-code">cd /var/www/rd.intranet &amp;&amp; git pull --ff-only &amp;&amp; php rd migrate</code>
                </div>
            </div>
            <div class="dvr-step">
                <div class="dvr-step-num">2</div>
                <div class="dvr-step-body">
                    <div class="dvr-step-title">Cadastre a credencial</div>
                    <div class="dvr-step-text">IP + usuário + senha admin na tabela "Credenciais por IP" acima. Vários equipamentos com a mesma senha? Use a "Credencial padrão" em vez de repetir.</div>
                </div>
            </div>
            <div class="dvr-step">
                <div class="dvr-step-num">3</div>
                <div class="dvr-step-body">
                    <div class="dvr-step-title">Cadastre o Ativo</div>
                    <div class="dvr-step-text">Ativos &gt; Novo Ativo &gt; informe o IP &gt; clique em "Detectar" -- identifica marca/modelo/firmware sozinho.</div>
                </div>
            </div>
            <div class="dvr-step">
                <div class="dvr-step-num">4</div>
                <div class="dvr-step-body">
                    <div class="dvr-step-title">Colete os dados completos</div>
                    <div class="dvr-step-text">Na ficha do ativo, "Detectar e coletar automaticamente" -- traz série, firmware, status do HD e a aba "Canais".</div>
                </div>
            </div>
            <div class="dvr-step dvr-step-last">
                <div class="dvr-step-num">5</div>
                <div class="dvr-step-body">
                    <div class="dvr-step-title">Ative a coleta periódica</div>
                    <div class="dvr-step-text">Dashboard de Ativos &gt; "Integrações de rede" &gt; "Ativar coleta" no card DVR/NVR Intelbras. <strong>Necessário</strong> pros alertas automáticos funcionarem.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 dvr-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-broadcast"></i> Alertas automáticos -- visão geral</strong>
        <p class="text-muted small mt-2 mb-3">Tudo roda sozinho, a cada coleta periódica. Detalhe completo de cada um logo abaixo.</p>
        <div class="table-responsive">
            <table class="table table-sm dvr-tabela-alertas align-middle mb-0">
                <thead>
                    <tr>
                        <th>Alerta</th>
                        <th>Nível</th>
                        <th>Abre chamado?</th>
                        <th>Onde aparece</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="bi bi-camera-video-off text-danger"></i> Sem sinal</td>
                        <td>Por canal</td>
                        <td><?= Badge::make('Automático', 'danger') ?></td>
                        <td class="text-muted small">Badge vermelho, aba "Canais"</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-camera-video text-warning"></i> Câmera tampada</td>
                        <td>Por canal</td>
                        <td><?= Badge::make('Só sinalização', 'secondary') ?></td>
                        <td class="text-muted small">Badge amarelo + botão "Testar"</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-hdd-network text-danger"></i> Problema no HD</td>
                        <td>Por equipamento</td>
                        <td><?= Badge::make('Automático - Urgente', 'danger') ?></td>
                        <td class="text-muted small">Aviso no topo da aba "Canais"</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 dvr-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-book"></i> Documentação técnica</strong>
        <p class="text-muted small mt-2 mb-3">Detalhe de cada alerta, como usar os canais e como a integração conversa com o equipamento por baixo dos panos.</p>

        <div class="accordion dvr-accordion" id="acordeaoDocDvr">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docSemSinal">
                        <i class="bi bi-camera-video-off text-danger me-2"></i> Sem sinal -- abre chamado automático
                    </button>
                </h2>
                <div id="docSemSinal" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocDvr">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            A cada coleta periódica, todo canal marcado <strong>"Em uso"</strong> (chave na aba
                            "Canais", ligada por padrão em todo canal novo) que estiver <strong>"Sem sinal"</strong>
                            e ainda não tiver um chamado em aberto abre um chamado automático --
                            <strong>não precisa ter "acabado de cair"</strong>: um canal que já estava sem sinal
                            antes mesmo da coleta periódica ser ativada também é avisado na primeira coleta
                            depois disso.
                        </p>
                        <ul class="small text-muted mb-0">
                            <li>Solicitante: <strong>RD.Intranet - Robô</strong> (<code>robo@rd.intranet</code>), canal de abertura "sistema".</li>
                            <li>Categoria: <strong><?= htmlspecialchars($categoriaAlerta['nome'] ?? 'DVR/NVR') ?></strong>
                                <?php if (!empty($categoriaAlerta['setor_nome'])): ?>
                                    -- setor padrão hoje: <strong><?= htmlspecialchars($categoriaAlerta['setor_nome']) ?></strong>.
                                <?php else: ?>
                                    -- <span class="text-danger">sem setor padrão configurado</span>, o chamado abre sem setor.
                                <?php endif; ?>
                                Pra trocar, edite a categoria "DVR/NVR" em <a href="<?= url('/chamados/categorias') ?>">Chamados &gt; Categorias</a> (busca por <strong>nome</strong>).
                            </li>
                            <li><strong>Não duplica</strong> -- com um chamado já aberto pra aquele canal, a próxima coleta não abre outro.</li>
                            <li><strong>Não fecha sozinho</strong> -- fechar (ou reabrir depois) é sempre manual.</li>
                            <li>Câmera que não existe de verdade? Desmarque "Em uso" nesse canal pra parar de gerar chamado.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docTampada">
                        <i class="bi bi-camera-video text-warning me-2"></i> Câmera tampada -- revisão manual, sem chamado
                    </button>
                </h2>
                <div id="docTampada" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocDvr">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            A cada coleta, o sistema também consulta o evento <strong>VideoBlind</strong> do
                            DVR/NVR (lente coberta/desfocada de propósito) e mostra um badge amarelo
                            <strong>"Tampada"</strong> na aba "Canais".
                        </p>
                        <div class="dvr-callout dvr-callout-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <div>
                                <strong>De propósito NÃO abre chamado automático.</strong> Testado ao vivo contra 3
                                DVRs reais, conferido canal por canal com a imagem: o próprio algoritmo do DVR
                                (config. "BlindDetect", sensibilidade padrão "Level 3") dispara falso positivo com
                                frequência em cena escura/baixo contraste, mesmo sem nada cobrindo a lente -- de 5
                                canais checados, só 2 eram problema real. O badge é só sinalização pro operador
                                conferir o snapshot e decidir.
                            </div>
                        </div>
                        <p class="text-muted small mt-3 mb-1"><strong>Duas chaves de controle, direto na aba "Canais":</strong></p>
                        <ul class="small text-muted mb-0">
                            <li><strong>Sensibilidade por canal</strong> (1 a 6, padrão de fábrica 3) -- grava direto no "BlindDetect" do DVR. Baixando pra 1 ou 2 numa cena escura, reduz o falso positivo na origem. O botão <strong>"Testar"</strong> confere na hora, sem esperar a próxima coleta.</li>
                            <li><strong>Liga/desliga por canal</strong> (ícone de sino) -- pra quando nem a sensibilidade mínima resolve (limitação conhecida do algoritmo em infravermelho/baixa luz, não é bug do RD.Intranet). Desliga só aquele canal, sem afetar os outros.</li>
                            <li><strong>Liga/desliga por DVR/NVR inteiro</strong> -- switch no cabeçalho da aba "Canais", já que cada equipamento fica num ambiente diferente.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docHd">
                        <i class="bi bi-hdd-network text-danger me-2"></i> Problema no HD -- abre chamado automático (Urgente)
                    </button>
                </h2>
                <div id="docHd" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocDvr">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            A cada coleta, o sistema também consulta <strong>StorageNotExist</strong> (disco não
                            encontrado) e <strong>StorageLowSpace</strong> (pouco espaço livre). Diferente dos
                            alertas por canal, esse é <strong>por equipamento</strong> (o HD é compartilhado por
                            todos os canais) -- aviso vermelho no topo da aba "Canais", com link pro chamado.
                        </p>
                        <ul class="small text-muted mb-0">
                            <li>Mesmo solicitante e categoria dos outros alertas; prioridade <strong>Urgente</strong> (perda de gravação é mais grave que uma câmera fora do ar).</li>
                            <li><strong>Não duplica</strong> nem fecha sozinho, mesma regra dos demais.</li>
                            <li>Nunca observamos ao vivo uma resposta positiva desses dois eventos (DVRs em produção sempre "sem ocorrência") -- o alerta está pronto pro primeiro caso real, mas o formato exato da resposta não pôde ser confirmado por falta de um HD com falha pra testar.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docCanais">
                        <i class="bi bi-camera-video me-2"></i> Ver canal e renomear
                    </button>
                </h2>
                <div id="docCanais" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocDvr">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Na aba "Canais", cada linha tem dois botões: o de câmera abre uma <strong>foto do
                            momento</strong> (não é vídeo ao vivo contínuo -- isso exigiria um servidor de mídia à
                            parte, RTSP não roda direto em HTML); o lápis renomeia o canal de verdade no próprio
                            DVR (mesmo nome que aparece na tela dele). No modal da foto dá pra <strong>atualizar</strong>
                            sem fechar e <strong>baixar a imagem</strong> direto, sem precisar de print de tela.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docTecnico">
                        <i class="bi bi-cpu me-2"></i> Como funciona por baixo dos panos
                    </button>
                </h2>
                <div id="docTecnico" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocDvr">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            DVR/NVR Intelbras rodam firmware da família Dahua (rebrand) -- confirmado testando ao
                            vivo: o endpoint <code>/cgi-bin/magicBox.cgi</code> responde normalmente, só pedindo
                            autenticação (Digest, com o usuário/senha configurados acima). A Intelbras trata a
                            documentação oficial dessa API como confidencial (exige acordo de confidencialidade
                            com o CNPJ da empresa) -- mas o próprio PDF "API of HTTP Protocol Specification
                            V3.35_Intelbras" (com a marca Intelbras na capa) está publicamente hospedado, e
                            confirma ser o mesmo protocolo HTTP da Dahua. Cada comando novo (snapshot, renomear
                            canal, ajustar sensibilidade etc.) foi testado ao vivo contra um equipamento real antes
                            de entrar no sistema -- inclusive os que escrevem algo no DVR foram testados e
                            revertidos manualmente antes de qualquer linha de código.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.dvr-doc-card .card-body { padding: 1.25rem 1.5rem; }

.dvr-steps { display: flex; flex-direction: column; gap: 0; }
.dvr-step { display: flex; gap: .9rem; position: relative; padding-bottom: 1.25rem; }
.dvr-step::before {
    content: ''; position: absolute; left: 13px; top: 30px; bottom: 0;
    width: 2px; background: linear-gradient(to bottom, #cfe2ff, #e9ecef);
}
.dvr-step-last::before { display: none; }
.dvr-step-num {
    flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%;
    background: #0d6efd; color: #fff; font-weight: 600; font-size: .8rem;
    display: flex; align-items: center; justify-content: center; z-index: 1;
}
.dvr-step-title { font-weight: 600; font-size: .9rem; }
.dvr-step-text { color: #6c757d; font-size: .82rem; margin-top: 2px; }
.dvr-code {
    display: block; margin-top: .5rem; padding: .5rem .75rem; border-radius: .375rem;
    background: #0d1117; color: #7ee787; font-size: .78rem; overflow-x: auto; white-space: pre;
}

.dvr-tabela-alertas th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; border-top: none; }
.dvr-tabela-alertas td { font-size: .85rem; }

.dvr-accordion .accordion-button {
    font-size: .88rem; font-weight: 600; background: #f8f9fa;
}
.dvr-accordion .accordion-button:not(.collapsed) {
    background: #eef4ff; color: #0d3b8c; box-shadow: none;
}
.dvr-accordion .accordion-button:focus { box-shadow: none; }
.dvr-accordion .accordion-item { border-color: #e9ecef; }

.dvr-callout {
    display: flex; gap: .6rem; padding: .75rem .9rem; border-radius: .5rem; font-size: .82rem; margin-top: .75rem;
}
.dvr-callout i { font-size: 1.1rem; flex: 0 0 auto; }
.dvr-callout-warning { background: #fff8e6; color: #664d03; border: 1px solid #ffe69c; }
.dvr-callout-warning i { color: #997404; }
</style>

<script>
(function () {
    const botaoRemover = document.getElementById('botaoRemoverConfigIntelbrasDvr');
    if (botaoRemover) {
        botaoRemover.addEventListener('click', function () {
            if (confirm('Remover a credencial padrão? Os DVR/NVR sem credencial própria por IP param de coletar até ser configurada de novo.')) {
                document.getElementById('formRemoverConfigIntelbrasDvr').submit();
            }
        });
    }

    document.querySelectorAll('.botao-revelar-senha-dvr').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const span = botao.closest('span').querySelector('.senha-dvr');
            const icone = botao.querySelector('i');
            const revelado = span.textContent !== '••••••••';

            if (revelado) {
                span.textContent = '••••••••';
                icone.className = 'bi bi-eye';
            } else {
                span.textContent = span.dataset.senha || '(vazio)';
                icone.className = 'bi bi-eye-slash';
            }
        });
    });

    document.querySelectorAll('.botao-editar-credencial-dvr').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById('campoCredIp').value = botao.dataset.ip;
            document.getElementById('campoCredUsuario').value = botao.dataset.usuario;
            document.getElementById('campoCredSenha').value = botao.dataset.senha;
            document.getElementById('campoCredIp').scrollIntoView({ behavior: 'smooth', block: 'center' });
            document.getElementById('campoCredUsuario').focus();
        });
    });

    document.querySelectorAll('.formRemoverCredencialDvr').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const ip = form.querySelector('input[name="ip"]').value;
            if (!confirm('Remover a credencial de ' + ip + '? A coleta desse equipamento para de funcionar até cadastrar de novo (ou até ele passar a usar a credencial padrão, se houver).')) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - DVR/NVR Intelbras';

require __DIR__ . '/../layouts/main.php';
