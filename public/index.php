<?php
session_start();

if (isset($_SESSION['usuario'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = $_GET['erro'] ?? '';
?>
<h2>RD Intranet</h2>

<?php if ($erro): ?>
<p style="color:red;">Usuário ou senha inválidos.</p>
<?php endif; ?>

<form method="post" action="login.php">
    <label>Usuário</label><br>
    <input type="text" name="login" required><br><br>

    <label>Senha</label><br>
    <input type="password" name="senha" required><br><br>

    <button type="submit">Entrar</button>
</form>
