/*
 * Componente de UX pra campos de senha: botao "olhinho" pra mostrar/esconder,
 * checklist ao vivo de complexidade (conforme a politica configurada em
 * Administracao > Usuarios > Politica de senha) e aviso ao vivo se
 * "confirmar senha" bate com a nova senha -- sem precisar apagar tudo pra
 * saber que errou. Usado em /perfil, /administracao/usuarios (novo/senha) e
 * /login/redefinir.
 *
 * A checagem de "dados obvios" aqui e' so uma aproximacao pra UX (nome/login
 * digitados no form, sequencias e termos comuns bem conhecidos) -- quem
 * decide de verdade e' sempre o servidor (PasswordPolicyService::validar),
 * essa lista existe so pra nao deixar o usuario "achar" que a senha esta ok
 * e levar um erro surpresa ao salvar.
 */
(function () {
    var SEQUENCIAS = ['0123', '1234', '2345', '3456', '4567', '5678', '6789', '9876', '8765', '7654', '6543', '5432', '4321', '3210'];
    var TERMOS_COMUNS = ['123456', 'senha', 'password', 'qwerty', '111111', '000000', 'abcdef'];

    function contemDadosObvios(senha, dados) {
        var baixa = senha.toLowerCase();
        var campos = ['nome', 'login', 'email'];

        for (var i = 0; i < campos.length; i++) {
            var valor = ((dados || {})[campos[i]] || '').toLowerCase().trim();
            if (!valor) continue;

            var partes = campos[i] === 'nome' ? valor.split(/\s+/) : [valor.split('@')[0]];
            for (var j = 0; j < partes.length; j++) {
                if (partes[j].length >= 3 && baixa.indexOf(partes[j]) !== -1) return true;
            }
        }

        for (var s = 0; s < SEQUENCIAS.length; s++) if (senha.indexOf(SEQUENCIAS[s]) !== -1) return true;
        for (var t = 0; t < TERMOS_COMUNS.length; t++) if (baixa.indexOf(TERMOS_COMUNS[t]) !== -1) return true;
        if (/(.)\1{3,}/.test(senha)) return true;

        return false;
    }

    function criarToggleOlho(input) {
        if (input.closest('.senha-ui-grupo')) return;

        var grupo = document.createElement('div');
        grupo.className = 'input-group senha-ui-grupo';
        input.parentNode.insertBefore(grupo, input);
        grupo.appendChild(input);

        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'btn btn-outline-secondary';
        botao.tabIndex = -1;
        botao.innerHTML = '<i class="bi bi-eye"></i>';
        grupo.appendChild(botao);

        botao.addEventListener('click', function () {
            var mostrando = input.type === 'text';
            input.type = mostrando ? 'password' : 'text';
            botao.innerHTML = mostrando ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
        });
    }

    function montarRequisitos(politica) {
        var itens = [];
        itens.push({ chave: 'comprimento', label: 'Pelo menos ' + politica.comprimentoMinimo + ' caracteres', testar: function (s) { return s.length >= politica.comprimentoMinimo; } });
        if (politica.maiusculaMinuscula) itens.push({ chave: 'maiuscula', label: 'Letras maiúsculas e minúsculas', testar: function (s) { return /[A-Z]/.test(s) && /[a-z]/.test(s); } });
        if (politica.numero) itens.push({ chave: 'numero', label: 'Pelo menos um número', testar: function (s) { return /[0-9]/.test(s); } });
        if (politica.especial) itens.push({ chave: 'especial', label: 'Um caractere especial (!, @, #, $...)', testar: function (s) { return /[^A-Za-z0-9]/.test(s); } });
        if (politica.dadosObvios) itens.push({ chave: 'obvios', label: 'Sem nome, login ou sequências óbvias', testar: function (s, dados) { return s.length > 0 && !contemDadosObvios(s, dados); } });
        return itens;
    }

    function aplicar(opcoes) {
        var campoSenha = document.getElementById(opcoes.campoSenha);
        if (!campoSenha) return;

        criarToggleOlho(campoSenha);

        var campoConfirmacao = opcoes.campoConfirmacao ? document.getElementById(opcoes.campoConfirmacao) : null;
        if (campoConfirmacao) criarToggleOlho(campoConfirmacao);

        var campoAtual = opcoes.campoAtual ? document.getElementById(opcoes.campoAtual) : null;
        if (campoAtual) criarToggleOlho(campoAtual);

        var politica = opcoes.politica || {};
        var dadosObviosFn = typeof opcoes.dadosObvios === 'function' ? opcoes.dadosObvios : function () { return opcoes.dadosObvios || {}; };
        var requisitos = montarRequisitos(politica);

        var containerChecklist = opcoes.checklistContainer ? document.getElementById(opcoes.checklistContainer) : null;
        if (containerChecklist && requisitos.length) {
            requisitos.forEach(function (r) {
                var linha = document.createElement('div');
                linha.className = 'senha-ui-requisito text-muted';
                linha.dataset.chave = r.chave;
                linha.innerHTML = '<i class="bi bi-circle"></i> <span>' + r.label + '</span>';
                containerChecklist.appendChild(linha);
            });
        }

        var containerMatch = opcoes.matchContainer ? document.getElementById(opcoes.matchContainer) : null;

        function atualizarChecklist() {
            if (!containerChecklist) return;
            var valor = campoSenha.value;
            requisitos.forEach(function (r) {
                var linha = containerChecklist.querySelector('[data-chave="' + r.chave + '"]');
                if (!linha) return;
                var ok = r.testar(valor, dadosObviosFn());
                linha.classList.toggle('text-success', ok);
                linha.classList.toggle('text-muted', !ok);
                linha.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
            });
        }

        function atualizarMatch() {
            if (!containerMatch || !campoConfirmacao) return;

            if (campoConfirmacao.value === '') {
                containerMatch.innerHTML = '';
                return;
            }

            if (campoConfirmacao.value === campoSenha.value) {
                containerMatch.innerHTML = '<i class="bi bi-check-circle-fill"></i> As senhas conferem.';
                containerMatch.className = 'small mt-1 text-success';
            } else {
                containerMatch.innerHTML = '<i class="bi bi-x-circle-fill"></i> As senhas não conferem.';
                containerMatch.className = 'small mt-1 text-danger';
            }
        }

        campoSenha.addEventListener('input', function () {
            atualizarChecklist();
            atualizarMatch();
        });
        if (campoConfirmacao) campoConfirmacao.addEventListener('input', atualizarMatch);

        atualizarChecklist();
    }

    window.RdSenhaUI = { aplicar: aplicar };
})();
