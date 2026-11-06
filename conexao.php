<?php
// conexao.php - liga o PHP ao MySQL. Todas as paginas que usam a BD fazem: require "conexao.php";

// Hora de Mocambique (UTC+2). Sem isto o PHP pode usar outro fuso e os prazos ficam errados.
date_default_timezone_set("Africa/Maputo");

// XAMPP: utilizador root, sem senha
$host = "localhost";
$bd   = "acadex";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$bd;charset=utf8mb4", $user, $pass);
    // Lanca erro quando uma consulta falha (em vez de falhar em silencio)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // fetch() devolve sempre arrays associativos: $linha["titulo"]
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // O MySQL tambem usa a hora de Mocambique (NOW(), TIMESTAMP)
    $pdo->exec("SET time_zone = '+02:00'");
} catch (PDOException $e) {
    die("Erro ao ligar à base de dados.");
}
