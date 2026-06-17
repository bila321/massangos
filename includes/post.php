<?php
// public/post.php 

define('SECURE_ACCESS', true);

// **2. DEFINE O AMBIENTE AQUI (apenas uma vez)!**
define('ENVIRONMENT', 'development'); // Usa 'development' durante o desenvolvimento

// 3. Inclui o arquivo de configuração.
require_once __DIR__ . '/../includes/config.php';

// 4. Inclui outros arquivos essenciais (db, functions, se tiveres).
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

if (!is_logged_in()) {
    set_message("Você precisa estar logado para acessar as publicações.", "danger");
    redirect(BASE_URL . 'login.php');
}

// --- LÓGICA DE DADOS (Antes de qualquer saída HTML) ---

// Obter o ID do item do feed da URL
$feed_item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$feed_item_id) {
    set_message("Item do feed não especificado.", "danger");
    redirect(BASE_URL);
}

// Obter o item do feed
$feed_item = FeedItem::getFeedItemById($pdo, $feed_item_id);

if (!$feed_item) {
    set_message("Conteúdo não encontrado no feed.", "danger");
    redirect(BASE_URL);
}

$item_type = $feed_item['item_type'];
$original_item_id = $feed_item['item_id'];

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
    set_message("Detalhes do conteúdo não encontrados.", "danger");
    redirect(BASE_URL);
}

$author = User::getUserById($pdo, $feed_item['user_id']);
if (!$author) {
    set_message("Autor da postagem não encontrado.", "danger");
    redirect(BASE_URL);
}

$current_user_id = is_logged_in() ? get_current_user_id() : null;
$like_info = Like::getFeedItemLikesDislikesCount($pdo, $feed_item_id);
$user_vote = is_logged_in() ? Like::getUserFeedItemVote($pdo, $feed_item_id, $current_user_id) : null;
$is_post_owner = ($current_user_id && $author['id'] == $current_user_id);
$is_admin = isset($_SESSION['admin_id']);

// Verificar se é um repost para exibição
$isRepost = false;
$sharedAuthorName = null;
$sharedType = null;
$sharedId = null;

if ($item_type === 'post' && !empty($content_data['is_repost']) && !empty($content_data['shared_post_id']) && !empty($content_data['shared_item_type'])) {
    $isRepost = true;
    $sharedType = $content_data['shared_item_type'];
    $sharedId = (int)$content_data['shared_post_id'];

    $sharedData = null;
    switch ($sharedType) {
        case 'post':
            $sharedData = Post::getPostById($pdo, $sharedId);
            break;
        case 'video':
            $sharedData = Video::getVideoById($pdo, $sharedId);
            break;
        case 'album':
            $sharedData = Album::getAlbumById($pdo, $sharedId);
            break;
    }

    if ($sharedData) {
        $sharedAuthor = User::getUserById($pdo, $sharedData['user_id']);
        $sharedAuthorName = $sharedAuthor['username'] ?? 'Usuário';
        // Para exibição do conteúdo no modal, usamos os dados do original
        $content_data = $sharedData;
        $original_item_id = $sharedId;
        $item_type = $sharedType;
    }
}

// Verificar privacidade do perfil do autor (do post atual, não do compartilhado)
$author_privacy = $author['profile_privacy'] ?? 'public';
if ($author_privacy === 'followers' && !$is_post_owner && !$is_admin) {
    $is_following_author = is_logged_in() ? User::isFollowing($pdo, $current_user_id, $author['id']) : false;
    $is_mutual_with_author = is_logged_in() ? User::isMutualFollower($pdo, $current_user_id, $author['id']) : false;

    if (!$is_following_author && !$is_mutual_with_author) {
        set_message("Este conteúdo é privado. Você precisa seguir o usuário para ver esta publicação.", "danger");
        redirect(BASE_URL . 'index.php');
        exit();
    }
}

// Verificar se o conteúdo está aprovado
$is_approved = $content_data['is_approved'] ?? 1;
if (!$is_approved && !$is_post_owner && !$is_admin) {
    set_message("Este conteúdo está aguardando aprovação administrativa.", "warning");
    redirect(BASE_URL);
}

$comment_tree = Comment::getCommentsForFeedItem($pdo, $feed_item_id, $current_user_id);
$comment_count = Comment::getCommentCountForFeedItem($pdo, $feed_item_id);

$logged_in_user_profile_pic = 'profiles/default_profile.png';
if (is_logged_in()) {
    $logged_in_user_data = User::getUserById($pdo, $current_user_id);
    if ($logged_in_user_data && !empty($logged_in_user_data['profile_picture'])) {
        $logged_in_user_profile_pic = $logged_in_user_data['profile_picture'];
    }
}

