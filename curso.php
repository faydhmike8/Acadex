<?php
// curso.php?id=3 - página de um curso: módulos e aulas
require "auth.php";
require "conexao.php";
exigir_login();

$curso_id = (int) ($_GET["id"] ?? $_POST["curso_id"] ?? 0);
$curso    = carregar_curso($pdo, $curso_id);   // já verifica se tenho acesso
$gestor   = pode_gerir_curso($curso);          // true = admin ou docente dono

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();
    if (!$gestor) {
        http_response_code(403);
        die("Sem permissão.");
    }
    $acao = $_POST["acao"] ?? "";

    if ($acao === "novo_modulo") {
        $titulo = trim($_POST["titulo"] ?? "");
        if ($titulo === "" || mb_strlen($titulo) > 120) {
            flash("Indique o título do módulo (até 120 caracteres).", "erro");
        } else {
            // A nova posição é a maior ordem existente + 1
            $st = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 FROM modulos WHERE curso_id = ?");
            $st->execute([$curso_id]);
            $ordem = (int) $st->fetchColumn();
            $pdo->prepare("INSERT INTO modulos (curso_id, titulo, ordem) VALUES (?, ?, ?)")
                ->execute([$curso_id, $titulo, $ordem]);
            flash("Módulo criado.");
        }

    } elseif ($acao === "nova_aula") {
        $modulo_id = (int) ($_POST["modulo_id"] ?? 0);
        $titulo    = trim($_POST["titulo"] ?? "");
        $video     = trim($_POST["video_url"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");

        // O módulo tem de pertencer a ESTE curso (não confiar no id vindo do formulário)
        $st = $pdo->prepare("SELECT 1 FROM modulos WHERE id = ? AND curso_id = ?");
        $st->execute([$modulo_id, $curso_id]);

        if (!$st->fetchColumn()) {
            flash("Módulo inválido.", "erro");
        } elseif ($titulo === "" || mb_strlen($titulo) > 150) {
            flash("Indique o título da aula (até 150 caracteres).", "erro");
        } elseif ($video !== "" && !preg_match('~^https?://~i', $video)) {
            // Só aceitamos http(s): evita links perigosos como javascript:
            flash("O link do vídeo tem de começar por http:// ou https://", "erro");
        } else {
            $st = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 FROM aulas WHERE modulo_id = ?");
            $st->execute([$modulo_id]);
            $ordem = (int) $st->fetchColumn();
            $pdo->prepare("INSERT INTO aulas (modulo_id, titulo, video_url, descricao, ordem) VALUES (?, ?, ?, ?, ?)")
                ->execute([$modulo_id, $titulo, $video !== "" ? $video : null, $descricao, $ordem]);
            flash("Aula adicionada.");
        }

    } elseif ($acao === "remover_aula") {
        // DELETE com JOIN: só apaga se a aula for deste curso
        $pdo->prepare(
            "DELETE a FROM aulas a JOIN modulos m ON m.id = a.modulo_id
             WHERE a.id = ? AND m.curso_id = ?"
        )->execute([(int) ($_POST["id"] ?? 0), $curso_id]);
        flash("Aula removida.");

    } elseif ($acao === "remover_modulo") {
        // As aulas do módulo apagam-se em cascata
        $pdo->prepare("DELETE FROM modulos WHERE id = ? AND curso_id = ?")
            ->execute([(int) ($_POST["id"] ?? 0), $curso_id]);
        flash("Módulo removido.");

    } elseif ($acao === "apagar_curso") {
        // Antes de apagar o curso, apaga do disco os ficheiros que ele tinha
        $st = $pdo->prepare("SELECT ficheiro_guardado FROM materiais WHERE curso_id = ?");
        $st->execute([$curso_id]);
        apagar_ficheiros($st->fetchAll(PDO::FETCH_COLUMN), "materiais");

        $st = $pdo->prepare(
            "SELECT e.ficheiro_guardado FROM entregas e JOIN tarefas t ON t.id = e.tarefa_id WHERE t.curso_id = ?"
        );
        $st->execute([$curso_id]);
        apagar_ficheiros($st->fetchAll(PDO::FETCH_COLUMN), "entregas");

        $pdo->prepare("DELETE FROM cursos WHERE id = ?")->execute([$curso_id]);
        flash("Curso apagado.");
        redirecionar("cursos.php");
    }
    redirecionar("curso.php?id=" . $curso_id);
}

// Módulos do curso e, por baixo, as aulas de cada um
$st = $pdo->prepare("SELECT * FROM modulos WHERE curso_id = ? ORDER BY ordem, id");
$st->execute([$curso_id]);
$modulos = $st->fetchAll();

$st = $pdo->prepare(
    "SELECT a.* FROM aulas a JOIN modulos m ON m.id = a.modulo_id
     WHERE m.curso_id = ? ORDER BY a.ordem, a.id"
);
$st->execute([$curso_id]);
$aulas_por_modulo = [];
foreach ($st->fetchAll() as $a) {
    $aulas_por_modulo[$a["modulo_id"]][] = $a;
}

$titulo_pagina = $curso["titulo"];
require "cabecalho.php";
?>
<h1><?= e($curso["titulo"]) ?></h1>
<p class="sub">Docente: <?= e($curso["docente_nome"]) ?></p>
<?php if (trim((string) $curso["descricao"]) !== ""): ?>
    <p><?= nl2br(e($curso["descricao"])) ?></p>
<?php endif; ?>

<?php if ($gestor): ?>
    <div class="acoes">
        <a class="btn btn-sec btn-peq" href="curso_form.php?id=<?= (int) $curso_id ?>">Editar curso</a>
        <form method="POST" action="curso.php"
              onsubmit="return confirm('Apagar o curso e TUDO o que tem (aulas, materiais, tarefas, entregas)?');">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="apagar_curso">
            <input type="hidden" name="curso_id" value="<?= (int) $curso_id ?>">
            <button class="btn btn-peq btn-perigo" type="submit">Apagar curso</button>
        </form>
    </div>
<?php endif; ?>

<?php abas_curso($curso_id, "aulas"); ?>

<?php if (!$modulos): ?>
    <p class="vazio">Este curso ainda não tem módulos.</p>
<?php endif; ?>

<?php foreach ($modulos as $m): ?>
    <div class="cartao">
        <div class="cabeca-modulo">
            <h3><?= e($m["titulo"]) ?></h3>
            <?php if ($gestor): ?>
                <form method="POST" action="curso.php"
                      onsubmit="return confirm('Remover o módulo e as suas aulas?');">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="remover_modulo">
                    <input type="hidden" name="curso_id" value="<?= (int) $curso_id ?>">
                    <input type="hidden" name="id" value="<?= (int) $m["id"] ?>">
                    <button class="btn btn-peq btn-perigo" type="submit">Remover módulo</button>
                </form>
            <?php endif; ?>
        </div>

        <?php $lista = $aulas_por_modulo[$m["id"]] ?? []; ?>
        <?php if (!$lista): ?>
            <p class="vazio">Sem aulas neste módulo.</p>
        <?php else: ?>
            <ul class="lista-aulas">
                <?php foreach ($lista as $a): ?>
                    <li>
                        <a href="aula.php?id=<?= (int) $a["id"] ?>"><?= e($a["titulo"]) ?></a>
                        <?php if ($gestor): ?>
                            <form method="POST" action="curso.php"
                                  onsubmit="return confirm('Remover esta aula?');">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="remover_aula">
                                <input type="hidden" name="curso_id" value="<?= (int) $curso_id ?>">
                                <input type="hidden" name="id" value="<?= (int) $a["id"] ?>">
                                <button class="btn btn-peq btn-sec" type="submit">Remover</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($gestor): ?>
            <details class="cartao" style="margin:12px 0 0">
                <summary>Adicionar aula a este módulo</summary>
                <form method="POST" action="curso.php">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="nova_aula">
                    <input type="hidden" name="curso_id" value="<?= (int) $curso_id ?>">
                    <input type="hidden" name="modulo_id" value="<?= (int) $m["id"] ?>">
                    <label>Título da aula</label>
                    <input type="text" name="titulo" maxlength="150" required>
                    <label>Link do vídeo (YouTube ou outro, opcional)</label>
                    <input type="url" name="video_url" placeholder="https://...">
                    <label>Descrição</label>
                    <textarea name="descricao"></textarea>
                    <button class="btn" type="submit">Adicionar aula</button>
                </form>
            </details>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if ($gestor): ?>
    <h2>Novo módulo</h2>
    <div class="cartao">
        <form method="POST" action="curso.php" class="linha">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="novo_modulo">
            <input type="hidden" name="curso_id" value="<?= (int) $curso_id ?>">
            <input type="text" name="titulo" maxlength="120" placeholder="Título do módulo" required>
            <button class="btn" type="submit">Criar módulo</button>
        </form>
    </div>
<?php endif; ?>

<?php require "rodape.php"; ?>
