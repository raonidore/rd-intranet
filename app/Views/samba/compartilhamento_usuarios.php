<?php
ob_start();

$mapa = [];
foreach ($autorizados as $a) {
    $mapa[$a['usuario_id']] = $a;
}
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-people"></i> Usuários do Compartilhamento</h5>
        <small class="text-muted"><?= htmlspecialchars($compartilhamento['nome']) ?></small>
    </div>

    <div class="card-body">
        <form id="formUsuarios">
            <input type="hidden" name="id" value="<?= htmlspecialchars($compartilhamento['id']) ?>">

            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Login</th>
                        <th>Leitura</th>
                        <th>Escrita</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <?php $perm = $mapa[$u['id']] ?? null; ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nome']) ?></td>
                            <td><?= htmlspecialchars($u['login']) ?></td>

                            <td>
                                <input type="checkbox" name="usuarios[<?= $u['id'] ?>][leitura]" <?= $perm && (int)$perm['leitura'] ? 'checked' : '' ?>>
                            </td>

                            <td>
                                <input type="checkbox" name="usuarios[<?= $u['id'] ?>][escrita]" <?= $perm && (int)$perm['escrita'] ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <small class="text-muted">Escrita já inclui poder apagar (a lixeira do compartilhamento sempre registra a exclusão, então não há um nível separado só de "excluir").</small>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary" id="botaoSalvarUsuarios"><i class="bi bi-save"></i> Salvar usuários</button>
                <a href="<?= url('/samba/compartilhamentos') ?>" class="btn btn-secondary">Voltar</a>
            </div>
        </form>

        <div class="mt-4 d-none" id="painelAplicacaoAcl">
            <hr>
            <p class="mb-2 small text-muted" id="textoStatusAcl">
                Aplicando permissões no sistema de arquivos (isso roda em segundo plano -- pode
                fechar esta tela, a aplicação continua mesmo assim)...
            </p>
            <div class="progress" style="height:22px">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="barraProgressoAcl"
                     role="progressbar" style="width:0%">0%</div>
            </div>
            <div class="alert alert-danger mt-3 d-none" id="alertaErroAcl">
                <strong id="tituloErroAcl"></strong>
                <pre class="mb-0 mt-2 small" id="detalheErroAcl" style="white-space:pre-wrap; word-break:break-word; max-height:220px; overflow:auto;"></pre>
            </div>
            <div class="alert alert-success mt-3 d-none" id="alertaSucessoAcl">
                Permissões aplicadas com sucesso em todos os arquivos e pastas do compartilhamento.
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('formUsuarios');
    const botao = document.getElementById('botaoSalvarUsuarios');
    const painel = document.getElementById('painelAplicacaoAcl');
    const textoStatus = document.getElementById('textoStatusAcl');
    const barra = document.getElementById('barraProgressoAcl');
    const alertaErro = document.getElementById('alertaErroAcl');
    const tituloErro = document.getElementById('tituloErroAcl');
    const detalheErro = document.getElementById('detalheErroAcl');
    const alertaSucesso = document.getElementById('alertaSucessoAcl');

    const urlSalvar = <?= json_encode(url('/samba/compartilhamentos/usuarios')) ?>;
    const urlStatus = <?= json_encode(url('/samba/compartilhamentos/usuarios/status')) ?>;

    let intervaloPoll = null;

    function pararPoll() {
        if (intervaloPoll) {
            clearInterval(intervaloPoll);
            intervaloPoll = null;
        }
    }

    function atualizarBarra(pct) {
        const p = Math.max(0, Math.min(100, pct || 0));
        barra.style.width = p + '%';
        barra.textContent = p + '%';
    }

    function consultarStatus(nomeCompartilhamento) {
        intervaloPoll = setInterval(async function () {
            try {
                const res = await fetch(urlStatus + '?nome=' + encodeURIComponent(nomeCompartilhamento));
                const dados = await res.json();

                if (dados.status === 'rodando') {
                    atualizarBarra(dados.percentual);
                    textoStatus.textContent = 'Aplicando permissões no sistema de arquivos (' +
                        (dados.processados || 0) + ' de ' + (dados.total || 0) + ' itens)...';
                    return;
                }

                if (dados.status === 'concluido') {
                    pararPoll();
                    atualizarBarra(100);
                    barra.classList.remove('progress-bar-animated');
                    textoStatus.classList.add('d-none');
                    alertaSucesso.classList.remove('d-none');
                    return;
                }

                if (dados.status === 'erro') {
                    pararPoll();
                    barra.classList.remove('progress-bar-animated');
                    textoStatus.classList.add('d-none');
                    tituloErro.textContent = dados.mensagem || 'Erro ao aplicar permissões no sistema.';
                    detalheErro.textContent = dados.saida || '';
                    alertaErro.classList.remove('d-none');
                    return;
                }

                // "desconhecido" -- job ainda nao escreveu o primeiro status, continua tentando
            } catch (e) {
                // falha de rede pontual -- tenta de novo no proximo tick
            }
        }, 1500);
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        botao.disabled = true;
        alertaErro.classList.add('d-none');
        alertaSucesso.classList.add('d-none');
        textoStatus.classList.remove('d-none');
        barra.classList.add('progress-bar-animated');
        atualizarBarra(0);
        painel.classList.remove('d-none');

        try {
            const res = await fetch(urlSalvar, { method: 'POST', body: new FormData(form) });
            const dados = await res.json();

            if (!dados.success) {
                textoStatus.classList.add('d-none');
                tituloErro.textContent = dados.message || 'Erro ao salvar usuários.';
                detalheErro.textContent = '';
                alertaErro.classList.remove('d-none');
                return;
            }

            consultarStatus(dados.compartilhamento);
        } catch (e) {
            textoStatus.classList.add('d-none');
            tituloErro.textContent = 'Erro de rede ao salvar usuários.';
            detalheErro.textContent = '';
            alertaErro.classList.remove('d-none');
        } finally {
            botao.disabled = false;
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Usuários do Compartilhamento';
require __DIR__ . '/../layouts/main.php';
