<?php

define('SECURE_ACCESS', true);

// Define o ambiente (apenas uma vez, aqui)
define('ENVIRONMENT', 'development'); // 'development' ou 'production'
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';

SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Controllers\FeedController;

// O FeedController::load() já garante is_logged_in() (com redirect) e
// devolve todos os dados que a view precisa, já enriquecidos e em batch.
// Fallback: se $pdo não está no escopo global (db.php incluído anteriormente
// dentro de uma função), recupera via singleton ou re-inclui db.php.
if (!isset($pdo)) {
    if (class_exists('\Massango\Core\Database')) {
        $pdo = \Massango\Core\Database::getInstance();
    } else {
        require __DIR__ . '/../includes/db.php';
        global $pdo;
    }
}
$feedController = new FeedController($pdo);
extract($feedController->load());

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/views/home/index.view.php';
require_once __DIR__ . '/../includes/footer.php';
