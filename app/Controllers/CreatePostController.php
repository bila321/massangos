<?php
// app/Controllers/CreatePostController.php
namespace Massango\Controllers;

use Massango\Models\User;
use Massango\Services\PostService;

/**
 * Responsável exclusivamente por renderizar o formulário de criação.
 * O PostController existente (handle) trata o POST — não é tocado.
 */
class CreatePostController
{
    private \PDO $pdo;
    private int  $userId;
    private PostService $postService;

    public function __construct(\PDO $pdo, int $userId)
    {
        $this->pdo         = $pdo;
        $this->userId      = $userId;
        $this->postService = new PostService($pdo, $userId);
    }

    public function show(): void
    {
        if (!is_logged_in()) {
            set_message("Você precisa estar logado para publicar.", "danger");
            redirect(BASE_URL . 'login.php');
        }

        $user_data  = User::getUserById($this->pdo, $this->userId);
        $user_stats = $this->postService->getUserStats($this->userId);
        $rules      = $this->postService->getSaleRules($user_stats);

        // header.php e os seus includes (topbar, sidebar…) foram escritos
        // antes do MVC e ainda lêem $pdo como variável global.
        // Expor aqui mantém compatibilidade sem alterar os includes legados.
        $GLOBALS['pdo'] = $this->pdo;

        require_once __DIR__ . '/../../includes/views/post/create.view.php';
    }
}
