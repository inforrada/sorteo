<?php
declare(strict_types=1);

/**
 * generate_hash.php
 * Utilidad de línea de comandos para generar el hash bcrypt de una contraseña.
 * Uso: php generate_hash.php "mi_nueva_contraseña"
 *
 * ⚠️  ELIMINAR O PROTEGER este archivo en producción.
 *     Solo debe usarse localmente para actualizar ADMIN_PASS en el .env
 */

// Solo permitir ejecución por CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos.' . PHP_EOL);
}

$password = $argv[1] ?? null;

if ($password === null || $password === '') {
    fwrite(STDERR, 'Uso: php generate_hash.php "tu_contraseña"' . PHP_EOL);
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

echo PHP_EOL;
echo '✅ Hash generado correctamente.' . PHP_EOL;
echo PHP_EOL;
echo 'Copia esta línea en tu archivo .env:' . PHP_EOL;
echo "ADMIN_PASS={$hash}" . PHP_EOL;
echo PHP_EOL;
