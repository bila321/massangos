<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Controllers\FeedController;
use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Comment;
use Massango\Models\Like;
use Massango\Models\Video;
use Massango\Models\Album;
use Massango\Models\FeedItem;
use Massango\Models\Notification;

$data = (new FeedController($pdo))->load();
extract($data);

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/premium_lightbox.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/cards.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/repost-header.css">

<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/pages/media-backdrop.js" defer></script>

<div class="posts-list">
    <?php if (!empty($feedItems)): ?>

        <?php foreach ($feedItems as $item): ?>
            <?php
            // ── Componentes especiais (sugestões, anúncios) ──────────────────
            if (isset($item['type'])) {
                switch ($item['type']) {
                    case 'suggested_users':
                        include __DIR__ . '/components/suggested_users.php';
                        break;
                    case 'admin_ad':
                        include __DIR__ . '/components/admin_ad.php';
                        break;
                    case 'suggested_albums':
                        include __DIR__ . '/components/suggested_albums.php';
                        break;
                }
                continue;
            }

            // ── Extrair variáveis do item ────────────────────────────────────
            $content_data  = $item['content_data'];
            $author        = $item['author'];
            $like_info     = $item['like_info'];
            $user_vote     = $item['user_vote'];
            $comment_count = $item['comment_count'];
            $ai_analysis   = $item['ai_analysis'] ?? null;
            $is_post_owner = $item['is_post_owner'];
            $is_admin      = $item['is_admin'];
            $should_blur   = $item['should_blur'];

            $isRepost     = $item['isRepost'];
            $sharedData   = $item['sharedData'];
            $sharedType   = $item['sharedType'];
            $sharedAuthor = $item['sharedAuthor'];
            $sharedId     = $item['sharedId'] ?? null;

            $is_shared_owner = $isRepost && isset($sharedData['user_id'])
                && $sharedData['user_id'] == $current_user_id;

            $can_see_sale_indicator =
                (isset($item['is_for_sale']) && $item['is_for_sale'] && ($is_post_owner || $is_admin))
                || ($isRepost && isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']
                    && ($is_shared_owner || $is_admin));
            ?>

            <article class="post-card card feed-item-wrapper <?= $item['item_type'] === 'album' ? 'album-card-style' : '' ?>"
                data-type="all"
                data-feed-item-id="<?= (int)$item['feed_item_id'] ?>">

                <?php include __DIR__ . '/../includes/feed/_post-header.php'; ?>

                <div class="post-content">
                    <?php if ($isRepost && $sharedData && $sharedAuthor): ?>
                        <?php include __DIR__ . '/../includes/feed/_repost-content.php'; ?>
                    <?php else: ?>
                        <?php include __DIR__ . '/../includes/feed/_' . $item['item_type'] . '-media.php'; ?>
                    <?php endif; ?>
                </div>

                <?php include __DIR__ . '/../includes/feed/_post-footer.php'; ?>

            </article>

        <?php endforeach; ?>

    <?php else: ?>
        <p class="no-content-message" style="margin: 0 auto;">
            Nenhuma postagem encontrada. Seja o primeiro a postar!
        </p>
    <?php endif; ?>
</div><!-- /.posts-list -->


<!-- ============================================================
     Lightbox Premium (Facebook Reels Style)
     ============================================================ -->
<div id="feedLightbox" class="photo-lightbox-modal">
    <div class="close-lightbox" data-action="close-lightbox">
        <i class="fa-regular fa-eye-slash"></i>
    </div>
    <div class="photo-lightbox-content">
        <div class="reels-scroll-nav">
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(-1)">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(1)">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>
        <div class="photo-display-area">
            <div id="lightboxScrollContainer">
                <!-- Reels items injected via JS -->
            </div>
        </div>
        <div class="photo-sidebar">
            <div class="photo-sidebar-header">
                <h3>Comentários</h3>
                <button class="sidebar-close-btn" data-action="close-sidebar"
                    style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px;">
                    <i class="fa-regular fa-eye-slash"></i>
                </button>
            </div>
            <div class="photo-sidebar-body" id="lightboxCommentsArea">
                <!-- Comments injected via JS -->
            </div>
            <div class="photo-comment-form-area">
                <?php if (is_logged_in()): ?>
                    <form id="lightboxCommentForm" class="photo-comment-form">
                        <div class="comment-input-wrapper">
                            <input type="text" id="lightboxCommentInput"
                                placeholder="Escreva um comentário..." autocomplete="off">
                            <button type="submit">Enviar</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="login-to-comment"
                        style="padding: 10px; text-align: center; color: #b0b3b8; font-size: 14px;">
                        Faça <a href="<?= BASE_URL ?>login.php"
                            style="color: var(--reels-accent); text-decoration: none; font-weight: 600;">login</a>
                        para comentar.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- Modal de convite para verificação -->
<div id="verificationInviteModal" class="verification-invite-modal">
    <div class="verification-invite-content">
        <span class="invite-close" onclick="closeVerificationInviteModal()">&times;</span>
        <div class="invite-illustration">
            <svg viewBox="0 0 200 200" class="invite-id">
                <rect x="40" y="60" width="120" height="80" rx="12" />
                <circle cx="75" cy="95" r="15" />
                <rect x="100" y="85" width="45" height="6" rx="3" />
                <rect x="100" y="100" width="35" height="6" rx="3" />
            </svg>
            <div class="invite-magnifier"></div>
        </div>
        <h2>Verifique sua conta</h2>
        <p>
            Para acessar conteúdos pagos, comprar acessos ou vender publicações,
            é necessário verificar sua conta primeiro.<br><br>
            A verificação ajuda a manter a comunidade segura e aumenta
            a confiança entre os usuários.
        </p>
        <button class="invite-verify-btn" onclick="proceedToVerification()">
            Fazer verificação
        </button>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/verificationmodal.php'; ?>


<!-- ============================================================
     Scripts — ordem correta e sem duplicados
     ============================================================ -->

<!-- 1. Variáveis globais PRIMEIRO -->
<script>
    window.BASE_URL = "<?php echo BASE_URL; ?>";
    window.UPLOAD_URL = "<?php echo UPLOAD_URL; ?>";
    window.CURRENT_USER_ID = <?php echo is_logged_in() ? get_current_user_id() : 'null'; ?>;
    window.POST_OWNER_ID = null; // definido por item via lightbox
    window.IS_POST_OWNER = (window.CURRENT_USER_ID !== null && window.POST_OWNER_ID !== null && window.CURRENT_USER_ID == window.POST_OWNER_ID);
    window.CURRENT_USER_PROFILE_PICTURE = "<?php echo htmlspecialchars($_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png'); ?>";
    window.IS_VERIFIED_CREATOR = <?php echo json_encode((bool)($logged_in_user_data['is_verified_creator'] ?? false)); ?>;
</script>

<!-- 2. Scripts de dependência -->
<script src="<?= BASE_URL ?>assets/js/core/common_notifications.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/notifications.js"></script>

<!-- 3. Main.js (tem toggleShareMenu, handleRepost, etc.) -->
<script src="<?= BASE_URL ?>assets/js/core/main.js"></script>

<!-- 4. Comments e tracking -->
<script src="<?= BASE_URL ?>assets/js/components/comments.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/track_views.js"></script>

<!-- 5. Premium lightbox (depende das funções globais) -->
<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>

<!-- 6. Home page JS -->
<script src="<?= BASE_URL ?>assets/js/pages/home.js"></script>

<script src="<?= BASE_URL ?>assets/js/components/save.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>