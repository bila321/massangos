<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Services\SearchService;
use PDO;

/**
 * SearchController
 *
 * Valida o request, delega ao SearchService e passa dados à view.
 * Não contém SQL nem HTML.
 */
class SearchController
{
    private SearchService $service;

    public function __construct(private PDO $pdo)
    {
        $this->service = new SearchService($pdo);
    }

    public function show(): void
    {
        // 1. Input sanitizado
        $query        = trim($_GET['q'] ?? '');
        $type         = in_array($_GET['type']  ?? '', SearchService::ALLOWED_TYPES, true)
            ? $_GET['type']
            : 'all';
        $price_filter = in_array($_GET['price'] ?? '', SearchService::ALLOWED_PRICES, true)
            ? $_GET['price']
            : 'all';

        // 2. Dados
        $current_user_id = get_current_user_id();
        $result           = $this->service->search($query, $type, $price_filter, $current_user_id);

        $user_results = $result['users'];
        $results      = $result['items'];

        // 3. Render
        require_once __DIR__ . '/../../includes/header.php';
        require       __DIR__ . '/../../includes/views/search/search.view.php';
        require_once  __DIR__ . '/../../includes/footer.php';
    }
}
