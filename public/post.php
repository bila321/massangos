<?php
// public/post.php - REFATORADO FINAL - Fase 1: Layout de Alta Fidelidade com Lógica Completa

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

// ===== VERIFICAÇÕES DE PRIVACIDADE =====
$author_privacy = $author['profile_privacy'] ?? 'public';
if ($author_privacy === 'followers' && !$is_post_owner && !$is_admin) {
    $is_following_author = User::isFollowing($pdo, $current_user_id, $author['id']);
    $is_mutual_with_author = User::isMutualFollower($pdo, $current_user_id, $author['id']);

    if (!$is_following_author && !$is_mutual_with_author) {
        if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Conteúdo privado']);
            exit;
        }
        set_message("Este conteúdo é privado. Você precisa seguir o usuário para ver esta publicação.", "danger");
        redirect(BASE_URL . 'index.php');
        exit();
    }
}

// ===== VERIFICAR APROVAÇÃO =====
$is_approved = $content_data['is_approved'] ?? 1;
if (!$is_approved && !$is_post_owner && !$is_admin) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Aguardando aprovação']);
        exit;
    }
    set_message("Este conteúdo está aguardando aprovação administrativa.", "warning");
    redirect(BASE_URL);
}

// ===== VERIFICAR ACESSO PAGO =====
$paymentService = new \Massango\Services\PaymentService($pdo);
$hasAccess = $paymentService->hasAccess($current_user_id, $item_type, $original_item_id) || $is_admin;

