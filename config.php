<?php
declare(strict_types=1);

/**
 * config.php
 * Carga las variables de entorno desde el archivo .env y las expone
 * como constantes tipadas para toda la aplicación.
 */

// ──────────────────────────────────────────────
// 1. Carga del archivo .env
// ──────────────────────────────────────────────
$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    throw new RuntimeException('No se encontró el archivo .env. Copia .env.example y configúralo.');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    $line = trim($line);
    // Ignorar comentarios
    if (str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $key   = trim($key);
    $value = trim($value);

    // Eliminar comillas envolventes si las hay
    if (
        (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
        (str_starts_with($value, "'") && str_ends_with($value, "'"))
    ) {
        $value = substr($value, 1, -1);
    }

    $_ENV[$key] = $value;
    putenv("$key=$value");
}

// ──────────────────────────────────────────────
// 2. Función auxiliar para obtener variables de entorno con valor por defecto
// ──────────────────────────────────────────────
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ──────────────────────────────────────────────
// 3. Configuración de Base de Datos
// ──────────────────────────────────────────────
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', env('DB_NAME', 'eventos_sorteo'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// ──────────────────────────────────────────────
// 4. Configuración del Evento Activo
// ──────────────────────────────────────────────
define('EVENT_SLUG', env('EVENT_SLUG', 'evento-generico'));
define('EVENT_NAME', env('EVENT_NAME', 'Evento'));

// ──────────────────────────────────────────────
// 5. Preguntas del Evento (desde JSON en .env)
// ──────────────────────────────────────────────
$questionsJson = env('EVENT_QUESTIONS', '[]');
$decodedQuestions = json_decode($questionsJson, associative: true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedQuestions)) {
    throw new RuntimeException('La variable EVENT_QUESTIONS en .env no contiene un JSON válido: ' . json_last_error_msg());
}

define('EVENT_QUESTIONS', $decodedQuestions);

// ──────────────────────────────────────────────
// 6. Configuración de Cookies
// ──────────────────────────────────────────────
define('COOKIE_NAME',    'event_user_session');
define('COOKIE_SECRET',  env('COOKIE_SECRET', 'fallback_secret_inseguro'));
define('COOKIE_EXPIRES', 7 * 24 * 60 * 60); // 7 días en segundos

// ──────────────────────────────────────────────
// 6b. Credenciales del panel de administración
// ──────────────────────────────────────────────
define('ADMIN_USER', env('ADMIN_USER', 'admin'));
define('ADMIN_PASS', env('ADMIN_PASS', ''));  // Hash bcrypt obligatorio

// ──────────────────────────────────────────────
// 7. Configuración general de la aplicación
// ──────────────────────────────────────────────
define('APP_VERSION', '1.0.0');
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));
