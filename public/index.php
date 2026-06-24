<?php

/**
 * public/index.php
 *
 * Entry point — responsabilidade única: bootstrap + arrancar o Controller.
 *
 * Lógica de negócio → app/Controllers/FeedController.php
 * Templates HTML    → includes/views/home/index.view.php
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';

use Massango\Controllers\FeedController;
use Massango\Core\Database;

// O FeedController::load() já garante is_logged_in() (com redirect) e
// devolve todos os dados que a view precisa, já enriquecidos e em batch.
$data = (new FeedController(Database::getInstance()))->load();

// ── Desempacotar explicitamente (mais seguro que extract) ─────────────────────
$current_user_id            = $data['current_user_id'];
$feedItems                  = $data['feedItems'];
$notifications               = $data['notifications'];
$logged_in_user_data         = $data['logged_in_user_data'];
$user_data                   = $data['user_data'];
$logged_in_user_profile_pic  = $data['logged_in_user_profile_pic'];
$suggested_users             = $data['suggested_users'];
$recent_albums               = $data['recent_albums'];
$saved_ids                   = $data['saved_ids'];
$csrf_token                  = $data['csrf_token'];

// ── Render ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/views/home/index.view.php';
require_once __DIR__ . '/../includes/footer.php';
