<?php
declare(strict_types=1);

/**
 * sorteo.php
 * Panel de administración para realizar el sorteo del evento.
 * Protegido por autenticación de sesión PHP con credenciales del .env.
 *
 * Tres modalidades de filtro:
 *   1. Solo participantes con TODAS las respuestas correctas.
 *   2. Participantes que NO tienen todas correctas (o no completaron).
 *   3. Todos los inscritos (sorteo general).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ══════════════════════════════════════════════════════════════════════════════
// BLOQUE DE AUTENTICACIÓN — debe ejecutarse antes de cualquier salida HTML
// ══════════════════════════════════════════════════════════════════════════════

session_start();

/** Nombre de la clave en $_SESSION que indica sesión de admin activa */
const ADMIN_SESSION_KEY = 'sorteo_admin_authenticated';

// ── Acción de cierre de sesión ────────────────────────────────────────────────
if (filter_input(INPUT_GET, 'logout') !== null) {
    $_SESSION = [];
    session_destroy();
    header('Location: sorteo.php');
    exit;
}

// ── Procesar intento de login (POST) ─────────────────────────────────────────
$loginError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION[ADMIN_SESSION_KEY])) {
    $inputUser = trim(filter_input(INPUT_POST, 'admin_user', FILTER_DEFAULT) ?? '');
    $inputPass = filter_input(INPUT_POST, 'admin_pass', FILTER_DEFAULT) ?? '';

    $validUser = hash_equals(ADMIN_USER, $inputUser);
    $validPass = password_verify($inputPass, ADMIN_PASS);

    if ($validUser && $validPass) {
        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(delete_old_session: true);
        $_SESSION[ADMIN_SESSION_KEY] = true;
        // Redirigir a GET para evitar reenvío del formulario
        header('Location: sorteo.php' . (isset($_GET['filtro']) ? '?filtro=' . urlencode($_GET['filtro']) : ''));
        exit;
    }

    $loginError = 'Usuario o contraseña incorrectos.';
}

