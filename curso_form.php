<?php
// curso_form.php - cria um curso novo, ou edita um existente (curso_form.php?id=3)
require "auth.php";
require "conexao.php";
exigir_papel(["admin", "docente"]);

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);

// Valores por defeito do formulário (curso novo)
$curso = ["id" => 0, "titulo" => "", "descricao" => "", "docente_id" => eu()];

if ($id > 0) {
    // Edição: carrega o curso e confirma que posso geri-lo
    $curso = carregar_curso($pdo, $id);
    if (!pode_gerir_curso($curso)) {
        http_response_code(403);
        die("Sem permissão.");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();
    $titulo    = trim($_POST["titulo"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $docente   = eu();

    // O admin pode escolher o docente; o docente fica sempre com os seus cursos
    if (papel() === "admin") {
        $docente = (int) ($_POST["docente_id"] ?? eu());
        $st = $pdo->prepare("SELECT 1 FROM utilizadores WHERE id = ? AND papel IN ('docente', 'admin')");
        $st->execute([$docente]);
        if (!$st->fetchColumn()) {
            $docente = eu();
        }
    }

    if ($titulo === "" || mb_strlen($titulo) > 120) {
        flash("Indique o título do curso (até 120 caracteres).", "erro");
        $curso["titulo"]    = $titulo;
        $curso["descricao"] = $descricao;
    } else {
        if ($id > 0) {
            $pdo->prepare("UPDATE cursos SET titulo = ?, descricao = ?, docente_id = ? WHERE id = ?")
                ->execute([$titulo, $descricao, $docente, $id]);
            flash("Curso atualizado.");
            redirecionar("curso.php?id=" . $id);
        } else {
            $pdo->prepare("INSERT INTO cursos (titulo, descricao, docente_id) VALUES (?, ?, ?)")
                ->execute([$titulo, $descricao, $docente]);
            flash("Curso criado. Adicione agora módulos e aulas.");
            redirecionar("curso.php?id=" . $pdo->lastInsertId());
        }
    }
}

$docentes = [];
if (papel() === "admin") {
    $docentes = $pdo->query("SELECT id, utilizador FROM utilizadores WHERE papel IN ('docente', 'admin') ORDER BY utilizador")->fetchAll();
}

$titulo_pagina = $id > 0 ? "Editar curso" : "Novo curso";
require "cabecalho.php";
?>
<h1><?= e($titulo_pagina) ?></h1>

<div class="cartao">
    <form method="POST" action="curso_form.php">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" maxlength="120" required value="<?= e($curso["titulo"]) ?>">

        <label for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao"><?= e($curso["descricao"]) ?></textarea>

        <?php if (papel() === "admin"): ?>
            <label for="docente_id">Docente responsável</label>
            <select id="docente_id" name="docente_id">
                <?php foreach ($docentes as $d): ?>
                    <option value="<?= (int) $d["id"] ?>" <?= (int) $d["id"] === (int) $curso["docente_id"] ? "selected" : "" ?>>
                        <?= e($d["utilizador"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <div class="acoes">
            <button class="btn" type="submit">Guardar</button>
            <a class="btn btn-sec" href="<?= $id > 0 ? "curso.php?id=" . (int) $id : "cursos.php" ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php require "rodape.php"; ?>
