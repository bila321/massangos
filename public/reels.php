<?php
/**
 * public/reels.php
 *
 * Entry point — responsabilidade única: bootstrap + arrancar o Controller.
 *
 * Lógica de negócio → app/Controllers/ReelsController.php
 * Templates HTML    → includes/views/reels/reels.view.php
 *
 * NOTA (2026-06-21): a view deixou de mostrar grid/filtros — abre
 * directamente no lightbox. O Controller continua a aceitar $_GET por
 * retrocompatibilidade (ex: se algures ainda existir um link com
 * ?sale=1), mas a view actual ignora esses filtros. Quando os filtros
 * forem movidos para dentro do lightbox (sidebar), este Controller
 * provavelmente precisará de ajustes — não é necessário agora.
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../app/bootstrap.php';

use Massango\Controllers\ReelsController;
use Massango\Core\Database;

// reels usa layout próprio, sem .feed-container
$hide_feed_container = true;
$hide_sidebar        = false;

// ── Controller: toda a lógica de negócio ─────────────────────────────────────
$data = (new ReelsController(Database::getInstance()))->load($_GET);

// ── Desempacotar explicitamente (mais seguro que extract) ─────────────────────
// Só as variáveis que reels.view.php (versão "direct open") realmente usa.
$current_user_id    = $data['current_user_id'];
$logged_in_user_data = $data['logged_in_user_data'];
$reels               = $data['reels'];
$csrf_token          = $data['csrf_token'];

// ── Render ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/views/reels/reels.view.php';
require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/reels_lightbox.php';