// ===== ÁLBUM: contagens para o preview =====
$alb_photo_count = 0;
$alb_video_count = 0;
$alb_explicit    = 0;
if ($item_type === 'album') {
    $s = $pdo->prepare("SELECT COUNT(*) FROM album_photos WHERE album_id = ?");
    $s->execute([$original_item_id]);
    $alb_photo_count = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE album_id = ? AND post_type = 'video'");
    $s->execute([$original_item_id]);
    $alb_video_count = (int)$s->fetchColumn();

    $s = $pdo->prepare("
        SELECT AVG(ma.explicit_percentage) AS avg_explicit
        FROM media_analysis ma
        WHERE (ma.type = 'album' AND ma.post_id = ?)
    ");
    $s->execute([$original_item_id]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    $alb_explicit = ($r && $r['avg_explicit'] !== null) ? (int)round($r['avg_explicit']) : 0;
}


// ===== AI ANALYSIS — blur de conteúdo explícito =====
// Para reposts: usar o ID do conteúdo original
$analysis_id   = $original_item_id;
$analysis_type = $item_type; // 'post', 'video' ou 'album'

$stmt_ai = $pdo->prepare(
    "SELECT is_sensitive, score, explicit_percentage, risk_level, triggered_by, status
     FROM media_analysis
     WHERE post_id = ? AND type = ?
     ORDER BY id DESC LIMIT 1"
);
$stmt_ai->execute([$analysis_id, $analysis_type]);
$ai_analysis = $stmt_ai->fetch(PDO::FETCH_ASSOC) ?: null;

// Flags de conveniência
$ai_is_sensitive  = $ai_analysis && (bool)$ai_analysis['is_sensitive'];
$ai_explicit_pct  = $ai_analysis ? (float)($ai_analysis['explicit_percentage'] ?? 0) : 0;
$ai_risk_level    = $ai_analysis['risk_level'] ?? 'low';
$ai_triggered_by  = $ai_analysis['triggered_by'] ?? null;

// Blur se sensível OU percentagem >= 40 (médio/alto)
// Aplica-se a todos incluindo o dono — apenas admin isento.
// No futuro: dono poderá desativar com o toggle de preferências.
$show_blur = ($ai_is_sensitive || $ai_explicit_pct >= 40) && !$is_admin;

// ===== DADOS DO UTILIZADOR LOGADO =====
$logged_in_user_profile_pic = 'profiles/default_profile.png';
$logged_in_user_data = User::getUserById($pdo, $current_user_id);
if ($logged_in_user_data && !empty($logged_in_user_data['profile_picture'])) {
    $logged_in_user_profile_pic = $logged_in_user_data['profile_picture'];
}

// ===== VERIFICAR SE É AJAX =====
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
if (!$is_ajax) {
$extra_css = ['reels.css'];
$extra_head_js = ['components/description-truncate.js'];
$hide_feed_container = true;
    require_once __DIR__ . '/../includes/header.php';
}

$feed_item_id_escaped = htmlspecialchars($feed_item_id);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- ═══════════════════════════════════════════════════════════════════
     LIGHTBOX V3 - Layout de Alta Fidelidade
     ═══════════════════════════════════════════════════════════════════ -->
<div id="postLightboxV3" class="lightbox-v3-container <?= $is_ajax ? '' : 'full-page' ?>" data-feed-item-id="<?= $feed_item_id_escaped ?>">

    <!-- 1. Botão Fechar (Canto Superior Esquerdo) -->
    <button class="v3-close-btn" data-action="close-lightbox" title="Fechar (Esc)">
        <i class="fa-solid fa-xmark"></i>
    </button>

    <!-- 2. Botões de Navegação -->
    <button class="v3-nav-btn v3-prev" data-action="nav-prev" title="Anterior">
        <i class="fa-solid fa-chevron-left"></i>
    </button>
    <button class="v3-nav-btn v3-next" data-action="nav-next" title="Próximo">
        <i class="fa-solid fa-chevron-right"></i>
    </button>

    <!-- 3. Conteúdo Central -->
    <div class="v3-main-content">
        <div class="v3-media-wrapper">
            <?php if ($item_type === 'post' && !empty($content_data['image_path'])): ?>
                <?php if ($hasAccess): ?>
                    <?php if ($show_blur): ?>
                        <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['image_path']) ?>"
                            class="v3-media-item v3-blurred" id="v3MediaImg" alt="Post">
                        <div class="v3-blur-shield" id="v3BlurShield">
                            <i class="fa-solid fa-eye-slash"></i>
                            <p>Conteúdo pode ser explícito</p>
                            <small><?= round($ai_explicit_pct) ?>% de conteúdo adulto detectado</small>
                            <button class="v3-reveal-btn" id="v3RevealBtn">
                                <i class="fa-solid fa-eye"></i>&nbsp; Ver mesmo assim
                            </button>
                        </div>
                    <?php else: ?>
                        <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['image_path']) ?>"
                            class="v3-media-item" id="v3MediaImg" alt="Post">
                    <?php endif; ?>
                <?php else: ?>
                    <div class="v3-lock-overlay">
                        <i class="fas fa-lock"></i>
                        <h3>Conteúdo Pago</h3>
                        <p><?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
                        <a href="checkout.php?type=post&id=<?= (int)$original_item_id ?>" class="v3-buy-btn">Comprar Acesso</a>
                    </div>
                <?php endif; ?>

            <?php elseif ($item_type === 'post' && empty($content_data['image_path'])): ?>
                <!-- Post de texto puro -->
                <div class="v3-text-post">
                    <p><?= nl2br(htmlspecialchars($content_data['content'] ?? '')) ?></p>
                </div>

            <?php elseif ($item_type === 'video'): ?>
                <?php if ($hasAccess): ?>
                    <?php if ($show_blur): ?>
                        <?php
                        $thumb_src = !empty($content_data['thumbnail_path'])
                            ? UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path'])
                            : UPLOAD_URL . htmlspecialchars($content_data['video_path']);
                        ?>
                        <img src="<?= $thumb_src ?>" class="v3-media-item v3-blurred" id="v3MediaImg" alt="Thumbnail do vídeo">
                        <div class="v3-blur-shield" id="v3BlurShield">
                            <i class="fa-solid fa-eye-slash"></i>
                            <p>Conteúdo pode ser explícito</p>
                            <small><?= round($ai_explicit_pct) ?>% de conteúdo adulto detectado</small>
                            <button class="v3-reveal-btn" id="v3RevealBtn">
                                <i class="fa-solid fa-eye"></i>&nbsp; Ver mesmo assim
                            </button>
                        </div>
                        <!-- Vídeo oculto até revelação -->
                        <video src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                            class="v3-media-item" id="v3MediaVideo"
                            style="display:none;" playsinline loop preload="auto"></video>
                        <div class="v3-video-controls" id="v3VideoControls" style="display:none;">
                            <button class="v3-video-btn" data-action="toggle-play" title="Play/Pausa">
                                <i class="fa-solid fa-play"></i>
                            </button>
                            <button class="v3-video-btn" data-action="toggle-mute" title="Mudo/Som">
                                <i class="fa-solid fa-volume-xmark"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <video src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                            class="v3-media-item" playsinline loop preload="auto"></video>
                        <div class="v3-video-controls">
                            <button class="v3-video-btn" data-action="toggle-play" title="Play/Pausa">
                                <i class="fa-solid fa-play"></i>
                            </button>
                            <button class="v3-video-btn" data-action="toggle-mute" title="Mudo/Som">
                                <i class="fa-solid fa-volume-xmark"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="v3-lock-overlay">
                        <i class="fas fa-lock"></i>
                        <h3>Vídeo Pago</h3>
                        <p><?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
                        <a href="checkout.php?type=video&id=<?= (int)$original_item_id ?>" class="v3-buy-btn">Comprar Acesso</a>
                    </div>
                <?php endif; ?>

            <?php elseif ($item_type === 'album'): ?>
                <?php if ($hasAccess): ?>
                    <?php if ($show_blur): ?>
                        <div class="alb-cover" id="albCover">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['cover_photo_url']) ?>"
                                class="v3-media-item alb-cover-img v3-blurred" id="v3MediaImg" alt="Capa do Álbum">
                            <div class="v3-blur-shield" id="v3BlurShield">
                                <i class="fa-solid fa-eye-slash"></i>
                                <p>Conteúdo pode ser explícito</p>
                                <small><?= round($ai_explicit_pct) ?>% de conteúdo adulto detectado</small>
                                <button class="v3-reveal-btn" id="v3RevealBtn">
                                    <i class="fa-solid fa-eye"></i>&nbsp; Ver mesmo assim
                                </button>
                            </div>
                            <div class="alb-cover-stats" style="opacity:0.4;">
                                <?php if ($alb_photo_count > 0): ?><span>Fotos: <?= $alb_photo_count ?></span><?php endif; ?>
                                <?php if ($alb_video_count > 0): ?><span>Vídeos: <?= $alb_video_count ?></span><?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Capa do álbum + botão para abrir view_album.php -->
                        <div class="alb-cover" id="albCover">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['cover_photo_url']) ?>"
                                class="v3-media-item alb-cover-img" alt="Capa do Álbum">
                            <div class="alb-cover-stats">
                                <?php if ($alb_photo_count > 0): ?><span>Fotos: <?= $alb_photo_count ?></span><?php endif; ?>
                                <?php if ($alb_video_count > 0): ?><span>Vídeos: <?= $alb_video_count ?></span><?php endif; ?>
                                <?php if ($alb_explicit   > 0): ?><span>Explicidade: <?= $alb_explicit ?>%</span><?php endif; ?>
                            </div>
                            <a href="<?= BASE_URL ?>view_album.php?id=<?= (int)$original_item_id ?>" class="alb-ver-btn">
                                <i class="fa-solid fa-images"></i> Ver Álbum
                            </a>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="v3-lock-overlay">
                        <i class="fas fa-lock"></i>
                        <h3>Álbum Pago</h3>
                        <div style="display:flex;gap:10px;justify-content:center;font-size:13px;color:#aaa;flex-wrap:wrap;">
                            <?php if ($alb_photo_count > 0): ?><span>Fotos: <?= $alb_photo_count ?></span><?php endif; ?>
                            <?php if ($alb_video_count > 0): ?><span>Vídeos: <?= $alb_video_count ?></span><?php endif; ?>
                            <?php if ($alb_explicit   > 0): ?><span>Explicidade: <?= $alb_explicit ?>%</span><?php endif; ?>
                        </div>
                        <p><?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
                        <a href="<?= BASE_URL ?>checkout.php?type=album&id=<?= (int)$original_item_id ?>" class="v3-buy-btn">Comprar Acesso</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div><!-- /.v3-media-wrapper -->
    </div><!-- /.v3-main-content -->

    <!-- 4. Informações do Autor (Canto Inferior Esquerdo) -->
    <div class="v3-author-info">
        <div class="v3-author-header">
            <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'profiles/default_profile.png') ?>" class="v3-author-avatar" alt="<?= htmlspecialchars($author['username']) ?>">
            <div class="v3-author-text">
                <a href="<?= BASE_URL ?>profile.php?user_id=<?= $author['id'] ?>" class="v3-author-name"><?= htmlspecialchars($author['username']) ?></a>
                <span class="v3-post-date"><?= htmlspecialchars(
                                                !empty($feed_item['created_at'])
                                                    ? date('d/m/Y \à\s H\hi', strtotime($feed_item['created_at']))
                                                    : date('d/m/Y')
                                            ) ?></span>
            </div>
        </div>
        <?php
        $caption_text = '';
        if ($item_type === 'post')       $caption_text = $content_data['content'] ?? '';
        elseif ($item_type === 'video')  $caption_text = $content_data['caption'] ?? '';
        elseif ($item_type === 'album')  $caption_text = $content_data['description'] ?? '';
        ?>
        <?php if (!empty($caption_text)): ?>
            <p class="v3-post-caption"><?= htmlspecialchars($caption_text) ?></p>
        <?php endif; ?>
    </div>

    <!-- 5. Barra de Ações (Centro Inferior) -->
    <div class="v3-action-bar">
        <!-- Like -->
        <div class="v3-action-group">
            <button class="v3-action-btn v3-btn-like <?= ($user_vote === 1 || $user_vote === 'like') ? 'active' : '' ?>" data-action="like" title="Curtir">
                <i class="fa-solid fa-thumbs-up"></i>
            </button>
            <span class="v3-action-label" id="v3LikeCount"><?= $like_info['likes'] ?? 0 ?></span>
        </div>

        <!-- Comentar -->
        <div class="v3-action-group">
            <button class="v3-action-btn v3-btn-dark" data-action="toggle-comments" title="Comentar">
                <i class="fa-regular fa-comment"></i>
            </button>
            <span class="v3-action-label" id="v3CommentCount">0</span>
        </div>

        <!-- Guardar -->
        <div class="v3-action-group">
            <button class="v3-action-btn v3-btn-dark" data-action="save" title="Guardar">
                <i class="fa-regular fa-bookmark"></i>
            </button>
            <span class="v3-action-label">Guardar</span>
        </div>

        <!-- Partilhar -->
        <div class="v3-action-group">
            <button class="v3-action-btn v3-btn-dark" data-action="share" title="Partilhar">
                <i class="fa-solid fa-share"></i>
            </button>
            <span class="v3-action-label" id="v3ShareCount">0</span>
        </div>

        <!-- Mais Opções -->
        <div class="v3-action-group">
            <button class="v3-action-btn v3-btn-dark" data-action="more" title="Mais opções">
                <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>
        </div>
    </div>

    <!-- Menu de Mais Opções -->
    <?php if ($is_post_owner || $is_admin): ?>
        <div class="v3-more-menu" id="v3MoreMenu" style="display:none;">
            <a href="<?= BASE_URL ?>edit_post.php?id=<?= (int)$original_item_id ?>&type=<?= htmlspecialchars($item_type) ?>" class="v3-menu-item">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <button class="v3-menu-item v3-menu-danger" data-action="delete-item" data-item-id="<?= (int)$original_item_id ?>" data-item-type="<?= htmlspecialchars($item_type) ?>">
                <i class="fa-solid fa-trash"></i> Apagar
            </button>
        </div>
    <?php endif; ?>

    <!-- Sidebar de Comentários -->
    <aside class="v3-comments-sidebar" id="v3CommentsSidebar">
        <div class="v3-sidebar-header">
            <h3><i class="fa-solid fa-comment"></i> Comentários</h3>
            <button class="v3-sidebar-close" onclick="window.toggleV3Sidebar()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="v3-comments-list" id="v3CommentsList">
            <div style="text-align:center;padding:30px;color:#aaa;">
                <i class="fas fa-spinner fa-spin"></i> Carregando comentários...
            </div>
        </div>

        <div class="v3-comment-form-area">
            <?php if (is_logged_in()): ?>
                <form id="v3CommentForm" class="v3-comment-form">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($logged_in_user_profile_pic) ?>" class="v3-comment-avatar" alt="Você">
                    <div class="v3-comment-input-wrapper">
                        <input type="text" id="v3CommentInput" placeholder="Adicione um comentário..." autocomplete="off">
                        <button type="submit" title="Publicar">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <p style="text-align:center;font-size:13px;color:#aaa;padding:15px;">
                    Faça <a href="<?= BASE_URL ?>login.php" style="color:#00f28f;">login</a> para comentar.
                </p>
            <?php endif; ?>
        </div>
    </aside>

