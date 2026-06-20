<?php
// includes/views/post/post.view.php
// Variáveis disponíveis (injectadas pelo PostController):
// $feed_item, $feed_item_id, $item_type, $content_data,
// $author, $current_user_id, $is_post_owner, $is_admin,
// $like_info, $user_vote, $show_blur,
// $logged_in_user_profile_pic, $logged_in_user_data
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/post.css">

<div id="postLightboxV3" class="va-lb-overlay">

    <button class="va-lb-close-btn" data-action="close-lightbox">
        <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="va-lb-main-content">
        <div class="va-lb-media">
            <div id="vaLbImgWrap">

                <?php if ($item_type === 'post' && !empty($content_data['image_path'])): ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['image_path']) ?>"
                         class="va-lb-img <?= $show_blur ? 'v3-blurred' : '' ?>"
                         id="v3MediaImg"
                         alt="">

                <?php elseif ($item_type === 'video'): ?>
                    <video src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                           class="va-lb-img <?= $show_blur ? 'v3-blurred' : '' ?>"
                           id="v3MediaVideo"
                           playsinline loop preload="auto"
                           <?= $show_blur ? '' : 'controls' ?>></video>

                <?php elseif ($item_type === 'album'): ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['cover_photo_url']) ?>"
                         class="va-lb-img <?= $show_blur ? 'v3-blurred' : '' ?>"
                         id="v3MediaImg"
                         alt="">
                <?php endif; ?>

                <?php if ($show_blur): ?>
                    <div class="v3-blur-shield" id="v3BlurShield">
                        <i class="fa-solid fa-eye-slash" style="font-size:40px;color:#fff;margin-bottom:15px;"></i>
                        <button class="v3-reveal-btn" id="v3RevealBtn"
                                style="padding:10px 30px;border-radius:25px;border:none;background:#fff;font-weight:700;cursor:pointer;">
                            Ver Conteúdo
                        </button>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <div class="va-lb-bottom-bar">
        <div class="va-lb-author-info">
            <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?: 'profiles/default_profile.png') ?>"
                 class="va-lb-author-avatar"
                 alt="">
            <div class="va-lb-author-text">
                <a href="profile.php?username=<?= htmlspecialchars($author['username']) ?>"
                   class="va-lb-author-name">
                    <?= htmlspecialchars($author['username']) ?>
                </a>
                <span class="va-lb-sb-date"><?= format_datetime_ago($feed_item['created_at']) ?></span>
            </div>
            <p class="va-lb-caption"><?= htmlspecialchars($content_data['content'] ?? '') ?></p>
        </div>

        <div class="va-lb-action-bar">
            <div class="va-lb-action-group">
                <button class="va-lb-action-btn va-lb-btn-like <?= $user_vote === 'like' ? 'active' : '' ?>"
                        data-action="like">
                    <i class="fa-<?= $user_vote === 'like' ? 'solid' : 'regular' ?> fa-heart"></i>
                </button>
                <span class="va-lb-action-label" id="v3LikeCount"><?= (int)$like_info['likes'] ?></span>
            </div>
            <div class="va-lb-action-group">
                <button class="va-lb-action-btn" data-action="toggle-comments">
                    <i class="fa-regular fa-message"></i>
                </button>
                <span class="va-lb-action-label" id="v3CommentCount">0</span>
            </div>
            <div class="va-lb-action-group">
                <button class="va-lb-action-btn" data-action="save">
                    <i class="fa-regular fa-bookmark"></i>
                </button>
            </div>
        </div>
    </div>

    <aside class="va-lb-comments-sidebar" id="v3CommentsSidebar">
        <div class="va-lb-sidebar-header">
            <h3 style="margin:0;">
                <i class="fa-regular fa-message"></i> Comentários
            </h3>
            <button class="va-lb-sidebar-close" data-action="toggle-comments"
                    style="background:none;border:none;color:#fff;cursor:pointer;font-size:20px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="va-lb-comments-list" id="v3CommentsList"></div>
        <div class="va-lb-comment-form-area">
            <form id="v3CommentForm" class="va-lb-comment-form">
                <div class="va-lb-comment-input-wrap">
                    <textarea id="v3CommentInput" class="va-lb-comment-input"
                              placeholder="Escreva um comentário..." rows="1"></textarea>
                    <button type="submit"
                            style="background:none;border:none;color:var(--v3-green);cursor:pointer;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </aside>

</div>

<script>
const POST_DATA = {
    feedItemId:  <?= (int)$feed_item_id ?>,
    itemType:    <?= json_encode($item_type) ?>,
    isPostOwner: <?= $is_post_owner ? 'true' : 'false' ?>,
    showBlur:    <?= $show_blur ? 'true' : 'false' ?>
};
</script>
<script src="<?= BASE_URL ?>assets/js/pages/post.js"></script>