// Verificar se é uma requisição AJAX para o modal
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/post.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modules/icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/comments_new.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/comment.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/cards.css">

<section class="single-post-section">
    <div class="main-layout-container">
        <main class="main-content-area full-width">
            <article class="post-card" data-feed-item-id="<?= htmlspecialchars($feed_item_id) ?>">
                <!-- ============================================
         HEADER: Informações do Autor
         ============================================ -->
                <header class="post-card-header">
                    <div class="author-info">
                        <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'default_profile.png') ?>"
                            alt="Foto de perfil de <?= htmlspecialchars($author['username']) ?>"
                            class="profile-thumb">

                        <?php if ($isRepost && $sharedAuthor): ?>
                            <img src="<?= UPLOAD_URL . htmlspecialchars($sharedAuthor['profile_picture'] ?? 'default_profile.png') ?>"
                                alt="Repostagem de <?= htmlspecialchars($sharedAuthor['username']) ?>"
                                class="profile-thumb-secondary">
                        <?php endif; ?>

                        <div class="text-info">
                            <div class="author-line">
                                <a href="<?= BASE_URL ?>profile.php?id=<?= htmlspecialchars($author['id']) ?>"
                                    class="post-author">
                                    <?= htmlspecialchars($author['username']) ?>
                                </a>

                                <?php if (is_logged_in() && !$is_post_owner): ?>
                                    <?php
                                    $is_following = User::isFollowing($pdo, $current_user_id, $author['id']);
                                    $has_request = User::hasPendingFollowRequest($pdo, $current_user_id, $author['id']);
                                    $follow_label = $is_following ? 'Seguindo' : ($has_request ? 'Pendente' : 'Seguir');
                                    $follow_class = $is_following ? 'following' : ($has_request ? 'pending' : '');
                                    ?>
                                    <button class="follow-btn-mini <?= $follow_class ?>"
                                        onclick="App.toggleFollow(<?= (int)$author['id'] ?>, this)"
                                        data-user-id="<?= (int)$author['id'] ?>"
                                        aria-label="<?= htmlspecialchars($follow_label) ?>">
                                        <?= $follow_label ?>
                                    </button>
                                <?php endif; ?>

                                <?php if ($isRepost && $sharedAuthor): ?>
                                    <div class="repost-info">
                                        <i class="fa-solid fa-retweet repost-icon-small" aria-hidden="true"></i>
                                        <a href="<?= BASE_URL ?>profile.php?id=<?= $sharedAuthor['id'] ?>"
                                            class="shared-username-link">
                                            <?= htmlspecialchars($sharedAuthor['username']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if ($is_post_owner && isset($content_data['is_for_sale']) && $content_data['is_for_sale']): ?>
                                    <div class="sale-indicator">
                                        <i class="fas fa-tag" aria-hidden="true"></i> À VENDA
                                        <?php if (isset($content_data['is_approved']) && !$content_data['is_approved']): ?>
                                            <span class="status-badge pending">
                                                <i class="fas fa-clock" aria-hidden="true"></i> Pendente
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge active">
                                                <i class="fas fa-check" aria-hidden="true"></i> Ativo
                                            </span>
                                        <?php endif; ?>
                                        <a href="sales_performance.php?type=<?= $item_type ?>&id=<?= $original_item_id ?>"
                                            title="Ver Desempenho de Vendas"
                                            class="sales-chart-link"
                                            aria-label="Ver desempenho de vendas">
                                            <i class="fas fa-chart-bar" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="post-date" aria-label="Data da publicação">
                                <?= format_datetime_ago($feed_item['created_at']) ?>
                            </span>
                        </div>
                    </div>
                </header>

                <!-- ============================================
         MEDIA: Conteúdo (Imagem, Vídeo, Álbum)
         ============================================ -->
                <div class="post-card-media">
                    <?php
                    $paymentService = new \Massango\Services\PaymentService($pdo);
                    $hasAccess = $paymentService->hasAccess($current_user_id ?? 0, $item_type, $original_item_id);
                    if ($is_admin) $hasAccess = true;
                    ?>

                    <?php if ($item_type === 'post'): ?>
                        <?php if (isset($content_data['post_type']) && $content_data['post_type'] === 'text'): ?>
                            <div class="post-text ql-editor"><?= $content_data['content'] ?></div>
                        <?php else: ?>
                            <?php if (!empty($content_data['image_path'])): ?>
                                <?php if ($hasAccess): ?>
                                    <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['image_path']) ?>"
                                        alt="Imagem da publicação"
                                        class="post-media-image">
                                <?php else: ?>
                                    <div class="content-locked" data-is-paid="<?= (isset($content_data['is_for_sale']) && $content_data['is_for_sale']) ? 'true' : 'false' ?>">
                                        <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path'] ?: $content_data['image_path']) ?>"
                                            alt="Imagem da publicação (bloqueada)"
                                            class="content-thumbnail">
                                        <div class="lock-overlay">
                                            <i class="fas fa-lock" aria-hidden="true"></i>
                                            <p><?= htmlspecialchars(getLockedContentMessage($content_data, $user_data['is_verified_creator'] ?? 0)) ?></p>
                                            <button class="btn btn-primary"
                                                onclick="<?= ($user_data['is_verified_creator'] ?? 0) ? "window.location.href='checkout.php?type=post&id=" . $original_item_id . "'" : "openVerificationInviteModal()" ?>"
                                                aria-label="Comprar conteúdo">
                                                <?= ($user_data['is_verified_creator'] ?? 0) ? 'Comprar' : 'Verificar Conta' ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php elseif ($item_type === 'video'): ?>
                        <?php if ($hasAccess): ?>
                            <video src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                                controls
                                class="post-media-video"
                                aria-label="Vídeo da publicação"></video>
                        <?php else: ?>
                            <div class="content-locked" data-is-paid="<?= (isset($content_data['is_for_sale']) && $content_data['is_for_sale']) ? 'true' : 'false' ?>">
                                <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path']) ?>"
                                    alt="Miniatura do vídeo (bloqueada)"
                                    class="content-thumbnail">
                                <div class="lock-overlay">
                                    <i class="fas fa-lock" aria-hidden="true"></i>
                                    <i class="fa-solid fa-play" aria-hidden="true"></i>
                                    <div class="view-count">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        <?= number_format($content_data['views_count'] ?? 0) ?> visualizações
                                    </div>
                                    <p><?= htmlspecialchars(getLockedContentMessage($content_data, $user_data['is_verified_creator'] ?? 0)) ?></p>
                                    <button class="btn btn-primary"
                                        onclick="<?= ($user_data['is_verified_creator'] ?? 0) ? "window.location.href='checkout.php?type=video&id=" . $original_item_id . "'" : "openVerificationInviteModal()" ?>"
                                        aria-label="Comprar vídeo">
                                        <?= ($user_data['is_verified_creator'] ?? 0) ? 'Comprar' : 'Verificar Conta' ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($item_type === 'album'): ?>
                        <div class="album-content">
                            <h3 class="album-title"><?= htmlspecialchars($content_data['album_name'] ?? $content_data['name'] ?? '') ?></h3>
                            <?php if ($hasAccess): ?>
                                <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['cover_photo_url']) ?>"
                                    alt="Capa do álbum"
                                    class="album-cover-image">
                                <a href="<?= BASE_URL ?>view_album.php?id=<?= $original_item_id ?>"
                                    class="btn btn-primary btn-block"
                                    aria-label="Ver álbum completo">
                                    Ver Álbum
                                </a>
                            <?php else: ?>
                                <div class="content-locked" data-is-paid="<?= (isset($content_data['is_for_sale']) && $content_data['is_for_sale']) ? 'true' : 'false' ?>">
                                    <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['cover_photo_url']) ?>"
                                        alt="Capa do álbum (bloqueada)"
                                        class="content-thumbnail">
                                    <div class="lock-overlay">
                                        <i class="fas fa-lock" aria-hidden="true"></i>
                                        <p><?= htmlspecialchars(getLockedContentMessage($content_data, $user_data['is_verified_creator'] ?? 0)) ?></p>
                                        <button class="btn btn-primary"
                                            onclick="window.location.href='checkout.php?type=album&id=<?= $original_item_id ?>'"
                                            aria-label="Comprar álbum">
                                            Comprar Álbum
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ============================================
         INFO: Descrição/Caption
         ============================================ -->
                <div class="post-card-info">
                    <?php if ($item_type === 'post' && !empty($content_data['content'])): ?>
                        <p class="post-text"><?= nl2br(htmlspecialchars($content_data['content'])) ?></p>
                    <?php elseif ($item_type === 'video' && !empty($content_data['caption'])): ?>
                        <p class="post-text"><?= nl2br(htmlspecialchars($content_data['caption'])) ?></p>
                    <?php elseif ($item_type === 'album' && !empty($content_data['album_description'])): ?>
                        <p class="post-text"><?= nl2br(htmlspecialchars($content_data['album_description'] ?? $content_data['description'] ?? '')) ?></p>
                    <?php endif; ?>
                </div>

                <!-- ============================================
         ACTIONS: Likes, Comentários, Compartilhamento
         ============================================ -->
                <div class="post-card-actions">
                    <button class="btn btn-action btn-like <?= ($user_vote === 'like' ? 'active' : '') ?>"
                        data-feed-item-id="<?= htmlspecialchars($feed_item_id) ?>"
                        data-action="like"
                        aria-label="Curtir publicação">
                        <i class="fa-solid fa-thumbs-up" aria-hidden="true"></i>
                        <span class="action-count"><?= $like_info['likes'] ?></span>
                    </button>

                    <button class="btn btn-action btn-dislike <?= ($user_vote === 'dislike' ? 'active' : '') ?>"
                        data-feed-item-id="<?= htmlspecialchars($feed_item_id) ?>"
                        data-action="dislike"
                        aria-label="Descurtir publicação">
                        <i class="fa-solid fa-thumbs-down" aria-hidden="true"></i>
                        <span class="action-count"><?= $like_info['dislikes'] ?></span>
                    </button>

                    <?php
                    $share_count = \Massango\Models\Post::getShareCount($pdo, (int)$feed_item_id);
                    $can_link = $content_data['allow_share_link'] ?? 1;
                    $can_repost = $content_data['allow_share_repost'] ?? 1;
                    ?>

                    <div class="share-container">
                        <button class="btn btn-action btn-share"
                            onclick="toggleShareMenu(<?= $feed_item_id ?>)"
                            aria-label="Compartilhar publicação"
                            aria-expanded="false"
                            aria-controls="share-menu-<?= $feed_item_id ?>">
                            <i class="fa-solid fa-share" aria-hidden="true"></i>
                            <span class="action-count"><?= $share_count ?></span>
                        </button>

                        <div id="share-menu-<?= $feed_item_id ?>"
                            class="share-dropdown"
                            style="display: none;"
                            role="menu">
                            <ul role="none">
                                <?php if ($can_repost): ?>
                                    <li role="none">
                                        <button role="menuitem"
                                            onclick="handleRepost(<?= $feed_item_id ?>)"
                                            class="share-option">
                                            <i class="fa-solid fa-retweet" aria-hidden="true"></i> Repostar
                                        </button>
                                    </li>
                                <?php endif; ?>

                                <?php if ($can_link): ?>
                                    <li role="none">
                                        <button role="menuitem"
                                            onclick="copyToClipboard('<?= BASE_URL ?>post.php?id=<?= $feed_item_id ?>', <?= $feed_item_id ?>)"
                                            class="share-option">
                                            <i class="fa-solid fa-link" aria-hidden="true"></i> Copiar Link
                                        </button>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ============================================
         COMMENTS: Seção de Comentários
         ============================================ -->
                <div class="post-card-comments" data-feed-item-id="<?= htmlspecialchars($feed_item_id) ?>" role="region" aria-label="Comentários">
                    <?php if (is_logged_in()): ?>
                        <div class="comment-form-container">
                            <div class="comment-input-area">
                                <img src="<?= UPLOAD_URL . htmlspecialchars($logged_in_user_profile_pic) ?>"
                                    alt="Sua foto de perfil"
                                    class="profile-thumb-small">
                                <textarea class="comment-textarea"
                                    placeholder="Adicione um comentário..."
                                    data-feed-item-id="<?= htmlspecialchars($feed_item_id) ?>"
                                    aria-label="Campo de comentário"></textarea>
                                <button class="btn btn-primary btn-send-comment"
                                    onclick="postComment(this)"
                                    aria-label="Postar comentário">
                                    Postar
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="login-to-comment">
                            Faça <a href="<?= BASE_URL ?>login.php" style="color: var(--primary); text-decoration: none;">login</a> para comentar.
                        </p>
                    <?php endif; ?>

                    <div class="comments-content">
                        <div class="comments-list" role="list">
                            <?php if (!empty($comment_tree)): ?>
                                <?php display_comments($comment_tree, $current_user_id, $is_post_owner, $pdo); ?>
                            <?php else: ?>
                                <p class="no-comments-yet">
                                    Nenhum comentário ainda. Seja o primeiro a comentar!
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </article>

            <?php
            /**
             * Função auxiliar para obter mensagem de conteúdo bloqueado
             */
            function getLockedContentMessage($contentData, $userIsVerified)
            {
                if (!$userIsVerified) {
                    return 'Conteúdo Verificado: Faça upgrade para acessar';
                }
                return sprintf(
                    'Conteúdo Pago: %s MT',
                    number_format($contentData['price'] ?? 0, 2, ',', '.')
                );
            }
            ?>