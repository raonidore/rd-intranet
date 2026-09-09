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
                <strong>(Opcional) Ative a coleta periódica</strong> -- no Dashboard de Ativos, aba
                "Integrações de rede", botão "Ativar coleta" no card DVR/NVR Intelbras.
            </li>
        </ol>
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
            confidencialidade assinado com o CNPJ da empresa pra liberar), mas o protocolo em si é o
            mesmo já documentado publicamente fora dos canais da Intelbras -- por isso construímos essa
            integração testando os comandos direto contra o equipamento, em vez de aguardar
            documentação oficial.
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
