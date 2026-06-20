<?php

/**
 * public/settings.php
 *
 * Ponto de entrada — bootstrap + Controller.
 *
 * Lógica de negócio → app/Controllers/SettingsController.php  (já existente)
 * Templates HTML    → includes/views/settings/
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

SecurityManager::initSecurity();

if (session_status() === PHP_SESSION_NONE) session_start();

// Auth guard
if (!is_logged_in()) {
    set_message("Você precisa estar logado para acessar as configurações.", "danger");
    redirect(BASE_URL . 'login.php');
}

require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Controllers\FeedController;
use Massango\Models\User;

// ── Dados do utilizador via FeedController ────────────────────────────────────
$data = (new FeedController($pdo))->load();
extract($data);

// ── Dados extra para esta página ──────────────────────────────────────────────
$blocked_users = User::getBlockedUsers($pdo, $current_user_id);

// ── Render ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
require       __DIR__ . '/../includes/views/settings/settings.view.php';
require_once  __DIR__ . '/../includes/footer.php';
