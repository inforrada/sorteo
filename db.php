<?php
declare(strict_types=1);

/**
 * db.php
 * Proporciona una conexión PDO singleton centralizada.
 * Todas las consultas usan sentencias preparadas (Prepared Statements).
 */

require_once __DIR__ . '/config.php';

final class Database
{
    private static ?PDO $instance = null;

    // Constructor privado: no se puede instanciar directamente
    private function __construct() {}

    /**
     * Devuelve la instancia singleton de PDO.
     * Si no existe, la crea con los parámetros de configuración.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,  // Usar sentencias preparadas nativas
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // En producción, no exponer el mensaje de error al usuario
                error_log('[DB] Error de conexión: ' . $e->getMessage());
                throw new RuntimeException('No se pudo conectar a la base de datos. Revisa la configuración.');
            }
        }

        return self::$instance;
    }

    /**
     * Crea la base de datos y las tablas si no existen (idempotente).
     *
     * Flujo:
     *   1. Conecta a MySQL SIN especificar dbname (así no falla si la BD no existe).
     *   2. Ejecuta CREATE DATABASE IF NOT EXISTS.
     *   3. Resetea el singleton para que la siguiente llamada a getInstance()
     *      se conecte ya con el dbname correcto en el DSN.
     *   4. Crea las tablas.
     */
    public static function migrate(): void
    {
        // ── Paso 1: conexión temporal sin dbname ───────────────────────────
        $dsnNoDB = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            DB_HOST,
            DB_PORT
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $bootstrapPdo = new PDO($dsnNoDB, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[DB] Error de conexión sin dbname: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar al servidor MySQL. Comprueba que XAMPP está en marcha y que las credenciales en .env son correctas.');
        }

        // ── Paso 2: crear la base de datos si no existe ────────────────────
        // Usamos backticks y sustituimos manualmente porque esta consulta
        // no admite parámetros (CREATE DATABASE no acepta placeholders).
        $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', DB_NAME);
        $bootstrapPdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // ── Paso 3: resetear el singleton con la conexión completa (con dbname)
        self::$instance = null;
        $pdo = self::getInstance(); // ya incluye dbname en el DSN

        // ── Paso 4: crear las tablas ───────────────────────────────────────
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS event_registrations (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                event_slug VARCHAR(100) NOT NULL,
                email      VARCHAR(255) NOT NULL,
                name       VARCHAR(150) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_event_email (event_slug, email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS user_answers (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                event_slug      VARCHAR(100) NOT NULL,
                email           VARCHAR(255) NOT NULL,
                question_id     INT NOT NULL,
                selected_answer VARCHAR(50) NOT NULL,
                is_correct      BOOLEAN NOT NULL,
                answered_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_question_per_event (event_slug, email, question_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }
}
