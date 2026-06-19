<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Services\SavedService;
use PDO;

/**
 * SavedController
 *
 * Valida o request, delega ao SavedService e passa dados à view.
 * Não contém SQL nem HTML.
 */
class SavedController
{
    private SavedService $service;

    public function __construct(private PDO $pdo)
    {
        $this->service = new SavedService($pdo);
    }

    public function show(): void
    {
        // 1. Auth
        if (!is_logged_in()) {
            redirect(BASE_URL . 'login.php');
        }

        // 2. Input sanitizado
        $filter = $_GET['type'] ?? 'all';
        if (!in_array($filter, SavedService::ALLOWED_FILTERS, true)) {
            $filter = 'all';
        }
        $page = max(1, (int)($_GET['page'] ?? 1));

        // 3. CSRF
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrf_token = $_SESSION['csrf_token'];

        // 4. Dados
        $current_user_id = get_current_user_id();
        $data            = $this->service->load($current_user_id, $filter, $page);
        $is_admin        = isset($_SESSION['admin_id']);

        // 5. Render
        $pdo = $this->pdo; // legacy includes esperam $pdo no escopo local

        extract($data); // items, total, total_pages, filter, page, ai_map

        require_once __DIR__ . '/../../includes/header.php';
        require      __DIR__ . '/../../includes/views/saved/saved.view.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }
}
