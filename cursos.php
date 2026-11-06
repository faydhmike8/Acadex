<?php
require "auth.php";
require "conexao.php";
exigir_login();

$papel = papel();

// Inscrever / cancelar inscrição (só estudantes)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();
    exigir_papel(["estudante"]);

    $curso_id = (int) ($_POST["curso_id"] ?? 0);
    $acao     = $_POST["acao"] ?? "";

    if ($acao === "inscrever") {
        $st = $pdo->prepare("SELECT 1 FROM cursos WHERE id = ?");
        $st->execute([$curso_id]);
        if ($st->fetchColumn()) {
            // INSERT IGNORE: se já estiver inscrito não dá erro (chave primária composta)
            $pdo->prepare("INSERT IGNORE INTO inscricoes (estudante_id, curso_id) VALUES (?, ?)")
                ->execute([eu(), $curso_id]);
            flash("Inscrição feita com sucesso.");
        } else {
            flash("Curso não encontrado.", "erro");
        }
    } elseif ($acao === "cancelar") {
        $pdo->prepare("DELETE FROM inscricoes WHERE estudante_id = ? AND curso_id = ?")
            ->execute([eu(), $curso_id]);
        flash("Inscrição cancelada.");
    }
    redirecionar("cursos.php");
}

// A lista muda conforme o papel
$base = "SELECT c.*, u.utilizador AS docente_nome,
                (SELECT COUNT(*) FROM inscricoes WHERE curso_id = c.id) AS alunos, ";

if ($papel === "estudante") {
    // Todos os cursos, marcando em quais já estou inscrito
    $sql = $base . "EXISTS(SELECT 1 FROM inscricoes WHERE curso_id = c.id AND estudante_id = ?) AS inscrito
            FROM cursos c JOIN utilizadores u ON u.id = c.docente_id
            ORDER BY inscrito DESC, c.titulo";
    $params = [eu()];
} elseif ($papel === "docente") {
    // Só os meus cursos
    $sql = $base . "0 AS inscrito
            FROM cursos c JOIN utilizadores u ON u.id = c.docente_id
            WHERE c.docente_id = ?
            ORDER BY c.titulo";
    $params = [eu()];
} else {
    // Admin: todos
    $sql = $base . "0 AS inscrito
            FROM cursos c JOIN utilizadores u ON u.id = c.docente_id
            ORDER BY c.titulo";
    $params = [];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cursos = $stmt->fetchAll();

$titulo_pagina = "Cursos";
require "cabecalho.php";
?>
<h1>Cursos</h1>
<p class="sub">
    <?php if ($papel === "estudante"): ?>
        Inscreva-se num curso para aceder às aulas, matéria e tarefas.
    <?php elseif ($papel === "docente"): ?>
        Os cursos que leciona.
    <?php else: ?>
        Todos os cursos da plataforma.
    <?php endif; ?>
</p>

<?php if ($papel !== "estudante"): ?>
    <div class="acoes" style="margin-bottom:16px"><a class="btn" href="curso_form.php">Novo curso</a></div>
<?php endif; ?>

<?php if (!$cursos): ?>
    <p class="vazio">Ainda não há cursos.</p>
<?php else: ?>
    <div class="grelha">
    <?php foreach ($cursos as $c): ?>
        <div class="cartao">
            <h3><?= e($c["titulo"]) ?></h3>
            <p class="sub">Docente: <?= e($c["docente_nome"]) ?> · <?= (int) $c["alunos"] ?> estudante(s)</p>
            <p><?= e(mb_strimwidth((string) $c["descricao"], 0, 140, "…", "UTF-8")) ?></p>
            <div class="acoes">
                <?php if ($papel === "estudante" && !$c["inscrito"]): ?>
                    <form method="POST" action="cursos.php">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="inscrever">
                        <input type="hidden" name="curso_id" value="<?= (int) $c["id"] ?>">
                        <button class="btn btn-peq" type="submit">Inscrever-me</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-peq" href="curso.php?id=<?= (int) $c["id"] ?>">Abrir</a>
                    <?php if ($papel === "estudante"): ?>
                        <form method="POST" action="cursos.php"
                              onsubmit="return confirm('Cancelar a inscrição neste curso?');">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="cancelar">
                            <input type="hidden" name="curso_id" value="<?= (int) $c["id"] ?>">
                            <button class="btn btn-peq btn-sec" type="submit">Cancelar inscrição</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require "rodape.php"; ?>