// ── Si no está autenticado → mostrar pantalla de login y detener ──────────────
if (!isset($_SESSION[ADMIN_SESSION_KEY])) {
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido — <?= htmlspecialchars(EVENT_NAME) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:          #070d1a;
            --bg-card:     rgba(255, 255, 255, 0.06);
            --accent:      #7c6dfa;
            --accent-glow: rgba(124, 109, 250, 0.30);
            --accent-2:    #48cfad;
            --error-bg:    rgba(255, 101, 132, 0.12);
            --error-bdr:   rgba(255, 101, 132, 0.35);
            --error-clr:   #ff6584;
            --text:        #edf2ff;
            --muted:       #8892b0;
            --border:      rgba(255, 255, 255, 0.09);
            --input-bg:    rgba(255, 255, 255, 0.06);
            --input-focus: rgba(124, 109, 250, 0.20);
            --radius:      18px;
            --radius-sm:   12px;
            --shadow:      0 24px 64px rgba(0, 0, 0, 0.6);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background-image:
                radial-gradient(ellipse 80% 55% at 15% 10%,  rgba(124,109,250,.22) 0%, transparent 58%),
                radial-gradient(ellipse 60% 45% at 88% 92%, rgba(72,207,173,.15) 0%, transparent 58%);
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            animation: slideUp .6s cubic-bezier(.2,.8,.4,1) both;
        }

        /* ── Icono de candado ── */
        .lock-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(124,109,250,.3), rgba(72,207,173,.2));
            border: 1px solid rgba(124,109,250,.4);
            font-size: 2.2rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 0 40px var(--accent-glow);
            animation: pulse 3s ease-in-out infinite;
        }

        .login-header { text-align: center; margin-bottom: 2.2rem; }
        .login-header .event-badge {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #a89dff;
            margin-bottom: .6rem;
        }
        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text) 40%, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .login-header p {
            margin-top: .5rem;
            color: var(--muted);
            font-size: .9rem;
        }

        /* ── Tarjeta del formulario ── */
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2.4rem 2.6rem;
            box-shadow: var(--shadow);
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
        }
        @media (max-width: 480px) {
            .login-card { padding: 1.8rem 1.4rem; }
        }

        /* ── Error ── */
        .error-box {
            display: flex;
            align-items: center;
            gap: .6rem;
            background: var(--error-bg);
            border: 1px solid var(--error-bdr);
            border-radius: var(--radius-sm);
            padding: .85rem 1.1rem;
            margin-bottom: 1.5rem;
            color: var(--error-clr);
            font-size: .9rem;
            font-weight: 500;
            animation: shake .4s ease;
        }

        /* ── Campos ── */
        .form-group { margin-bottom: 1.3rem; }

        label {
            display: block;
            font-size: .78rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .09em;
            margin-bottom: .5rem;
        }

        .input-wrap {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            pointer-events: none;
            opacity: .5;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: .85rem 1rem .85rem 2.7rem;
            background: var(--input-bg);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: inherit;
            font-size: 1rem;
            outline: none;
            transition: border-color .2s, background .2s, box-shadow .2s;
        }
        input:focus {
            border-color: var(--accent);
            background: var(--input-focus);
            box-shadow: 0 0 0 4px var(--accent-glow);
        }
        input::placeholder { color: #3d4a6b; }

        /* ── Toggle mostrar contraseña ── */
        .toggle-pass {
            position: absolute;
            right: .9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            font-size: 1rem;
            padding: .2rem;
            transition: color .2s;
        }
        .toggle-pass:hover { color: var(--accent); }

        /* ── Botón de login ── */
        .btn-login {
            width: 100%;
            margin-top: .4rem;
            padding: 1rem;
            border: none;
            border-radius: var(--radius-sm);
            background: linear-gradient(135deg, var(--accent) 0%, #9b8fff 50%, var(--accent-2) 100%);
            background-size: 200% 200%;
            background-position: 0% 50%;
            color: #fff;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: .03em;
            cursor: pointer;
            transition: background-position .4s ease, transform .15s, box-shadow .2s;
        }
        .btn-login:hover {
            background-position: 100% 50%;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--accent-glow);
        }
        .btn-login:active { transform: translateY(0); }

        /* ── Nota de seguridad ── */
        .security-note {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .78rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
        }

        @keyframes slideUp  { from { opacity:0; transform: translateY(32px); }  to { opacity:1; transform: none; } }
        @keyframes pulse    { 0%,100% { box-shadow: 0 0 30px var(--accent-glow); } 50% { box-shadow: 0 0 55px var(--accent-glow); } }
        @keyframes shake    {
            0%,100% { transform: translateX(0); }
            20%     { transform: translateX(-6px); }
            40%     { transform: translateX(6px); }
            60%     { transform: translateX(-4px); }
            80%     { transform: translateX(4px); }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="lock-icon">🔐</div>

    <div class="login-header">
        <p class="event-badge"><?= htmlspecialchars(EVENT_NAME) ?></p>
        <h1>Panel de Sorteo</h1>
        <p>Acceso restringido a administradores</p>
    </div>

    <div class="login-card">

        <?php if ($loginError !== null): ?>
        <div class="error-box" role="alert" id="login-error">
            <span>⚠️</span>
            <?= htmlspecialchars($loginError) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="sorteo.php" id="login-form" autocomplete="off" novalidate>

            <div class="form-group">
                <label for="admin_user">Usuario</label>
                <div class="input-wrap">
                    <span class="input-icon">👤</span>
                    <input
                        type="text"
                        id="admin_user"
                        name="admin_user"
                        placeholder="Nombre de usuario"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="admin_pass">Contraseña</label>
                <div class="input-wrap">
                    <span class="input-icon">🔑</span>
                    <input
                        type="password"
                        id="admin_pass"
                        name="admin_pass"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        type="button"
                        class="toggle-pass"
                        id="toggle-pass-btn"
                        aria-label="Mostrar u ocultar contraseña"
                        title="Mostrar contraseña"
                    >👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="btn-login">
                Acceder al panel →
            </button>
        </form>

        <p class="security-note">
            <span>🛡️</span> Sesión protegida · Solo administradores
        </p>
    </div>
</div>

<script>
// Toggle mostrar/ocultar contraseña
document.getElementById('toggle-pass-btn').addEventListener('click', () => {
    const input = document.getElementById('admin_pass');
    const btn   = document.getElementById('toggle-pass-btn');
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
        btn.title = 'Ocultar contraseña';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
        btn.title = 'Mostrar contraseña';
    }
});
</script>

</body>
</html>
    <?php
    exit; // Detener ejecución: no mostrar el panel hasta que esté autenticado
}

// ══════════════════════════════════════════════════════════════════════════════
// A PARTIR DE AQUÍ: usuario autenticado como administrador
// ══════════════════════════════════════════════════════════════════════════════

// ──────────────────────────────────────────────
// Lógica del sorteo
// ──────────────────────────────────────────────

