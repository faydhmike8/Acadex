<?php
// funcoes.php - funções auxiliares partilhadas por todas as páginas.
// Não se abre diretamente: é incluído pelo auth.php.

/* ================= Básicos ================= */

// Escapa texto antes de o mostrar no HTML (protege contra XSS).
// Use SEMPRE e() ao imprimir algo que veio da BD ou do utilizador.
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, "UTF-8");
}

// Redireciona e termina o script. Tem de ser chamada ANTES de qualquer HTML.
function redirecionar(string $url): void
{
    header("Location: " . $url);
    exit;
}

// Formata uma data da BD como 26/10/2026 14:30
function data_pt(?string $data): string
{
    return $data ? date("d/m/Y H:i", strtotime($data)) : "—";
}

// Id e papel do utilizador com sessão iniciada (guardados no login)
function eu(): int
{
    return (int) ($_SESSION["id"] ?? 0);
}

function papel(): string
{
    return $_SESSION["papel"] ?? "";
}

/* ================= Mensagens "flash" =================
   Guardam um aviso na sessão para o mostrar UMA vez, na página seguinte.
   Padrão: processar POST -> flash() -> redirecionar() -> a nova página mostra o aviso. */

function flash(string $texto, string $tipo = "ok"): void
{
    $_SESSION["flash"] = ["texto" => $texto, "tipo" => $tipo];
}

function mostrar_flash(): void
{
    if (isset($_SESSION["flash"])) {
        $f = $_SESSION["flash"];
        unset($_SESSION["flash"]);
        echo '<div class="flash flash-' . e($f["tipo"]) . '">' . e($f["texto"]) . '</div>';
    }
}

/* ================= CSRF =================
   Impede que outro site faça o navegador do utilizador submeter formulários nossos.
   Cada formulário leva um campo escondido com o token; o POST confirma-o. */

function csrf_token(): string
{
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_validar(): void
{
    $enviado = $_POST["csrf"] ?? "";
    if (!hash_equals($_SESSION["csrf"] ?? "", $enviado)) {
        http_response_code(400);
        die("Pedido inválido (token de segurança). Volte atrás e tente de novo.");
    }
}

/* ================= Cursos e permissões ================= */

// Admin gere todos os cursos; docente só os seus.
function pode_gerir_curso(array $curso): bool
{
    return papel() === "admin"
        || (papel() === "docente" && (int) $curso["docente_id"] === eu());
}

// Carrega um curso e confirma que o utilizador tem acesso:
//  - admin: sempre | docente: se for o dono | estudante: se estiver inscrito.
// Se não existir ou não tiver acesso, termina com 404/403.
function carregar_curso(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        "SELECT c.*, u.utilizador AS docente_nome
         FROM cursos c
         JOIN utilizadores u ON u.id = c.docente_id
         WHERE c.id = ?"
    );
    $stmt->execute([$id]);
    $curso = $stmt->fetch();

    if (!$curso) {
        http_response_code(404);
        die("Curso não encontrado. <a href='cursos.php'>Voltar aos cursos</a>");
    }

    if (!pode_gerir_curso($curso)) {
        $inscrito = false;
        if (papel() === "estudante") {
            $s = $pdo->prepare("SELECT 1 FROM inscricoes WHERE estudante_id = ? AND curso_id = ?");
            $s->execute([eu(), $id]);
            $inscrito = (bool) $s->fetchColumn();
        }
        if (!$inscrito) {
            http_response_code(403);
            die("Sem acesso a este curso. <a href='cursos.php'>Voltar aos cursos</a>");
        }
    }
    return $curso;
}

// Desenha as abas Aulas | Matéria teórica | Tarefas de um curso
function abas_curso(int $curso_id, string $ativa): void
{
    $abas = [
        "aulas"     => ["curso.php?id=", "Aulas"],
        "materiais" => ["materiais.php?curso=", "Matéria teórica"],
        "tarefas"   => ["tarefas.php?curso=", "Tarefas"],
    ];
    echo '<nav class="abas">';
    foreach ($abas as $chave => [$url, $nome]) {
        $classe = ($chave === $ativa) ? "ativa" : "";
        echo '<a class="' . $classe . '" href="' . $url . $curso_id . '">' . e($nome) . '</a>';
    }
    echo '</nav>';
}

/* ================= Mensagens ================= */

function contar_nao_lidas(PDO $pdo): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM mensagens WHERE destinatario_id = ? AND lida = 0");
    $st->execute([eu()]);
    return (int) $st->fetchColumn();
}

/* ================= Ficheiros ================= */

// Guarda um ficheiro enviado por formulário em uploads/<subpasta>/.
// Devolve [nome_original, nome_guardado] se correr bem, ou uma STRING com o erro.
function guardar_upload(array $f, string $subpasta)
{
    $permitidas = ["pdf", "doc", "docx", "ppt", "pptx", "xls", "xlsx", "txt", "zip", "jpg", "jpeg", "png"];
    $max_bytes  = 10 * 1024 * 1024; // 10 MB

    $erro = $f["error"] ?? UPLOAD_ERR_NO_FILE;
    if ($erro === UPLOAD_ERR_NO_FILE) {
        return "Escolha um ficheiro.";
    }
    if ($erro !== UPLOAD_ERR_OK) {
        return "Erro no envio do ficheiro (código " . $erro . "). Verifique o tamanho máximo do PHP.";
    }
    if ($f["size"] > $max_bytes) {
        return "O ficheiro excede 10 MB.";
    }

    $original = basename($f["name"]);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $permitidas, true)) {
        return "Tipo de ficheiro não permitido (." . $ext . ").";
    }

    $pasta = __DIR__ . "/uploads/" . $subpasta;
    if (!is_dir($pasta)) {
        mkdir($pasta, 0775, true);
    }

    // Nome aleatório: evita nomes repetidos e que alguém adivinhe o endereço do ficheiro
    $guardado = bin2hex(random_bytes(16)) . "." . $ext;
    if (!move_uploaded_file($f["tmp_name"], $pasta . "/" . $guardado)) {
        return "Não foi possível guardar o ficheiro.";
    }
    return [$original, $guardado];
}

// Apaga do disco uma lista de ficheiros guardados
function apagar_ficheiros(array $nomes, string $subpasta): void
{
    foreach ($nomes as $nome) {
        $caminho = __DIR__ . "/uploads/" . $subpasta . "/" . basename((string) $nome);
        if (is_file($caminho)) {
            unlink($caminho);
        }
    }
}

// Converte um link do YouTube no endereço "embed" (para mostrar o vídeo na página).
// Devolve null se não for YouTube.
function video_embed(string $url): ?string
{
    if (preg_match('~(?:youtube\.com/watch\?(?:.*&)?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return "https://www.youtube.com/embed/" . $m[1];
    }
    return null;
}
