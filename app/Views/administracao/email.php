<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-envelope me-1"></i> E-mail (SMTP)</h4>
    <small class="text-muted d-block mb-1">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
    <small class="text-muted">
        Uma conta SMTP só, pro sistema inteiro -- usada por vários módulos diferentes pra avisar gente que nem
        sempre tem login aqui dentro (solicitante de chamado, participante externo de projeto). Veja a lista
        completa mais abaixo.
    </small>
</div>

<div class="card border-0 shadow-sm" style="max-width:640px">
    <div class="card-body">
        <form method="post" action="<?= url('/administracao/email/salvar') ?>" id="formEmail">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Servidor SMTP</label>
                    <input type="text" name="host" class="form-control" required
                           value="<?= htmlspecialchars($host) ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Porta</label>
                    <input type="number" name="porta" class="form-control" required value="<?= (int)$porta ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Usuário</label>
                    <input type="text" name="usuario" class="form-control" required
                           value="<?= htmlspecialchars($usuario) ?>" placeholder="backup@suaempresa.com.br">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control"
                           placeholder="<?= $configurado ? '•••••••• (deixe em branco para manter)' : 'senha ou senha de app' ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Criptografia</label>
                    <select name="criptografia" class="form-select">
                        <option value="tls" <?= $criptografia === 'tls' ? 'selected' : '' ?>>STARTTLS</option>
                        <option value="ssl" <?= $criptografia === 'ssl' ? 'selected' : '' ?>>SSL/TLS</option>
                        <option value="nenhuma" <?= $criptografia === 'nenhuma' ? 'selected' : '' ?>>Nenhuma</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nome do remetente</label>
                    <input type="text" name="remetente_nome" class="form-control"
                           value="<?= htmlspecialchars($remetenteNome) ?>" placeholder="RD Intranet">
                </div>
                <div class="col-md-4">
                    <label class="form-label">E-mail do remetente</label>
                    <input type="email" name="remetente_email" class="form-control" required
                           value="<?= htmlspecialchars($remetenteEmail) ?>" placeholder="backup@suaempresa.com.br">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <div>
                    <?= $configurado
                        ? '<span class="badge text-bg-success">Configurado</span>'
                        : '<span class="badge text-bg-secondary">Não configurado</span>' ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="botaoTestarEmail" <?= $configurado ? '' : 'disabled title="Salve a configuração antes de testar"' ?>>
                        <i class="bi bi-send"></i> Enviar e-mail de teste
                    </button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </div>
        </form>

        <div class="alert alert-info small mt-3 mb-0" id="resultadoTeste" style="display:none"></div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 email-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-rocket-takeoff"></i> Como configurar</strong>
        <div class="email-steps mt-3">
            <div class="email-step">
                <div class="email-step-num">1</div>
                <div class="email-step-body">
                    <div class="email-step-title">Escolha (ou crie) a conta de e-mail que vai enviar</div>
                    <div class="email-step-text">Pode ser a conta da empresa (ex: <code>contato@suaempresa.com.br</code>, no provedor de e-mail de vocês) ou uma conta dedicada só pra isso -- o nome/e-mail que aparece pro destinatário é o "Remetente" configurado aqui, não precisa ser igual ao "Usuário" de login.</div>
                </div>
            </div>
            <div class="email-step">
                <div class="email-step-num">2</div>
                <div class="email-step-body">
                    <div class="email-step-title">Gmail/Google Workspace exige "senha de app"</div>
                    <div class="email-step-text">Se for usar uma conta Gmail, a senha normal <strong>não funciona</strong> aqui -- é preciso ativar a verificação em 2 etapas na conta Google e gerar uma "Senha de app" específica (Conta Google &gt; Segurança &gt; Senhas de app). Cole essa senha de 16 letras no campo "Senha" abaixo.</div>
                </div>
            </div>
            <div class="email-step">
                <div class="email-step-num">3</div>
                <div class="email-step-body">
                    <div class="email-step-title">Preencha o formulário acima</div>
                    <div class="email-step-text">Servidor e porta variam por provedor -- os mais comuns: Gmail (<code>smtp.gmail.com</code>, porta <code>587</code>, STARTTLS), Outlook/Office 365 (<code>smtp.office365.com</code>, porta <code>587</code>, STARTTLS), provedores de hospedagem costumam usar porta <code>465</code> com SSL/TLS.</div>
                </div>
            </div>
            <div class="email-step">
                <div class="email-step-num">4</div>
                <div class="email-step-body">
                    <div class="email-step-title">Salve e envie um e-mail de teste</div>
                    <div class="email-step-text">O botão "Enviar e-mail de teste" pede um endereço de destino na hora e manda uma mensagem de verdade -- é o jeito mais rápido de saber se host/porta/senha estão certos antes de depender disso em produção.</div>
                </div>
            </div>
            <div class="email-step email-step-last">
                <div class="email-step-num">5</div>
                <div class="email-step-body">
                    <div class="email-step-title">Pronto -- os módulos abaixo passam a funcionar sozinhos</div>
                    <div class="email-step-text">Não precisa configurar nada em cada módulo individualmente -- todos usam esta mesma conta automaticamente assim que ela estiver "Configurada" (badge verde acima).</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 email-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-diagram-3"></i> Módulos que usam este e-mail -- visão geral</strong>
        <p class="text-muted small mt-2 mb-3">Nenhum desses módulos manda e-mail por conta própria -- todos passam por aqui. Sem SMTP configurado, cada um deles simplesmente não envia nada (nenhum trava por causa disso).</p>
        <div class="table-responsive">
            <table class="table table-sm email-tabela-modulos align-middle mb-0">
                <thead>
                    <tr>
                        <th>Módulo</th>
                        <th>Quando dispara</th>
                        <th>Pra quem</th>
                        <th>Gatilho</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="bi bi-cloud-arrow-up text-primary"></i> Backup em Nuvem</td>
                        <td class="text-muted small">Ao concluir um backup (sucesso e/ou falha, por destino)</td>
                        <td class="text-muted small">E-mails cadastrados em cada destino de backup</td>
                        <td><?= \App\Components\Badge::make('Automático', 'secondary') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-ticket-perforated text-primary"></i> Chamados -- resposta pública</td>
                        <td class="text-muted small">Alguém do time responde um chamado com comentário "público"</td>
                        <td class="text-muted small">E-mail do solicitante</td>
                        <td><?= \App\Components\Badge::make('Automático', 'secondary') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-star text-warning"></i> Chamados -- avaliação de atendimento</td>
                        <td class="text-muted small">Chamado é marcado como "Resolvido"</td>
                        <td class="text-muted small">E-mail do solicitante (cai pro WhatsApp se não tiver e-mail)</td>
                        <td><?= \App\Components\Badge::make('Automático', 'secondary') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-box-arrow-in-right text-info"></i> Portal do Solicitante</td>
                        <td class="text-muted small">Solicitante pede acesso ao portal (login sem senha)</td>
                        <td class="text-muted small">E-mail informado no login do portal</td>
                        <td><?= \App\Components\Badge::make('Sob demanda', 'info') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-kanban text-info"></i> Portal de Projetos</td>
                        <td class="text-muted small">Participante externo pede acesso ao portal (login sem senha)</td>
                        <td class="text-muted small">E-mail cadastrado do participante</td>
                        <td><?= \App\Components\Badge::make('Sob demanda', 'info') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-people text-primary"></i> Notificações de Projetos</td>
                        <td class="text-muted small">Alguém é incluído num projeto/tarefa, ou o prazo muda</td>
                        <td class="text-muted small">Participante interno (e-mail) ou externo (e-mail e/ou WhatsApp)</td>
                        <td><?= \App\Components\Badge::make('Automático', 'secondary') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-shield-lock text-danger"></i> Certificado HTTPS</td>
                        <td class="text-muted small">Certificado perto de vencer ou já vencido</td>
                        <td class="text-muted small">E-mail de alerta cadastrado em Segurança &gt; Certificado</td>
                        <td><?= \App\Components\Badge::make('Automático (cron diário)', 'secondary') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-key text-secondary"></i> Recuperação de senha</td>
                        <td class="text-muted small">Usuário clica em "Esqueci minha senha" no login</td>
                        <td class="text-muted small">E-mail cadastrado do usuário (Administração &gt; Usuários)</td>
                        <td><?= \App\Components\Badge::make('Sob demanda', 'info') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 email-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-book"></i> Documentação técnica</strong>
        <p class="text-muted small mt-2 mb-3">Detalhe de cada módulo e como o envio funciona por baixo dos panos.</p>

        <div class="accordion email-accordion" id="acordeaoDocEmail">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docBackupEmail">
                        <i class="bi bi-cloud-arrow-up text-primary me-2"></i> Backup em Nuvem
                    </button>
                </h2>
                <div id="docBackupEmail" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocEmail">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Em <a href="<?= url('/backup/configuracao') ?>">Backup &gt; Configuração</a>, cada destino de
                            backup tem seu próprio campo de "e-mail de notificação" (aceita vários endereços separados
                            por vírgula), com duas chaves independentes: avisar <strong>ao concluir com sucesso</strong>
                            (relatório do que foi salvo) e avisar <strong>ao falhar</strong> (pra alguém saber na hora
                            que o backup daquele destino parou de rodar, sem precisar ficar checando manualmente).
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docChamadosEmail">
                        <i class="bi bi-ticket-perforated text-primary me-2"></i> Chamados -- resposta e avaliação
                    </button>
                </h2>
                <div id="docChamadosEmail" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocEmail">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            Dois momentos diferentes do mesmo chamado disparam e-mail pro solicitante:
                        </p>
                        <ul class="small text-muted mb-0">
                            <li><strong>Resposta pública</strong> -- todo comentário marcado como "público" (visível pro solicitante, diferente de uma nota interna do time) manda o conteúdo da resposta por e-mail na hora.</li>
                            <li><strong>Avaliação de atendimento</strong> -- assim que o chamado passa pra "Resolvido", o solicitante recebe um link pra avaliar o atendimento (nota + comentário). Se ele não tiver e-mail cadastrado mas tiver telefone, a pergunta vai por WhatsApp em vez disso.</li>
                            <li>Os dois usam o mesmo link de acesso ao <strong>Portal do Solicitante</strong> (login sem senha) por baixo dos panos.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docPortaisEmail">
                        <i class="bi bi-box-arrow-in-right text-info me-2"></i> Portais (Solicitante e Projetos) -- login sem senha
                    </button>
                </h2>
                <div id="docPortaisEmail" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocEmail">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Solicitante de chamado e participante externo de projeto não têm usuário/senha nesse
                            sistema -- pra acessar o portal deles, informam o e-mail cadastrado e recebem um
                            <strong>link mágico</strong> (token de uso único, com validade) por e-mail. Sem SMTP
                            configurado, esses dois portais escondem sozinhos a opção de login por e-mail na tela
                            (não aparece um formulário quebrado, simplesmente não oferece essa via).
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docProjetosEmail">
                        <i class="bi bi-people text-primary me-2"></i> Notificações de Projetos
                    </button>
                </h2>
                <div id="docProjetosEmail" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocEmail">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Participante <strong>interno</strong> (já é usuário do sistema) só recebe aviso por
                            e-mail -- não tem campo de telefone cadastrado. Participante <strong>externo</strong> tem
                            telefone próprio no cadastro do projeto, então recebe pelos dois canais quando o campo
                            estiver preenchido (e-mail sempre que possível, WhatsApp complementar). Dispara ao incluir
                            alguém num projeto/tarefa nova.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docCertificadoEmail">
                        <i class="bi bi-shield-lock text-danger me-2"></i> Alerta de certificado HTTPS
                    </button>
                </h2>
                <div id="docCertificadoEmail" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocEmail">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Um cron diário confere a validade do certificado HTTPS instalado (normalmente renovado
                            sozinho via Let's Encrypt) e manda um alerta <strong>só quando algo está errado</strong>
                            -- perto de vencer ou já vencido -- pro e-mail cadastrado em Segurança &gt; Certificado
                            (endereço próprio, separado do "Remetente" configurado aqui). Existe de propósito pra
                            avisar quando a renovação automática falhar, em vez de deixar o site parar de responder
                            HTTPS sem ninguém perceber.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docSenhaEmail">
                        <i class="bi bi-key text-secondary me-2"></i> Recuperação de senha
                    </button>
                </h2>
                <div id="docSenhaEmail" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocEmail">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            "Esqueci minha senha" na tela de login manda um link de redefinição pro e-mail cadastrado
                            do usuário (Administração &gt; Usuários). Assim como os portais, essa opção só aparece na
                            tela de login quando o SMTP está configurado -- sem isso, quem esquecer a senha precisa
                            pedir pra um administrador redefinir manualmente.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docTecnicoEmail">
                        <i class="bi bi-cpu me-2"></i> Como funciona por baixo dos panos
                    </button>
                </h2>
                <div id="docTecnicoEmail" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocEmail">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Envio via <strong>PHPMailer</strong> por SMTP autenticado -- não usa a função
                            <code>mail()</code> do PHP (que a maioria dos provedores de hospedagem bloqueia ou marca
                            como spam). É <strong>uma conta só pro sistema inteiro</strong> (não por usuário nem por
                            módulo) -- toda mensagem sai em nome do "Remetente" configurado acima, mesmo que o
                            assunto seja sobre o chamado de outra pessoa. A senha fica cifrada no banco (mesmo
                            esquema usado pela credencial do DVR/NVR e pela API Key do UniFi), nunca em texto puro.
                            Todo módulo listado acima checa <code>EmailService::configurado()</code> antes de tentar
                            enviar -- por isso nada quebra visivelmente quando o SMTP não está preenchido, cada
                            aviso simplesmente não sai.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.email-doc-card .card-body { padding: 1.25rem 1.5rem; }

