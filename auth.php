<?php
// auth.php - sessão e controlo de acesso (login e papéis).
// Inclua no TOPO de cada página protegida:  require "auth.php";

// Só inicia a sessão se ainda não estiver iniciada (evita o aviso "session already active")
if (session_status() === PHP_SESSION_NONE) {
    // httponly: o JavaScript não consegue ler o cookie da sessão
    session_set_cookie_params(["httponly" => true, "samesite" => "Lax"]);
    session_start();
}

require_once __DIR__ . "/funcoes.php";

// Exige que exista alguém com login feito; senão manda para o login.
function exigir_login(): void
{
    if (!isset($_SESSION["id"])) {
        redirecionar("login.php");
    }
}

// Exige login E que o papel esteja na lista. Ex.: exigir_papel(["admin", "docente"]);
function exigir_papel(array $permitidos): void
{
    exigir_login();
    if (!in_array(papel(), $permitidos, true)) {
        http_response_code(403);
        die("Sem permissão para aceder a esta página. <a href='painel.php'>Voltar ao painel</a>");
    }
}
