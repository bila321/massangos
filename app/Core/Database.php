<?php
namespace Massango\Core;

class Database
{
    private static ?\PDO $instance = null;

    public static function getInstance(): \PDO
    {
        if (self::$instance === null) {
            if (!defined('DB_HOST')) {
                require_once dirname(__DIR__, 2) . '/includes/config.php';
            }
            self::$instance = new \PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }
        return self::$instance;
    }

    /**
     * Permite registar uma instancia PDO existente (ex: do db.php)
     * para evitar dupla ligacao a base de dados.
     */
    public static function setInstance(\PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    private function __construct() {}
    private function __clone() {}
}