<?php
declare(strict_types=1);

/**
 * auth.php
 * Funciones puras de gestión de cookies firmadas con HMAC-SHA256.
 * Este archivo NO contiene lógica de redirección ni efectos secundarios;
 * puede ser incluido de forma segura desde cualquier punto de entrada
 * (index.php, quiz.php, register.php…).
 */

// Requiere config para acceder a COOKIE_SECRET y COOKIE_EXPIRES
require_once __DIR__ . '/config.php';

// ──────────────────────────────────────────────
// Funciones de gestión de cookies firmadas
// ──────────────────────────────────────────────

/**
 * Genera el payload de la cookie en formato JSON y lo firma con HMAC-SHA256.
 * Formato del valor: base64url(payload).hmac_hex
 */
function createSignedCookie(string $email, string $eventSlug): string
{
    $payload = json_encode([
        'email' => $email,
        'slug'  => $eventSlug,
        'exp'   => time() + COOKIE_EXPIRES,
    ], JSON_THROW_ON_ERROR);

    $encodedPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $signature      = hash_hmac('sha256', $encodedPayload, COOKIE_SECRET);

    return $encodedPayload . '.' . $signature;
}

/**
 * Verifica y decodifica la cookie firmada.
 * Devuelve el array de datos o null si la firma es inválida o ha expirado.
 */
function verifySignedCookie(string $cookieValue): ?array
{
    $parts = explode('.', $cookieValue, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$encodedPayload, $signature] = $parts;

    // Verificación segura contra timing attacks
    $expectedSignature = hash_hmac('sha256', $encodedPayload, COOKIE_SECRET);
    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }

    try {
        $payload = json_decode(
            base64_decode(strtr($encodedPayload, '-_', '+/')),
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );
    } catch (\JsonException) {
        return null;
    }

    // Comprobar expiración
    if (!isset($payload['exp']) || $payload['exp'] < time()) {
        return null;
    }

    return $payload;
}

/**
 * Emite la cookie de sesión hacia el navegador.
 */
function emitSessionCookie(string $email, string $eventSlug): void
{
    $cookieValue = createSignedCookie($email, $eventSlug);
    $expires     = time() + COOKIE_EXPIRES;

    setcookie(COOKIE_NAME, $cookieValue, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
