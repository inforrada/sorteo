<?php
declare(strict_types=1);

/**
 * quiz.php
 * Renderiza las preguntas del evento y persiste las respuestas del usuario.
 * Requiere cookie de sesión válida; en caso contrario redirige a index.php.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php'; // verifySignedCookie()

// ──────────────────────────────────────────────
// 1. Verificar sesión por cookie
// ──────────────────────────────────────────────
$sessionData = null;
if (isset($_COOKIE[COOKIE_NAME])) {
    $sessionData = verifySignedCookie($_COOKIE[COOKIE_NAME]);
}

// Verificar que la cookie pertenece al evento actual
if (
    $sessionData === null ||
    ($sessionData['slug'] ?? '') !== EVENT_SLUG
) {
    header('Location: index.php?msg=session_expired');
    exit;
}

$userEmail = $sessionData['email'];
$questions = EVENT_QUESTIONS;

// ──────────────────────────────────────────────
// 2. Procesar respuesta enviada (POST)
// ──────────────────────────────────────────────
$feedbackMessage = null;
$feedbackType    = null; // 'success' | 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questionId     = (int) (filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT) ?? 0);
    $selectedAnswer = strtoupper(trim(filter_input(INPUT_POST, 'selected_answer', FILTER_DEFAULT) ?? ''));

    // Localizar la pregunta en la configuración
    $matchedQuestion = null;
    foreach ($questions as $q) {
        if ((int) $q['id'] === $questionId) {
            $matchedQuestion = $q;
            break;
        }
    }

    if ($matchedQuestion !== null && $selectedAnswer !== '') {
        $isCorrect = ($selectedAnswer === strtoupper($matchedQuestion['correcta']));

        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO user_answers (event_slug, email, question_id, selected_answer, is_correct)
                VALUES (:slug, :email, :qid, :answer, :correct)
                ON DUPLICATE KEY UPDATE
                    selected_answer = VALUES(selected_answer),
                    is_correct      = VALUES(is_correct),
                    answered_at     = CURRENT_TIMESTAMP
            SQL);

            $stmt->execute([
                ':slug'    => EVENT_SLUG,
                ':email'   => $userEmail,
                ':qid'     => $questionId,
                ':answer'  => $selectedAnswer,
                ':correct' => $isCorrect ? 1 : 0,
            ]);

            $feedbackMessage = $isCorrect
                ? '✅ ¡Correcto! Bien hecho.'
                : '❌ Incorrecto. La respuesta correcta era: ' . htmlspecialchars($matchedQuestion['correcta']);
            $feedbackType    = $isCorrect ? 'success' : 'error';

        } catch (PDOException $e) {
            error_log('[quiz] Error PDO: ' . $e->getMessage());
            $feedbackMessage = '⚠️ Error al guardar la respuesta. Inténtalo de nuevo.';
            $feedbackType    = 'error';
        }
    }
}

// ──────────────────────────────────────────────
// 3. Obtener respuestas ya registradas del usuario
// ──────────────────────────────────────────────
try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare(<<<SQL
        SELECT question_id, selected_answer, is_correct
        FROM   user_answers
        WHERE  event_slug = :slug AND email = :email
    SQL);
    $stmt->execute([':slug' => EVENT_SLUG, ':email' => $userEmail]);
    $answeredMap = [];
    foreach ($stmt->fetchAll() as $row) {
        $answeredMap[(int) $row['question_id']] = $row;
    }
} catch (PDOException $e) {
    error_log('[quiz] Error al leer respuestas: ' . $e->getMessage());
    $answeredMap = [];
}

$totalQuestions  = count($questions);
$totalAnswered   = count($answeredMap);
$totalCorrect    = array_reduce($answeredMap, fn(int $c, array $r) => $c + (int) $r['is_correct'], 0);
$quizCompleted   = ($totalAnswered >= $totalQuestions && $totalQuestions > 0);

// Pregunta activa: primera sin responder
$activeQuestion = null;
if (!$quizCompleted) {
    foreach ($questions as $q) {
        if (!isset($answeredMap[(int) $q['id']])) {
            $activeQuestion = $q;
            break;
        }
    }
}

// Obtener nombre del usuario desde BD
$stmt = $pdo->prepare('SELECT name FROM event_registrations WHERE event_slug = :slug AND email = :email LIMIT 1');
$stmt->execute([':slug' => EVENT_SLUG, ':email' => $userEmail]);
$userName = $stmt->fetchColumn() ?: 'Participante';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuestionario — <?= htmlspecialchars(EVENT_NAME) ?></title>
    <meta name="description" content="Responde las preguntas del cuestionario de <?= htmlspecialchars(EVENT_NAME) ?> para participar en el sorteo.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary:    #0a0f1e;
            --bg-card:       rgba(255, 255, 255, 0.05);
            --bg-card-hover: rgba(255, 255, 255, 0.09);
            --accent-1:      #6c63ff;
            --accent-2:      #48cfad;
            --accent-error:  #ff6584;
            --accent-success:#48cfad;
            --text-primary:  #f0f4ff;
            --text-muted:    #8892b0;
            --border:        rgba(255, 255, 255, 0.10);
            --radius:        16px;
            --shadow:        0 8px 32px rgba(0, 0, 0, 0.45);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            background-image:
                radial-gradient(ellipse 80% 50% at 20% 10%, rgba(108,99,255,.18) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 90%, rgba(72,207,173,.12) 0%, transparent 60%);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem 4rem;
        }

        header {
            text-align: center;
            margin-bottom: 2.5rem;
            animation: fadeDown .6s ease both;
        }
        header .event-name {
            font-size: .85rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--accent-1);
            font-weight: 600;
        }
        header h1 {
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            font-weight: 800;
            margin-top: .4rem;
            background: linear-gradient(135deg, var(--text-primary) 40%, var(--accent-1));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        header .welcome {
            color: var(--text-muted);
            margin-top: .5rem;
            font-size: .95rem;
        }

        .progress-bar-wrap {
            width: 100%;
            max-width: 680px;
            background: var(--border);
            border-radius: 99px;
            height: 8px;
            margin-bottom: 2rem;
            overflow: hidden;
            animation: fadeUp .6s .2s ease both;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, var(--accent-1), var(--accent-2));
            transition: width .6s ease;
            width: <?= $totalQuestions > 0 ? round(($totalAnswered / $totalQuestions) * 100) : 0 ?>%;
        }
        .progress-label {
            text-align: center;
            font-size: .8rem;
            color: var(--text-muted);
            margin-bottom: 1.8rem;
            animation: fadeUp .6s .25s ease both;
        }

        .card {
            width: 100%;
            max-width: 680px;
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2.5rem 2.8rem;
            box-shadow: var(--shadow);
            animation: fadeUp .6s .3s ease both;
        }

        /* Feedback */
        .feedback {
            border-radius: 10px;
            padding: 1rem 1.4rem;
            margin-bottom: 1.8rem;
            font-size: .95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        .feedback.success { background: rgba(72,207,173,.15); border: 1px solid rgba(72,207,173,.35); color: var(--accent-success); }
        .feedback.error   { background: rgba(255,101,132,.12); border: 1px solid rgba(255,101,132,.3);  color: var(--accent-error); }

        /* Pregunta */
        .question-number {
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--accent-1);
            margin-bottom: .6rem;
        }
        .question-text {
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1.55;
            margin-bottom: 1.8rem;
        }

        /* Opciones */
        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 520px) { .options-grid { grid-template-columns: 1fr; } }

        .option-btn {
            position: relative;
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: 1rem 1.2rem;
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: .95rem;
            font-weight: 500;
            cursor: pointer;
            transition: background .2s, border-color .2s, transform .15s;
            text-align: left;
        }
        .option-btn:hover {
            background: var(--bg-card-hover);
            border-color: var(--accent-1);
            transform: translateY(-2px);
        }
        .option-btn.selected {
            border-color: var(--accent-1);
            background: rgba(108,99,255,.18);
        }
        .option-key {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(108,99,255,.25);
            color: var(--accent-1);
            font-weight: 700;
            font-size: .85rem;
            flex-shrink: 0;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--accent-1), #9b8fff);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s, transform .15s;
            letter-spacing: .03em;
        }
        .submit-btn:hover { opacity: .88; transform: translateY(-1px); }
        .submit-btn:active { transform: translateY(0); }

        /* Historial de respuestas */
        .answers-history {
            margin-top: 2.5rem;
        }
        .answers-history h2 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 1rem;
        }
        .answer-item {
            display: flex;
            align-items: flex-start;
            gap: .8rem;
            padding: .85rem 1.1rem;
            border-radius: 10px;
            margin-bottom: .6rem;
            border: 1px solid var(--border);
            background: var(--bg-card);
            font-size: .9rem;
        }
        .answer-item .badge {
            font-size: .75rem;
            font-weight: 700;
            padding: .25rem .55rem;
            border-radius: 6px;
            flex-shrink: 0;
            margin-top: .1rem;
        }
        .badge.ok  { background: rgba(72,207,173,.2); color: var(--accent-success); }
        .badge.ko  { background: rgba(255,101,132,.2); color: var(--accent-error); }

        /* Completado */
        .completed-card {
            text-align: center;
        }
        .completed-card .trophy {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        .completed-card h2 {
            font-size: 1.7rem;
            font-weight: 800;
            margin-bottom: .6rem;
        }
        .completed-card p {
            color: var(--text-muted);
            line-height: 1.6;
        }
        .score-badge {
            display: inline-block;
            margin-top: 1.5rem;
            padding: .7rem 1.8rem;
            border-radius: 99px;
            background: linear-gradient(135deg, var(--accent-1), var(--accent-2));
            font-weight: 700;
            font-size: 1.1rem;
            color: #fff;
        }

        @keyframes fadeDown { from { opacity:0; transform: translateY(-20px); } to { opacity:1; transform: none; } }
        @keyframes fadeUp   { from { opacity:0; transform: translateY(20px);  } to { opacity:1; transform: none; } }
        @keyframes pulse    { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
    </style>
</head>
<body>

<header>
    <p class="event-name"><?= htmlspecialchars(EVENT_NAME) ?></p>
    <h1>Cuestionario del Evento</h1>
    <p class="welcome">Hola, <strong><?= htmlspecialchars($userName) ?></strong> — <?= htmlspecialchars($userEmail) ?></p>
</header>

<div class="progress-bar-wrap" role="progressbar" aria-valuenow="<?= $totalAnswered ?>" aria-valuemin="0" aria-valuemax="<?= $totalQuestions ?>">
    <div class="progress-bar-fill"></div>
</div>
<p class="progress-label"><?= $totalAnswered ?> de <?= $totalQuestions ?> preguntas respondidas &bull; <?= $totalCorrect ?> correctas</p>

<div class="card">

    <?php if ($feedbackMessage !== null): ?>
    <div class="feedback <?= $feedbackType ?>" role="alert">
        <?= htmlspecialchars($feedbackMessage) ?>
    </div>
    <?php endif; ?>

    <?php if ($quizCompleted): ?>
    <!-- ── Cuestionario completado ── -->
    <div class="completed-card">
        <div class="trophy">🏆</div>
        <h2>¡Cuestionario completado!</h2>
        <p>Has respondido todas las preguntas del evento.<br>¡Espera los resultados del sorteo!</p>
        <div class="score-badge"><?= $totalCorrect ?> / <?= $totalQuestions ?> correctas</div>
    </div>

    <?php elseif ($activeQuestion !== null): ?>
    <!-- ── Pregunta activa ── -->
    <?php
        $qIndex = array_search($activeQuestion, $questions, strict: true);
        $qNum   = is_int($qIndex) ? $qIndex + 1 : $totalAnswered + 1;
    ?>
    <form method="POST" action="quiz.php" id="quiz-form">
        <input type="hidden" name="question_id" value="<?= (int) $activeQuestion['id'] ?>">

        <p class="question-number">Pregunta <?= $qNum ?> de <?= $totalQuestions ?></p>
        <p class="question-text"><?= htmlspecialchars($activeQuestion['pregunta']) ?></p>

        <div class="options-grid" role="radiogroup" aria-label="Opciones de respuesta">
            <?php foreach ($activeQuestion['opciones'] as $key => $text): ?>
            <button
                type="submit"
                name="selected_answer"
                value="<?= htmlspecialchars($key) ?>"
                class="option-btn"
                id="option-<?= htmlspecialchars($key) ?>"
            >
                <span class="option-key"><?= htmlspecialchars($key) ?></span>
                <?= htmlspecialchars($text) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </form>
    <?php endif; ?>

    <!-- ── Historial de respuestas anteriores ── -->
    <?php if (!empty($answeredMap)): ?>
    <div class="answers-history">
        <h2>Tus respuestas</h2>
        <?php foreach ($questions as $q): ?>
            <?php
            $qId = (int) $q['id'];
            if (!isset($answeredMap[$qId])) continue;
            $row = $answeredMap[$qId];
            $isOk = (bool) $row['is_correct'];
            ?>
            <div class="answer-item">
                <span class="badge <?= $isOk ? 'ok' : 'ko' ?>"><?= $isOk ? '✓' : '✗' ?></span>
                <div>
                    <strong><?= htmlspecialchars($q['pregunta']) ?></strong><br>
                    <span style="color: var(--text-muted); font-size:.85rem;">
                        Tu respuesta: <strong><?= htmlspecialchars($row['selected_answer']) ?></strong>
                        — Correcta: <strong><?= htmlspecialchars($q['correcta']) ?></strong>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- /.card -->

</body>
</html>
