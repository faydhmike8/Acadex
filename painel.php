<?php
require "auth.php";
require "conexao.php";
exigir_login();

$papel     = papel();
$id        = eu();
$nao_lidas = contar_nao_lidas($pdo);

// Cada papel vê dados diferentes: consultamos só o que esse papel precisa.
if ($papel === "admin") {
    $totais = $pdo->query(
        "SELECT (SELECT COUNT(*) FROM utilizadores) AS utilizadores,
                (SELECT COUNT(*) FROM utilizadores WHERE papel = 'docente') AS docentes,
                (SELECT COUNT(*) FROM utilizadores WHERE papel = 'estudante') AS estudantes,
                (SELECT COUNT(*) FROM cursos) AS cursos"
    )->fetch();

    $recentes = $pdo->query(
        "SELECT utilizador, email, papel, criado_em FROM utilizadores ORDER BY id DESC LIMIT 5"
    )->fetchAll();

} elseif ($papel === "docente") {
    // Os meus cursos + número de estudantes inscritos em cada um
    $stmt = $pdo->prepare(
        "SELECT c.id, c.titulo,
                (SELECT COUNT(*) FROM inscricoes i WHERE i.curso_id = c.id) AS alunos
         FROM cursos c
         WHERE c.docente_id = ?
         ORDER BY c.titulo"
    );
    $stmt->execute([$id]);
    $meus_cursos = $stmt->fetchAll();

    // Entregas dos meus cursos que ainda não têm nota (LEFT JOIN + "n.id IS NULL")
    $stmt = $pdo->prepare(
        "SELECT t.id AS tarefa_id, t.titulo AS tarefa, c.titulo AS curso,
                u.utilizador, e.entregue_em
         FROM entregas e
         JOIN tarefas t ON t.id = e.tarefa_id
         JOIN cursos c ON c.id = t.curso_id
         JOIN utilizadores u ON u.id = e.estudante_id
         LEFT JOIN notas n ON n.entrega_id = e.id
         WHERE c.docente_id = ? AND n.id IS NULL
         ORDER BY e.entregue_em ASC
         LIMIT 8"
    );
    $stmt->execute([$id]);
    $por_avaliar = $stmt->fetchAll();

} else { // estudante
    $stmt = $pdo->prepare(
        "SELECT c.id, c.titulo, u.utilizador AS docente
         FROM inscricoes i
         JOIN cursos c ON c.id = i.curso_id
         JOIN utilizadores u ON u.id = c.docente_id
         WHERE i.estudante_id = ?
         ORDER BY c.titulo"
    );
    $stmt->execute([$id]);
    $meus_cursos = $stmt->fetchAll();

    // Tarefas dos cursos em que estou inscrito, ainda sem entrega e dentro do prazo
    $stmt = $pdo->prepare(
        "SELECT t.id, t.titulo, t.prazo, c.titulo AS curso
         FROM tarefas t
         JOIN inscricoes i ON i.curso_id = t.curso_id AND i.estudante_id = ?
         JOIN cursos c ON c.id = t.curso_id
         LEFT JOIN entregas e ON e.tarefa_id = t.id AND e.estudante_id = i.estudante_id
         WHERE e.id IS NULL AND (t.prazo IS NULL OR t.prazo >= NOW())
         ORDER BY (t.prazo IS NULL), t.prazo ASC
         LIMIT 8"
    );
    $stmt->execute([$id]);
    $pendentes = $stmt->fetchAll();
}

$titulo_pagina = "Painel";
require "cabecalho.php";
?>
<h1>Olá, <?= e($_SESSION["utilizador"]) ?></h1>
<p class="sub">Perfil: <?= e($papel) ?></p>