$filterMode     = filter_input(INPUT_GET, 'filtro', FILTER_DEFAULT) ?? '';
$validModes     = ['todos_correctos', 'no_todos_correctos', 'todos_inscritos'];
$winner         = null;
$pool           = [];
$errorMsg       = null;
$totalPreguntas = count(EVENT_QUESTIONS);

if (in_array($filterMode, $validModes, strict: true)) {
    try {
        $pdo = Database::getInstance();

        $pool = match ($filterMode) {
            // ── Modalidad 1: Todos correctos ──────────────────────────────────────────────
            'todos_correctos' => (function () use ($pdo, $totalPreguntas): array {
                $stmt = $pdo->prepare(<<<SQL
                    SELECT er.email, er.name
                    FROM   event_registrations er
                    INNER JOIN (
                        SELECT   email, COUNT(*) AS correctas
                        FROM     user_answers
                        WHERE    event_slug = :slug AND is_correct = 1
                        GROUP BY email
                        HAVING   correctas >= :total
                    ) ua ON er.email = ua.email
                    WHERE  er.event_slug = :slug2
                SQL);
                $stmt->execute([
                    ':slug'  => EVENT_SLUG,
                    ':total' => $totalPreguntas,
                    ':slug2' => EVENT_SLUG,
                ]);
                return $stmt->fetchAll();
            })(),

            // ── Modalidad 2: NO todos correctos ───────────────────────────────────────────
            'no_todos_correctos' => (function () use ($pdo, $totalPreguntas): array {
                $stmt = $pdo->prepare(<<<SQL
                    SELECT er.email, er.name
                    FROM   event_registrations er
                    WHERE  er.event_slug = :slug
                      AND  er.email NOT IN (
                          SELECT   email
                          FROM     user_answers
                          WHERE    event_slug = :slug2 AND is_correct = 1
                          GROUP BY email
                          HAVING   COUNT(*) >= :total
                      )
                SQL);
                $stmt->execute([
                    ':slug'  => EVENT_SLUG,
                    ':slug2' => EVENT_SLUG,
                    ':total' => $totalPreguntas,
                ]);
                return $stmt->fetchAll();
            })(),

            // ── Modalidad 3: Todos los inscritos ──────────────────────────────────────────
            'todos_inscritos' => (function () use ($pdo): array {
                $stmt = $pdo->prepare(<<<SQL
                    SELECT email, name
                    FROM   event_registrations
                    WHERE  event_slug = :slug
                SQL);
                $stmt->execute([':slug' => EVENT_SLUG]);
                return $stmt->fetchAll();
            })(),
        };

        // Seleccionar ganador aleatorio en PHP (sobre el conjunto ya filtrado)
        if (!empty($pool)) {
            $winner = $pool[array_rand($pool)];
        }

    } catch (PDOException $e) {
        error_log('[sorteo] Error PDO: ' . $e->getMessage());
        $errorMsg = 'Error al consultar la base de datos. Revisa los logs.';
    }
}

