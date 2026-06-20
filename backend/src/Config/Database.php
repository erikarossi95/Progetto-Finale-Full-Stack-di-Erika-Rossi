<?php

declare(strict_types=1);

namespace Snaply\Config;

use PDO;
use PDOException;
use RuntimeException;
use Snaply\Core\Env;

/**
 * Connessione PDO a MySQL come singleton.
 * Una sola istanza per richiesta, riusata da tutti i model.
 */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
        // Costruttore privato: si usa solo Database::connection().
    }

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = Env::get('DB_HOST', 'localhost');
        $name = Env::get('DB_NAME', 'snaply');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');

        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";

        try {
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Errore generico verso l'esterno; il dettaglio resta nei log.
            error_log('DB connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed');
        }

        return self::$instance;
    }
}
