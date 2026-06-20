<?php
// app/Controllers/NotificationController.php

namespace Massango\Controllers;

use Massango\Services\NotificationService;

class NotificationController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function handle(): void
    {
        // ===== AUTENTICAÇÃO =====
        if (!is_logged_in()) {
            set_message('Você precisa estar logado para ver suas notificações.', 'danger');
            redirect(BASE_URL . 'login.php');
        }

        $current_user_id = (int) get_current_user_id();

        // ===== DADOS =====
        $service       = new NotificationService($this->pdo);
        $notifications = $service->getForUser($current_user_id);

        // ===== HEADER (não em modo AJAX) =====
        $is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
        if (!$is_ajax) {
            require_once __DIR__ . '/../../includes/header.php';
        }

        // ===== VIEW =====
        require __DIR__ . '/../../includes/views/notifications/notifications.view.php';

        // ===== FOOTER (não em modo AJAX) =====
        if (!$is_ajax) {
            require_once __DIR__ . '/../../includes/footer.php';
        }
    }
}
