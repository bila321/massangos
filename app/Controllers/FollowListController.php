<?php
// app/Controllers/FollowListController.php

namespace Massango\Controllers;

use Massango\Models\User;

class FollowListController
{
    private \PDO $pdo;

    /** 'followers' ou 'following' */
    private string $mode;

    public function __construct(\PDO $pdo, string $mode)
    {
        $this->pdo  = $pdo;
        $this->mode = $mode;
    }

    public function handle(): void
    {
        // ===== POST deve ser processado ANTES do output =====
        // (original processava depois do HTML — bug de headers already sent)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleFollowToggle();
            // handleFollowToggle() termina sempre com redirect()
        }

        // ===== PARÂMETRO =====
        $user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$user_id) {
            set_message('ID de utilizador não especificado.', 'danger');
            redirect(BASE_URL);
        }

        // ===== UTILIZADOR DO PERFIL =====
        $profile_user = User::getUserById($this->pdo, $user_id);
        if (!$profile_user) {
            set_message('Utilizador não encontrado.', 'danger');
            redirect(BASE_URL);
        }

        // ===== VERIFICAR BLOQUEIO =====
        $current_user_id = get_current_user_id();
        if ($current_user_id && $current_user_id !== (int)$user_id) {
            $blocked_by_me = User::isBlocking($this->pdo, $current_user_id, $user_id);
            $blocked_me    = User::isBlocking($this->pdo, $user_id, $current_user_id);
            if ($blocked_by_me || $blocked_me) {
                set_message('Acesso restrito.', 'danger');
                redirect(BASE_URL);
            }
        }

        // ===== LISTA =====
        $users = $this->mode === 'followers'
            ? User::getFollowersList($this->pdo, $user_id)
            : User::getFollowingList($this->pdo, $user_id);

        // ===== RENDER =====
        require_once __DIR__ . '/../../includes/header.php';

        $mode         = $this->mode;
        $pdo          = $this->pdo;
        require __DIR__ . '/../../includes/views/follow_list/follow_list.view.php';

        require_once __DIR__ . '/../../includes/footer.php';
    }

    // ─── POST: seguir / deixar de seguir ─────────────────────────────
    private function handleFollowToggle(): never
    {
        $current_user_id = get_current_user_id();
        $target_id       = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
        $action          = $_POST['action'] ?? '';
        $redirect_url    = $_POST['redirect_url'] ?? BASE_URL;

        if ($current_user_id && $target_id && $current_user_id !== $target_id) {
            $target = User::getUserById($this->pdo, $target_id);
            $name   = $target ? htmlspecialchars($target['username']) : 'utilizador';

            if ($action === 'follow') {
                User::followUser($this->pdo, $current_user_id, $target_id)
                    ? set_message("Começaste a seguir {$name}!", 'success')
                    : set_message('Erro ao seguir utilizador.', 'danger');
            } elseif ($action === 'unfollow') {
                User::unfollowUser($this->pdo, $current_user_id, $target_id)
                    ? set_message("Deixaste de seguir {$name}.", 'info')
                    : set_message('Erro ao deixar de seguir utilizador.', 'danger');
            }
        }

        redirect($redirect_url);
    }
}
