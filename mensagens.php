<?php
// mensagens.php - caixa de entrada, enviadas e nova mensagem
require "auth.php";
require "conexao.php";
exigir_login();

$eu = eu();

// ---------- Enviar mensagem ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();

    $para    = (int) ($_POST["para"] ?? 0);
    $assunto = trim($_POST["assunto"] ?? "");
    $texto   = trim($_POST["texto"] ?? "");
    if ($assunto === "") {
        $assunto = "(sem assunto)";
    }

    $st = $pdo->prepare("SELECT 1 FROM utilizadores WHERE id = ?");
    $st->execute([$para]);

    if ($para === $eu || !$st->fetchColumn()) {
        flash("Escolha um destinatário válido (não pode enviar para si próprio).", "erro");
        redirecionar("mensagens.php?aba=nova");
    } elseif ($texto === "" || mb_strlen($texto) > 5000 || mb_strlen($assunto) > 150) {
        flash("Escreva a mensagem (até 5000 caracteres) e um assunto até 150 caracteres.", "erro");
        redirecionar("mensagens.php?aba=nova&para=" . $para);
    }

    $pdo->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, assunto, texto) VALUES (?, ?, ?, ?)")
        ->execute([$eu, $para, $assunto, $texto]);
    flash("Mensagem enviada.");
    redirecionar("mensagens.php?aba=enviadas");
}

$aba = $_GET["aba"] ?? "entrada";
if (!in_array($aba, ["entrada", "enviadas", "nova"], true)) {
    $aba = "entrada";
}

// ---------- Abrir uma mensagem ----------
$ver  = (int) ($_GET["ver"] ?? 0);
$msg  = null;
if ($ver > 0) {
    $st = $pdo->prepare(
        "SELECT m.*, r.utilizador AS remetente, d.utilizador AS destinatario
         FROM mensagens m
         JOIN utilizadores r ON r.id = m.remetente_id
         JOIN utilizadores d ON d.id = m.destinatario_id
         WHERE m.id = ? AND (m.remetente_id = ? OR m.destinatario_id = ?)"
    );
    $st->execute([$ver, $eu, $eu]);
    $msg = $st->fetch();

    // Só quem recebeu marca como lida (e antes do cabeçalho calcular o contador)
    if ($msg && (int) $msg["destinatario_id"] === $eu && !$msg["lida"]) {
        $pdo->prepare("UPDATE mensagens SET lida = 1 WHERE id = ?")->execute([$ver]);
    }
}

// ---------- Listas ----------
$lista = [];
if (!$msg && $aba === "entrada") {
    $st = $pdo->prepare(
        "SELECT m.*, u.utilizador AS outro FROM mensagens m
         JOIN utilizadores u ON u.id = m.remetente_id
         WHERE m.destinatario_id = ? ORDER BY m.enviada_em DESC, m.id DESC LIMIT 100"
    );
    $st->execute([$eu]);
    $lista = $st->fetchAll();
} elseif (!$msg && $aba === "enviadas") {
    $st = $pdo->prepare(
        "SELECT m.*, u.utilizador AS outro FROM mensagens m
         JOIN utilizadores u ON u.id = m.destinatario_id
         WHERE m.remetente_id = ? ORDER BY m.enviada_em DESC, m.id DESC LIMIT 100"
    );
    $st->execute([$eu]);
    $lista = $st->fetchAll();
}

// ---------- Formulário: lista de possíveis destinatários ----------
$pessoas = [];
$para_pre = (int) ($_GET["para"] ?? 0);
$assunto_pre = mb_substr((string) ($_GET["assunto"] ?? ""), 0, 150);
if (!$msg && $aba === "nova") {
    $st = $pdo->prepare("SELECT id, utilizador, papel FROM utilizadores WHERE id <> ? ORDER BY utilizador");
    $st->execute([$eu]);
    $pessoas = $st->fetchAll();
}

$titulo_pagina = "Mensagens";
require "cabecalho.php";
?>
<h1>Mensagens</h1>

<nav class="abas">
    <a class="<?= (!$msg && $aba === "entrada") ? "ativa" : "" ?>" href="mensagens.php?aba=entrada">Caixa de entrada</a>
    <a class="<?= (!$msg && $aba === "enviadas") ? "ativa" : "" ?>" href="mensagens.php?aba=enviadas">Enviadas</a>
    <a class="<?= (!$msg && $aba === "nova") ? "ativa" : "" ?>" href="mensagens.php?aba=nova">Nova mensagem</a>
</nav>

<?php if ($ver > 0 && !$msg): ?>
    <p class="vazio">Mensagem não encontrada.</p>

<?php elseif ($msg): ?>
    <?php
        $outro_id = ((int) $msg["remetente_id"] === $eu) ? $msg["destinatario_id"] : $msg["remetente_id"];
        $assunto_resp = preg_match('/^Re: /i', $msg["assunto"]) ? $msg["assunto"] : "Re: " . $msg["assunto"];
    ?>
    <div class="cartao">
        <h3><?= e($msg["assunto"]) ?></h3>
        <p class="sub">
            De <strong><?= e($msg["remetente"]) ?></strong> para <strong><?= e($msg["destinatario"]) ?></strong>
            · <?= e(data_pt($msg["enviada_em"])) ?>
        </p>
        <p><?= nl2br(e($msg["texto"])) ?></p>
        <div class="acoes">
            <a class="btn btn-peq" href="mensagens.php?aba=nova&amp;para=<?= (int) $outro_id ?>&amp;assunto=<?= urlencode($assunto_resp) ?>">Responder</a>
            <a class="btn btn-peq btn-sec" href="mensagens.php">Voltar</a>
        </div>
    </div>

<?php elseif ($aba === "nova"): ?>
    <div class="cartao">
        <form method="POST" action="mensagens.php">
            <?= csrf_campo() ?>
            <label>Para</label>
            <select name="para" required>
                <option value="">— escolha —</option>
                <?php foreach ($pessoas as $p): ?>
                    <option value="<?= (int) $p["id"] ?>" <?= (int) $p["id"] === $para_pre ? "selected" : "" ?>>
                        <?= e($p["utilizador"]) ?> (<?= e($p["papel"]) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Assunto</label>
            <input type="text" name="assunto" maxlength="150" value="<?= e($assunto_pre) ?>">
            <label>Mensagem</label>
            <textarea name="texto" maxlength="5000" required></textarea>
            <button class="btn" type="submit">Enviar</button>
        </form>
    </div>

<?php else: ?>
    <?php if (!$lista): ?>
        <p class="vazio"><?= $aba === "entrada" ? "Não recebeu nenhuma mensagem." : "Ainda não enviou mensagens." ?></p>
    <?php else: ?>
        <div class="tabela-scroll">
        <table>
            <tr><th><?= $aba === "entrada" ? "De" : "Para" ?></th><th>Assunto</th><th>Data</th></tr>
            <?php foreach ($lista as $m): ?>
                <tr class="<?= ($aba === "entrada" && !$m["lida"]) ? "nao-lida" : "" ?>">
                    <td><?= e($m["outro"]) ?></td>
                    <td><a href="mensagens.php?ver=<?= (int) $m["id"] ?>"><?= e($m["assunto"]) ?></a></td>
                    <td><?= e(data_pt($m["enviada_em"])) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require "rodape.php"; ?>
