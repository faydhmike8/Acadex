<?php
require "auth.php";
require "conexao.php";

// Quem já está logado vai direto para o painel
if (isset($_SESSION["id"])) {
    header("Location: painel.php");
    exit;
}

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $utilizador = trim($_POST["user"] ?? "");
    $senha      = $_POST["password"] ?? "";

    $stmt = $pdo->prepare("SELECT id, utilizador, senha_hash, papel FROM utilizadores WHERE utilizador = ?");
    $stmt->execute([$utilizador]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    // password_verify compara a senha digitada com o hash guardado
    if ($u && password_verify($senha, $u["senha_hash"])) {
        session_regenerate_id(true);
        $_SESSION["id"]         = $u["id"];
        $_SESSION["utilizador"] = $u["utilizador"];
        $_SESSION["papel"]      = $u["papel"];
        header("Location: painel.php");
        exit;
    }

    // Mesma mensagem para utilizador inexistente e senha errada
    $msg = "Utilizador ou senha incorretos.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AcaDex</title>
    <link rel="stylesheet" type="text/css" href="EstiloLogin.css" />
</head>

<body>
    <form action="login.php" method="POST" class="form-example">
        Utilizador:
        <input type="text" name="user" size="20" required />
        Senha:
        <input type="password" name="password" size="20" required />
        <input type="submit" value="Login"/>
        <input type="reset" value="Limpar"/>

        <?php if ($msg !== ""): ?>
            <p class="mensagem"><?= htmlspecialchars($msg) ?></p>
        <?php endif; ?>

        <a href="registo.php" class="link-registo">Criar conta</a>
    </form>
</body>
</html>