// ──────────────────────────────────────────────
// Estadísticas generales del evento
// ──────────────────────────────────────────────
$stats = ['total_inscritos' => 0, 'total_completaron' => 0, 'total_todos_correctos' => 0];
try {
    $pdo ??= Database::getInstance();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM event_registrations WHERE event_slug = :slug');
    $stmt->execute([':slug' => EVENT_SLUG]);
    $stats['total_inscritos'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(<<<SQL
        SELECT COUNT(DISTINCT email) FROM user_answers
        WHERE event_slug = :slug
    SQL);
    $stmt->execute([':slug' => EVENT_SLUG]);
    $stats['total_respondieron'] = (int) $stmt->fetchColumn();

    if ($totalPreguntas > 0) {
        $stmt = $pdo->prepare(<<<SQL
            SELECT COUNT(*) FROM (
                SELECT   email
                FROM     user_answers
                WHERE    event_slug = :slug AND is_correct = 1
                GROUP BY email
                HAVING   COUNT(*) >= :total
            ) sub
        SQL);
        $stmt->execute([':slug' => EVENT_SLUG, ':total' => $totalPreguntas]);
        $stats['total_todos_correctos'] = (int) $stmt->fetchColumn();
    }
} catch (PDOException) { /* silenciar en la vista */ }

// Modos disponibles para el selector
$modeLabels = [
    'todos_correctos'     => '🏅  Solo con todas las respuestas correctas',
    'no_todos_correctos'  => '🎲  Los que NO acertaron todas',
    'todos_inscritos'     => '👥  Todos los inscritos',
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Sorteo — <?= htmlspecialchars(EVENT_NAME) ?></title>
    <meta name="description" content="Panel de administración del sorteo para <?= htmlspecialchars(EVENT_NAME) ?>.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:         #070d1a;
            --bg-card:    rgba(255,255,255,.045);
            --bg-glass:   rgba(255,255,255,.07);
            --accent:     #7c6dfa;
            --accent-2:   #48cfad;
            --gold:       #f6c90e;
            --error:      #ff6584;
            --text:       #edf2ff;
            --muted:      #8892b0;
            --border:     rgba(255,255,255,.09);
            --radius:     18px;
            --shadow:     0 12px 40px rgba(0,0,0,.5);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            background-image:
                radial-gradient(ellipse 70% 50% at 10% 5%,  rgba(124,109,250,.2) 0%, transparent 55%),
                radial-gradient(ellipse 55% 40% at 90% 95%, rgba(72,207,173,.14) 0%, transparent 55%);
            padding: 2rem 1rem 5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ── Barra superior de sesión ── */
        .session-bar {
            width: 100%;
            max-width: 820px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .8rem;
            margin-bottom: 1.5rem;
            animation: fadeDown .5s ease both;
        }
        .session-bar .session-info {
            font-size: .82rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .session-bar .session-info strong { color: var(--text); }
        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .4rem .9rem;
            border: 1px solid var(--border);
            border-radius: 99px;
            background: rgba(255,101,132,.1);
            color: #ff8fa3;
            font-family: inherit;
            font-size: .8rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, border-color .2s;
        }
        .btn-logout:hover { background: rgba(255,101,132,.2); border-color: rgba(255,101,132,.4); }

        header { text-align: center; margin-bottom: 3rem; animation: fadeDown .6s ease both; }
        header .label {
            font-size: .78rem; letter-spacing: .14em; text-transform: uppercase;
            color: var(--accent); font-weight: 700; margin-bottom: .4rem;
        }
        header h1 {
            font-size: clamp(2rem, 5vw, 3rem); font-weight: 800;
            background: linear-gradient(135deg, var(--text) 40%, var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        header p { color: var(--muted); margin-top: .5rem; }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.2rem;
            width: 100%; max-width: 820px;
            margin-bottom: 2.8rem;
            animation: fadeUp .5s .15s ease both;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.4rem 1.6rem;
            text-align: center;
            backdrop-filter: blur(14px);
        }
        .stat-card .num {
            font-size: 2.4rem; font-weight: 800;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .stat-card .desc { color: var(--muted); font-size: .82rem; margin-top: .3rem; }

        /* Filter panel */
        .filter-panel {
            width: 100%; max-width: 820px;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2.2rem 2.5rem;
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
            animation: fadeUp .5s .25s ease both;
        }
        .filter-panel h2 { font-size: 1.1rem; font-weight: 700; margin-bottom: 1.4rem; }

        .modes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.8rem;
        }

        .mode-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: .5rem;
            padding: 1.4rem 1.2rem;
            border-radius: 14px;
            border: 2px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            text-decoration: none;
            font-weight: 600;
            font-size: .9rem;
            transition: border-color .2s, background .2s, transform .15s;
        }
        .mode-link:hover { border-color: var(--accent); background: rgba(124,109,250,.15); transform: translateY(-3px); }
        .mode-link.active { border-color: var(--accent); background: rgba(124,109,250,.22); }
        .mode-link .icon { font-size: 2rem; }

        /* Resultado del sorteo */
        .result-box {
            margin-top: 2.4rem;
            animation: popIn .5s cubic-bezier(.34,1.56,.64,1) both;
        }

        .winner-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: .8rem;
            padding: 2.5rem;
            background: linear-gradient(135deg, rgba(246,201,14,.1), rgba(124,109,250,.18));
            border: 2px solid var(--gold);
            border-radius: var(--radius);
        }
        .winner-card .trophy { font-size: 3.5rem; animation: spin 1s ease; }
        .winner-card h3 { font-size: .8rem; text-transform: uppercase; letter-spacing: .12em; color: var(--gold); }
        .winner-card .winner-name { font-size: 2rem; font-weight: 800; }
        .winner-card .winner-email {
            color: var(--muted); font-size: .95rem;
            background: rgba(255,255,255,.08);
            padding: .4rem 1rem; border-radius: 99px;
        }

        .no-winner-card {
            padding: 2rem;
            text-align: center;
            border: 1.5px dashed var(--border);
            border-radius: var(--radius);
            color: var(--muted);
        }
        .no-winner-card .icon { font-size: 2.5rem; margin-bottom: .8rem; }

        .pool-list { margin-top: 1.6rem; }
        .pool-list h3 { font-size: .85rem; text-transform: uppercase; letter-spacing: .1em; color: var(--muted); margin-bottom: .8rem; }
        .pool-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .88rem;
        }
        .pool-table th, .pool-table td {
            padding: .65rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .pool-table th { color: var(--muted); font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; }
        .pool-table tr:last-child td { border-bottom: none; }
        .pool-table tr.winner-row td { color: var(--gold); font-weight: 700; }

        .error-msg {
            padding: 1rem 1.4rem;
            border-radius: 10px;
            background: rgba(255,101,132,.12);
            border: 1px solid rgba(255,101,132,.3);
            color: var(--error);
            margin-top: 1.5rem;
        }

        @keyframes fadeDown { from { opacity:0; transform: translateY(-20px); } to { opacity:1; transform: none; } }
        @keyframes fadeUp   { from { opacity:0; transform: translateY(24px);  } to { opacity:1; transform: none; } }
        @keyframes popIn    { from { opacity:0; transform: scale(.85); }        to { opacity:1; transform: scale(1); } }
        @keyframes spin     { from { transform: rotate(-20deg) scale(.7); }     to { transform: rotate(0) scale(1); } }
    </style>
</head>
<body>

<!-- Barra de sesión -->
<div class="session-bar">
    <span class="session-info">🔐 Sesión activa como <strong><?= htmlspecialchars(ADMIN_USER) ?></strong></span>
    <a href="sorteo.php?logout" class="btn-logout" id="btn-logout">⏻ Cerrar sesión</a>
</div>

<header>
    <p class="label">Panel de Administración</p>
    <h1>🎉 Sorteo del Evento</h1>
    <p><?= htmlspecialchars(EVENT_NAME) ?> &mdash; Slug: <code><?= htmlspecialchars(EVENT_SLUG) ?></code></p>
</header>

<!-- Estadísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="num"><?= $stats['total_inscritos'] ?></div>
        <div class="desc">Participantes inscritos</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= $stats['total_respondieron'] ?? 0 ?></div>
        <div class="desc">Que respondieron alguna pregunta</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= $stats['total_todos_correctos'] ?></div>
        <div class="desc">Con todas las respuestas correctas</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= $totalPreguntas ?></div>
        <div class="desc">Preguntas del cuestionario</div>
    </div>
</div>

<!-- Panel de filtros -->
<div class="filter-panel">
    <h2>🎯 Selecciona la modalidad del sorteo</h2>

    <div class="modes-grid">
        <?php foreach ($modeLabels as $mode => $label): ?>
        <a
            href="sorteo.php?filtro=<?= urlencode($mode) ?>"
            class="mode-link <?= $filterMode === $mode ? 'active' : '' ?>"
            id="btn-<?= htmlspecialchars($mode) ?>"
        >
            <span class="icon"><?= mb_substr($label, 0, 2) ?></span>
            <?= htmlspecialchars(mb_substr($label, 3)) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($errorMsg !== null): ?>
        <div class="error-msg">⚠️ <?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <?php if (in_array($filterMode, $validModes, strict: true)): ?>
    <div class="result-box">

        <?php if ($winner !== null): ?>
        <!-- ── Ganador ── -->
        <div class="winner-card" id="winner-card">
            <div class="trophy">🏆</div>
            <h3>¡Ganador del sorteo!</h3>
            <p class="winner-name"><?= htmlspecialchars($winner['name']) ?></p>
            <p class="winner-email"><?= htmlspecialchars($winner['email']) ?></p>
        </div>
        <?php else: ?>
        <div class="no-winner-card">
            <div class="icon">🔍</div>
            <p><strong>Sin participantes en esta categoría.</strong></p>
            <p>No hay nadie que cumpla el filtro seleccionado para este evento.</p>
        </div>
        <?php endif; ?>

        <!-- Lista completa del pool -->
        <?php if (!empty($pool)): ?>
        <div class="pool-list">
            <h3>Pool del sorteo (<?= count($pool) ?> participantes)</h3>
            <table class="pool-table">
                <thead>
                    <tr><th>#</th><th>Nombre</th><th>Email</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pool as $i => $participant): ?>
                    <tr class="<?= ($winner !== null && $participant['email'] === $winner['email']) ? 'winner-row' : '' ?>">
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($participant['name']) ?></td>
                        <td><?= htmlspecialchars($participant['email']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div><!-- /.filter-panel -->

</body>
</html>
