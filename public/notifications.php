<?php

/**
 * massangosu — Notifications (v3)
 * Corrigido: query SQL com JOINs correctos ao schema real (amassangos.sql)
 */
define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("Você precisa estar logado para ver suas notificações.", "danger");
    redirect(BASE_URL . 'login.php');
}

$current_user_id = (int) get_current_user_id();

/*
 * ═══════════════════════════════════════════════════════════════════
 * MAPEAMENTO entity_id POR TIPO DE NOTIFICAÇÃO (do schema real)
 * ───────────────────────────────────────────────────────────────────
 * feed_item_liked   → entity_id = feed_items.id
 *   feed_items.item_type = 'post'  → thumbnail em posts.thumbnail_path
 *   feed_items.item_type = 'video' → thumbnail em videos.thumbnail_path
 *   feed_items.item_type = 'album' → thumbnail em albums.thumbnail_path
 *
 * new_comment       → entity_id = feed_items.id   (mesmo fluxo acima)
 * comment_reply     → entity_id = comments.id      (sem thumbnail directo;
 *                     usamos feed_items via comments.feed_item_id)
 *
 * post_reposted     → entity_id = posts.id  (o post-repost criado)
 *   posts.is_repost = 1, posts.shared_post_id → ID do conteúdo original
 *   posts.shared_item_type = 'post'  → thumbnail em orig_post.thumbnail_path
 *   posts.shared_item_type = 'video' → thumbnail em orig_video.thumbnail_path
 *   posts.shared_item_type = 'album' → thumbnail em orig_album.thumbnail_path
 *
 * follow_request    → entity_id = sender users.id  (sem thumbnail)
 * album_partnership_request → entity_id = album_partners.id (sem thumbnail)
 * ═══════════════════════════════════════════════════════════════════
 */
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

        /* ── Remetente ────────────────────────────────────────────── */
        u.username        AS sender_username,
        u.profile_picture AS sender_avatar,

        /* ── Thumbnail final (prioridade: repost > feed_item) ──────
         *
         * Para post_reposted:
         *   repost_post.shared_item_type decide qual tabela usar para
         *   o conteúdo original. Aliás os JOINs orig_* abaixo.
         *
         * Para os restantes tipos com feed_item:
         *   fi.item_type decide entre posts / videos / albums.
         * ─────────────────────────────────────────────────────────── */
        CASE
            /* ── REPOST: thumbnail do conteúdo original ── */
            WHEN n.type = 'post_reposted' THEN
                CASE repost_post.shared_item_type
                    WHEN 'post'  THEN orig_post.thumbnail_path
                    WHEN 'video' THEN orig_video.thumbnail_path
                    WHEN 'album' THEN orig_album.thumbnail_path
                    ELSE NULL
                END

                     /* ── LIKE / COMMENT em feed_item ── */
            WHEN fi.id IS NOT NULL THEN
                CASE fi.item_type
                    WHEN 'post'  THEN fi_post.thumbnail_path
                    WHEN 'video' THEN fi_video.thumbnail_path
                    WHEN 'album' THEN fi_album.thumbnail_path
                    ELSE NULL
                END

            /* ── PHOTO: thumbnail da foto individual ── */
            WHEN n.type = 'photo_liked' THEN
                photo_direct.thumbnail_path

            /* ── PHOTO_COMMENT: thumbnail via comment → foto ── */
            WHEN n.type IN ('photo_commented', 'photo_comment_reply', 'photo_comment_liked') THEN
                photo_via_comment.thumbnail_path

            ELSE NULL
        END AS post_thumbnail,

        /* ── Título / alt da imagem ────────────────────────────────── */
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
            WHEN n.type = 'photo_liked' THEN
                photo_direct.album_id
            WHEN n.type IN ('photo_commented', 'photo_comment_reply', 'photo_comment_liked') THEN
                photo_via_comment.album_id
            ELSE NULL
        END AS photo_album_id,

        CASE
            WHEN n.type = 'photo_liked' THEN
                photo_direct.id
            WHEN n.type IN ('photo_commented', 'photo_comment_reply', 'photo_comment_liked') THEN
                photo_via_comment.id
            ELSE NULL
        END AS photo_id_target


    FROM notifications n

    /* ── Remetente ─────────────────────────────────────────────────── */
    LEFT JOIN users u
        ON u.id = n.sender_id

    /* ════════════════════════════════════════════════════════════════
     * RAMO A — tipos que usam entity_id = feed_items.id
     * (feed_item_liked, new_comment, comment_reply com feed_item_id)
     * ════════════════════════════════════════════════════════════════ */
    LEFT JOIN feed_items fi
        ON fi.id = n.entity_id
        AND n.type IN ('feed_item_liked', 'new_comment', 'comment_reply')

    /* Post ligado ao feed_item */
    LEFT JOIN posts fi_post
        ON fi_post.id = fi.item_id
        AND fi.item_type = 'post'

    /* Vídeo ligado ao feed_item (tabela própria) */
    LEFT JOIN videos fi_video
        ON fi_video.id = fi.item_id
        AND fi.item_type = 'video'

    /* Álbum ligado ao feed_item */
    LEFT JOIN albums fi_album
        ON fi_album.id = fi.item_id
        AND fi.item_type = 'album'

    /* ════════════════════════════════════════════════════════════════
     * RAMO B — post_reposted: entity_id = posts.id (o post-repost)
     *   O post-repost aponta para o conteúdo ORIGINAL via
     *   shared_post_id + shared_item_type.
     * ════════════════════════════════════════════════════════════════ */
    LEFT JOIN posts repost_post
        ON repost_post.id = n.entity_id
        AND n.type = 'post_reposted'

    /* Conteúdo original do repost — post */
    LEFT JOIN posts orig_post
        ON orig_post.id = repost_post.shared_post_id
        AND repost_post.shared_item_type = 'post'

    /* Conteúdo original do repost — vídeo */
    LEFT JOIN videos orig_video
        ON orig_video.id = repost_post.shared_post_id
        AND repost_post.shared_item_type = 'video'

        /* Conteúdo original do repost — álbum */
    LEFT JOIN albums orig_album
        ON orig_album.id = repost_post.shared_post_id
        AND repost_post.shared_item_type = 'album'

    /* ╔══════════════════════════════════════════════════════════
     * RAMO C — photo_liked / photo_commented
     *   entity_id = album_photos.id (foto individual)
     * ╚══════════════════════════════════════════════════════════ */
    LEFT JOIN album_photos photo_direct
        ON photo_direct.id = n.entity_id
        AND n.type = 'photo_liked'

    /* ╔══════════════════════════════════════════════════════════
     * RAMO D — photo_comment_reply / photo_comment_liked
     *   entity_id = photo_comments.id → buscar a foto
     * ╚══════════════════════════════════════════════════════════ */
    LEFT JOIN photo_comments pc_target
        ON pc_target.id = n.entity_id
        AND n.type IN ('photo_commented', 'photo_comment_reply', 'photo_comment_liked')

    LEFT JOIN album_photos photo_via_comment
        ON photo_via_comment.id = pc_target.photo_id

    WHERE n.recipient_id = :recipient_id

    ORDER BY n.created_at DESC
    LIMIT 50
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':recipient_id' => $current_user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    /* Em produção, registar o erro e mostrar lista vazia */
    error_log('Notifications query error: ' . $e->getMessage());
    $notifications = [];
}

