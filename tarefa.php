<?php
// tarefa.php?id=7 - uma tarefa: o estudante entrega; o docente vê as entregas e dá notas
require "auth.php";
require "conexao.php";
exigir_login();

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);

$st = $pdo->prepare("SELECT * FROM tarefas WHERE id = ?");
$st->execute([$id]);
$tarefa = $st->fetch();
if (!$tarefa) {
    http_response_code(404);
    die("Tarefa não encontrada. <a href='cursos.php'>Voltar aos cursos</a>");
}

$curso  = carregar_curso($pdo, (int) $tarefa["curso_id"]);
$gestor = pode_gerir_curso($curso);
$prazo_passou = $tarefa["prazo"] && strtotime($tarefa["prazo"]) < time();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();
    $acao = $_POST["acao"] ?? "";

    // ----- Estudante entrega (ou substitui) o ficheiro -----
    if ($acao === "entregar") {
        if (papel() !== "estudante") {
            http_response_code(403);
            die("Só estudantes entregam tarefas.");
        }
        $st = $pdo->prepare(
            "SELECT e.id, e.ficheiro_guardado,
                    (SELECT COUNT(*) FROM notas n WHERE n.entrega_id = e.id) AS avaliada
             FROM entregas e WHERE e.tarefa_id = ? AND e.estudante_id = ?"
        );
        $st->execute([$id, eu()]);
        $existente = $st->fetch();

        if ($prazo_passou) {
            flash("O prazo de entrega terminou.", "erro");
        } elseif ($existente && (int) $existente["avaliada"] > 0) {
            flash("A entrega já foi avaliada e não pode ser alterada.", "erro");
        } else {
            $r = guardar_upload($_FILES["ficheiro"] ?? [], "entregas");
            if (is_string($r)) {
                flash($r, "erro");
            } else {
                [$original, $guardado] = $r;
                $comentario = trim($_POST["comentario"] ?? "");
                if ($existente) {
                    // Substitui: apaga o ficheiro antigo do disco
                    apagar_ficheiros([$existente["ficheiro_guardado"]], "entregas");
                    $pdo->prepare(
                        "UPDATE entregas SET ficheiro_original = ?, ficheiro_guardado = ?, comentario = ?, entregue_em = NOW()
                         WHERE id = ?"
                    )->execute([$original, $guardado, $comentario, $existente["id"]]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO entregas (tarefa_id, estudante_id, ficheiro_original, ficheiro_guardado, comentario)
                         VALUES (?, ?, ?, ?, ?)"
                    )->execute([$id, eu(), $original, $guardado, $comentario]);
                }
                flash("Entrega registada.");
            }
        }

    // ----- Docente atribui nota -----
    } elseif ($acao === "avaliar") {
        if (!$gestor) {
            http_response_code(403);
            die("Sem permissão.");
        }
        $entrega_id = (int) ($_POST["entrega_id"] ?? 0);
        $valor_txt  = str_replace(",", ".", trim($_POST["valor"] ?? ""));
        $comentario = trim($_POST["comentario"] ?? "");

        // A entrega tem de ser desta tarefa
        $st = $pdo->prepare("SELECT 1 FROM entregas WHERE id = ? AND tarefa_id = ?");
        $st->execute([$entrega_id, $id]);

        if (!$st->fetchColumn()) {
            flash("Entrega inválida.", "erro");
        } elseif (!is_numeric($valor_txt) || (float) $valor_txt < 0 || (float) $valor_txt > 20) {
            flash("A nota tem de estar entre 0 e 20.", "erro");
        } else {
            // ON DUPLICATE KEY UPDATE: se já existir nota, atualiza; senão cria
            $pdo->prepare(
                "INSERT INTO notas (entrega_id, valor, comentario) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor), comentario = VALUES(comentario), avaliado_em = NOW()"
            )->execute([$entrega_id, round((float) $valor_txt, 1), $comentario]);
            flash("Nota guardada.");
        }
    }
    redirecionar("tarefa.php?id=" . $id);
}

if ($gestor) {
    // Todos os estudantes inscritos, com a entrega e a nota (se existirem)
    $st = $pdo->prepare(
        "SELECT u.id AS estudante_id, u.utilizador,
                e.id AS entrega_id, e.ficheiro_original, e.comentario AS entrega_comentario, e.entregue_em,
                n.valor, n.comentario AS nota_comentario
         FROM inscricoes i
         JOIN utilizadores u ON u.id = i.estudante_id
         LEFT JOIN entregas e ON e.estudante_id = u.id AND e.tarefa_id = ?
         LEFT JOIN notas n ON n.entrega_id = e.id
         WHERE i.curso_id = ?
         ORDER BY u.utilizador"
    );
    $st->execute([$id, $curso["id"]]);
    $linhas = $st->fetchAll();
} else {
    $st = $pdo->prepare(
        "SELECT e.*, n.valor, n.comentario AS nota_comentario
         FROM entregas e LEFT JOIN notas n ON n.entrega_id = e.id
         WHERE e.tarefa_id = ? AND e.estudante_id = ?"
    );
    $st->execute([$id, eu()]);
    $minha = $st->fetch();
}

