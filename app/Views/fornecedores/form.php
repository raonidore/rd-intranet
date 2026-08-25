<?php
ob_start();

use App\Components\Alert;

$editando = $fornecedor !== null;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <a href="<?= url('/fornecedores' . ($editando ? '/ver?id=' . (int)$fornecedor['id'] : '')) ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
    <h4 class="mb-0 mt-1"><?= $editando ? 'Editar fornecedor' : 'Novo fornecedor' ?></h4>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= url($editando ? '/fornecedores/editar' : '/fornecedores/novo') ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int)$fornecedor['id'] ?>">
            <?php endif; ?>

            <h6 class="text-muted text-uppercase small mb-3">Dados gerais e identificação</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-7">
                    <label class="form-label">Razão social *</label>
                    <input type="text" name="razao_social" id="campoRazaoSocial" class="form-control" required maxlength="200"
                           value="<?= htmlspecialchars($fornecedor['razao_social'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Nome fantasia *</label>
                    <input type="text" name="nome_fantasia" id="campoNomeFantasia" class="form-control" required maxlength="150"
                           value="<?= htmlspecialchars($fornecedor['nome_fantasia'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">CNPJ / CPF</label>
                    <div class="input-group">
                        <input type="text" name="cnpj_cpf" id="campoCnpjCpf" class="form-control" maxlength="18"
                               placeholder="00.000.000/0000-00"
                               value="<?= htmlspecialchars($fornecedor['cnpj_cpf'] ?? '') ?>">
                        <button type="button" class="btn btn-outline-secondary" id="botaoBuscarCnpj" title="Buscar dados na Receita pelo CNPJ">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <small class="text-muted" id="statusCnpj">Busca automática funciona só pra CNPJ (14 dígitos) -- não existe consulta pública de dados por CPF.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Inscrição estadual</label>
                    <div class="input-group">
                        <input type="text" name="inscricao_estadual" id="campoIe" class="form-control" maxlength="30"
                               value="<?= htmlspecialchars($fornecedor['inscricao_estadual'] ?? '') ?>"
                               <?= !empty($fornecedor['inscricao_estadual_isento']) ? 'disabled' : '' ?>>
                        <span class="input-group-text">
                            <input class="form-check-input mt-0" type="checkbox" name="inscricao_estadual_isento" value="1" id="checkIsento"
                                   title="Isento" <?= !empty($fornecedor['inscricao_estadual_isento']) ? 'checked' : '' ?>>
                        </span>
                    </div>
                    <small class="text-muted">Marque a caixinha se for isento.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Inscrição municipal</label>
                    <input type="text" name="inscricao_municipal" class="form-control" maxlength="30"
                           value="<?= htmlspecialchars($fornecedor['inscricao_municipal'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Porte da empresa</label>
                    <select name="porte" class="form-select">
                        <option value="">-- Não informado --</option>
                        <?php foreach (['ME' => 'Microempresa (ME)', 'EPP' => 'Empresa de Pequeno Porte (EPP)', 'Demais' => 'Demais'] as $valor => $rotulo): ?>
                            <option value="<?= $valor ?>" <?= ($fornecedor['porte'] ?? '') === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Tipo de serviço</label>
                    <div class="input-group">
                        <select name="tipo_servico_id" id="selectTipoServico" class="form-select">
                            <option value="">-- Selecione --</option>
                            <?php foreach ($tiposServico as $tipo): ?>
                                <option value="<?= (int)$tipo['id'] ?>" <?= (int)($fornecedor['tipo_servico_id'] ?? 0) === (int)$tipo['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalTiposServico">
                            <i class="bi bi-gear"></i> Gerenciar
                        </button>
                    </div>
                </div>
            </div>

            <h6 class="text-muted text-uppercase small mb-3">Endereço e localização</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" id="campoCep" class="form-control" maxlength="10"
                           value="<?= htmlspecialchars($fornecedor['cep'] ?? '') ?>">
                    <small class="text-muted" id="statusCep"></small>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Logradouro</label>
                    <input type="text" name="logradouro" id="campoLogradouro" class="form-control" maxlength="200"
                           value="<?= htmlspecialchars($fornecedor['logradouro'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Número</label>
                    <input type="text" name="numero" id="campoNumero" class="form-control" maxlength="20"
                           value="<?= htmlspecialchars($fornecedor['numero'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Complemento</label>
                    <input type="text" name="complemento" id="campoComplemento" class="form-control" maxlength="100"
                           value="<?= htmlspecialchars($fornecedor['complemento'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" id="campoBairro" class="form-control" maxlength="100"
                           value="<?= htmlspecialchars($fornecedor['bairro'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" id="campoCidade" class="form-control" maxlength="100"
                           value="<?= htmlspecialchars($fornecedor['cidade'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">UF</label>
                    <input type="text" name="uf" id="campoUf" class="form-control text-uppercase" maxlength="2"
                           value="<?= htmlspecialchars($fornecedor['uf'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">País</label>
                    <input type="text" name="pais" class="form-control" maxlength="60"
                           value="<?= htmlspecialchars($fornecedor['pais'] ?? 'Brasil') ?>">
                </div>
            </div>

            <h6 class="text-muted text-uppercase small mb-3">Contato</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Nome do contato principal</label>
                    <input type="text" name="contato_nome" class="form-control" maxlength="150"
                           value="<?= htmlspecialchars($fornecedor['contato_nome'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">E-mail comercial / financeiro</label>
                    <input type="email" name="email" id="campoEmail" class="form-control" maxlength="190"
                           value="<?= htmlspecialchars($fornecedor['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Telefone / celular</label>
                    <input type="text" name="telefone" id="campoTelefone" class="form-control" maxlength="30"
                           value="<?= htmlspecialchars($fornecedor['telefone'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Site</label>
                    <input type="text" name="site" class="form-control" maxlength="255"
                           placeholder="https://..."
                           value="<?= htmlspecialchars($fornecedor['site'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Como abrir chamado com esse fornecedor</label>
                    <textarea name="canal_abertura_chamado" class="form-control" rows="3"
                              placeholder="Ex: Central 0800 1234, portal do cliente em suporte.brisanet.com.br, ou e-mail suporte@brisanet.com.br"><?= htmlspecialchars($fornecedor['canal_abertura_chamado'] ?? '') ?></textarea>
                </div>

                <?php if ($editando): ?>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativoCheck"
                                   <?= !empty($fornecedor['ativo']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativoCheck">Fornecedor ativo</label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= $editando ? 'Salvar alterações' : 'Cadastrar fornecedor' ?>
                </button>
                <a href="<?= url('/fornecedores' . ($editando ? '/ver?id=' . (int)$fornecedor['id'] : '')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<!-- Modal gerenciar tipos de serviço -->
<div class="modal fade" id="modalTiposServico" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tipos de serviço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="novoTipoServicoNome" class="form-control" placeholder="Novo tipo de serviço" maxlength="100">
                    <button type="button" class="btn btn-primary" id="btnCriarTipoServico"><i class="bi bi-plus-lg"></i></button>
                </div>
                <ul class="list-group" id="listaTiposServico"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const checkIsento = document.getElementById('checkIsento');
    const campoIe = document.getElementById('campoIe');
    checkIsento.addEventListener('change', function () {
        campoIe.disabled = checkIsento.checked;
        if (checkIsento.checked) campoIe.value = '';
    });

    const campoCep = document.getElementById('campoCep');
    const statusCep = document.getElementById('statusCep');
    campoCep.addEventListener('blur', async function () {
        const cep = campoCep.value.replace(/\D/g, '');
        if (cep.length !== 8) return;

        statusCep.textContent = 'Buscando...';
        try {
            const res = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
            const dados = await res.json();
            if (dados.erro) {
                statusCep.textContent = 'CEP não encontrado.';
                return;
            }
            document.getElementById('campoLogradouro').value = dados.logradouro || '';
            document.getElementById('campoBairro').value = dados.bairro || '';
            document.getElementById('campoCidade').value = dados.localidade || '';
            document.getElementById('campoUf').value = dados.uf || '';
            statusCep.textContent = '';
        } catch (e) {
            statusCep.textContent = 'Não foi possível buscar o CEP agora -- preencha manualmente.';
        }
    });

    // --- CNPJ/CPF: mascara enquanto digita (00.000.000/0000-00 ou 000.000.000-00) ---
    const campoCnpjCpf = document.getElementById('campoCnpjCpf');
    const statusCnpj = document.getElementById('statusCnpj');
    const textoAjudaCnpj = statusCnpj.textContent;

    function mascararCnpjCpf(digitos) {
        if (digitos.length <= 11) {
            return digitos
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        return digitos
            .replace(/(\d{2})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1/$2')
            .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }

    campoCnpjCpf.addEventListener('input', function () {
        const digitos = campoCnpjCpf.value.replace(/\D/g, '').slice(0, 14);
        campoCnpjCpf.value = mascararCnpjCpf(digitos);
    });

    // --- Busca automática dos dados do CNPJ na Receita (via BrasilAPI, pública e gratuita) ---
    async function buscarCnpj() {
        const digitos = campoCnpjCpf.value.replace(/\D/g, '');
        if (digitos.length !== 14) {
            statusCnpj.textContent = 'Informe um CNPJ completo (14 dígitos) pra buscar -- CPF não tem consulta pública.';
            return;
        }

        statusCnpj.textContent = 'Buscando na Receita...';
        try {
            const res = await fetch('https://brasilapi.com.br/api/cnpj/v1/' + digitos);
            if (!res.ok) {
                statusCnpj.textContent = 'CNPJ não encontrado.';
                return;
            }
            const dados = await res.json();

            document.getElementById('campoRazaoSocial').value = dados.razao_social || '';
            document.getElementById('campoNomeFantasia').value = dados.nome_fantasia || dados.razao_social || '';
            document.getElementById('campoCep').value = dados.cep || '';
            document.getElementById('campoLogradouro').value = [dados.descricao_tipo_de_logradouro, dados.logradouro].filter(Boolean).join(' ');
            document.getElementById('campoNumero').value = dados.numero || '';
            document.getElementById('campoComplemento').value = dados.complemento || '';
            document.getElementById('campoBairro').value = dados.bairro || '';
            document.getElementById('campoCidade').value = dados.municipio || '';
            document.getElementById('campoUf').value = dados.uf || '';
            if (dados.email) document.getElementById('campoEmail').value = dados.email;
            if (dados.ddd_telefone_1) document.getElementById('campoTelefone').value = dados.ddd_telefone_1;

            statusCnpj.textContent = 'Dados preenchidos a partir da Receita Federal.';
        } catch (e) {
            statusCnpj.textContent = 'Não foi possível buscar o CNPJ agora -- preencha manualmente.';
        }
    }

    document.getElementById('botaoBuscarCnpj').addEventListener('click', buscarCnpj);
    campoCnpjCpf.addEventListener('blur', function () {
        if (campoCnpjCpf.value.replace(/\D/g, '').length === 14) {
            buscarCnpj();
        } else {
            statusCnpj.textContent = textoAjudaCnpj;
        }
    });

    const urlListar = <?= json_encode(url('/fornecedores/tipos-servico')) ?>;
    const urlCriar = <?= json_encode(url('/fornecedores/tipos-servico/criar')) ?>;
    const urlAtualizar = <?= json_encode(url('/fornecedores/tipos-servico/atualizar')) ?>;
    const urlExcluir = <?= json_encode(url('/fornecedores/tipos-servico/excluir')) ?>;
    const selectTipo = document.getElementById('selectTipoServico');
    const lista = document.getElementById('listaTiposServico');

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    async function carregarTipos() {
        const res = await fetch(urlListar);
        const dados = await res.json();
        if (!dados.success) return;

        lista.innerHTML = '';
        const valorAtual = selectTipo.value;
        selectTipo.innerHTML = '<option value="">-- Selecione --</option>';

        dados.tipos.forEach(t => {
            if (t.ativo == 1) {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.nome;
                if (String(t.id) === valorAtual) opt.selected = true;
                selectTipo.appendChild(opt);
            }

            const item = document.createElement('li');
            item.className = 'list-group-item d-flex justify-content-between align-items-center';
            item.innerHTML = '<span' + (t.ativo == 1 ? '' : ' class="text-muted"') + '>' + escapeHtml(t.nome) + (t.ativo == 1 ? '' : ' <span class="badge text-bg-secondary">inativo</span>') + '</span>';

            const acoes = document.createElement('div');
            acoes.className = 'd-flex gap-2';

            const btnToggle = document.createElement('button');
            btnToggle.type = 'button';
            btnToggle.className = 'btn btn-sm btn-outline-secondary';
            btnToggle.textContent = t.ativo == 1 ? 'Desativar' : 'Ativar';
            btnToggle.onclick = async () => {
                await fetch(urlAtualizar, { method: 'POST', body: new URLSearchParams({ id: t.id, nome: t.nome, ativo: t.ativo == 1 ? '0' : '1' }) });
                carregarTipos();
            };

            const btnExcluir = document.createElement('button');
            btnExcluir.type = 'button';
            btnExcluir.className = 'btn btn-sm btn-outline-danger';
            btnExcluir.innerHTML = '<i class="bi bi-trash"></i>';
            btnExcluir.onclick = async () => {
                const res2 = await fetch(urlExcluir, { method: 'POST', body: new URLSearchParams({ id: t.id }) });
                const dados2 = await res2.json();
                if (!dados2.success) alert(dados2.message);
                carregarTipos();
            };

            acoes.appendChild(btnToggle);
            acoes.appendChild(btnExcluir);
            item.appendChild(acoes);
            lista.appendChild(item);
        });
    }

    document.getElementById('btnCriarTipoServico').addEventListener('click', async function () {
        const campo = document.getElementById('novoTipoServicoNome');
        if (!campo.value.trim()) return;

        const res = await fetch(urlCriar, { method: 'POST', body: new URLSearchParams({ nome: campo.value.trim() }) });
        const dados = await res.json();
        if (!dados.success) {
            alert(dados.message);
            return;
        }
        campo.value = '';
        carregarTipos();
    });

    document.getElementById('modalTiposServico').addEventListener('show.bs.modal', carregarTipos);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = $editando ? 'Editar fornecedor' : 'Novo fornecedor';

require __DIR__ . '/../layouts/main.php';
