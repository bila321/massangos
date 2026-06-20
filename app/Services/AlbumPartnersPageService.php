<?php
declare(strict_types=1);

namespace Massango\Services;

use Massango\Models\Album;
use Massango\Models\AlbumPartner;
use Massango\Models\User;
use PDO;

/**
 * AlbumPartnersPageService
 *
 * Encapsula a lógica de negócio da página de gestão de parceiros de álbum:
 * carregar álbum, parceiros, calcular percentagens e verificar permissões.
 * Não emite HTML nem headers.
 */
class AlbumPartnersPageService
{
    public function __construct(private PDO $pdo) {}

    /**
     * Carrega todos os dados necessários para a página.
     * Devolve null se o álbum não existir.
     *
     * @return array{
     *   album: array,
     *   owner_username: string,
     *   partners: array,
     *   is_owner: bool,
     *   is_partner: bool,
     *   user_partner_info: array|null,
     *   total_percentage: float,
     *   available_percentage: float
     * }|null
     */
    public function load(int $album_id, int $user_id): ?array
    {
        $album = Album::getAlbumById($this->pdo, $album_id);
        if (!$album) return null;

        $partners = AlbumPartner::getAlbumPartners($this->pdo, $album_id);

        $is_owner          = ((int)$album['user_id'] === $user_id);
        $is_partner        = false;
        $user_partner_info = null;

        foreach ($partners as $partner) {
            if ((int)$partner['user_id'] === $user_id) {
                $is_partner        = true;
                $user_partner_info = $partner;
                break;
            }
        }

        $total_percentage = 0.0;
        foreach ($partners as $partner) {
            if ($partner['status'] !== 'rejected') {
                $total_percentage += (float)$partner['percentage'];
            }
        }
        $available_percentage = 100 - $total_percentage;

        $owner_username = $is_owner
            ? 'Você'
            : '@' . (User::getUserById($this->pdo, $album['user_id'])['username'] ?? 'desconhecido');

        return [
            'album'                 => $album,
            'owner_username'        => $owner_username,
            'partners'              => $partners,
            'is_owner'              => $is_owner,
            'is_partner'            => $is_partner,
            'user_partner_info'     => $user_partner_info,
            'total_percentage'      => $total_percentage,
            'available_percentage'  => $available_percentage,
        ];
    }

    /**
     * Verifica se o utilizador pode visualizar a página
     * (é dono do álbum ou é parceiro).
     */
    public function canView(array $data): bool
    {
        return $data['is_owner'] || $data['is_partner'];
    }
}