</div>

<!-- ═══════════════════════════════════════════════════════════════════
     ESTILOS CSS - Layout de Alta Fidelidade
     ═══════════════════════════════════════════════════════════════════ -->
<style>
    /* ── Modo página inteira (não-ajax) ── */
    .lightbox-v3-container.full-page {
        position: fixed;
        inset: 0;
    }

    /* ── Post de texto puro ── */
    .v3-text-post {
        max-width: 600px;
        padding: 32px 40px;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        text-align: center;
    }

    .v3-text-post p {
        margin: 0;
        font-size: 20px;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.92);
        white-space: pre-wrap;
        word-break: break-word;
    }

    :root {
        --v3-green: #00f28f;
        --v3-dark-btn: rgba(255, 255, 255, 0.1);
        --v3-text-gray: #a0a0a0;
        --v3-sidebar-bg: #1a1a1a;
    }

    .lightbox-v3-container {
        position: fixed;
        inset: 0;
        background: #000;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        color: #fff;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        overflow: hidden;
    }

    /* ── Botão Fechar ── */
    .v3-close-btn {
        position: absolute;
        top: 20px;
        left: 20px;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
        font-size: 24px;
        cursor: pointer;
        z-index: 100;
        padding: 10px;
        border-radius: 50%;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .v3-close-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: scale(1.1);
    }

    /* ── Botões de Navegação ── */
    .v3-nav-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 250;
        font-size: 22px;
        transition: all 0.2s ease, right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .v3-nav-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-50%) scale(1.1);
    }

    .v3-prev {
        left: 20px;
    }

    .v3-next {
        right: 20px;
        transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Shift next button when sidebar is open */
    .lightbox-v3-container.sidebar-open .v3-next {
        right: 380px;
    }

    /* ── Conteúdo Principal ── */
    .v3-main-content {
        flex: 1;
        min-height: 0;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        overflow: hidden;
        transition: margin-right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .v3-media-wrapper {
        position: relative;
        width: 100%;
        max-width: 100%;
        height: 100%;
        max-height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .v3-media-item {
        max-width: 100%;
        max-height: 80vh;
        object-fit: contain;
        border-radius: 16px;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
        margin-top: -25px;

    }

    .v3-album-indicator {
        position: absolute;
        bottom: 20px;
        left: 20px;
        background: rgba(0, 0, 0, 0.5);
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 10;
    }

    .v3-view-album-btn {
        position: absolute;
        bottom: 20px;
        right: 20px;
        background: var(--v3-green);
        color: #000;
        padding: 8px 16px;
        border-radius: 20px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        z-index: 10;
        transition: transform 0.2s;
    }

    .v3-view-album-btn:hover {
        transform: scale(1.05);
    }

    /* ── Controles de Vídeo ── */
    .v3-video-controls {
        position: absolute;
        bottom: 20px;
        right: 20px;
        display: flex;
        gap: 8px;
        z-index: 10;
    }

    .v3-video-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        transition: all 0.2s;
    }

    .v3-video-btn:hover {
        background: rgba(0, 0, 0, 0.7);
        transform: scale(1.05);
    }

    /* ── Lock Overlay ── */
    .v3-lock-overlay {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
        text-align: center;
    }

    .v3-lock-overlay i {
        font-size: 48px;
        opacity: 0.5;
    }

    .v3-lock-overlay h3 {
        margin: 0;
        font-size: 20px;
    }

    .v3-lock-overlay p {
        margin: 0;
        color: var(--v3-text-gray);
    }

    .v3-buy-btn {
        background: var(--v3-green);
        color: #000;
        padding: 10px 24px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 600;
        transition: transform 0.2s;
    }

    .v3-buy-btn:hover {
        transform: scale(1.05);
    }

    /* ── Informações do Autor ── */
    .v3-author-info {
        position: absolute;
        bottom: 88px;
        left: 40px;
        z-index: 80;
        max-width: 380px;
        padding: 10px 14px 8px;
        border-radius: 12px;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.5) 0%, transparent 100%);
    }

    .v3-author-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }

    .v3-author-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.5);
    }

    .v3-author-text {
        display: flex;
        flex-direction: column;
    }

    .v3-author-name {
        font-weight: 700;
        font-size: 16px;
        color: #fff;
        text-decoration: none;
        transition: opacity 0.2s;
    }

    .v3-author-name:hover {
        opacity: 0.8;
    }

    .v3-post-date {
        font-size: 12px;
        color: var(--v3-text-gray);
    }

    .v3-post-caption {
        margin: 0;
        font-size: 14px;
        line-height: 1.4;
        color: rgba(255, 255, 255, 0.9);
    }

    /* ── Barra de Ações ── */
    .v3-action-bar {
        position: absolute;
        bottom: 25px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        align-items: center;
        gap: 20px;
        z-index: 100;
        border-radius: 40px;
        padding: 8px 20px;
        white-space: nowrap;
        margin-top: 10px;
    }

    /* Separator between action groups */
    .v3-action-group+.v3-action-group {
        padding-left: 20px;
    }

    /* Shift action bar when sidebar open */
    .lightbox-v3-container.sidebar-open .v3-action-bar {
        transform: translateX(calc(-50% - 180px));
    }

    .v3-action-group {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 8px;
    }

    .v3-action-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .v3-action-btn:active {
        transform: scale(0.9);
    }

    .v3-btn-like {
        background: var(--v3-green);
        color: #000;
    }

    .v3-btn-like.active {
        background: var(--v3-green);
        box-shadow: 0 0 16px rgba(0, 242, 143, 0.4);
    }

    .v3-btn-dark {
        background: var(--v3-dark-btn);
        color: #fff;
        backdrop-filter: blur(8px);
    }

    .v3-btn-dark:hover {
        background: rgba(255, 255, 255, 0.15);
    }

    .v3-action-label {
        font-size: 13px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.85);
        white-space: nowrap;
        min-width: 16px;
    }

    /* ── Menu de Mais Opções ── */
    .v3-more-menu {
        position: fixed;
        bottom: 96px;
        right: 40px;
        background: rgba(26, 26, 26, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 8px 0;
        min-width: 140px;
        z-index: 101;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.6);
    }

    .v3-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 10px 16px;
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        font-size: 13px;
        text-align: left;
        transition: background 0.2s;
        text-decoration: none;
    }

    .v3-menu-item:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    .v3-menu-danger {
        color: #ff4757;
    }

    /* ── Sidebar de Comentários ── */
    .v3-comments-sidebar {
        position: fixed;
        top: 0;
        right: 0;
        width: 0;
        height: 100%;
        background: var(--v3-sidebar-bg);
        display: flex;
        flex-direction: column;
        z-index: 150;
        overflow: hidden;
        transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: -4px 0 24px rgba(0, 0, 0, 0.6);
    }

    .v3-comments-sidebar.open {
        width: 360px;
    }

    .v3-sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        flex-shrink: 0;
    }

    .v3-sidebar-header h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .v3-sidebar-close {
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.7);
        font-size: 18px;
        cursor: pointer;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
        padding: 0;
    }

    .v3-sidebar-close:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .v3-comments-list {
        flex: 1;
        overflow-y: auto;
        padding: 12px 16px;
    }

    .v3-comment-form-area {
        padding: 12px 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        background: var(--v3-sidebar-bg);
        flex-shrink: 0;
    }

    .v3-comment-form {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .v3-comment-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .v3-comment-input-wrapper {
        flex: 1;
        display: flex;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 20px;
        padding: 6px 14px;
        align-items: center;
    }

    .v3-comment-input-wrapper input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: #fff;
        font-size: 14px;
    }

    .v3-comment-input-wrapper input::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .v3-comment-input-wrapper button {
        background: none;
        border: none;
        color: var(--v3-green);
        cursor: pointer;
        font-size: 15px;
        padding: 4px;
        transition: transform 0.2s;
    }

    .v3-comment-input-wrapper button:hover {
        transform: scale(1.15);
    }

    /* ── Mobile ── */
    @media (max-width: 768px) {
        .v3-close-btn {
            top: 12px;
            left: 12px;
            width: 40px;
            height: 40px;
            font-size: 18px;
        }

        .v3-nav-btn {
            width: 44px;
            height: 44px;
            font-size: 18px;
        }

        .v3-prev {
            left: 12px;
        }

        .v3-next {
            right: 12px;
        }

        .v3-main-content {
            padding: 20px;
        }

        .v3-media-item {
            max-height: 60vh;
        }

        .v3-author-info {
            bottom: 80px;
            left: 16px;
            max-width: calc(100% - 32px);
            padding: 8px 12px 6px;
        }

        .v3-action-bar {
            bottom: 16px;
            gap: 10px;
            padding: 7px 14px;
            max-width: 92vw;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .v3-action-bar::-webkit-scrollbar {
            display: none;
        }

        .v3-action-group+.v3-action-group {
            padding-left: 10px;
        }

        .v3-action-btn {
            width: 38px;
            height: 38px;
            font-size: 15px;
        }

        .v3-action-label {
            font-size: 12px;
        }

        /* Mobile sidebar: bottom sheet */
        .v3-comments-sidebar {
            top: auto;
            bottom: 0;
            right: 0;
            width: 100% !important;
            height: 0;
            border-radius: 18px 18px 0 0;
            transition: height 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .v3-comments-sidebar.open {
            width: 100%;
            height: 72vh;
        }

        /* On mobile sidebar-open: no shift, nav buttons stay in place */
        .lightbox-v3-container.sidebar-open .v3-main-content {
            margin-right: 0;
        }

        .lightbox-v3-container.sidebar-open .v3-next {
            right: 12px;
        }

        .lightbox-v3-container.sidebar-open .v3-action-bar {
            transform: translateX(-50%);
        }

        .v3-more-menu {
            bottom: 88px;
            right: 16px;
        }
    }

    /* ── Tablet (769px–1200px) ── */
    @media (min-width: 769px) and (max-width: 1200px) {
        .lightbox-v3-container.sidebar-open .v3-main-content {
            margin-right: 320px;
        }

        .v3-comments-sidebar.open {
            width: 320px;
        }

        .lightbox-v3-container.sidebar-open .v3-next {
            right: 340px;
        }

        .lightbox-v3-container.sidebar-open .v3-action-bar {
            transform: translateX(calc(-50% - 160px));
        }
    }

    /* ── Desktop grande (>1200px) ── */
    @media (min-width: 1201px) {
        .lightbox-v3-container.sidebar-open .v3-main-content {
            margin-right: 360px;
            /* igual à largura do sidebar */
        }

        .lightbox-v3-container.sidebar-open .v3-action-bar {
            transform: translateX(calc(-50% - 180px));
            /* metade do sidebar */
        }

        /* .v3-next já tem a regra geral: right: 380px — mantém-se */
    }

    /* ══════════════════════════════════════════════════════
    /* ── Álbum: preview de capa ── */
    .alb-cover {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: albCoverIn 0.35s cubic-bezier(0.34, 1.4, 0.64, 1) both;
    }

    @keyframes albCoverIn {
        from {
            opacity: 0;
            transform: scale(0.93);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .alb-cover-img {
        max-height: 80vh;
        max-width: 100%;
        object-fit: contain;
        border-radius: 16px;
        transition: filter 0.22s ease;
    }

    .alb-cover:hover .alb-cover-img {
        filter: brightness(0.78);
    }

    /* Botão "Ver Álbum" — link para view_album.php */
    .alb-ver-btn {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(20, 20, 20, 0.78);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.2);
        font-size: 15px;
        font-weight: 600;
        padding: 11px 34px;
        border-radius: 28px;
        cursor: pointer;
        white-space: nowrap;
        z-index: 5;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s, border-color 0.2s, transform 0.2s, box-shadow 0.2s;
        opacity: 0;
    }

    .alb-cover:hover .alb-ver-btn {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1.04);
    }

    .alb-ver-btn:hover {
        background: rgba(0, 242, 143, 0.2);
        border-color: rgba(0, 242, 143, 0.55);
        box-shadow: 0 4px 20px rgba(0, 242, 143, 0.2);
    }

    .alb-cover-stats {
        position: absolute;
        bottom: 22px;
        left: 22px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        pointer-events: none;
        z-index: 5;
    }

    .alb-cover-stats span {
        font-size: 13px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.95);
        text-shadow: 0 1px 6px rgba(0, 0, 0, 0.95);
        line-height: 1.7;
    }

    @media (max-width: 768px) {
        .alb-ver-btn {
            opacity: 1;
            font-size: 14px;
            padding: 10px 28px;
        }

        .alb-cover-stats span {
            font-size: 12px;
        }
    }

    /* ── Blur Shield — conteúdo explícito ── */
    .v3-blurred {
        filter: blur(18px) !important;
        transform: scale(1.04);
        transition: filter 0.3s ease, transform 0.3s ease;
        pointer-events: none;
        user-select: none;
    }

    .v3-blur-shield {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.52);
        z-index: 90;
        gap: 10px;
        text-align: center;
        padding: 20px;
        border-radius: 16px;
    }

    .v3-blur-shield i {
        font-size: 2.2rem;
        color: #fff;
        opacity: 0.85;
    }

    .v3-blur-shield p {
        margin: 0;
        color: #fff;
        font-size: 0.95rem;
        font-weight: 600;
        text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
        max-width: 280px;
    }

    .v3-blur-shield small {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.78rem;
    }

    .v3-reveal-btn {
        margin-top: 6px;
        padding: 9px 26px;
        border-radius: 22px;
        border: none;
        background: #fff;
        color: #000;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .v3-reveal-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.4);
    }
