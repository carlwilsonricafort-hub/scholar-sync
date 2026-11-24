<?php
/**
 * Database.php
 * Singleton MySQLi connection provider.
 * All resource classes call Database::get() to obtain the shared connection.
 */
class Database
{
    private static ?mysqli $connection = null;

    public static function get(): mysqli
    {
        if (self::$connection === null) {
            self::$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if (self::$connection->connect_error) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed.',
                ]);
                exit();
            }

            self::$connection->set_charset('utf8mb4');
        }

        return self::$connection;
    }
}