<?php
require "auth.php";
require "conexao.php";
exigir_papel(["admin"]);   // só o admin entra aqui

$papeis = ["admin", "docente", "estudante"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();
    $acao = $_POST["acao"] ?? "";
    $uid  = (int) ($_POST["id"] ?? 0);

    if ($uid === eu()) {
        // Evita o admin ficar sem acesso por engano
        flash("Não pode alterar nem apagar a sua própria conta.", "erro");

    } elseif ($acao === "papel") {
        $novo = $_POST["papel"] ?? "";
        // Valida contra a lista: nunca confiar no valor vindo do formulário
        if (!in_array($novo, $papeis, true)) {
            flash("Papel inválido.", "erro");
        } else {
            $pdo->prepare("UPDATE utilizadores SET papel = ? WHERE id = ?")->execute([$novo, $uid]);
            flash("Papel atualizado. A alteração vale a partir do próximo login dessa pessoa.");
        }

    } elseif ($acao === "apagar") {
        // Os cursos, mensagens, entregas... dessa pessoa apagam-se em cascata (ON DELETE CASCADE)
        $pdo->prepare("DELETE FROM utilizadores WHERE id = ?")->execute([$uid]);
        flash("Utilizador apagado.");
    }
    redirecionar("admin.php");
}

$utilizadores = $pdo->query(
    "SELECT id, utilizador, email, papel, criado_em FROM utilizadores ORDER BY id"
)->fetchAll();

$titulo_pagina = "Administração";
require "cabecalho.php";
?>
<h1>Gestão de utilizadores</h1>
<p class="sub">Aqui o admin atribui os papéis: <strong>admin</strong>, <strong>docente</strong> ou <strong>estudante</strong>.</p>

<div class="tabela-scroll">
<table>
    <tr><th>ID</th><th>Utilizador</th><th>Email</th><th>Criado em</th><th>Papel</th><th></th></tr>
    <?php foreach ($utilizadores as $u): ?>
        <tr>
            <td><?= (int) $u["id"] ?></td>
            <td><?= e($u["utilizador"]) ?></td>
            <td><?= e($u["email"]) ?></td>
            <td><?= e(data_pt($u["criado_em"])) ?></td>
            <td>
                <?php if ((int) $u["id"] === eu()): ?>
                    <span class="etiqueta"><?= e($u["papel"]) ?></span> (você)
                <?php else: ?>
                    <form method="POST" action="admin.php" class="linha">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="papel">
                        <input type="hidden" name="id" value="<?= (int) $u["id"] ?>">
                        <select name="papel">
                            <?php foreach ($papeis as $p): ?>
                                <option value="<?= e($p) ?>" <?= $p === $u["papel"] ? "selected" : "" ?>><?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-peq" type="submit">Guardar</button>
                    </form>
                <?php endif; ?>
            </td>
            <td>
                <?php if ((int) $u["id"] !== eu()): ?>
                    <form method="POST" action="admin.php"
                          onsubmit="return confirm('Apagar este utilizador e tudo o que lhe pertence (cursos, mensagens, entregas)?');">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="apagar">
                        <input type="hidden" name="id" value="<?= (int) $u["id"] ?>">
                        <button class="btn btn-peq btn-perigo" type="submit">Apagar</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</div>

<?php require "rodape.php"; ?>
