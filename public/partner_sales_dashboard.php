<?php

/**
 * public/partner_sales_dashboard.php
 *
 * Entry point — responsabilidade única: bootstrap + arrancar o Controller.
 *
 * Lógica de negócio → app/Controllers/PartnerSalesDashboardController.php
 *                      (orquestra o Model existente: SalesReport)
 * Templates HTML    → includes/views/partner_dashboard/partner_sales_dashboard.view.php
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

use Massango\Controllers\PartnerSalesDashboardController;
use Massango\Core\Database;

if (!is_logged_in()) {
    set_message("Você precisa estar logado.", "danger");
    redirect(BASE_URL . 'login.php');
    exit();
}

$userId       = get_current_user_id();
$periodInput  = $_GET['period'] ?? 'all';

// ── Controller: valida período + carrega dados via Model existente ───────────
$controller = new PartnerSalesDashboardController(Database::getInstance());
$data       = $controller->load($userId, $periodInput);

// ── Desempacotar explicitamente (mais seguro que extract) ─────────────────────
$period       = $data['period'];
$stats        = $data['stats'];
$sales_report = $data['sales_report'];
$top_albums   = $data['top_albums'];
$top_creators = $data['top_creators'];

// ── Render ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
require       __DIR__ . '/../includes/views/partner_dashboard/partner_sales_dashboard.view.php';
require_once  __DIR__ . '/../includes/footer.php';
