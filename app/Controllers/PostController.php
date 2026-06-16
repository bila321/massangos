<?php
// app/Controllers/PostController.php
namespace Massango\Controllers;

use Massango\Services\PostService;

class PostController
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

    public function handle(array $post, array $files): array
    {
        $action = $post['action'] ?? '';

        if ($action === 'delete_post') {
            $postId = (int)($post['post_id'] ?? 0);
            $stmt   = $this->pdo->prepare(
                "DELETE FROM posts WHERE id = ? AND user_id = ?"
            );
            $ok = $stmt->execute([$postId, $this->userId]);
            return $ok
                ? ['success' => true,  'message' => 'Post apagado.']
                : ['success' => false, 'message' => 'Erro ao apagar.'];
        }

        if (isset($files['image']) && !isset($files['post_image'])) {
            $files['post_image'] = $files['image'];
        }

        return $this->postService->createPost($post, $files);
    }
}
