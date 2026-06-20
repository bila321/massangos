<?php
// app/Controllers/CreatePostController.php

namespace Massango\Controllers;

use Massango\Services\PostService;

class CreatePostController
{
    private \PDO $pdo;
    private int $userId;

    public function __construct(\PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    // ── GET: renderizar o formulário ──────────────────────────────────
    public function show(): void
    {
        if (!is_logged_in()) {
            redirect(BASE_URL . 'login.php');
        }

        $postService = new PostService($this->pdo, $this->userId);

        $user_data  = \Massango\Models\User::getUserById($this->pdo, $this->userId);
        $user_stats = $postService->getUserStats($this->userId);
        $rules      = $postService->getSaleRules($user_stats);

        // header/footer ficam aqui — nunca na view
        $hide_feed_container = true;
        require_once __DIR__ . '/../../includes/header.php';
        require __DIR__ . '/../../includes/views/post/create_view.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }

    // ── POST: processar submissão AJAX ────────────────────────────────
    public function handle(array $post, array $files): array
    {
        if (!is_logged_in()) {
            return ['success' => false, 'message' => 'Não autorizado.'];
        }

        $type = $post['post_type'] ?? '';

        // album → actions/album.php  |  video → actions/video.php
        // Só text e photo passam por actions/post.php
        return match ($type) {
            'text'  => $this->handleText($post),
            'photo' => $this->handlePhoto($post, $files),
            default => ['success' => false, 'message' => 'Tipo de publicação inválido.'],
        };
    }

    // ─── Handlers por tipo (delegam ao PostService) ───────────────────

    private function handleText(array $post): array
    {
        $content = trim(strip_tags($post['content'] ?? ''));
        if (empty($content)) {
            return ['success' => false, 'message' => 'O conteúdo não pode estar vazio.'];
        }

        $service = new PostService($this->pdo, $this->userId);
        return $service->createPost(array_merge($post, ['content' => $content, 'post_type' => 'text']), []);
    }

    private function handlePhoto(array $post, array $files): array
    {
        if (empty($files['image']['tmp_name'])) {
            return ['success' => false, 'message' => 'Seleccione uma imagem para publicar.'];
        }

        // PostService espera $files['post_image'] — remapear o campo
        $mappedFiles = ['post_image' => $files['image']];

        $service = new PostService($this->pdo, $this->userId);
        return $service->createPost(
            array_merge($post, ['post_type' => 'photo']),
            $mappedFiles
        );
    }

    private function handleAlbum(array $post, array $files): array
    {
        if (empty($files['images']['name'][0])) {
            return ['success' => false, 'message' => 'Seleccione pelo menos uma foto para o álbum.'];
        }

        $service = new PostService($this->pdo, $this->userId);
        return $service->createPost(array_merge($post, ['post_type' => 'album']), $files);
    }

    private function handleVideo(array $post, array $files): array
    {
        if (empty($files['video']['name'])) {
            return ['success' => false, 'message' => 'Seleccione um ficheiro de vídeo.'];
        }

        $service = new PostService($this->pdo, $this->userId);
        return $service->createPost(array_merge($post, ['post_type' => 'video']), $files);
    }
}