<?php if ($papel === "admin"): ?>

    <div class="grelha">
        <div class="cartao"><div class="numero"><?= (int) $totais["utilizadores"] ?></div>Utilizadores</div>
        <div class="cartao"><div class="numero"><?= (int) $totais["docentes"] ?></div>Docentes</div>
        <div class="cartao"><div class="numero"><?= (int) $totais["estudantes"] ?></div>Estudantes</div>
        <div class="cartao"><div class="numero"><?= (int) $totais["cursos"] ?></div>Cursos</div>
        <div class="cartao"><a href="mensagens.php"><div class="numero"><?= $nao_lidas ?></div></a>Mensagens por ler</div>
    </div>

    <h2>Últimos registos</h2>
    <div class="tabela-scroll">
    <table>
        <tr><th>Utilizador</th><th>Email</th><th>Papel</th><th>Criado em</th></tr>
        <?php foreach ($recentes as $r): ?>
            <tr>
                <td><?= e($r["utilizador"]) ?></td>
                <td><?= e($r["email"]) ?></td>
                <td><span class="etiqueta"><?= e($r["papel"]) ?></span></td>
                <td><?= e(data_pt($r["criado_em"])) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <a class="btn" href="admin.php">Gerir utilizadores e papéis</a>

<?php elseif ($papel === "docente"): ?>

    <div class="grelha">
        <div class="cartao"><div class="numero"><?= count($meus_cursos) ?></div>Os meus cursos</div>
        <div class="cartao"><div class="numero"><?= count($por_avaliar) ?></div>Entregas por avaliar</div>
        <div class="cartao"><a href="mensagens.php"><div class="numero"><?= $nao_lidas ?></div></a>Mensagens por ler</div>
    </div>

    <h2>Os meus cursos</h2>
    <?php if (!$meus_cursos): ?>
        <p class="vazio">Ainda não criou nenhum curso.</p>
    <?php else: ?>
        <div class="grelha">
        <?php foreach ($meus_cursos as $c): ?>
            <div class="cartao">
                <h3><?= e($c["titulo"]) ?></h3>
                <p class="sub"><?= (int) $c["alunos"] ?> estudante(s) inscrito(s)</p>
                <a class="btn btn-peq" href="curso.php?id=<?= (int) $c["id"] ?>">Abrir</a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="acoes"><a class="btn" href="curso_form.php">Novo curso</a></div>

    <h2>Entregas por avaliar</h2>
    <?php if (!$por_avaliar): ?>
        <p class="vazio">Nada por avaliar.</p>
    <?php else: ?>
        <div class="tabela-scroll">
        <table>
            <tr><th>Estudante</th><th>Tarefa</th><th>Curso</th><th>Entregue em</th></tr>
            <?php foreach ($por_avaliar as $p): ?>
                <tr>
                    <td><?= e($p["utilizador"]) ?></td>
                    <td><a href="tarefa.php?id=<?= (int) $p["tarefa_id"] ?>"><?= e($p["tarefa"]) ?></a></td>
                    <td><?= e($p["curso"]) ?></td>
                    <td><?= e(data_pt($p["entregue_em"])) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>

<?php else: ?>

    <div class="grelha">
        <div class="cartao"><div class="numero"><?= count($meus_cursos) ?></div>Cursos em que estou</div>
        <div class="cartao"><div class="numero"><?= count($pendentes) ?></div>Tarefas pendentes</div>
        <div class="cartao"><a href="mensagens.php"><div class="numero"><?= $nao_lidas ?></div></a>Mensagens por ler</div>
    </div>

    <h2>Os meus cursos</h2>
    <?php if (!$meus_cursos): ?>
        <p class="vazio">Ainda não está inscrito em nenhum curso. <a href="cursos.php">Ver cursos disponíveis</a></p>
    <?php else: ?>
        <div class="grelha">
        <?php foreach ($meus_cursos as $c): ?>
            <div class="cartao">
                <h3><?= e($c["titulo"]) ?></h3>
                <p class="sub">Docente: <?= e($c["docente"]) ?></p>
                <a class="btn btn-peq" href="curso.php?id=<?= (int) $c["id"] ?>">Abrir</a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2>Tarefas pendentes</h2>
    <?php if (!$pendentes): ?>
        <p class="vazio">Não tem tarefas pendentes.</p>
    <?php else: ?>
        <div class="tabela-scroll">
        <table>
            <tr><th>Tarefa</th><th>Curso</th><th>Prazo</th></tr>
            <?php foreach ($pendentes as $t): ?>
                <tr>
                    <td><a href="tarefa.php?id=<?= (int) $t["id"] ?>"><?= e($t["titulo"]) ?></a></td>
                    <td><?= e($t["curso"]) ?></td>
                    <td><?= $t["prazo"] ? e(data_pt($t["prazo"])) : "Sem prazo" ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require "rodape.php"; ?>
