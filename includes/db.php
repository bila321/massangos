<?php

/**
 * includes/db.php
 * Conexão PDO centralizada para o projecto Massangos.
 *
 * Carregue este ficheiro através de app/bootstrap.php (nunca directamente):
 *   require_once __DIR__ . '/../app/bootstrap.php';
 *
 * A variável $pdo fica disponível globalmente.
 * O bootstrap.php regista esta instância em Database::setInstance($pdo)
 * para que Controllers/Services usem sempre Database::getInstance()
 * sem abrir uma segunda ligação.
 *
 * SCHEMA: gerir via database/migrations/ — nunca criar tabelas aqui.
 */

defined('SECURE_ACCESS') or die('Acesso directo não permitido.');

/* ── Credenciais ────────────────────────────────────────────
   Defina estas constantes em includes/config.php (ou num .env).
   Nunca escreva senhas aqui directamente em produção.
   ────────────────────────────────────────────────────────── */
if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_PORT'))    define('DB_PORT',    '3306');
if (!defined('DB_NAME'))    define('DB_NAME',    'amassangos');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');        // altere em produção
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/* ── DSN ────────────────────────────────────────────────── */
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    DB_HOST,
    DB_PORT,
    DB_NAME,
    DB_CHARSET
);

/* ── Opções PDO ─────────────────────────────────────────── */
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

/* ── Ligação ────────────────────────────────────────────── */
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
} catch (PDOException $e) {
    error_log('[Massangos DB] Falha na ligação: ' . $e->getMessage());

    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die('<pre style="color:red;font-family:monospace">
<b>Erro de base de dados (modo desenvolvimento)</b>
' . htmlspecialchars($e->getMessage()) . '
</pre>');
    }

    http_response_code(503);
    die('Serviço temporariamente indisponível. Tente novamente mais tarde.');
}

/* ── Registo no Singleton ─────────────────────────────────── */

// Garante que $pdo fique acessível globalmente mesmo se este ficheiro
// for incluído dentro de uma função (escopo local).
global $pdo;

// Regista também no singleton para controllers/services usarem Database::getInstance()
if (isset($pdo) && class_exists('\Massango\Core\Database')) {
    \Massango\Core\Database::setInstance($pdo);
}
