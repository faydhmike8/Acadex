<?php
// tarefas.php?curso=3 - lista de tarefas de um curso (o docente também as cria aqui)
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

    if ($acao === "nova") {
        $titulo    = trim($_POST["titulo"] ?? "");
        $enunciado = trim($_POST["enunciado"] ?? "");
        $prazo_txt = trim($_POST["prazo"] ?? "");
        $prazo     = null;

        // O campo datetime-local envia "2026-11-05T14:30"; a BD quer "2026-11-05 14:30:00"
        if ($prazo_txt !== "") {
            $d = DateTime::createFromFormat("Y-m-d\TH:i", $prazo_txt);
            $prazo = $d ? $d->format("Y-m-d H:i:00") : false;
        }

        if ($titulo === "" || mb_strlen($titulo) > 150) {
            flash("Indique o título da tarefa (até 150 caracteres).", "erro");
        } elseif ($prazo === false) {
            flash("Prazo inválido.", "erro");
        } else {
            $pdo->prepare("INSERT INTO tarefas (curso_id, titulo, enunciado, prazo) VALUES (?, ?, ?, ?)")
                ->execute([$curso_id, $titulo, $enunciado, $prazo]);
            flash("Tarefa criada.");
        }

    } elseif ($acao === "remover") {
        $tid = (int) ($_POST["id"] ?? 0);
        $st = $pdo->prepare("SELECT id FROM tarefas WHERE id = ? AND curso_id = ?");
        $st->execute([$tid, $curso_id]);
        if ($st->fetchColumn()) {
            // Apaga do disco as entregas desta tarefa; a BD apaga as linhas em cascata
            $st = $pdo->prepare("SELECT ficheiro_guardado FROM entregas WHERE tarefa_id = ?");
            $st->execute([$tid]);
            apagar_ficheiros($st->fetchAll(PDO::FETCH_COLUMN), "entregas");
            $pdo->prepare("DELETE FROM tarefas WHERE id = ?")->execute([$tid]);
            flash("Tarefa removida.");
        }
    }
    redirecionar("tarefas.php?curso=" . $curso_id);
}

if ($gestor) {
    // Para o docente: quantas entregas e quantas faltam avaliar
    $st = $pdo->prepare(
        "SELECT t.*,
                (SELECT COUNT(*) FROM entregas WHERE tarefa_id = t.id) AS entregas,
                (SELECT COUNT(*) FROM entregas e2 LEFT JOIN notas n2 ON n2.entrega_id = e2.id
                  WHERE e2.tarefa_id = t.id AND n2.id IS NULL) AS por_avaliar
         FROM tarefas t WHERE t.curso_id = ?
         ORDER BY (t.prazo IS NULL), t.prazo, t.id"
    );
    $st->execute([$curso_id]);
} else {
    // Para o estudante: a minha entrega e a minha nota, se existirem
    $st = $pdo->prepare(
        "SELECT t.*, e.id AS entrega_id, n.valor AS nota
         FROM tarefas t
         LEFT JOIN entregas e ON e.tarefa_id = t.id AND e.estudante_id = ?
         LEFT JOIN notas n ON n.entrega_id = e.id
         WHERE t.curso_id = ?
         ORDER BY (t.prazo IS NULL), t.prazo, t.id"
    );
    $st->execute([eu(), $curso_id]);
}
$tarefas = $st->fetchAll();

$titulo_pagina = "Tarefas - " . $curso["titulo"];
require "cabecalho.php";
?>
<h1><?= e($curso["titulo"]) ?></h1>
<p class="sub">Docente: <?= e($curso["docente_nome"]) ?></p>
<?php abas_curso($curso_id, "tarefas"); ?>

<?php if (!$tarefas): ?>
    <p class="vazio">Ainda não há tarefas neste curso.</p>
<?php else: ?>
    <div class="tabela-scroll">
    <table>
        <tr>
            <th>Tarefa</th><th>Prazo</th>
            <th><?= $gestor ? "Entregas" : "Estado" ?></th>
            <?php if ($gestor): ?><th></th><?php endif; ?>
        </tr>
        <?php foreach ($tarefas as $t): ?>
            <?php $passou = $t["prazo"] && strtotime($t["prazo"]) < time(); ?>
            <tr>
                <td><a href="tarefa.php?id=<?= (int) $t["id"] ?>"><?= e($t["titulo"]) ?></a></td>
                <td><?= $t["prazo"] ? e(data_pt($t["prazo"])) : "Sem prazo" ?></td>
                <td>
                    <?php if ($gestor): ?>
                        <?= (int) $t["entregas"] ?> entregue(s)
                        <?php if ((int) $t["por_avaliar"] > 0): ?>
                            <span class="etiqueta aviso"><?= (int) $t["por_avaliar"] ?> por avaliar</span>
                        <?php endif; ?>
                    <?php elseif ($t["nota"] !== null): ?>
                        <span class="etiqueta ok">Nota: <?= e(rtrim(rtrim((string) $t["nota"], "0"), ".")) ?>/20</span>
                    <?php elseif ($t["entrega_id"]): ?>
                        <span class="etiqueta">Entregue</span>
                    <?php elseif ($passou): ?>
                        <span class="etiqueta erro">Prazo terminado</span>
                    <?php else: ?>
                        <span class="etiqueta aviso">Pendente</span>
                    <?php endif; ?>
                </td>
                <?php if ($gestor): ?>
                    <td>
                        <form method="POST" action="tarefas.php"
                              onsubmit="return confirm('Remover a tarefa e todas as entregas?');">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="remover">
                            <input type="hidden" name="curso" value="<?= (int) $curso_id ?>">
                            <input type="hidden" name="id" value="<?= (int) $t["id"] ?>">
                            <button class="btn btn-peq btn-perigo" type="submit">Remover</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
<?php endif; ?>

<?php if ($gestor): ?>
    <h2>Nova tarefa</h2>
    <div class="cartao">
        <form method="POST" action="tarefas.php">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="nova">
            <input type="hidden" name="curso" value="<?= (int) $curso_id ?>">
            <label>Título</label>
            <input type="text" name="titulo" maxlength="150" required>
            <label>Enunciado</label>
            <textarea name="enunciado"></textarea>
            <label>Prazo de entrega (opcional)</label>
            <input type="datetime-local" name="prazo">
            <button class="btn" type="submit">Criar tarefa</button>
        </form>
    </div>
<?php endif; ?>

<?php require "rodape.php"; ?>