function nota_txt($v): string
{
    return rtrim(rtrim((string) $v, "0"), ".");
}

$titulo_pagina = $tarefa["titulo"];
require "cabecalho.php";
?>
<p class="sub"><a href="tarefas.php?curso=<?= (int) $curso["id"] ?>">← Tarefas de <?= e($curso["titulo"]) ?></a></p>
<h1><?= e($tarefa["titulo"]) ?></h1>
<p class="sub">
    Prazo: <?= $tarefa["prazo"] ? e(data_pt($tarefa["prazo"])) : "sem prazo" ?>
    <?php if ($prazo_passou): ?> <span class="etiqueta erro">terminado</span><?php endif; ?>
</p>

<?php if (trim((string) $tarefa["enunciado"]) !== ""): ?>
    <div class="cartao"><?= nl2br(e($tarefa["enunciado"])) ?></div>
<?php endif; ?>

<?php if ($gestor): ?>

    <h2>Entregas dos estudantes</h2>
    <?php if (!$linhas): ?>
        <p class="vazio">Ainda não há estudantes inscritos neste curso.</p>
    <?php else: ?>
        <div class="tabela-scroll">
        <table>
            <tr><th>Estudante</th><th>Entrega</th><th>Nota (0 a 20)</th></tr>
            <?php foreach ($linhas as $l): ?>
                <tr>
                    <td><?= e($l["utilizador"]) ?></td>
                    <td>
                        <?php if ($l["entrega_id"]): ?>
                            <a href="download.php?tipo=entrega&amp;id=<?= (int) $l["entrega_id"] ?>"><?= e($l["ficheiro_original"]) ?></a><br>
                            <small><?= e(data_pt($l["entregue_em"])) ?></small>
                            <?php if (trim((string) $l["entrega_comentario"]) !== ""): ?>
                                <br><small>“<?= e($l["entrega_comentario"]) ?>”</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="etiqueta erro">Não entregou</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($l["entrega_id"]): ?>
                            <form method="POST" action="tarefa.php" class="linha">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="avaliar">
                                <input type="hidden" name="id" value="<?= (int) $id ?>">
                                <input type="hidden" name="entrega_id" value="<?= (int) $l["entrega_id"] ?>">
                                <input type="number" class="nota-input" name="valor" min="0" max="20" step="0.1"
                                       value="<?= $l["valor"] !== null ? e(nota_txt($l["valor"])) : "" ?>" required>
                                <input type="text" name="comentario" placeholder="Comentário (opcional)"
                                       value="<?= e($l["nota_comentario"]) ?>">
                                <button class="btn btn-peq" type="submit">Guardar</button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>

<?php elseif (papel() === "estudante"): ?>

    <h2>A minha entrega</h2>
    <?php if ($minha): ?>
        <div class="cartao">
            <p>Ficheiro: <a href="download.php?tipo=entrega&amp;id=<?= (int) $minha["id"] ?>"><?= e($minha["ficheiro_original"]) ?></a>
               · entregue em <?= e(data_pt($minha["entregue_em"])) ?></p>
            <?php if ($minha["valor"] !== null): ?>
                <p style="margin-top:8px"><span class="etiqueta ok">Nota: <?= e(nota_txt($minha["valor"])) ?>/20</span></p>
                <?php if (trim((string) $minha["nota_comentario"]) !== ""): ?>
                    <p class="sub" style="margin-top:8px">Comentário do docente: <?= e($minha["nota_comentario"]) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="sub" style="margin-top:8px">Ainda não foi avaliada.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($prazo_passou): ?>
        <p class="vazio">O prazo terminou: já não é possível entregar<?= $minha ? " nem alterar a entrega" : "" ?>.</p>
    <?php elseif ($minha && $minha["valor"] !== null): ?>
        <p class="vazio">A entrega já foi avaliada e não pode ser alterada.</p>
    <?php else: ?>
        <div class="cartao">
            <form method="POST" action="tarefa.php" enctype="multipart/form-data">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="entregar">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <label><?= $minha ? "Substituir o ficheiro entregue" : "Ficheiro da entrega" ?> (até 10 MB)</label>
                <input type="file" name="ficheiro" required>
                <label>Comentário (opcional)</label>
                <textarea name="comentario"></textarea>
                <button class="btn" type="submit"><?= $minha ? "Substituir entrega" : "Entregar" ?></button>
            </form>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require "rodape.php"; ?>
