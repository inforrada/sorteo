<?php
declare(strict_types=1);

/**
 * index.php
 * Punto de entrada principal.
 *   1. Carga configuración y conexión BD (crea tablas si no existen).
 *   2. Si el usuario tiene cookie válida para el evento actual → redirige a quiz.php.
 *   3. Si no → muestra el formulario de registro.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php'; // verifySignedCookie()

// ──────────────────────────────────────────────
// 1. Migración automática (crea tablas si no existen)
// ──────────────────────────────────────────────
try {
    Database::migrate();
} catch (RuntimeException $e) {
    // Mostrar error amigable si no se puede conectar a la BD
    http_response_code(503);
    echo '<h1>Error de configuración</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// ──────────────────────────────────────────────
// 2. Verificar si ya tiene sesión activa
// ──────────────────────────────────────────────
if (isset($_COOKIE[COOKIE_NAME])) {
    $session = verifySignedCookie($_COOKIE[COOKIE_NAME]);
    if ($session !== null && ($session['slug'] ?? '') === EVENT_SLUG) {
        header('Location: quiz.php');
        exit;
    }
}

// ──────────────────────────────────────────────
// 3. Recoger mensajes/errores de redirecciones previas
// ──────────────────────────────────────────────
$errors  = (array) (filter_input(INPUT_GET, 'errors',  FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []);
$oldName  = htmlspecialchars(trim(filter_input(INPUT_GET, 'name',  FILTER_DEFAULT) ?? ''));
$oldEmail = htmlspecialchars(trim(filter_input(INPUT_GET, 'email', FILTER_DEFAULT) ?? ''));
$msg      = filter_input(INPUT_GET, 'msg', FILTER_DEFAULT) ?? '';

if ($msg === 'session_expired') {
    $errors[] = 'Tu sesión ha expirado o es inválida. Por favor, regístrate de nuevo.';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(EVENT_NAME) ?> — Registro</title>
    <meta name="description" content="Regístrate en <?= htmlspecialchars(EVENT_NAME) ?> para participar en el cuestionario y el sorteo.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary:    #080e1d;
            --bg-card:       rgba(255, 255, 255, 0.055);
            --accent-1:      #7c6dfa;
            --accent-2:      #48cfad;
            --accent-glow:   rgba(124, 109, 250, 0.30);
            --error-bg:      rgba(255, 101, 132, 0.12);
            --error-border:  rgba(255, 101, 132, 0.35);
            --error-color:   #ff6584;
            --text-primary:  #edf2ff;
            --text-muted:    #8892b0;
            --border:        rgba(255, 255, 255, 0.09);
            --input-bg:      rgba(255, 255, 255, 0.06);
            --input-focus:   rgba(124, 109, 250, 0.22);
            --radius-lg:     20px;
            --radius-sm:     12px;
            --shadow:        0 20px 60px rgba(0, 0, 0, 0.55);
            --transition:    .22s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background-image:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(124,109,250,.22) 0%, transparent 60%),
                radial-gradient(ellipse 65% 45% at 85% 90%, rgba(72,207,173,.15) 0%, transparent 60%),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Ccircle cx='1' cy='1' r='1' fill='rgba(255,255,255,.04)'/%3E%3C/svg%3E");
        }

        /* ── Hero ── */
        .hero {
            text-align: center;
            margin-bottom: 2.8rem;
            animation: fadeDown .65s cubic-bezier(.2,.8,.4,1) both;
        }
        .hero .badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: rgba(124,109,250,.18);
            border: 1px solid rgba(124,109,250,.4);
            color: #b3aaff;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: .35rem .9rem;
            border-radius: 99px;
            margin-bottom: 1.2rem;
        }
        .hero h1 {
            font-size: clamp(2rem, 6vw, 3.4rem);
            font-weight: 800;
            line-height: 1.15;
            background: linear-gradient(135deg, var(--text-primary) 35%, var(--accent-1) 70%, var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero p {
            margin-top: 1rem;
            color: var(--text-muted);
            font-size: 1.05rem;
            max-width: 480px;
        }

        /* ── Tarjeta ── */
        .card {
            width: 100%;
            max-width: 500px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2.8rem 3rem;
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            animation: fadeUp .65s .15s cubic-bezier(.2,.8,.4,1) both;
        }
        @media (max-width: 560px) {
            .card { padding: 2rem 1.5rem; border-radius: var(--radius-sm); }
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: .35rem;
        }
        .card-subtitle {
            font-size: .9rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        /* ── Errores ── */
        .errors-box {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            border-radius: var(--radius-sm);
            padding: .9rem 1.2rem;
            margin-bottom: 1.6rem;
        }
        .errors-box ul { list-style: none; }
        .errors-box li {
            color: var(--error-color);
            font-size: .9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .errors-box li + li { margin-top: .4rem; }

        /* ── Formulario ── */
        .form-group { margin-bottom: 1.4rem; }

        label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: .55rem;
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: .85rem 1.1rem;
            background: var(--input-bg);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 1rem;
            outline: none;
            transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
        }
        input[type="text"]:focus,
        input[type="email"]:focus {
            border-color: var(--accent-1);
            background: var(--input-focus);
            box-shadow: 0 0 0 4px var(--accent-glow);
        }
        input::placeholder { color: #4a5680; }

        /* ── Botón de envío ── */
        .btn-submit {
            width: 100%;
            margin-top: .6rem;
            padding: 1rem;
            border: none;
            border-radius: var(--radius-sm);
            background: linear-gradient(135deg, var(--accent-1) 0%, #9b8fff 50%, var(--accent-2) 100%);
            background-size: 200% 200%;
            background-position: 0% 50%;
            color: #fff;
            font-family: inherit;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: .03em;
            cursor: pointer;
            transition: background-position .4s ease, transform .15s, opacity .2s;
        }
        .btn-submit:hover {
            background-position: 100% 50%;
            transform: translateY(-2px);
            opacity: .92;
        }
        .btn-submit:active { transform: translateY(0); }

        /* ── Footer de la tarjeta ── */
        .card-footer {
            text-align: center;
            margin-top: 1.6rem;
            font-size: .82rem;
            color: var(--text-muted);
        }
        .card-footer a {
            color: var(--accent-1);
            text-decoration: none;
            font-weight: 600;
        }
        .card-footer a:hover { text-decoration: underline; }

        /* ── Features ── */
        .features {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 2.5rem;
            animation: fadeUp .65s .3s ease both;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: .5rem;
            color: var(--text-muted);
            font-size: .82rem;
        }
        .feature-item span { font-size: 1.1rem; }

        /* ── Animations ── */
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-28px); }
            to   { opacity: 1; transform: none; }
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>

<!-- Hero -->
<div class="hero">
    <div class="badge">🎉 Participación activa</div>
    <h1><?= htmlspecialchars(EVENT_NAME) ?></h1>
    <p>Regístrate, responde el cuestionario y entra en el sorteo del evento.</p>
</div>

<!-- Tarjeta de registro -->
<div class="card">
    <h2 class="card-title">Regístrate para participar</h2>
    <p class="card-subtitle">Tu sesión se mantendrá activa durante 7 días en este dispositivo.</p>

    <?php if (!empty($errors)): ?>
    <div class="errors-box" role="alert">
        <ul>
            <?php foreach ($errors as $error): ?>
            <li><span>⚠️</span><?= htmlspecialchars((string) $error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="register.php" id="register-form" novalidate>
        <div class="form-group">
            <label for="name">Nombre completo</label>
            <input
                type="text"
                id="name"
                name="name"
                placeholder="María García"
                value="<?= $oldName ?>"
                required
                autocomplete="name"
                minlength="2"
            >
        </div>

        <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="maria@ejemplo.com"
                value="<?= $oldEmail ?>"
                required
                autocomplete="email"
            >
        </div>

        <button type="submit" class="btn-submit" id="btn-register">
            Participar en el evento →
        </button>
    </form>

    <div class="card-footer">
        ¿Eres el organizador? <a href="sorteo.php" id="link-admin">Accede al panel de sorteo</a>
    </div>
</div>

<!-- Características -->
<div class="features">
    <div class="feature-item"><span>🔒</span> Sin contraseña requerida</div>
    <div class="feature-item"><span>⚡</span> Acceso inmediato</div>
    <div class="feature-item"><span>🍪</span> Sesión de 7 días</div>
    <div class="feature-item"><span>🎲</span> Sorteo en tiempo real</div>
</div>

</body>
</html>
