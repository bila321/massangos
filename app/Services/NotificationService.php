<?php
// app/Services/NotificationService.php

namespace Massango\Services;

use PDO;
use PDOException;
use DateTime;

class NotificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Devolve as últimas 50 notificações do utilizador,
     * com thumbnails e metadados resolvidos via JOINs.
     *
     * Mapeamento entity_id por tipo (do schema amassangos):
     *   feed_item_liked / new_comment / comment_reply → feed_items.id
     *   post_reposted                                 → posts.id (o post-repost)
     *   photo_liked                                   → album_photos.id
     *   photo_commented / photo_comment_reply
     *     / photo_comment_liked                       → photo_comments.id
     *   follow_request / album_partnership_request    → sem thumbnail
     */
    public function getForUser(int $userId): array
    {
        $sql = "
            SELECT
                n.id,
                n.recipient_id,
                n.sender_id,
                n.type,
                n.entity_id,
                n.message,
                n.link,
                n.is_read,
                n.created_at,

                /* ── Remetente ── */
                u.username        AS sender_username,
                u.profile_picture AS sender_avatar,

                /* ── Thumbnail final (prioridade: repost > feed_item > foto) ──
                 *
                 * post_reposted  → shared_item_type decide entre orig_post /
                 *                  orig_video / orig_album
                 * feed_item_*    → fi.item_type decide entre fi_post / fi_video
                 *                  / fi_album
                 * photo_liked    → photo_direct
                 * photo_comment* → photo_via_comment
                 */
                CASE
                    WHEN n.type = 'post_reposted' THEN
                        CASE repost_post.shared_item_type
                            WHEN 'post'  THEN orig_post.thumbnail_path
                            WHEN 'video' THEN orig_video.thumbnail_path
                            WHEN 'album' THEN orig_album.thumbnail_path
                            ELSE NULL
                        END
                    WHEN fi.id IS NOT NULL THEN
                        CASE fi.item_type
                            WHEN 'post'  THEN fi_post.thumbnail_path
                            WHEN 'video' THEN fi_video.thumbnail_path
                            WHEN 'album' THEN fi_album.thumbnail_path
                            ELSE NULL
                        END
                    WHEN n.type = 'photo_liked' THEN
                        photo_direct.thumbnail_path
                    WHEN n.type IN ('photo_commented', 'photo_comment_reply', 'photo_comment_liked') THEN
                        photo_via_comment.thumbnail_path
                    ELSE NULL
                END AS post_thumbnail,

                /* ── Título / alt ── */
                CASE
                    WHEN n.type = 'post_reposted' THEN
                        CASE repost_post.shared_item_type
                            WHEN 'post'  THEN LEFT(orig_post.content, 80)
                            WHEN 'video' THEN orig_video.caption
                            WHEN 'album' THEN orig_album.name
                            ELSE NULL
                        END
                    WHEN fi.id IS NOT NULL THEN
                        CASE fi.item_type
                            WHEN 'post'  THEN LEFT(fi_post.content, 80)
                            WHEN 'video' THEN fi_video.caption
                            WHEN 'album' THEN fi_album.name
                            ELSE NULL
                        END
                    WHEN n.type = 'photo_liked' THEN
                        COALESCE(photo_direct.caption, 'Foto do álbum')
                    WHEN n.type IN ('photo_commented', 'photo_comment_reply', 'photo_comment_liked') THEN
                        COALESCE(photo_via_comment.caption, 'Foto do álbum')
                    ELSE NULL
                END AS post_title,

                /* ── album_id e photo_id para abrir lightbox directamente ── */
                CASE
                    WHEN n.type = 'photo_liked' THEN photo_direct.album_id
                    WHEN n.type IN ('photo_commented', 'photo_comment_reply', 'photo_comment_liked')
                        THEN photo_via_comment.album_id
                    ELSE NULL
                END AS photo_album_id,

                CASE
                    WHEN n.type = 'photo_liked' THEN photo_direct.id
                    WHEN n.type IN ('photo_commented', 'photo_comment_reply', 'photo_comment_liked')
                        THEN photo_via_comment.id
                    ELSE NULL
                END AS photo_id_target

            FROM notifications n

            /* ── Remetente ── */
            LEFT JOIN users u ON u.id = n.sender_id

            /* ════ RAMO A — entity_id = feed_items.id ════
             * (feed_item_liked, new_comment, comment_reply) */
            LEFT JOIN feed_items fi
                ON fi.id = n.entity_id
                AND n.type IN ('feed_item_liked', 'new_comment', 'comment_reply')

            LEFT JOIN posts  fi_post  ON fi_post.id  = fi.item_id AND fi.item_type = 'post'
            LEFT JOIN videos fi_video ON fi_video.id = fi.item_id AND fi.item_type = 'video'
            LEFT JOIN albums fi_album ON fi_album.id = fi.item_id AND fi.item_type = 'album'

            /* ════ RAMO B — post_reposted: entity_id = posts.id ════
             * O post-repost aponta para o original via shared_post_id + shared_item_type */
            LEFT JOIN posts repost_post
                ON repost_post.id = n.entity_id
                AND n.type = 'post_reposted'

            LEFT JOIN posts  orig_post  ON orig_post.id  = repost_post.shared_post_id AND repost_post.shared_item_type = 'post'
            LEFT JOIN videos orig_video ON orig_video.id = repost_post.shared_post_id AND repost_post.shared_item_type = 'video'
            LEFT JOIN albums orig_album ON orig_album.id = repost_post.shared_post_id AND repost_post.shared_item_type = 'album'

            /* ════ RAMO C — photo_liked: entity_id = album_photos.id ════ */
            LEFT JOIN album_photos photo_direct
                ON photo_direct.id = n.entity_id
                AND n.type = 'photo_liked'

            /* ════ RAMO D — photo_comment_*: entity_id = photo_comments.id ════ */
            LEFT JOIN photo_comments pc_target
                ON pc_target.id = n.entity_id
                AND n.type IN ('photo_commented', 'photo_comment_reply', 'photo_comment_liked')

            LEFT JOIN album_photos photo_via_comment ON photo_via_comment.id = pc_target.photo_id

            WHERE n.recipient_id = :recipient_id
            ORDER BY n.created_at DESC
            LIMIT 50
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':recipient_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('NotificationService::getForUser — ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Classe CSS e ícone FontAwesome para o badge de tipo.
     * Retorna [$badge_class, $badge_icon].
     */
    public static function badge(string $type): array
    {
        return match (true) {
            str_contains($type, 'like')    => ['like',    'fa-heart'],
            str_contains($type, 'comment') => ['comment', 'fa-comment'],
            str_contains($type, 'reply')   => ['comment', 'fa-reply'],
            str_contains($type, 'follow')  => ['follow',  'fa-user-plus'],
            str_contains($type, 'partner') => ['partner', 'fa-handshake'],
            str_contains($type, 'repost')  => ['default', 'fa-retweet'],
            default                        => ['default', 'fa-bell'],
        };
    }

    /**
     * Agrupa a notificação em 'hoje' ou 'anteriores'.
     */
    public static function group(string $createdAt): string
    {
        $date = new DateTime($createdAt);
        $now  = new DateTime();
        return $date->format('Y-m-d') === $now->format('Y-m-d') ? 'hoje' : 'anteriores';
    }

    /**
     * Resolve a URL do avatar do remetente.
     */
    public static function senderAvatarUrl(array $notification): string
    {
        return !empty($notification['sender_avatar'])
            ? UPLOAD_URL . htmlspecialchars($notification['sender_avatar'])
            : UPLOAD_URL . 'default_profile.png';
    }

    /**
     * Resolve a URL da thumbnail da publicação.
     * Fotos de álbum têm um prefixo diferente das outras.
     */
    public static function thumbUrl(array $notification): string
    {
        if (empty($notification['post_thumbnail'])) {
            return '';
        }

        $isPhotoNotif = in_array($notification['type'], [
            'photo_liked', 'photo_commented',
            'photo_comment_reply', 'photo_comment_liked',
        ], true);

        return $isPhotoNotif
            ? UPLOAD_URL . 'albums/thumbnails/' . basename($notification['post_thumbnail'])
            : UPLOAD_URL . htmlspecialchars($notification['post_thumbnail']);
    }

    /**
     * Resolve o link da notificação.
     * Para fotos, constrói URL com hash para abrir o lightbox directamente.
     */
    public static function notifLink(array $notification): string
    {
        $isPhotoNotif = in_array($notification['type'], [
            'photo_liked', 'photo_commented',
            'photo_comment_reply', 'photo_comment_liked',
        ], true);

        if ($isPhotoNotif
            && !empty($notification['photo_album_id'])
            && !empty($notification['photo_id_target'])
        ) {
            return BASE_URL . 'view_album.php?id=' . (int)$notification['photo_album_id']
                 . '#photo-' . (int)$notification['photo_id_target'];
        }

        return !empty($notification['link']) ? BASE_URL . $notification['link'] : '#';
    }

    /**
     * Devolve o texto da mensagem sem o prefixo "@nome" ou "nome",
     * para evitar duplicação quando a view já mostra o nome em <strong>.
     */
    public static function messageWithoutPrefix(array $notification): string
    {
        $raw      = $notification['message'];
        $username = $notification['sender_username'] ?? '';
        $quoted   = preg_quote($username, '/');

        return ltrim(preg_replace('/^@?' . $quoted . '\s*/ui', '', $raw));
    }
}
