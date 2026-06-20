<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Models\Post;

class EditPostController extends AbstractEditController
{
    protected function itemType(): string
    {
        return 'post';
    }

    protected function fetchItem(int $id): ?array
    {
        return Post::getPostById($this->pdo, $id) ?: null;
    }

    protected function notLoggedInMessage(): string
    {
        return "Você precisa estar logado para editar publicações.";
    }

    protected function notFoundMessage(): string
    {
        return "Publicação não encontrada.";
    }

    protected function noPermissionMessage(): string
    {
        return "Você não tem permissão para editar esta publicação.";
    }

    protected function viewPath(): string
    {
        return __DIR__ . '/../../includes/views/edit/edit_post.view.php';
    }
}
