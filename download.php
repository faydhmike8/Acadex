<?php
// download.php?tipo=material&id=5  ou  download.php?tipo=entrega&id=9
// Os ficheiros ficam em uploads/ (bloqueada ao navegador). Só este script os entrega,
// depois de confirmar o login e o acesso ao curso.
require "auth.php";
require "conexao.php";
exigir_login();

$tipo = $_GET["tipo"] ?? "";
$id   = (int) ($_GET["id"] ?? 0);

if ($tipo === "material") {
    $st = $pdo->prepare("SELECT curso_id, ficheiro_original, ficheiro_guardado FROM materiais WHERE id = ?");
    $st->execute([$id]);
    $f = $st->fetch();
    if (!$f) {
        http_response_code(404);
        die("Ficheiro não encontrado.");
    }
    carregar_curso($pdo, (int) $f["curso_id"]);   // termina com 403 se não tiver acesso
    $subpasta = "materiais";

} elseif ($tipo === "entrega") {
    $st = $pdo->prepare(
        "SELECT e.estudante_id, e.ficheiro_original, e.ficheiro_guardado, t.curso_id
         FROM entregas e JOIN tarefas t ON t.id = e.tarefa_id
         WHERE e.id = ?"
    );
    $st->execute([$id]);
    $f = $st->fetch();
    if (!$f) {
        http_response_code(404);
        die("Ficheiro não encontrado.");
    }
    $curso = carregar_curso($pdo, (int) $f["curso_id"]);
    // Uma entrega só pode ser vista por quem gere o curso ou pelo próprio estudante
    if (!pode_gerir_curso($curso) && (int) $f["estudante_id"] !== eu()) {
        http_response_code(403);
        die("Sem permissão.");
    }
    $subpasta = "entregas";

} else {
    http_response_code(400);
    die("Pedido inválido.");
}

$caminho = __DIR__ . "/uploads/" . $subpasta . "/" . basename($f["ficheiro_guardado"]);
if (!is_file($caminho)) {
    http_response_code(404);
    die("O ficheiro já não existe no servidor.");
}

header("Content-Type: application/octet-stream");
header("Content-Length: " . filesize($caminho));
// Descarrega com o nome original que o utilizador enviou
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($f["ficheiro_original"]));
readfile($caminho);
exit;
