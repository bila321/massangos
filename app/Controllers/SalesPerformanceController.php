<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Services\SalesPerformanceService;
use PDO;

/**
 * SalesPerformanceController
 *
 * Valida o request, delega ao Service e passa dados à view.
 * Não contém SQL nem HTML.
 */
class SalesPerformanceController
{
    private SalesPerformanceService $service;

    public function __construct(private PDO $pdo)
    {
        $this->service = new SalesPerformanceService($pdo);
    }

    public function show(): void
    {
        // 1. Auth
        if (!is_logged_in()) {
            redirect(BASE_URL . 'login.php');
        }

        // 2. Input sanitizado
        $period = $_GET['period'] ?? 'all';
        if (!in_array($period, SalesPerformanceService::ALLOWED_PERIODS, true)) {
            $period = 'all';
        }

        $filter_type = isset($_GET['type']) ? (string)$_GET['type'] : null;
        $filter_id   = isset($_GET['id'])   ? (int)$_GET['id']    : null;

        // 3. Dados
        $user_id = get_current_user_id();
        $data    = $this->service->load($user_id, $period, $filter_type, $filter_id);

        // 4. Render
        $pdo = $this->pdo; // legacy includes esperam $pdo no escopo local
        extract($data);    // stats, chart_*, item_sales, max_sales, top_buyer, commission_rate

        $pageTitle = 'Performance de Vendas';

        require_once __DIR__ . '/../../includes/header.php';
        require      __DIR__ . '/../../includes/views/sales/sales_performance.view.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }
}
