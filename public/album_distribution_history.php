<?php
/**
 * public/album_distribution_history.php
 *
 * Entry point — responsabilidade única: bootstrap + arrancar o Controller.
 *
 * Lógica de negócio → app/Controllers/AlbumDistributionController.php
 *                      (orquestra Models existentes: Album, SalesReport)
 * Templates HTML    → includes/views/album/distribution_history.view.php
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../app/bootstrap.php';

use Massango\Controllers\AlbumDistributionController;
use Massango\Core\Database;

if (!is_logged_in()) {
    set_message("Você precisa estar logado.", "danger");
    redirect(BASE_URL . 'login.php');
    exit();
}

$userId  = get_current_user_id();
$albumId = (int) ($_GET['album_id'] ?? 0);

// ── Controller: valida permissão + carrega dados via Models existentes ────────
try {
    $controller = new AlbumDistributionController(Database::getInstance());
    $data       = $controller->load($albumId, $userId);
} catch (\RuntimeException $e) {
    set_message($e->getMessage(), "danger");
    redirect(BASE_URL . 'index.php');
    exit();
}

// ── Desempacotar explicitamente (mais seguro que extract) ─────────────────────
$album                = $data['album'];
$distribution_history = $data['distribution_history'];
$album_stats          = $data['album_stats'];
$partner_performance  = $data['partner_performance'];
$album_id             = $albumId; // usado na view (link "Voltar")

// ── Render ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
require       __DIR__ . '/../includes/views/album/distribution_history.view.php';
require_once  __DIR__ . '/../includes/footer.php';
