<?php
require "conexao.php";

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $utilizador = trim($_POST["user"] ?? "");
    $email      = trim($_POST["email"] ?? "");
    $senha      = $_POST["password"] ?? "";

    if ($utilizador === "" || $email === "" || strlen($senha) < 6) {
        $msg = "Preencha todos os campos. A senha deve ter pelo menos 6 caracteres.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Email inválido.";
    } else {
        // Verifica se já existe
        $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE utilizador = ? OR email = ?");
        $stmt->execute([$utilizador, $email]);

        if ($stmt->fetch()) {
            $msg = "Utilizador ou email já registado.";
        } else {
            // Nunca guardar a senha em texto simples: guardar o hash
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO utilizadores (utilizador, email, senha_hash) VALUES (?, ?, ?)"
            );
            $stmt->execute([$utilizador, $email, $hash]);
            $msg = "Conta criada! <a href='login.php'>Ir para o login</a>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AcaDex - Registo</title>
    <link rel="stylesheet" type="text/css" href="EstiloLogin.css" />
</head>
<body>
    <form action="registo.php" method="POST" class="form-example">
        Utilizador:
        <input type="text" name="user" size="20" required />
        Email:
        <input type="text" name="email" size="20" required />
        Senha:
        <input type="password" name="password" size="20" required />
        <input type="submit" value="Registar"/>
        <input type="reset" value="Limpar"/>
        <?php if ($msg !== ""): ?>
            <p class="mensagem"><?= $msg ?></p>
        <?php endif; ?>

        <a href="login.php" class="link-registo">Já tenho conta</a>
    </form>
</body>
</html>