</style>

<!-- ═══════════════════════════════════════════════════════════════════
     JAVASCRIPT - Interatividade Completa
     ═══════════════════════════════════════════════════════════════════ -->
<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const UPLOAD_URL = "<?= UPLOAD_URL ?>";
    const FEED_ITEM_ID = <?= (int)$feed_item_id ?>;
    const CURRENT_USER_ID = <?= (int)$current_user_id ?>;
    const ITEM_TYPE = "<?= htmlspecialchars($item_type) ?>";
    const ITEM_ID = <?= (int)$original_item_id ?>;

    // ── Blur / conteúdo explícito ──
    const V3_SHOW_BLUR = <?= $show_blur ? 'true' : 'false' ?>;
    const V3_EXPLICIT_PCT = <?= round($ai_explicit_pct) ?>;
    const V3_RISK_LEVEL = <?= json_encode($ai_risk_level) ?>;
    const V3_VIDEO_SRC = <?= ($item_type === 'video' && $hasAccess && !empty($content_data['video_path']))
                                ? json_encode(UPLOAD_URL . $content_data['video_path'])
                                : 'null' ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const lightbox = document.getElementById('postLightboxV3');
        if (!lightbox) return;

        // ── Lógica de revelação de blur ──────────────────────────────────
        if (V3_SHOW_BLUR) {
            const revealBtn = document.getElementById('v3RevealBtn');
            if (revealBtn) {
                revealBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const shield = document.getElementById('v3BlurShield');
                    const img = document.getElementById('v3MediaImg');

                    if (shield) shield.remove();
                    if (img) {
                        img.classList.remove('v3-blurred');
                        img.style.pointerEvents = '';
                    }

                    // Para vídeos com blur: trocar thumbnail pelo vídeo real
                    if (ITEM_TYPE === 'video' && V3_VIDEO_SRC) {
                        const vid = document.getElementById('v3MediaVideo');
                        const controls = document.getElementById('v3VideoControls');
                        if (img) img.style.display = 'none';
                        if (vid) {
                            vid.style.display = '';
                            vid.play().catch(() => {});
                        }
                        if (controls) controls.style.display = '';
                    }

                    // Para álbum: mostrar botão "Ver Álbum" após revelar
                    if (ITEM_TYPE === 'album') {
                        const stats = lightbox.querySelector('.alb-cover-stats');
                        const verBtn = lightbox.querySelector('.alb-ver-btn');
                        if (stats) stats.style.opacity = '';
                        if (!verBtn) {
                            const albCover = document.getElementById('albCover');
                            if (albCover) {
                                const a = document.createElement('a');
                                a.href = BASE_URL + 'view_album.php?id=' + ITEM_ID;
                                a.className = 'alb-ver-btn';
                                a.innerHTML = '<i class="fa-solid fa-images"></i> Ver Álbum';
                                albCover.appendChild(a);
                            }
                        }
                    }
                });
            }
        }

        let commentsLoaded = false;
        const sidebar = document.getElementById('v3CommentsSidebar');

        // ── Função para abrir/fechar sidebar ──
        window.toggleV3Sidebar = function() {
            sidebar.classList.toggle('open');
            lightbox.classList.toggle('sidebar-open', sidebar.classList.contains('open'));
            if (sidebar.classList.contains('open') && !commentsLoaded) {
                commentsLoaded = true;
                loadComments();
            }
        };

        // ── Carregar comentários ──
        function loadComments() {
            const commentsList = document.getElementById('v3CommentsList');
            fetch(BASE_URL + 'ajax/get_comments.php?feed_item_id=' + FEED_ITEM_ID)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.comments) {
                        document.getElementById('v3CommentCount').textContent = data.comments.length;
                        renderComments(data.comments, commentsList);
                    }
                })
                .catch(e => console.error('Erro ao carregar comentários:', e));
        }

        // ── Renderizar comentários ──
        function renderComments(comments, container) {
            if (!comments.length) {
                container.innerHTML = '<p style="text-align:center;color:#888;padding:40px;">Nenhum comentário ainda. Seja o primeiro!</p>';
                return;
            }
            let html = '<div style="display:flex;flex-direction:column;gap:16px;">';
            comments.forEach(c => {
                html += `<div style="display:flex;gap:12px;">
                    <img src="${escapeHtml(c.profile_picture)}" style="width:36px;height:36px;border-radius:50%;flex-shrink:0;">
                    <div style="flex:1;">
                        <div style="background:#3a3b3c;padding:10px 14px;border-radius:18px;">
                            <strong style="font-size:13px;color:#e4e6eb;display:block;">${escapeHtml(c.username)}</strong>
                            <p style="margin:0;font-size:14px;color:#e4e6eb;">${escapeHtml(c.content)}</p>
                        </div>
                        <span style="font-size:12px;color:#888;padding-left:4px;">${escapeHtml(c.formatted_created_at)}</span>
                    </div>
                </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        // ── Formulário de comentário ──
        const commentForm = document.getElementById('v3CommentForm');
        if (commentForm) {
            commentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const input = document.getElementById('v3CommentInput');
                const text = input.value.trim();
                if (!text) return;

                const fd = new FormData();
                fd.append('action', 'add_comment');
                fd.append('feed_item_id', FEED_ITEM_ID);
                fd.append('comment_content', text);

                fetch(BASE_URL + 'process_comment.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            input.value = '';
                            commentsLoaded = false;
                            loadComments();
                        }
                    })
                    .catch(e => console.error('Erro ao postar comentário:', e));
            });
        }

        // ── Cliques na barra de ações ──
        lightbox.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;

            const action = btn.dataset.action;

            if (action === 'close-lightbox') {
                if (window.history && window.history.back) {
                    window.history.back();
                } else {
                    window.location.href = BASE_URL;
                }
            } else if (action === 'like') {
                performVote('like', btn);
            } else if (action === 'toggle-comments') {
                e.stopPropagation();
                window.toggleV3Sidebar();
            } else if (action === 'more') {
                e.stopPropagation();
                const menu = document.getElementById('v3MoreMenu');
                if (menu) {
                    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                }
            } else if (action === 'save') {
                e.stopPropagation();
                const fd = new FormData();
                fd.append('feed_item_id', FEED_ITEM_ID);
                fd.append('action', 'save');
                fetch(BASE_URL + 'process_save.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            btn.classList.toggle('active');
                            const icon = btn.querySelector('i');
                            if (icon) icon.className = btn.classList.contains('active') ?
                                'fa-solid fa-bookmark' :
                                'fa-regular fa-bookmark';
                        }
                    })
                    .catch(e => console.error('Erro ao guardar:', e));
            } else if (action === 'share') {
                e.stopPropagation();
                const shareUrl = window.location.origin + window.location.pathname + '?id=' + FEED_ITEM_ID;
                if (navigator.share) {
                    navigator.share({
                        url: shareUrl
                    }).catch(() => {});
                } else if (navigator.clipboard) {
                    navigator.clipboard.writeText(shareUrl).then(() => {
                        const lbl = document.getElementById('v3ShareCount');
                        if (lbl) {
                            const old = lbl.textContent;
                            lbl.textContent = 'Copiado!';
                            setTimeout(() => lbl.textContent = old, 2000);
                        }
                    });
                }
            } else if (action === 'delete-item') {
                e.stopPropagation();
                const menu = document.getElementById('v3MoreMenu');
                if (menu) menu.style.display = 'none';
                if (!confirm('Tem certeza que deseja apagar este conteúdo? Esta ação não pode ser desfeita.')) return;
                const itemId = btn.dataset.itemId;
                const itemType = btn.dataset.itemType;
                const processMap = {
                    post: 'process_post.php',
                    video: 'process_video_post.php',
                    album: 'process_album_post.php'
                };
                const processFile = processMap[itemType] || 'process_post.php';
                const actionMap = {
                    post: 'delete_post',
                    video: 'delete_video',
                    album: 'delete_album'
                };
                const deleteAction = actionMap[itemType] || 'delete_post';
                const idField = itemType + '_id';
                const fd = new FormData();
                fd.append('action', deleteAction);
                fd.append(idField, itemId);
                fd.append('redirect_to', 'index.php');
                fetch(BASE_URL + processFile, {
                        method: 'POST',
                        body: fd
                    })
                    .then(() => {
                        window.location.href = BASE_URL + 'index.php';
                    })
                    .catch(e => console.error('Erro ao apagar:', e));
            } else if (action === 'toggle-play') {
                const vid = lightbox.querySelector('video.v3-media-item');
                if (vid) {
                    if (vid.paused) vid.play();
                    else vid.pause();
                    const icon = btn.querySelector('i');
                    if (icon) icon.className = vid.paused ? 'fa-solid fa-play' : 'fa-solid fa-pause';
                }
            } else if (action === 'toggle-mute') {
                const vid = lightbox.querySelector('video.v3-media-item');
                if (vid) {
                    vid.muted = !vid.muted;
                    const icon = btn.querySelector('i');
                    if (icon) icon.className = vid.muted ? 'fa-solid fa-volume-xmark' : 'fa-solid fa-volume-high';
                }
            }
        });

        // ── Função de vote (like/dislike) ──
        function performVote(type, btn) {
            const fd = new FormData();
            fd.append('feed_item_id', FEED_ITEM_ID);
            fd.append('action', type);

            fetch(BASE_URL + 'process_like.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const likeCount = document.getElementById('v3LikeCount');
                        if (likeCount && data.likes !== undefined) {
                            likeCount.textContent = data.likes;
                        }
                        btn.classList.toggle('active', data.user_vote === 1 || data.user_vote === 'like');
                    }
                })
                .catch(e => console.error('Erro ao dar like:', e));
        }

        // ── Escape para fechar ──
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (window.history && window.history.back) {
                    window.history.back();
                } else {
                    window.location.href = BASE_URL;
                }
            }
        });

        // ── Fechar menu ao clicar fora ──
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('v3MoreMenu');
            if (menu && !e.target.closest('[data-action="more"]') && !e.target.closest('.v3-more-menu')) {
                menu.style.display = 'none';
            }
        });

    });

    // ── Função auxiliar para escapar HTML ──
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
</script>

<?php if (!$is_ajax): ?>
    <script src="<?= BASE_URL ?>assets/js/core/common_notifications.js"></script>
    <script src="<?= BASE_URL ?>assets/js/components/likes.js"></script>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>