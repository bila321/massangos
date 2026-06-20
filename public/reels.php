<?php

/**
 * public/reels.php
 *
 * Entry point — responsabilidade única: bootstrap + arrancar o Controller.
 *
 * Lógica de negócio → app/Controllers/ReelsController.php
 * Templates HTML    → includes/views/reels/reels.view.php
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../app/bootstrap.php';

use Massango\Controllers\ReelsController;
use Massango\Core\Database;

// reels usa layout próprio, sem .feed-container
$hide_feed_container = true;
$hide_sidebar         = false;

// ── Controller: toda a lógica de negócio ─────────────────────────────────────
$data = (new ReelsController(Database::getInstance()))->load($_GET);

// ── Desempacotar explicitamente (mais seguro que extract) ─────────────────────
$current_user_id     = $data['current_user_id'];
$is_admin             = $data['is_admin'];
$logged_in_user_data  = $data['logged_in_user_data'];

$filter_search        = $data['filter_search'];
$filter_sale           = $data['filter_sale'];
$filter_sensitive      = $data['filter_sensitive'];
$filter_duration       = $data['filter_duration'];
$filter_price_min      = $data['filter_price_min'];
$filter_price_max      = $data['filter_price_max'];
$filter_quality        = $data['filter_quality'];
$filter_sort           = $data['filter_sort'];

$reels                 = $data['reels'];
$csrf_token            = $data['csrf_token'];
$active_chip           = $data['active_chip'];

$total                 = $data['total'];
$total_pages           = $data['total_pages'];
$page                  = $data['page'];
$per_page              = $data['per_page'];

// ── Render ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/views/reels/reels.view.php';
require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/reels_lightbox.php';
