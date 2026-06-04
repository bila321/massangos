<?php
/**
 * includes/db.php
 * Mantem $pdo global para compatibilidade com codigo existente.
 * Database::getInstance() reutiliza a mesma ligacao via singleton.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Regista a mesma instancia no singleton Database
    // para que Massango\Core\Database::getInstance() reutilize esta ligacao
    \Massango\Core\Database::setInstance($pdo);

} catch (PDOException $e) {
    error_log("Erro PDO: " . $e->getMessage());
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Erro de conexao: " . $e->getMessage());
    } else {
        die("Erro ao conectar com o banco de dados. Tente novamente mais tarde.");
    }
}

global $pdo;

function executeQuery(string $sql, array $params = []): \PDOStatement
{
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erro na query: " . $e->getMessage() . " SQL: " . $sql);
        throw new Exception("Erro na operacao do banco de dados");
    }
}

function fetchOne(string $sql, array $params = []): array|false
{
    return executeQuery($sql, $params)->fetch();
}

function fetchAll(string $sql, array $params = []): array
{
    return executeQuery($sql, $params)->fetchAll();
}

function insertAndGetId(string $sql, array $params = []): string|false
{
    global $pdo;
    executeQuery($sql, $params);
    return $pdo->lastInsertId();
}

function countRecords(string $sql, array $params = []): mixed
{
    return executeQuery($sql, $params)->fetchColumn();
}

function recordExists(string $table, array $conditions = []): bool
{
    $sql    = "SELECT COUNT(*) FROM `{$table}`";
    $params = [];
    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $column => $value) {
            $where[]  = "`{$column}` = ?";
            $params[] = $value;
        }
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    return (int)countRecords($sql, $params) > 0;
}

function executeTransaction(callable $callback): mixed
{
    global $pdo;
    try {
        $pdo->beginTransaction();
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro na transacao: " . $e->getMessage());
        throw $e;
    }
}