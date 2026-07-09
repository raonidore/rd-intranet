<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<style>
#sqlRapidoBody .CodeMirror { height: auto; min-height: 90px; border: 1px solid #ced4da; border-radius: 6px; font-size: 13px; }
</style>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#sqlRapidoBody">
        <span><i class="bi bi-code-slash"></i> SQL rápido</span>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse" id="sqlRapidoBody">
        <div class="card-body">
            <textarea id="sqlRapidoCampo" class="form-control font-monospace" rows="3"></textarea>
            <div class="mt-2 d-flex justify-content-between align-items-center">
                <small class="text-muted">Executa contra <code><?= htmlspecialchars($banco) ?></code>, na conexão <?= htmlspecialchars($conexao['nome']) ?>.</small>
                <button type="button" class="btn btn-sm btn-primary" id="sqlRapidoExecutar">
                    <i class="bi bi-play-fill"></i> Executar
                </button>
            </div>
            <div id="sqlRapidoResultado" class="mt-3"></div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
<script>
(function () {
    const CONEXAO_ID = <?= (int)$conexao['id'] ?>;
    const BANCO = <?= json_encode($banco) ?>;
    const SQL_URL = <?= json_encode(url('/banco-dados/console/sql-rapido')) ?>;

    const editor = CodeMirror.fromTextArea(document.getElementById('sqlRapidoCampo'), {
        mode: 'text/x-mysql',
        lineNumbers: true,
        matchBrackets: true,
    });

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function montarResultado(dados) {
        if (!dados.success) {
            return '<div class="alert alert-danger mb-0">' + escapeHtml(dados.mensagem) + '</div>';
        }
        if (dados.tipo === 'afetadas') {
            return '<div class="alert alert-success mb-0">' + dados.linhas_afetadas + ' linha(s) afetada(s).</div>';
        }

        let html = '<div class="mb-2 text-muted small">' + dados.total + ' linha(s) retornada(s).</div>';
        html += '<div style="overflow-x:auto"><table class="table table-sm table-hover align-middle"><thead><tr>';
        dados.colunas.forEach(function (col) { html += '<th>' + escapeHtml(col) + '</th>'; });
        html += '</tr></thead><tbody>';
        dados.linhas.forEach(function (linha) {
            html += '<tr>';
            dados.colunas.forEach(function (col) {
                const valor = linha[col];
                html += '<td>' + (valor === null ? '<span class="text-muted">NULL</span>' : escapeHtml(String(valor))) + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    document.getElementById('sqlRapidoExecutar').addEventListener('click', async function () {
        editor.save();
        const sql = document.getElementById('sqlRapidoCampo').value.trim();
        const resultadoEl = document.getElementById('sqlRapidoResultado');

        if (sql === '') {
            editor.focus();
            return;
        }

        const regexSeguro = /^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i;
        if (!regexSeguro.test(sql) && !confirm('Este comando pode alterar ou apagar dados e não pode ser desfeito. Continuar?')) {
            return;
        }

        const botao = this;
        botao.disabled = true;
        resultadoEl.innerHTML = '<div class="text-muted small"><i class="bi bi-hourglass-split"></i> Executando...</div>';

        try {
            const fd = new FormData();
            fd.append('conexao', CONEXAO_ID);
            fd.append('banco', BANCO);
            fd.append('sql', sql);
            const res = await fetch(SQL_URL, { method: 'POST', body: fd });
            const dados = await res.json();
            resultadoEl.innerHTML = montarResultado(dados);
        } catch (e) {
            resultadoEl.innerHTML = '<div class="alert alert-danger mb-0">Erro ao comunicar com o servidor.</div>';
        } finally {
            botao.disabled = false;
        }
    });
})();
</script>
