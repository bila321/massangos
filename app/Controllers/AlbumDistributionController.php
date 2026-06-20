<?php

namespace Massango\Controllers;

use Massango\Models\Album;
use Massango\Models\SalesReport;
use PDO;
use RuntimeException;

/**
 * AlbumDistributionController
 *
 * Responsabilidade única: validar acesso e orquestrar os Models
 * Album + SalesReport para o histórico de distribuição de um álbum.
 *
 * Não faz SQL directo — delega sempre aos Models existentes.
 */
class AlbumDistributionController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Carrega todos os dados do histórico de distribuição.
     *
     * @throws RuntimeException Se o álbum não existir ou o utilizador não tiver permissão.
     *                          O entry point decide como tratar (redirect + mensagem).
     */
    public function load(int $albumId, int $userId): array
    {
        if ($albumId <= 0) {
            throw new RuntimeException('Álbum não especificado.');
        }

        $album = Album::getAlbumById($this->pdo, $albumId);

        if (!$album || (int) $album['user_id'] !== $userId) {
            throw new RuntimeException('Você não tem permissão para ver este histórico.');
        }

        return [
            'album'               => $album,
            'distribution_history' => SalesReport::getCreatorSalesReport($this->pdo, $albumId, 100),
            'album_stats'         => SalesReport::getAlbumSalesStats($this->pdo, $albumId),
            'partner_performance' => SalesReport::getAlbumPartnerPerformance($this->pdo, $albumId),
        ];
    }
}
