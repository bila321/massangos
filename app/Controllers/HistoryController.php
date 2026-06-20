<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Models\User;
use Massango\Services\HistoryService;
use PDO;

/**
 * HistoryController
 *
 * Valida o request, delega ao HistoryService e passa dados à view.
 * Não contém SQL nem HTML.
 */
class HistoryController
{
    private HistoryService $service;

    public function __construct(private PDO $pdo)
    {
        $this->service = new HistoryService($pdo);
    }

    public function show(): void
    {
        // 1. Auth
        if (!is_logged_in()) {
            set_message("Você precisa estar logado para acessar o massangos.", "danger");
            redirect(BASE_URL . 'login.php');
        }

        $current_user_id = get_current_user_id();
        $is_admin        = isset($_SESSION['admin_id']);

        // 2. Dados do utilizador logado
        $logged_in_user_data = User::getUserById($this->pdo, $current_user_id) ?? [];

        // 3. Input sanitizado
        $page = max(1, (int)($_GET['page'] ?? 1));
        $filter = in_array($_GET['filter'] ?? '', HistoryService::ALLOWED_FILTERS, true)
            ? $_GET['filter']
            : 'all';

        // 4. Dados
        $data = $this->service->load($current_user_id, $filter, $page);
        $data['filter'] = $filter;
        $data['page']   = $page;

        // 5. Render
        $pdo = $this->pdo; // legacy includes esperam $pdo no escopo local
        extract($data);    // reactions, total, total_pages, db_error, db_error_detail, filter, page

        require_once __DIR__ . '/../../includes/header.php';
        require       __DIR__ . '/../../includes/views/history/history.view.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }
}
