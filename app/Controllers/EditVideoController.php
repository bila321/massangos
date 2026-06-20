<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Models\Video;

class EditVideoController extends AbstractEditController
{
    protected function itemType(): string
    {
        return 'video';
    }

    protected function fetchItem(int $id): ?array
    {
        return Video::getVideoById($this->pdo, $id) ?: null;
    }

    protected function notLoggedInMessage(): string
    {
        return "Você precisa estar logado para editar vídeos.";
    }

    protected function notFoundMessage(): string
    {
        return "Vídeo não encontrado.";
    }

    protected function noPermissionMessage(): string
    {
        return "Você não tem permissão para editar este vídeo.";
    }

    protected function viewPath(): string
    {
        return __DIR__ . '/../../includes/views/edit/edit_video.view.php';
    }
}
