<?php
declare(strict_types=1);

namespace Massango\Services;

use PDO;
use PDOException;

/**
 * HistoryService
 *
 * Encapsula a query UNION de reações (likes em posts/vídeos/álbuns,
 * likes em fotos, votos em comentários) e os helpers de formatação
 * usados pela view de histórico.
 * Não emite HTML nem headers.
 */
class HistoryService
{
    public const ALLOWED_FILTERS = ['all', 'posts', 'photos', 'comments'];
    public const PER_PAGE = 20;

    public function __construct(private PDO $pdo) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Ponto de entrada
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{reactions: array, total: int, total_pages: int, db_error: bool, db_error_detail: ?string}
     */
    public function load(int $user_id, string $filter, int $page): array
    {
        $offset = ($page - 1) * self::PER_PAGE;

        try {
            $result      = $this->buildReactionsQuery($user_id, $filter, self::PER_PAGE, $offset);
            $reactions   = $result['rows'];
            $total       = $result['total'];
            $total_pages = (int)ceil($total / self::PER_PAGE);

            return [
                'reactions'       => $reactions,
                'total'           => $total,
                'total_pages'     => max(1, $total_pages),
                'db_error'        => false,
                'db_error_detail' => null,
            ];
        } catch (PDOException $e) {
            error_log('[HistoryService] DB error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());

            return [
                'reactions'       => [],
                'total'           => 0,
                'total_pages'     => 1,
                'db_error'        => true,
                'db_error_detail' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development')
                    ? $e->getMessage()
                    : null,
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query UNION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{rows: array, total: int}
     */
    private function buildReactionsQuery(int $user_id, string $filter, int $limit, int $offset): array
    {
        $parts  = [];
        $params = [];

        if ($filter === 'all' || $filter === 'posts') {
            $parts[] = "
                SELECT
                    fil.id                AS reaction_id,
                    fil.type              AS reaction_type,
                    fil.created_at,
                    'feed'                AS source,
                    fi.item_type          AS content_type,
                    fi.item_id            AS content_ref_id,
                    fi.id                 AS feed_item_id,
                    COALESCE(p.content, v.caption, a.name, '')   AS content_preview,
                    COALESCE(p.image_path, v.thumbnail_path, '') AS media_thumb,
                    owner.username        AS owner_username,
                    owner.profile_picture AS owner_avatar,
                    owner.id              AS owner_id
                FROM feed_item_likes fil
                INNER JOIN feed_items fi ON fi.id = fil.feed_item_id
                INNER JOIN users owner   ON owner.id = fi.user_id
                LEFT  JOIN posts p       ON fi.item_type = 'post'  AND p.id = fi.item_id
                LEFT  JOIN videos v      ON fi.item_type = 'video' AND v.id = fi.item_id
                LEFT  JOIN albums a      ON fi.item_type = 'album' AND a.id = fi.item_id
                WHERE fil.user_id = :uid_feed
            ";
            $params[':uid_feed'] = $user_id;
        }

        if ($filter === 'all' || $filter === 'photos') {
            $parts[] = "
                SELECT
                    pl.id                 AS reaction_id,
                    pl.type               AS reaction_type,
                    pl.created_at,
                    'photo'               AS source,
                    'album_photo'         AS content_type,
                    pl.photo_id           AS content_ref_id,
                    NULL                  AS feed_item_id,
                    ''                    AS content_preview,
                    ap.photo_path         AS media_thumb,
                    owner.username        AS owner_username,
                    owner.profile_picture AS owner_avatar,
                    owner.id              AS owner_id
                FROM photo_likes pl
                INNER JOIN album_photos ap ON ap.id = pl.photo_id
                INNER JOIN albums alb      ON alb.id = ap.album_id
                INNER JOIN users owner     ON owner.id = alb.user_id
                WHERE pl.user_id = :uid_photo
            ";
            $params[':uid_photo'] = $user_id;
        }

        if ($filter === 'all' || $filter === 'comments') {
            $parts[] = "
                SELECT
                    cv.id                 AS reaction_id,
                    cv.vote_type          AS reaction_type,
                    cv.created_at,
                    'comment'             AS source,
                    'comment'             AS content_type,
                    cv.comment_id         AS content_ref_id,
                    c.feed_item_id        AS feed_item_id,
                    SUBSTRING(c.content, 1, 120) AS content_preview,
                    ''                    AS media_thumb,
                    owner.username        AS owner_username,
                    owner.profile_picture AS owner_avatar,
                    owner.id              AS owner_id
                FROM comment_votes cv
                INNER JOIN comments c  ON c.id = cv.comment_id
                INNER JOIN users owner ON owner.id = c.user_id
                WHERE cv.user_id = :uid_comment
            ";
            $params[':uid_comment'] = $user_id;
        }

        if (empty($parts)) {
            return ['rows' => [], 'total' => 0];
        }

        $union = implode(' UNION ALL ', $parts);

        $stmt_count = $this->pdo->prepare("SELECT COUNT(*) FROM ({$union}) AS t");
        foreach ($params as $key => $val) {
            $stmt_count->bindValue($key, $val, PDO::PARAM_INT);
        }
        $stmt_count->execute();
        $total = (int)$stmt_count->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM ({$union}) AS t ORDER BY created_at DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers estáticos de formatação (usados pela view)
    // ─────────────────────────────────────────────────────────────────────────

    public static function reactionIcon(string $type): string
    {
        return $type === 'like'
            ? '<i class="fa-solid fa-heart"></i>'
            : '<i class="fa-solid fa-thumbs-down"></i>';
    }

    public static function sourceLabel(string $source, string $contentType): string
    {
        return match ($source) {
            'feed' => match ($contentType) {
                'post'  => 'Publicação',
                'video' => 'Vídeo',
                'album' => 'Álbum',
                default => 'Conteúdo',
            },
            'photo'   => 'Foto',
            'comment' => 'Comentário',
            default   => 'Conteúdo',
        };
    }

    public static function sourceUrl(array $row): string
    {
        return match ($row['source']) {
            'feed', 'comment' => BASE_URL . 'post.php?id=' . (int)($row['feed_item_id'] ?? 0),
            'photo'           => BASE_URL . 'view_album.php?photo=' . (int)$row['content_ref_id'],
            default           => '#',
        };
    }

    public static function formatDate(string $datetime): string
    {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)     return 'Agora mesmo';
        if ($diff < 3600)   return floor($diff / 60)   . 'm atrás';
        if ($diff < 86400)  return floor($diff / 3600) . 'h atrás';
        if ($diff < 604800) return floor($diff / 86400) . 'd atrás';
        return date('d/m/Y', strtotime($datetime));
    }

    public static function avatarSrc(string $pic): string
    {
        if ($pic === 'default_profile.png' || $pic === '') {
            return BASE_URL . 'assets/img/default_profile.png';
        }
        return str_starts_with($pic, 'profiles/')
            ? BASE_URL . 'media-proxy.php?file=' . $pic
            : BASE_URL . 'media-proxy.php?file=profiles/' . $pic;
    }
}
