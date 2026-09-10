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

<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-body">
        <strong><i class="bi bi-list-ol"></i> Passo a passo completo</strong>
        <ol class="small text-muted mt-2 mb-0">
            <li class="mb-2">
                <strong>Código atualizado</strong> -- se esta tela é nova pra você, atualize o servidor
                primeiro (via SSH):
                <br><code>cd /var/www/rd.intranet &amp;&amp; git pull --ff-only &amp;&amp; php rd migrate</code>
                <br>A migration cria o tipo de ativo "DVR/NVR" -- sem ela, esse tipo não aparece no
                cadastro de Ativos.
            </li>
            <li class="mb-2">
                <strong>Cadastre a credencial do equipamento</strong> -- IP, usuário e senha admin (o
                mesmo login usado pra entrar na interface web dele) na tabela "Credenciais por IP" acima.
                Se vários DVR/NVR do site usam a mesma senha, configure só a "Credencial padrão" em vez de
                repetir em cada IP.
            </li>
            <li class="mb-2">
                <strong>Cadastre o equipamento como Ativo</strong> -- em Ativos &gt; Novo Ativo, informe o
                IP e clique em <strong>"Detectar"</strong> ao lado do campo IP: o sistema identifica
                sozinho e preenche marca/modelo/firmware.
            </li>
            <li class="mb-2">
                <strong>Colete os dados completos</strong> -- na ficha do ativo já salvo, clique em
                <strong>"Detectar e coletar automaticamente"</strong> pra trazer modelo, número de série,
                versão de firmware/hardware, status do HD e a aba "Canais" (nome e status de sinal de cada
                câmera).
            </li>
            <li>
                <strong>Ative a coleta periódica</strong> -- no Dashboard de Ativos, aba "Integrações de
                rede", botão "Ativar coleta" no card DVR/NVR Intelbras. <strong>Necessário</strong> pro
                alerta automático de canal sem sinal funcionar (veja abaixo) -- sem a coleta rodando
                sozinha a cada 30 min, ninguém detecta a queda de sinal.
            </li>
        </ol>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:900px">
    <div class="card-body">
        <strong><i class="bi bi-camera-video"></i> Ver canal, renomear canal</strong>
        <p class="text-muted small mt-2 mb-0">
            Na aba "Canais" da ficha do ativo, cada canal tem dois botões: o de câmera abre uma
            <strong>foto do momento</strong> (não é vídeo ao vivo contínuo -- transmitir vídeo de verdade
            no navegador exige um servidor de mídia à parte, RTSP não roda direto em HTML); o lápis
            renomeia o canal de verdade no próprio DVR (mesmo nome que aparece na tela dele).
        </p>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:900px">
    <div class="card-body">
        <strong><i class="bi bi-exclamation-triangle"></i> Alerta automático de canal sem sinal</strong>
        <p class="text-muted small mt-2 mb-3">
            A cada coleta periódica, todo canal marcado <strong>"Em uso"</strong> (chave na aba "Canais"
            da ficha do ativo, ligada por padrão em todo canal novo) que estiver <strong>"Sem
            sinal"</strong> e ainda não tiver um chamado em aberto abre um chamado automático --
            <strong>não precisa ter "acabado de cair"</strong>: um canal que já estava sem sinal antes
            mesmo da coleta periódica ser ativada também é avisado na primeira coleta depois disso, não
            só quem falha depois de já estar sendo monitorado.
        </p>
        <ul class="small text-muted mb-3">
            <li>Solicitante: <strong>RD.Intranet - Robô</strong> (<code>robo@rd.intranet</code>), canal de
                abertura "sistema" -- pra ficar claro na lista de chamados que ninguém abriu isso na mão.</li>
            <li>Categoria: <strong><?= htmlspecialchars($categoriaAlerta['nome'] ?? 'DVR/NVR') ?></strong>
                <?php if (!empty($categoriaAlerta['setor_nome'])): ?>
                    -- setor padrão dessa categoria hoje: <strong><?= htmlspecialchars($categoriaAlerta['setor_nome']) ?></strong>.
                <?php else: ?>
                    -- <span class="text-danger">essa categoria ainda não tem setor padrão configurado</span>, o chamado abre sem setor.
                <?php endif; ?>
                Pra trocar, edite a categoria "DVR/NVR" em <a href="<?= url('/chamados/categorias') ?>">Chamados &gt; Categorias</a> (o sistema busca essa categoria pelo <strong>nome</strong>, não muda o comportamento se você mudar o setor dela).
            </li>
            <li><strong>Não duplica</strong> -- enquanto já existir um chamado aberto (fila/em atendimento/aguardando cliente) pra aquele canal, a próxima coleta não abre outro, mesmo que continue sem sinal.</li>
            <li><strong>Não fecha sozinho</strong> quando o sinal volta -- fechar (ou reabrir, se cair de novo depois de fechado) é sempre manual.</li>
            <li>Câmera que <strong>não existe de verdade</strong> (canal sobrando, nunca vai ter sinal)? Desmarque "Em uso" nesse canal -- ele para de gerar chamado, mas continua aparecendo na lista normalmente.</li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:900px">
    <div class="card-body">
        <strong><i class="bi bi-camera-video-off"></i> Detecção de câmera tampada (revisão manual, sem chamado automático)</strong>
        <p class="text-muted small mt-2 mb-3">
            A cada coleta, o sistema também consulta o evento <strong>VideoBlind</strong> do DVR/NVR
            (lente coberta/desfocada de propósito, diferente de "Sem sinal") e mostra um badge amarelo
            <strong>"Tampada"</strong> na aba "Canais" pra todo canal que o próprio DVR estiver reportando
            assim.
        </p>
        <p class="text-muted small mt-2 mb-3">
            <strong>De propósito NÃO abre chamado automático</strong> como o de "Sem sinal" -- testado ao
            vivo contra os 3 DVRs em produção e conferido canal por canal comparando com a imagem real
            (botão de câmera): o algoritmo de detecção do próprio DVR (configuração "BlindDetect",
            sensibilidade padrão "Level 3" em todo canal) dispara falso positivo com frequência em cenas
            escuras ou de baixo contraste (chão liso à noite, parede escura) mesmo sem nada cobrindo a
            lente de verdade -- de 5 canais checados, só 2 eram problema real. Automatizar a abertura de
            chamado em cima desse sinal geraria chamado sem necessidade com frequência. O badge fica só
            como sinalização pro operador conferir o snapshot e decidir -- o mesmo processo manual que já
            vinha sendo usado antes dessa tela existir.
        </p>
        <p class="text-muted small mt-2 mb-3">
            <strong>Sensibilidade ajustável por canal</strong> -- a aba "Canais" da ficha de cada ativo tem
            uma coluna "Sensibilidade tampada" (1 a 6, padrão de fábrica 3) que grava direto na
            configuração "BlindDetect" do próprio DVR, e um botão "Testar" que checa na hora (sem esperar
            a próxima coleta) se o canal ainda está disparando com o nível atual. Baixando pra 1 ou 2 no
            canal que fica de frente pra uma cena escura/de baixo contraste, o próprio detector do DVR
            fica menos propenso a disparar falso positivo -- ataca o problema na origem.
        </p>
        <p class="text-muted small mt-2 mb-0">
            <strong>A chave de ativar/desativar é por DVR/NVR</strong>, não geral do sistema -- cada
            equipamento fica num ambiente diferente (iluminação, contraste), então o quanto essa detecção
            atrapalha ou ajuda pode variar de um pro outro. Fica na aba "Canais" da ficha de cada ativo, ao
            lado do título "Canais".
        </p>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:900px">
    <div class="card-body">
        <strong><i class="bi bi-hdd-network"></i> Alerta automático de problema no HD</strong>
        <p class="text-muted small mt-2 mb-3">
            A cada coleta periódica, o sistema também consulta os eventos <strong>StorageNotExist</strong>
            (disco não encontrado/desconectado) e <strong>StorageLowSpace</strong> (pouco espaço livre) do
            DVR/NVR. Diferente do alerta por canal, esse é <strong>por equipamento</strong> (o HD é
            compartilhado por todos os canais) -- aparece como um aviso vermelho no topo da aba "Canais"
            da ficha do ativo, com link pro chamado automático aberto.
        </p>
        <ul class="small text-muted mb-0">
            <li>Mesmo solicitante e categoria/setor dos outros dois alertas; prioridade <strong>Urgente</strong> (perda de gravação é mais grave que uma câmera fora do ar).</li>
            <li><strong>Não duplica</strong> nem fecha sozinho, mesma regra dos outros alertas.</li>
            <li>Nunca observamos ao vivo uma resposta positiva desses dois eventos (os DVRs em produção sempre reportam "sem ocorrência") -- o alerta está pronto e vai disparar no primeiro caso real, mas o formato exato da resposta da Intelbras nesse cenário não pôde ser confirmado por falta de um HD com falha pra testar.</li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:720px">
    <div class="card-body">
        <strong><i class="bi bi-info-circle"></i> Como isso funciona por baixo dos panos</strong>
        <p class="text-muted small mt-2 mb-0">
            DVR/NVR Intelbras rodam firmware da família Dahua (rebrand) -- confirmado testando ao vivo
            contra um equipamento real: o endpoint <code>/cgi-bin/magicBox.cgi</code> responde
            normalmente, só pedindo autenticação (Digest, com o usuário/senha configurados acima). A
            Intelbras trata a documentação oficial dessa API como confidencial (exige acordo de
            confidencialidade assinado com o CNPJ da empresa pra liberar) -- mas o próprio PDF
            "API of HTTP Protocol Specification V3.35_Intelbras" (a documentação oficial, com a marca
            Intelbras na capa) está publicamente hospedado, e confirma ser o mesmo protocolo HTTP da
            Dahua. Cada comando novo (snapshot, renomear canal, etc.) foi testado ao vivo contra um
            equipamento real antes de entrar no sistema -- inclusive os que escrevem algo no DVR (ex:
            renomear canal) foram testados e revertidos manualmente antes de qualquer linha de código.
        </p>
    </div>
</div>

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
