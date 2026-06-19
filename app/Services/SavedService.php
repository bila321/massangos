<?php
declare(strict_types=1);

namespace Massango\Services;

use PDO;

/**
 * SavedService
 *
 * Encapsula todas as queries e a lógica de negócio da página de guardados.
 * Não emite HTML nem headers.
 */
class SavedService
{
    // Tipos permitidos como filtro
    public const ALLOWED_FILTERS = ['all', 'post', 'video', 'album', 'photo', 'reel'];

    // Itens por página
    public const PER_PAGE = 24;

    public function __construct(private PDO $pdo) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Carregar página de guardados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{items: array, total: int, total_pages: int, filter: string, page: int, ai_analysis_map: array}
     */
    public function load(int $user_id, string $filter, int $page): array
    {
        $use_filter = ($filter !== 'all');
        $offset     = ($page - 1) * self::PER_PAGE;

        $items       = $this->fetchItems($user_id, $use_filter, $filter, self::PER_PAGE, $offset);
        $total       = $this->countItems($user_id, $use_filter, $filter);
        $total_pages = (int)ceil($total / self::PER_PAGE);
        $ai_map      = $this->fetchAiAnalysisMap($items);

        return compact('items', 'total', 'total_pages', 'filter', 'page', 'ai_map');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Queries privadas
    // ─────────────────────────────────────────────────────────────────────────

    private function fetchItems(
        int $user_id,
        bool $use_filter,
        string $filter,
        int $limit,
        int $offset
    ): array {
        $type_clause = $use_filter ? 'AND sp.item_type = :item_type' : '';

        $sql = "
            SELECT
                sp.id           AS save_id,
                sp.item_type,
                sp.item_id,
                sp.created_at   AS saved_at,

                -- Post / Reel
                p.image_path            AS post_thumb,
                p.video_thumbnail_path  AS post_video_thumb,
                p.content               AS post_content,
                p.price                 AS post_price,
                p.is_for_sale           AS post_for_sale,
                p.post_type,

                -- Video
                v.thumbnail_path        AS video_thumb,
                v.caption               AS video_caption,
                v.price                 AS video_price,
                v.is_for_sale           AS video_for_sale,

                -- Album
                a.thumbnail_path        AS album_thumb,
                a.cover_photo_url       AS album_cover,
                a.name                  AS album_name,
                a.price                 AS album_price,
                a.is_for_sale           AS album_for_sale,

                -- Photo
                ap.thumbnail_path       AS photo_thumb,
                ap.photo_path           AS photo_path,
                ap.caption              AS photo_caption,
                ap.album_id             AS photo_album_id,

                -- Autor
                u.username,
                u.profile_picture,
                u.id AS author_id

            FROM saved_posts sp
            LEFT JOIN posts       p  ON sp.item_type IN ('post','reel') AND sp.item_id = p.id
            LEFT JOIN videos      v  ON sp.item_type = 'video'          AND sp.item_id = v.id
            LEFT JOIN albums      a  ON sp.item_type = 'album'          AND sp.item_id = a.id
            LEFT JOIN album_photos ap ON sp.item_type = 'photo'          AND sp.item_id = ap.id
            LEFT JOIN users       u  ON (
                CASE sp.item_type
                    WHEN 'post'  THEN p.user_id
                    WHEN 'reel'  THEN p.user_id
                    WHEN 'video' THEN v.user_id
                    WHEN 'album' THEN a.user_id
                    WHEN 'photo' THEN ap.user_id
                END
            ) = u.id
            WHERE sp.user_id = :uid
            $type_clause
            ORDER BY sp.created_at DESC
            LIMIT :lim OFFSET :off
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,   PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        if ($use_filter) {
            $stmt->bindValue(':item_type', $filter, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function countItems(int $user_id, bool $use_filter, string $filter): int
    {
        $type_clause = $use_filter ? 'AND item_type = :item_type' : '';

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM saved_posts WHERE user_id = :uid $type_clause"
        );
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        if ($use_filter) {
            $stmt->bindValue(':item_type', $filter, PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Pré-carrega análise AI para todos os itens em batch (evita N+1).
     */
    private function fetchAiAnalysisMap(array $items): array
    {
        if (empty($items)) return [];

        $ids_by_type = [];
        foreach ($items as $it) {
            $type = $this->analysisType($it);
            if ($type !== '') {
                $ids_by_type[$type][] = (int)$it['item_id'];
            }
        }

        $map = [];
        foreach ($ids_by_type as $type => $ids) {
            $ids  = array_values(array_unique($ids));
            $ph   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT post_id, risk_level, status, explicit_percentage
                 FROM media_analysis
                 WHERE post_id IN ($ph) AND type = ?
                 ORDER BY id DESC"
            );
            $stmt->execute([...$ids, $type]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = $type . '_' . $row['post_id'];
                if (!isset($map[$key])) {
                    $map[$key] = $row;
                }
            }
        }

        return $map;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers públicos (usados pela view)
    // ─────────────────────────────────────────────────────────────────────────

    public function analysisType(array $item): string
    {
        return match ($item['item_type']) {
            'album' => 'album',
            'video' => 'video',
            'photo' => 'image',
            'reel'  => 'video',
            'post'  => (!empty($item['post_video_thumb']) || (($item['post_type'] ?? '') === 'reel'))
                ? 'video'
                : 'image',
            default => '',
        };
    }

    public static function itemThumb(array $item): string
    {
        return match ($item['item_type']) {
            'post', 'reel' => $item['post_video_thumb'] ?: $item['post_thumb'] ?: '',
            'video'        => $item['video_thumb'] ?: '',
            'album'        => $item['album_thumb'] ?: $item['album_cover'] ?: '',
            'photo'        => $item['photo_thumb'] ?: $item['photo_path'] ?: '',
            default        => '',
        };
    }

    public static function itemUrl(array $item): string
    {
        return match ($item['item_type']) {
            'post'  => BASE_URL . 'post.php?id='       . $item['item_id'],
            'reel'  => BASE_URL . 'reels.php?id='      . $item['item_id'],
            'video' => BASE_URL . 'post.php?id='       . $item['item_id'],
            'album' => BASE_URL . 'view_album.php?id=' . $item['item_id'],
            'photo' => BASE_URL . 'view_album.php?id=' . $item['photo_album_id']
                . '#photo-' . $item['item_id'],
            default => '#',
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'post'  => 'fa-image',
            'reel'  => 'fa-clapperboard',
            'video' => 'fa-play',
            'album' => 'fa-images',
            'photo' => 'fa-image',
            default => 'fa-bookmark',
        };
    }

    public static function itemPrice(array $item): float
    {
        return (float)match ($item['item_type']) {
            'post', 'reel' => $item['post_price']  ?? 0,
            'video'        => $item['video_price'] ?? 0,
            'album'        => $item['album_price'] ?? 0,
            default        => 0,
        };
    }

    public static function itemIsPaid(array $item): bool
    {
        return (bool)match ($item['item_type']) {
            'post', 'reel' => $item['post_for_sale']  ?? false,
            'video'        => $item['video_for_sale'] ?? false,
            'album'        => $item['album_for_sale'] ?? false,
            default        => false,
        };
    }
}
