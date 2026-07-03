<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Novo Usuário Samba</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <h2>RD Intranet</h2>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="samba.php">📁 Módulo Samba</a>
    <a href="samba_usuarios.php">👥 Usuários Samba</a>
    <a href="logout.php">🚪 Sair</a>
</div>

<div class="content">
    <h1>Novo Usuário Samba</h1>

    <div class="card">
        <form method="post" action="samba_usuario_salvar.php" onsubmit="mostrarLoading()">
            <label>Nome completo</label><br>
            <input type="text" name="nome" required><br><br>

            <label>Login</label><br>
            <input type="text" name="login" required placeholder="ex: luisaoliveira"><br><br>

            <label>Departamento</label><br>
            <select name="grupo" required>
                <option value="ti">TI</option>
                <option value="financeiro">Financeiro</option>
                <option value="cobranca">Cobrança</option>
            </select><br><br>

            <label>Acesso SSH</label><br>
            <select name="ssh" required>
                <option value="nao">Não</option>
                <option value="sim">Sim</option>
            </select><br><br>

            <label>Senha inicial</label><br>
            <input type="password" name="senha" required><br><br>

            <button class="btn btn-primary" type="submit">Criar usuário</button>
            <a class="btn btn-secondary" href="samba_usuarios.php">Voltar</a>
        </form>
    </div>
</div>

<div class="loading-overlay" id="loading">
    <div class="loading-box">
        <div class="spinner"></div>
        <h3>Criando usuário...</h3>
        <p>Aguarde enquanto a RD Intranet cria o usuário no Linux, Samba e banco de dados.</p>
    </div>
</div>

<script>
function mostrarLoading() {
    document.getElementById('loading').style.display = 'flex';
}
</script>

</body>
</html>
