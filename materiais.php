<?php
// materiais.php?curso=3 - matéria teórica de um curso (upload, pesquisa, download)
require "auth.php";
require "conexao.php";
exigir_login();

$curso_id = (int) ($_GET["curso"] ?? $_POST["curso"] ?? 0);
$curso    = carregar_curso($pdo, $curso_id);
$gestor   = pode_gerir_curso($curso);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();
    if (!$gestor) {
        http_response_code(403);
        die("Sem permissão.");
    }
    $acao = $_POST["acao"] ?? "";

    if ($acao === "enviar") {
        $titulo    = trim($_POST["titulo"] ?? "");
        $modulo_id = (int) ($_POST["modulo_id"] ?? 0);

        if ($titulo === "" || mb_strlen($titulo) > 150) {
            flash("Indique o título do material (até 150 caracteres).", "erro");
        } else {
            // Se escolheu um módulo, tem de ser deste curso; 0 = sem módulo ("Geral")
            if ($modulo_id > 0) {
                $st = $pdo->prepare("SELECT 1 FROM modulos WHERE id = ? AND curso_id = ?");
                $st->execute([$modulo_id, $curso_id]);
                if (!$st->fetchColumn()) {
                    $modulo_id = 0;
                }
            }
            $r = guardar_upload($_FILES["ficheiro"] ?? [], "materiais");
            if (is_string($r)) {
                flash($r, "erro");          // devolveu uma mensagem de erro
            } else {
                [$original, $guardado] = $r;
                $pdo->prepare(
                    "INSERT INTO materiais (curso_id, modulo_id, titulo, ficheiro_original, ficheiro_guardado)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([$curso_id, $modulo_id > 0 ? $modulo_id : null, $titulo, $original, $guardado]);
                flash("Material enviado.");
            }
        }

    } elseif ($acao === "remover") {
        $st = $pdo->prepare("SELECT id, ficheiro_guardado FROM materiais WHERE id = ? AND curso_id = ?");
        $st->execute([(int) ($_POST["id"] ?? 0), $curso_id]);
        $m = $st->fetch();
        if ($m) {
            apagar_ficheiros([$m["ficheiro_guardado"]], "materiais");
            $pdo->prepare("DELETE FROM materiais WHERE id = ?")->execute([$m["id"]]);
            flash("Material removido.");
        }
    }
    redirecionar("materiais.php?curso=" . $curso_id);
}

// Módulos (para o filtro e para o formulário)
$st = $pdo->prepare("SELECT id, titulo FROM modulos WHERE curso_id = ? ORDER BY ordem, id");
$st->execute([$curso_id]);
$modulos = $st->fetchAll();

// Pesquisa e filtro: a consulta vai ganhando condições conforme o que foi escolhido
$q             = trim($_GET["q"] ?? "");
$filtro_modulo = (int) ($_GET["modulo"] ?? 0);

$sql = "SELECT mt.*, m.titulo AS modulo_titulo
        FROM materiais mt
        LEFT JOIN modulos m ON m.id = mt.modulo_id
        WHERE mt.curso_id = ?";
$params = [$curso_id];
if ($q !== "") {
    $sql .= " AND mt.titulo LIKE ?";
    $params[] = "%" . $q . "%";
}
if ($filtro_modulo > 0) {
    $sql .= " AND mt.modulo_id = ?";
    $params[] = $filtro_modulo;
}
$sql .= " ORDER BY m.ordem, m.id, mt.criado_em DESC";

$st = $pdo->prepare($sql);
$st->execute($params);

// Agrupa por módulo: $grupos["Nome do módulo"][] = material
$grupos = [];
foreach ($st->fetchAll() as $mt) {
    $grupos[$mt["modulo_titulo"] ?? "Geral"][] = $mt;
}

$titulo_pagina = "Matéria teórica - " . $curso["titulo"];
require "cabecalho.php";
?>
<h1><?= e($curso["titulo"]) ?></h1>
<p class="sub">Docente: <?= e($curso["docente_nome"]) ?></p>
<?php abas_curso($curso_id, "materiais"); ?>

<form method="GET" action="materiais.php" class="linha" style="margin-bottom:16px">
    <input type="hidden" name="curso" value="<?= (int) $curso_id ?>">
    <input type="text" name="q" placeholder="Pesquisar pelo título" value="<?= e($q) ?>">
    <select name="modulo">
        <option value="0">Todos os módulos</option>
        <?php foreach ($modulos as $m): ?>
            <option value="<?= (int) $m["id"] ?>" <?= $filtro_modulo === (int) $m["id"] ? "selected" : "" ?>><?= e($m["titulo"]) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filtrar</button>
</form>

<?php if (!$grupos): ?>
    <p class="vazio">Não há materiais<?= ($q !== "" || $filtro_modulo > 0) ? " com este filtro" : " neste curso" ?>.</p>
<?php endif; ?>

<?php foreach ($grupos as $nome_grupo => $lista): ?>
    <h2><?= e($nome_grupo) ?></h2>
    <div class="tabela-scroll">
    <table>
        <tr><th>Título</th><th>Ficheiro</th><th>Enviado em</th><?php if ($gestor): ?><th></th><?php endif; ?></tr>
        <?php foreach ($lista as $mt): ?>
            <tr>
                <td><?= e($mt["titulo"]) ?></td>
                <td><a href="download.php?tipo=material&amp;id=<?= (int) $mt["id"] ?>"><?= e($mt["ficheiro_original"]) ?></a></td>
                <td><?= e(data_pt($mt["criado_em"])) ?></td>
                <?php if ($gestor): ?>
                    <td>
                        <form method="POST" action="materiais.php"
                              onsubmit="return confirm('Remover este material?');">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="remover">
                            <input type="hidden" name="curso" value="<?= (int) $curso_id ?>">
                            <input type="hidden" name="id" value="<?= (int) $mt["id"] ?>">
                            <button class="btn btn-peq btn-perigo" type="submit">Remover</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
<?php endforeach; ?>

<?php if ($gestor): ?>
    <h2>Enviar novo material</h2>
    <div class="cartao">
        <!-- enctype="multipart/form-data" é OBRIGATÓRIO para enviar ficheiros -->
        <form method="POST" action="materiais.php" enctype="multipart/form-data">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="enviar">
            <input type="hidden" name="curso" value="<?= (int) $curso_id ?>">

            <label>Título</label>
            <input type="text" name="titulo" maxlength="150" required>

            <label>Módulo</label>
            <select name="modulo_id">
                <option value="0">Geral (sem módulo)</option>
                <?php foreach ($modulos as $m): ?>
                    <option value="<?= (int) $m["id"] ?>"><?= e($m["titulo"]) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Ficheiro (PDF, Word, PowerPoint, Excel, TXT, ZIP, imagem - até 10 MB)</label>
            <input type="file" name="ficheiro" required>

            <button class="btn" type="submit">Enviar</button>
        </form>
    </div>
<?php endif; ?>

<?php require "rodape.php"; ?>
