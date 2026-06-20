<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Services\UsersListService;
use PDO;

/**
 * UsersListController
 *
 * Detecta modo AJAX/modal, delega ao Service e passa dados à view.
 * Não contém SQL nem HTML.
 */
class UsersListController
{
    private UsersListService $service;
    private bool $is_ajax;

    public function __construct(private PDO $pdo)
    {
        $this->service = new UsersListService($pdo);
        $this->is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
    }

    public function show(): void
    {
        if (!$this->is_ajax) {
            require_once __DIR__ . '/../../includes/header.php';
        }

        $users   = $this->service->getTopUsers();
        $is_ajax = $this->is_ajax;

        require __DIR__ . '/../../includes/views/users_list/users_list.view.php';

        if (!$this->is_ajax) {
            require_once __DIR__ . '/../../includes/footer.php';
        }
    }
}