/* ─────────────────────────────────────────────────────────────────────
 * Marcar automaticamente todas como lidas ao abrir a página?
 * Descomentado só se o produto usar esse comportamento.
 * ──────────────────────────────────────────────────────────────────── */
// $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0")
//     ->execute([$current_user_id]);

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
}

/* ── Helpers ────────────────────────────────────────────────────────── */

/**
 * Retorna a classe CSS e ícone do badge conforme o tipo de notificação.
 */
function notif_badge(string $type): array
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
 * Agrupa notificações por "Hoje" vs "Anteriores".
 */
function notif_group(string $created_at): string
{
    $date = new DateTime($created_at);
    $now  = new DateTime();
    return $date->format('Y-m-d') === $now->format('Y-m-d') ? 'hoje' : 'anteriores';
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/notifications.css">

<div class="notif-page">

    <!-- Cabeçalho -->
    <div class="notif-page-head">
        <h2><i class="fa-solid fa-bell"></i> Notificações</h2>
        <div class="notif-head-actions">
            <button class="notif-btn danger btn-clear-notifications" type="button">
                <i class="fa-solid fa-trash-can"></i> Limpar lidas
            </button>
            <button class="notif-btn" type="button" onclick="location.reload()">
                <i class="fa-solid fa-rotate"></i> Atualizar
            </button>
        </div>
    </div>

    <!-- Lista -->
    <div class="notif-list" id="notificationsList">

        <?php if (!empty($notifications)): ?>
            <?php
            $last_group = null;
            foreach ($notifications as $n):
                $is_unread          = !(bool) $n['is_read'];
                $is_follow_request  = ($n['type'] === 'follow_request');
                $is_partner_request = ($n['type'] === 'album_partnership_request');
                $group              = notif_group($n['created_at']);
                [$badge_class, $badge_icon] = notif_badge($n['type']);

                /* ── URLs ── */
                $link = !empty($n['link']) ? htmlspecialchars($n['link']) : '#';

                /* ── Avatar do remetente ── */
                /* users.profile_picture já contém o path relativo (ex: "default_profile.png"
                   ou "uploads/avatars/abc.jpg"). Ajusta o prefixo conforme a tua config. */
                $sender_avatar = !empty($n['sender_avatar'])
                    ? UPLOAD_URL . htmlspecialchars($n['sender_avatar'])
                    : UPLOAD_URL . 'default_profile.png';
                $sender_username = htmlspecialchars($n['sender_username'] ?? 'Utilizador');

                /* ── Thumbnail da publicação ── */
                // Thumbnail: para fotos individuais, usar a foto directa; para outros, usar normal
                $is_photo_notif = in_array($n['type'], ['photo_liked', 'photo_commented', 'photo_comment_reply', 'photo_comment_liked'], true);

                if ($is_photo_notif && !empty($n['post_thumbnail'])) {
                    // Foto individual de álbum — caminho directo
                    $post_thumb = UPLOAD_URL . 'albums/thumbnails/' . basename($n['post_thumbnail']);
                } elseif (!empty($n['post_thumbnail'])) {
                    $post_thumb = UPLOAD_URL . htmlspecialchars($n['post_thumbnail']);
                } else {
                    $post_thumb = '';
                }

                $post_thumb_alt = htmlspecialchars($n['post_title'] ?? '');

                // Link da notificação: para fotos, construir URL com hash para abrir lightbox
                if ($is_photo_notif && !empty($n['photo_album_id']) && !empty($n['photo_id_target'])) {
                    $notif_link = BASE_URL . 'view_album.php?id=' . (int)$n['photo_album_id']
                        . '#photo-' . (int)$n['photo_id_target'];
                } else {
                    $notif_link = !empty($n['link']) ? BASE_URL . $n['link'] : '#';
                }
            ?>

                <?php
                /* Separador de grupo */
                if ($group !== $last_group):
                    $last_group = $group;
                ?>
                    <div class="notif-group-label">
                        <?= $group === 'hoje' ? 'Hoje' : 'Anteriores' ?>
                    </div>
                <?php endif; ?>

                <div class="notif-item <?= $is_unread ? 'is-unread' : '' ?>"
                    data-notification-id="<?= (int) $n['id'] ?>">

                    <!-- Avatar + badge de tipo -->
                    <div class="notif-avatar-wrap <?= $is_unread ? 'is-unread' : '' ?>">
                        <img class="notif-avatar"
                            src="<?= $sender_avatar ?>"
                            alt="<?= $sender_username ?>"
                            loading="lazy">
                        <span class="notif-type-badge <?= $badge_class ?>" aria-hidden="true">
                            <i class="fa-solid <?= $badge_icon ?>"></i>
                        </span>
                    </div>

                    <!-- Corpo -->
                    <div class="notif-body">
                        <p class="notif-msg">
                            <?php
                            /*
                             * A coluna `message` contém o nome no início, em dois formatos:
                             *   "januario.bila gostou da sua publicação."   (likes, comments)
                             *   "@januario.bila repostou sua publicacao!"   (reposts — com @)
                             * Removemos o prefixo (@nome ou nome) para evitar duplicação.
                             */
                            $raw_msg      = $n['message'];
                            $name_escaped = htmlspecialchars($n['sender_username'] ?? '');
                            $name_quoted  = preg_quote($n['sender_username'] ?? '', '/');
                            // Aceita prefixo opcional "@" antes do nome
                            $rest_msg = ltrim(
                                preg_replace(
                                    '/^@?' . $name_quoted . '\s*/ui',
                                    '',
                                    $raw_msg
                                )
                            );
                            ?>
                            <strong><?= $name_escaped ?></strong>
                            <?= htmlspecialchars($rest_msg) ?>
                        </p>

                        <div class="notif-meta">
                            <span class="notif-time <?= $is_unread ? 'fresh' : '' ?>">
                                <?= format_datetime_ago($n['created_at']) ?>
                            </span>
                        </div>

                        <!-- Ações: pedido de seguimento -->
                        <?php if ($is_follow_request && $is_unread): ?>
                            <div class="notif-actions">
                                <form action="<?= BASE_URL ?>actions/follow_request.php" method="POST">
                                    <input type="hidden" name="follower_id"
                                        value="<?= (int) $n['sender_id'] ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" class="notif-btn primary">Aceitar</button>
                                </form>
                                <form action="<?= BASE_URL ?>actions/follow_request.php" method="POST">
                                    <input type="hidden" name="follower_id"
                                        value="<?= (int) $n['sender_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="notif-btn">Recusar</button>
                                </form>
                            </div>

                            <!-- Ações: pedido de parceria -->
                        <?php elseif ($is_partner_request && $is_unread): ?>
                            <div class="notif-actions">
                                <form action="<?= BASE_URL ?>process_partnership.php" method="POST">
                                    <input type="hidden" name="partner_id"
                                        value="<?= (int) $n['entity_id'] ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" class="notif-btn primary">
                                        <i class="fa-solid fa-handshake"></i> Aceitar
                                    </button>
                                </form>
                                <form action="<?= BASE_URL ?>process_partnership.php" method="POST">
                                    <input type="hidden" name="partner_id"
                                        value="<?= (int) $n['entity_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="notif-btn">Recusar</button>
                                </form>
                            </div>

                            <!-- Marcar como lida (outras notificações não lidas) -->
                        <?php elseif ($is_unread): ?>
                            <div class="notif-actions">
                                <button type="button"
                                    class="notif-btn btn-mark-read"
                                    style="font-size:.75rem;padding:4px 10px;">
                                    <i class="fa-solid fa-check"></i> Marcar como lida
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Thumbnail da publicação -->
                    <?php if ($post_thumb && !$is_follow_request && !$is_partner_request): ?>
                        <a href="<?= $link ?>" tabindex="-1" aria-hidden="true">
                            <img class="notif-thumb"
                                src="<?= $post_thumb ?>"
                                alt="<?= $post_thumb_alt ?>"
                                loading="lazy">
                        </a>
                    <?php elseif (!$is_follow_request && !$is_partner_request): ?>
                        <a href="<?= $link ?>" tabindex="-1" aria-hidden="true">
                            <div class="notif-thumb-placeholder">
                                <i class="fa-regular fa-image"></i>
                            </div>
                        </a>
                    <?php endif; ?>

                </div><!-- /notif-item -->

            <?php endforeach; ?>

        <?php else: ?>
            <div class="notif-empty">
                <i class="fa-solid fa-bell-slash"></i>
                <p>Ainda não tens notificações.</p>
            </div>
        <?php endif; ?>

    </div><!-- /notif-list -->
</div><!-- /notif-page -->

<script>
    /* Expõe BASE_URL ao JS sem depender de ficheiro externo */
    window.BASE_URL = '<?= addslashes(BASE_URL) ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/core/notifications.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>