<?php
declare(strict_types=1);

/**
 * register.php
 * Gestiona el registro de un usuario en el evento actual:
 *   1. Valida y sanitiza los datos del formulario.
 *   2. Inserta o recupera el registro en event_registrations.
 *   3. Emite una cookie firmada con HMAC-SHA256 que dura 7 días.
 *   4. Redirige al cuestionario (quiz.php).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php'; // createSignedCookie, verifySignedCookie, emitSessionCookie



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$name  = trim(filter_input(INPUT_POST, 'name',  FILTER_DEFAULT) ?? '');
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');

// Validaciones básicas
$errors = [];
if (mb_strlen($name) < 2) {
    $errors[] = 'El nombre debe tener al menos 2 caracteres.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El email no tiene un formato válido.';
}

if (!empty($errors)) {
    // Devolver a index con los errores codificados en la URL
    $query = http_build_query(['errors' => $errors, 'name' => $name, 'email' => $email]);
    header("Location: index.php?$query");
    exit;
}

// ──────────────────────────────────────────────
// Insertar o ignorar si ya existe (UPSERT seguro)
// ──────────────────────────────────────────────
try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare(<<<SQL
        INSERT IGNORE INTO event_registrations (event_slug, email, name)
        VALUES (:slug, :email, :name)
    SQL);

    $stmt->execute([
        ':slug'  => EVENT_SLUG,
        ':email' => $email,
        ':name'  => $name,
    ]);

} catch (PDOException $e) {
    error_log('[register] Error PDO: ' . $e->getMessage());
    header('Location: index.php?errors[]=' . urlencode('Error al registrar. Inténtalo de nuevo.'));
    exit;
}

// ──────────────────────────────────────────────
// Emitir cookie firmada (7 días)
// ──────────────────────────────────────────────
emitSessionCookie($email, EVENT_SLUG);

// Redirigir al cuestionario
header('Location: quiz.php');
exit;
