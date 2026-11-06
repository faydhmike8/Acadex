<?php
// aula.php?id=5 - mostra uma aula (vídeo + descrição)
require "auth.php";
require "conexao.php";
exigir_login();

$id = (int) ($_GET["id"] ?? 0);

$st = $pdo->prepare(
    "SELECT a.*, m.titulo AS modulo_titulo, m.curso_id
     FROM aulas a JOIN modulos m ON m.id = a.modulo_id
     WHERE a.id = ?"
);
$st->execute([$id]);
$aula = $st->fetch();

if (!$aula) {
    http_response_code(404);
    die("Aula não encontrada. <a href='cursos.php'>Voltar aos cursos</a>");
}

// Verifica se tenho acesso ao curso desta aula
$curso = carregar_curso($pdo, (int) $aula["curso_id"]);

// Lista de todas as aulas do curso, por ordem, para os botões Anterior / Seguinte
$st = $pdo->prepare(
    "SELECT a.id, a.titulo FROM aulas a JOIN modulos m ON m.id = a.modulo_id
     WHERE m.curso_id = ? ORDER BY m.ordem, m.id, a.ordem, a.id"
);
$st->execute([$curso["id"]]);
$todas = $st->fetchAll();

$anterior = $seguinte = null;
foreach ($todas as $i => $outra) {
    if ((int) $outra["id"] === $id) {
        $anterior = $todas[$i - 1] ?? null;
        $seguinte = $todas[$i + 1] ?? null;
    }
}

$embed = $aula["video_url"] ? video_embed($aula["video_url"]) : null;

$titulo_pagina = $aula["titulo"];
require "cabecalho.php";
?>
<p class="sub"><a href="curso.php?id=<?= (int) $curso["id"] ?>">← <?= e($curso["titulo"]) ?></a> · <?= e($aula["modulo_titulo"]) ?></p>
<h1><?= e($aula["titulo"]) ?></h1>

<?php if ($embed): ?>
    <div class="video">
        <iframe src="<?= e($embed) ?>" allowfullscreen loading="lazy"></iframe>
    </div>
<?php elseif ($aula["video_url"]): ?>
    <p><a class="btn" href="<?= e($aula["video_url"]) ?>" target="_blank" rel="noopener">Abrir vídeo / ligação da aula</a></p>
<?php endif; ?>

<?php if (trim((string) $aula["descricao"]) !== ""): ?>
    <div class="cartao"><?= nl2br(e($aula["descricao"])) ?></div>
<?php endif; ?>

<div class="navegacao-aulas">
    <?php if ($anterior): ?>
        <a class="btn btn-sec" href="aula.php?id=<?= (int) $anterior["id"] ?>">← <?= e($anterior["titulo"]) ?></a>
    <?php else: ?><span></span><?php endif; ?>
    <?php if ($seguinte): ?>
        <a class="btn" href="aula.php?id=<?= (int) $seguinte["id"] ?>"><?= e($seguinte["titulo"]) ?> →</a>
    <?php endif; ?>
</div>

<?php require "rodape.php"; ?>
