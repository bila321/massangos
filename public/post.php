<?php
// public/post.php - RETIFICADO - LIGHTBOX FULLSCREEN
define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Comment;
use Massango\Models\Like;
use Massango\Models\Video;
use Massango\Models\Album;
use Massango\Models\FeedItem;

// ===== AUTENTICAÇÃO =====
if (!is_logged_in()) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Não autorizado']);
        exit;
    }
    set_message("Você precisa estar logado para acessar as publicações.", "danger");
    redirect(BASE_URL . 'login.php');
}

// ===== OBTER DADOS DO FEED =====
$feed_item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$feed_item_id) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Item não especificado']);
        exit;
    }
    set_message("Item do feed não especificado.", "danger");
    redirect(BASE_URL);
}

$feed_item = FeedItem::getFeedItemById($pdo, $feed_item_id);
if (!$feed_item) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Conteúdo não encontrado']);
        exit;
    }
    set_message("Conteúdo não encontrado no feed.", "danger");
    redirect(BASE_URL);
}

$item_type = $feed_item['item_type'];
$original_item_id = $feed_item['item_id'];

// ===== OBTER CONTEÚDO ESPECÍFICO =====
$content_data = null;
switch ($item_type) {
    case 'post':
        $content_data = Post::getPostById($pdo, $original_item_id);
        break;
    case 'video':
        $content_data = Video::getVideoById($pdo, $original_item_id);
        break;
    case 'album':
        $content_data = Album::getAlbumById($pdo, $original_item_id);
        break;
}

if (!$content_data) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Detalhes não encontrados']);
        exit;
    }
    set_message("Detalhes do conteúdo não encontrados.", "danger");
    redirect(BASE_URL);
}

// ===== OBTER DADOS DO AUTOR =====
$author = User::getUserById($pdo, $feed_item['user_id']);
if (!$author) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Autor não encontrado']);
        exit;
    }
    set_message("Autor da postagem não encontrado.", "danger");
    redirect(BASE_URL);
}

// ===== DADOS DO UTILIZADOR ATUAL =====
$current_user_id = get_current_user_id();
$like_info = Like::getFeedItemLikesDislikesCount($pdo, $feed_item_id);
$user_vote = Like::getUserFeedItemVote($pdo, $feed_item_id, $current_user_id);
$is_post_owner = ($author['id'] == $current_user_id);
$is_admin = isset($_SESSION['admin_id']);

// ===== AI ANALYSIS — blur de conteúdo explícito =====
$analysis_id   = $original_item_id;
$analysis_type = $item_type;
$stmt_ai = $pdo->prepare("SELECT is_sensitive, explicit_percentage, risk_level FROM media_analysis WHERE post_id = ? AND type = ? ORDER BY id DESC LIMIT 1");
$stmt_ai->execute([$analysis_id, $analysis_type]);
$ai_analysis = $stmt_ai->fetch(PDO::FETCH_ASSOC) ?: null;
$ai_explicit_pct = $ai_analysis ? (float)($ai_analysis['explicit_percentage'] ?? 0) : 0;
$show_blur = ($ai_analysis && (bool)$ai_analysis['is_sensitive'] || $ai_explicit_pct >= 40) && !$is_admin;

// ===== DADOS DO UTILIZADOR LOGADO =====
$logged_in_user_profile_pic = 'profiles/default_profile.png';
$logged_in_user_data = User::getUserById($pdo, $current_user_id);
if ($logged_in_user_data && !empty($logged_in_user_data['profile_picture'])) {
    $logged_in_user_profile_pic = $logged_in_user_data['profile_picture'];
}

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
                        class="va-lb-img <?= $show_blur ? 'v3-blurred' : '' ?>" id="v3MediaImg">
                <?php elseif ($item_type === 'video'): ?>
                    <video src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                        class="va-lb-img <?= $show_blur ? 'v3-blurred' : '' ?>" id="v3MediaVideo"
                        playsinline loop preload="auto" <?= $show_blur ? '' : 'controls' ?>></video>
                <?php elseif ($item_type === 'album'): ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['cover_photo_url']) ?>"
                        class="va-lb-img <?= $show_blur ? 'v3-blurred' : '' ?>" id="v3MediaImg">
                <?php endif; ?>

                <?php if ($show_blur): ?>
                    <div class="v3-blur-shield" id="v3BlurShield">
                        <i class="fa-solid fa-eye-slash" style="font-size:40px; color:#fff; margin-bottom:15px;"></i>
                        <button class="v3-reveal-btn" id="v3RevealBtn"
                            style="padding:10px 30px; border-radius:25px; border:none; background:#fff; font-weight:700; cursor:pointer;">
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
                class="va-lb-author-avatar">
            <div class="va-lb-author-text">
                <a href="profile.php?username=<?= htmlspecialchars($author['username']) ?>" class="va-lb-author-name">
                    <?= htmlspecialchars($author['username']) ?>
                </a>
                <span class="va-lb-sb-date"><?= format_datetime_ago($feed_item['created_at']) ?></span>
            </div>
            <p class="va-lb-caption"><?= htmlspecialchars($content_data['content'] ?? '') ?></p>
        </div>

        <div class="va-lb-action-bar">
            <div class="va-lb-action-group">
                <button class="va-lb-action-btn va-lb-btn-like <?= $user_vote === 'like' ? 'active' : '' ?>" data-action="like">
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
            <h3 style="margin:0;"><i class="fa-regular fa-message"></i> Comentários</h3>
            <button class="va-lb-sidebar-close" data-action="toggle-comments" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="va-lb-comments-list" id="v3CommentsList"></div>
        <div class="va-lb-comment-form-area">
            <form id="v3CommentForm" class="va-lb-comment-form">
                <div class="va-lb-comment-input-wrap">
                    <textarea id="v3CommentInput" class="va-lb-comment-input" placeholder="Escreva um comentário..." rows="1"></textarea>
                    <button type="submit" style="background:none; border:none; color:var(--v3-green); cursor:pointer;"><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </form>
        </div>
    </aside>
</div>

<script src="<?= BASE_URL ?>assets/js/pages/post.js"></script>