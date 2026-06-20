<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Models\Album;
use Massango\Models\Photo;

/**
 * EditAlbumController
 *
 * Igual à base, mas precisa de fornecer $photos extra à view
 * (lista de fotos do álbum). Usa o hook extraViewData() em vez
 * de sobrescrever render() inteiro.
 */
class EditAlbumController extends AbstractEditController
{
    protected function itemType(): string
    {
        return 'album';
    }

    protected function fetchItem(int $id): ?array
    {
        return Album::getAlbumById($this->pdo, $id) ?: null;
    }

    protected function notLoggedInMessage(): string
    {
        return "Você precisa estar logado para editar álbuns.";
    }

    protected function notFoundMessage(): string
    {
        return "Álbum não encontrado.";
    }

    protected function noPermissionMessage(): string
    {
        return "Você não tem permissão para editar este álbum.";
    }

    protected function viewPath(): string
    {
        return __DIR__ . '/../../includes/views/edit/edit_album.view.php';
    }

    /**
     * @return array{photos: array}
     */
    protected function extraViewData(): array
    {
        return [
            'photos' => Photo::getPhotosByAlbumId($this->pdo, (int)$this->item['id']),
        ];
    }
}
