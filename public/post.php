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

<style>
    /* ═══════════════════════════════════════════════════════════════════
       LIGHTBOX V3 - Estilo Unificado (Garantia de Fullscreen)
       ═══════════════════════════════════════════════════════════════════ */
    :root {
        --v3-green: #00f28f;
        --v3-dark-btn: rgba(255, 255, 255, 0.1);
        --v3-text-gray: #a0a0a0;
        --v3-sidebar-bg: #1a1a1a;
    }

    /* Forçar o overlay a ignorar contentores pais */
    .va-lb-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: #000 !important;
        z-index: 999999 !important;
        /* Valor muito alto para sobrepor sidebars */
        display: flex;
        flex-direction: column;
        color: #fff;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        overflow: hidden;
    }

    .va-lb-close-btn {
        position: absolute;
        top: 20px;
        left: 20px;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
        font-size: 24px;
        cursor: pointer;
        z-index: 1001;
        padding: 10px;
        border-radius: 50%;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .va-lb-main-content {
        flex: 1;
        min-height: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px 80px;
        transition: margin-right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #vaLbImgWrap {
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.8);
        max-width: 100%;
        max-height: 100%;
    }

    .va-lb-img {
        max-width: 90vw;
        max-height: calc(100vh - 160px);
        object-fit: contain;
        border-radius: 14px;
    }

    .va-lb-bottom-bar {
        background: linear-gradient(to top, rgba(0, 0, 0, 0.9), transparent);
        padding: 20px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        z-index: 1000;
        transition: margin-right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .va-lb-overlay.sidebar-open .va-lb-main-content,
    .va-lb-overlay.sidebar-open .va-lb-bottom-bar {
        margin-right: 360px;
    }

    .va-lb-author-info {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .va-lb-author-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: 2px solid var(--v3-green);
    }

    .va-lb-author-name {
        font-weight: 700;
        color: #fff;
        text-decoration: none;
    }

    .va-lb-caption {
        margin-left: 15px;
        color: #ccc;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 400px;
    }

    .va-lb-action-bar {
        display: flex;
        gap: 20px;
    }

    .va-lb-action-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .va-lb-action-btn {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: none;
        background: var(--v3-dark-btn);
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .va-lb-btn-like.active {
        background: var(--v3-green);
        color: #000;
    }

    /* Sidebar */
    .va-lb-comments-sidebar {
        position: fixed !important;
        top: 0 !important;
        right: 0 !important;
        width: 0;
        height: 100vh !important;
        background: var(--v3-sidebar-bg);
        z-index: 1000000 !important;
        display: flex;
        flex-direction: column;
        transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: -5px 0 15px rgba(0, 0, 0, 0.5);
    }

    .va-lb-comments-sidebar.open {
        width: 360px;
    }

    .va-lb-sidebar-header {
        padding: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .va-lb-comments-list {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }

    .va-lb-comment-form-area {
        padding: 20px;
        background: #111;
    }

    .va-lb-comment-input-wrap {
        display: flex;
        background: #222;
        border-radius: 25px;
        padding: 8px 15px;
        gap: 10px;
    }

    .va-lb-comment-input {
        flex: 1;
        background: transparent;
        border: none;
        color: #fff;
        outline: none;
        resize: none;
    }

    /* Blur */
    .v3-blurred {
        filter: blur(30px);
    }

    .v3-blur-shield {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 100;
    }

    @media (max-width: 768px) {
        .va-lb-main-content {
            padding: 10px;
        }

        .va-lb-overlay.sidebar-open .va-lb-main-content {
            margin-right: 0;
        }

        .va-lb-comments-sidebar.open {
            width: 100%;
            height: 80vh !important;
            top: auto !important;
            bottom: 0 !important;
        }

        .va-lb-bottom-bar {
            flex-direction: column;
            align-items: flex-start;
        }

        .va-lb-caption {
            max-width: 100%;
            margin-left: 0;
            margin-top: 5px;
        }
    }
</style>

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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const lb = document.getElementById('postLightboxV3');
        const sidebar = document.getElementById('v3CommentsSidebar');
        let commentsLoaded = false;

        // Revelar blur
        const rev = document.getElementById('v3RevealBtn');
        if (rev) {
            rev.onclick = () => {
                document.getElementById('v3BlurShield').remove();
                const media = document.querySelector('.va-lb-img');
                if (media) media.classList.remove('v3-blurred');
            };
        }

        // Sidebar
        window.toggleV3Sidebar = () => {
            sidebar.classList.toggle('open');
            lb.classList.toggle('sidebar-open', sidebar.classList.contains('open'));
            if (sidebar.classList.contains('open') && !commentsLoaded) loadComments();
        };

        function loadComments() {
            fetch('api/comments.php?feed_item_id=<?= (int)$feed_item_id ?>')
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        commentsLoaded = true;
                        document.getElementById('v3CommentCount').textContent = d.comments.length;
                        const container = document.getElementById('v3CommentsList');
                        container.innerHTML = d.comments.map(c => `
                            <div style="display:flex; gap:12px; margin-bottom:15px;">
                                <img src="${UPLOAD_URL + c.profile_picture}" style="width:32px; height:32px; border-radius:50%;">
                                <div style="flex:1;">
                                    <div style="background:rgba(255,255,255,0.08); padding:10px; border-radius:15px;">
                                        <div style="font-weight:700; font-size:12px; margin-bottom:2px;">${c.username}</div>
                                        <div style="font-size:13px; color:#eee;">${c.content}</div>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    }
                });
        }

        lb.onclick = (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const act = btn.dataset.action;
            if (act === 'close-lightbox') window.history.back();
            if (act === 'toggle-comments') toggleV3Sidebar();
            if (act === 'like') {
                fetch('process_vote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `feed_item_id=<?= (int)$feed_item_id ?>&vote_type=like`
                }).then(r => r.json()).then(d => {
                    if (d.success) {
                        btn.classList.toggle('active');
                        document.getElementById('v3LikeCount').textContent = d.likes;
                    }
                });
            }
        };

        document.getElementById('v3CommentForm').onsubmit = (e) => {
            e.preventDefault();
            const input = document.getElementById('v3CommentInput');
            if (!input.value.trim()) return;
            const fd = new FormData();
            fd.append('feed_item_id', <?= (int)$feed_item_id ?>);
            fd.append('comment_content', input.value);
            fd.append('action', 'add_comment');
            fetch('process_comment.php', {
                method: 'POST',
                body: fd
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    input.value = '';
                    loadComments();
                }
            });
        };
    });
</script>