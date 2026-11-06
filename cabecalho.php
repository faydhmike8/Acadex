<?php
// cabecalho.php - topo comum a todas as páginas internas (menu, nome, sair).
// A página define $titulo_pagina e depois faz: require "cabecalho.php";
// Vem DEPOIS da lógica da página porque redirecionar() não funciona depois de haver HTML.
require_once "auth.php";
require_once "conexao.php";

$nao_lidas_menu = contar_nao_lidas($pdo);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina ?? "AcaDex") ?> - AcaDex</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
<header class="topo">
    <a class="marca" href="painel.php">AcaDex</a>
    <nav class="menu">
        <a href="painel.php">Painel</a>
        <a href="cursos.php">Cursos</a>
        <a href="mensagens.php">Mensagens<?php if ($nao_lidas_menu > 0): ?> <span class="badge"><?= $nao_lidas_menu ?></span><?php endif; ?></a>
        <?php if (papel() === "admin"): ?>
            <a href="admin.php">Administração</a>
        <?php endif; ?>
    </nav>
    <div class="utilizador">
        <?= e($_SESSION["utilizador"]) ?> <small>(<?= e(papel()) ?>)</small>
        · <a href="logout.php">Sair</a>
    </div>
</header>
<main class="conteudo">
<?php mostrar_flash(); ?>
