<?php

/**
 * public/wallet.php
 *
 * Entry point — responsabilidade única: bootstrap + arrancar o Controller.
 *
 * Lógica de negócio → app/Services/WalletService.php
 * Autenticação      → app/Controllers/WalletController.php (via Auth::requireAuth)
 * Templates HTML    → includes/views/wallet/wallet.view.php
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();

use Massango\Controllers\WalletController;
use Massango\Core\Database;

// ── Controller: autentica + carrega todos os dados ────────────────────────────
$data = (new WalletController(Database::getInstance()))->load();

// ── Desempacotar explicitamente (mais seguro que extract) ─────────────────────
$balance            = $data['balance'];
$earned             = $data['earned'];
$spent              = $data['spent'];
$partner_revenue    = $data['partner_revenue'];
$total_transactions = $data['total_transactions'];
$transactions       = $data['transactions'];

// ── Render ────────────────────────────────────────────────────────────────────
$pageTitle = 'Carteira';
require_once __DIR__ . '/../includes/header.php';
require       __DIR__ . '/../includes/views/wallet/wallet.view.php';
require_once  __DIR__ . '/../includes/footer.php';
