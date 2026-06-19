<?php

/**
 * View: home/index.view.php
 *
 * Recebe (via extract() feito em public/index.php, vindos de
 * FeedController::load()):
 *   $current_user_id, $feedItems, $notifications, $logged_in_user_data,
 *   $user_data, $logged_in_user_profile_pic, $suggested_users,
 *   $recent_albums, $saved_ids, $csrf_token
 *
 * Cada item "de conteúdo" dentro de $feedItems já vem enriquecido pelo
 * FeedController com: content_data, author, isRepost, sharedData,
 * sharedType, sharedAuthor, sharedId, is_post_owner, is_admin,
 * has_access, has_access_shared, ai_analysis, should_blur, like_info,
 * user_vote, comment_count, share_count, follow_label, follow_class,
 * is_saved, save_label, save_icon, save_class.
 *
 * Itens "especiais" (suggested_users, suggested_albums) vêm apenas com
 * a chave 'type' e são tratados separadamente. 'admin_ad' é um tipo
 * reservado mas ainda sem componente implementado (ver switch abaixo).
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/premium_lightbox.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/profile_layout.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/cards.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/repost-header.css">
<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/pages/media-backdrop.js" defer></script>

<div class="posts-list">
    <?php if (!empty($feedItems)): ?>
        <?php foreach ($feedItems as $item): ?>

            <?php if (isset($item['type'])): ?>
                <?php
                // Itens especiais injetados no feed (não são post/video/album)
                switch ($item['type']) {
                    case 'suggested_users':
                        include __DIR__ . '/../../../public/components/suggested_users.php';
                        break;
                    case 'suggested_albums':
                        include __DIR__ . '/../../../public/components/suggested_albums.php';
                        break;
                        // 'admin_ad' ainda não tem componente implementado
                        // (public/components/admin_ad.php não existe). Se o
                        // feed passar a emitir esse tipo, criar o partial
                        // antes de reativar este case.
                }
                ?>
                <?php continue; ?>
            <?php endif; ?>

            <?php
            // Desempacota o item enriquecido para o escopo local do loop.
            // As partials em includes/feed/ esperam estas variáveis soltas.
            $content_data    = $item['content_data'];
            $author           = $item['author'];
            $isRepost         = $item['isRepost'];
            $sharedData       = $item['sharedData'];
            $sharedType       = $item['sharedType'];
            $sharedAuthor     = $item['sharedAuthor'];
            $sharedId         = $item['sharedId'];
            $is_post_owner    = $item['is_post_owner'];
            $is_admin         = $item['is_admin'];
            $ai_analysis      = $item['ai_analysis'];
            $should_blur      = $item['should_blur'];
            $like_info        = $item['like_info'];
            $user_vote        = $item['user_vote'];
            $comment_count    = $item['comment_count'];

            $is_shared_owner = $isRepost
                && isset($sharedData['user_id'])
                && $sharedData['user_id'] == $current_user_id;

            $can_see_sale_indicator =
                (!empty($item['is_for_sale']) && ($is_post_owner || $is_admin))
                || ($isRepost && !empty($sharedData['is_for_sale']) && ($is_shared_owner || $is_admin));
            ?>

            <article class="post-card card feed-item-wrapper <?= ($item['item_type'] === 'album' ? 'album-card-style' : '') ?>"
                data-type="all"
                data-feed-item-id="<?= htmlspecialchars($item['feed_item_id']) ?>">

                <?php include __DIR__ . '/../../feed/_post-header.php'; ?>

                <div class="post-content">
                    <?php if ($isRepost && $sharedData && $sharedAuthor): ?>
                        <?php include __DIR__ . '/../../feed/_repost-content.php'; ?>
                    <?php endif; ?>

                    <?php if ($item['item_type'] === 'post'): ?>
                        <?php include __DIR__ . '/../../feed/_post-media.php'; ?>
                    <?php elseif ($item['item_type'] === 'video'): ?>
                        <?php include __DIR__ . '/../../feed/_video-media.php'; ?>
                    <?php elseif ($item['item_type'] === 'album'): ?>
                        <?php include __DIR__ . '/../../feed/_album-media.php'; ?>
                    <?php endif; ?>
                </div><!-- /.post-content -->

                <?php include __DIR__ . '/../../feed/_post-footer.php'; ?>

            </article>
        <?php endforeach; ?>

    <?php else: ?>
        <p class="no-content-message" style="margin: 0 auto;">Nenhuma postagem encontrada. Seja o primeiro a postar!</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/_feed-lightbox.php'; ?>
<?php include __DIR__ . '/_verification-invite-modal.php'; ?>
<?php require_once __DIR__ . '/../../verificationmodal.php'; ?>

<script>
    window.BASE_URL = "<?= BASE_URL ?>";
    window.UPLOAD_URL = "<?= UPLOAD_URL ?>";
    window.CURRENT_USER_ID = <?= $current_user_id ? (int)$current_user_id : 'null' ?>;
    window.CURRENT_USER_PROFILE_PICTURE = "<?= htmlspecialchars(UPLOAD_URL . $logged_in_user_profile_pic) ?>";
    window.IS_VERIFIED_CREATOR = <?= json_encode((bool)($logged_in_user_data['is_verified_creator'] ?? false)) ?>;
</script>

<script src="<?= BASE_URL ?>assets/js/core/common_notifications.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/notifications.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/comments.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/track_views.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/home.js"></script>