.email-steps { display: flex; flex-direction: column; gap: 0; }
.email-step { display: flex; gap: .9rem; position: relative; padding-bottom: 1.25rem; }
.email-step::before {
    content: ''; position: absolute; left: 13px; top: 30px; bottom: 0;
    width: 2px; background: linear-gradient(to bottom, #cfe2ff, #e9ecef);
}
.email-step-last::before { display: none; }
.email-step-num {
    flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%;
    background: #0d6efd; color: #fff; font-weight: 600; font-size: .8rem;
    display: flex; align-items: center; justify-content: center; z-index: 1;
}
.email-step-title { font-weight: 600; font-size: .9rem; }
.email-step-text { color: #6c757d; font-size: .82rem; margin-top: 2px; }

.email-tabela-modulos th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; border-top: none; }
.email-tabela-modulos td { font-size: .85rem; }

.email-accordion .accordion-button {
    font-size: .88rem; font-weight: 600; background: #f8f9fa;
}
.email-accordion .accordion-button:not(.collapsed) {
    background: #eef4ff; color: #0d3b8c; box-shadow: none;
}
.email-accordion .accordion-button:focus { box-shadow: none; }
.email-accordion .accordion-item { border-color: #e9ecef; }
</style>

<script>
(function () {
    const botao = document.getElementById('botaoTestarEmail');
    if (!botao) return;

    botao.addEventListener('click', async function () {
        const paraEmail = prompt('Enviar e-mail de teste para qual endereço?', <?= json_encode($remetenteEmail) ?>);
        if (!paraEmail) return;

        const resultadoBox = document.getElementById('resultadoTeste');
        botao.disabled = true;
        resultadoBox.style.display = '';
        resultadoBox.className = 'alert alert-info small mt-3 mb-0';
        resultadoBox.textContent = 'Enviando...';

        try {
            const dados = new URLSearchParams();
            dados.set('para', paraEmail);

            const res = await fetch(<?= json_encode(url('/administracao/email/testar')) ?>, { method: 'POST', body: dados });
            const resposta = await res.json();

            resultadoBox.className = 'alert small mt-3 mb-0 ' + (resposta.success ? 'alert-success' : 'alert-danger');
            resultadoBox.textContent = resposta.message;
        } catch (e) {
            resultadoBox.className = 'alert alert-danger small mt-3 mb-0';
            resultadoBox.textContent = 'Erro de rede ao testar o e-mail.';
        } finally {
            botao.disabled = false;
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Sistema - E-mail';

require __DIR__ . '/../layouts/main.php';
