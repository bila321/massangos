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


<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/premium_lightbox.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/card-modern.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/repost-header.css">

<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/pages/media-backdrop.js" defer></script>

<div class="posts-list">
    <?php if (!empty($feedItems)): ?>
        <?php foreach ($feedItems as $item): ?>
            <?php
            // Se existir type, incluir o componente correspondente e continuar
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

            // Todos os dados já vêm enriquecidos pelo FeedController::load()
            $content_data  = $item['content_data'];
            $author        = $item['author'];
            $like_info     = $item['like_info'];
            $user_vote     = $item['user_vote'];
            $comment_count = $item['comment_count'];
            $ai_analysis   = $item['ai_analysis'] ?? null;
            $is_post_owner = $item['is_post_owner'];
            $is_admin      = $item['is_admin'];
            $should_blur   = $item['should_blur'];
            ?>

            <?php
            // Repost e blur já vêm resolvidos do FeedController::load()
            $isRepost     = $item['isRepost'];
            $sharedData   = $item['sharedData'];
            $sharedType   = $item['sharedType'];
            $sharedAuthor = $item['sharedAuthor'];
            $sharedId     = $item['sharedId'] ?? null;
            ?>
            <article class="post-card card feed-item-wrapper <?= ($item['item_type'] === 'album' ? 'album-card-style' : '') ?>"
                data-type="all"
                data-feed-item-id="<?= htmlspecialchars($item['feed_item_id']) ?>">

                <div class="post-header">
                    <div class="header-author-wrapper">
                        <div class="avatar-container">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'default_profile.png') ?>" alt="Foto de perfil" class="profile-thumb">
                            <?php if ($isRepost && $sharedAuthor): ?>
                                <img src="<?= UPLOAD_URL . htmlspecialchars($sharedAuthor['profile_picture'] ?? 'default_profile.png') ?>" alt="Original" class="profile-thumb-secondary">
                            <?php endif; ?>
                        </div>
                        <div class="post-info">
                            <div class="author-line">
                                <a href="<?= BASE_URL ?>profile.php?id=<?= htmlspecialchars($author['id']) ?>" class="post-author"><?= htmlspecialchars($author['username']) ?></a><?php if (isset($author['is_verified']) && $author['is_verified']): ?><button class="verficacao-btn-mini" title="Conta Verificada"><i class="fa-solid fa-circle-check"></i> VERIFICADO</button><?php endif; ?>
                                <?php if (is_logged_in() && !$is_post_owner): ?>
                                    <?php
                                    $follow_label = $item['follow_label'];
                                    $follow_class = $item['follow_class'];
                                    ?>
                                    <button class="follow-btn-mini <?= $follow_class ?>"
                                        onclick="App.toggleFollow(<?= (int)$author['id'] ?>, this)"
                                        data-user-id="<?= (int)$author['id'] ?>">
                                        <?= $follow_label ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($isRepost && $sharedAuthor): ?>
                                    <i class="fa-solid fa-arrows-retweet repost-icon-small"></i>
                                    <a href="<?= BASE_URL ?>profile.php?id=<?= $sharedAuthor['id'] ?>" class="shared-username-link">
                                        <?= htmlspecialchars($sharedAuthor['username']) ?>
                                    </a><?php if (isset($sharedAuthor['is_verified']) && $sharedAuthor['is_verified']): ?><button class="verficacao-btn-mini" title="Conta Verificada"><i class="fa-solid fa-circle-check"></i> VERIFICADO</button><?php endif; ?><?php if (is_logged_in() && $current_user_id != $sharedAuthor['id']): ?><?php $is_following_shared = User::isFollowing($pdo, $current_user_id, $sharedAuthor['id']);
                                                                                                                                                                                                                                                                                                                                            $has_request_shared = User::hasPendingFollowRequest($pdo, $current_user_id, $sharedAuthor['id']);
                                                                                                                                                                                                                                                                                                                                            $follow_label_shared = $is_following_shared ? 'Seguindo' : ($has_request_shared ? 'Pendente' : 'Seguir');
                                                                                                                                                                                                                                                                                                                                            $follow_class_shared = $is_following_shared ? 'following' : ($has_request_shared ? 'pending' : ''); ?><button class="follow-btn-mini <?= $follow_class_shared ?>" onclick="App.toggleFollow(<?= (int)$sharedAuthor['id'] ?>, this)" data-user-id="<?= (int)$sharedAuthor['id'] ?>"><?= $follow_label_shared ?></button><?php endif; ?>
                                <?php endif; ?>
                                <?php
                                $is_shared_owner = $isRepost && isset($sharedData['user_id']) && $sharedData['user_id'] == $current_user_id;
                                $can_see_sale_indicator = (isset($item['is_for_sale']) && $item['is_for_sale'] && ($is_post_owner || $is_admin))
                                    || ($isRepost && isset($sharedData['is_for_sale']) && $sharedData['is_for_sale'] && ($is_shared_owner || $is_admin));
                                ?>
                                <?php if ($can_see_sale_indicator): ?>
                                    <div class="sale-indicator" style="background: var(--premium-gradient); color: white; padding: 5px 5px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="fa-solid fa-tag"></i> À VENDA
                                        <?php if (isset($item['is_approved']) && !$item['is_approved']): ?>
                                            <span style="background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; margin-left: 5px;"><i class="fa-regular fa-hourglass-half"></i> Pendente</span>
                                        <?php else: ?>
                                            <span style="background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; margin-left: 5px;"><i class="fa-solid fa-circle-check"></i> Ativo</span>
                                        <?php endif; ?>
                                        <a href="sales_performance.php?type=<?= $item['item_type'] ?>&id=<?= $item['id'] ?>" title="Ver Desempenho de Vendas" style="color: white; margin-left: 10px; background: rgba(255,255,255,0.2); width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; text-decoration: none;">
                                            <i class="fa-solid fa-chart-line" style="font-size: 0.7rem;"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="post-date"><?= format_datetime_ago($item['created_at']) ?></span>
                            <?php if ($ai_analysis): ?>
                                <?php if ($ai_analysis['status'] === 'processing' || $ai_analysis['status'] === 'pending'): ?>
                                    <span class="ai-badge ai-badge-analyzing"><i class="fa-regular fa-wand-magic-sparkles"></i> Em análise</span>
                                <?php elseif ($ai_analysis['status'] === 'done'): ?>
                                    <?php if ($ai_analysis['risk_level'] === 'low'): ?>
                                    <?php elseif ($ai_analysis['risk_level'] === 'medium'): ?>
                                        <span class="ai-badge ai-badge-sensitive"><i class="fa-solid fa-circle-dot"></i></span>
                                    <?php elseif ($ai_analysis['risk_level'] === 'high'): ?>
                                        <span class="ai-badge ai-badge-high"><i class="fa-solid fa-circle-dot"></i></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="header-right-actions">
                        <?php if (is_logged_in() && !$is_post_owner): ?>
                            <button class="hide-post-btn" onclick="hidePost(<?= (int)$item['feed_item_id'] ?>, this)" title="Ocultar publicação">
                                <i class="fa-regular fa-eye-slash"></i>
                            </button>
                        <?php endif; ?>

                        <?php if ($is_post_owner || $is_admin): ?>
                            <div class="post-actions-dropdown">
                                <button class="dropdown-toggle" aria-label="Opções do post">&#x22EE;</button>
                                <div class="dropdown-menu">
                                    <?php if ($item['item_type'] === 'post'): ?>
                                        <?php if ($is_post_owner): ?>
                                            <a href="<?= BASE_URL ?>edit_post.php?id=<?= htmlspecialchars($item['item_id']) ?>&redirect_to=index.php">Editar Publicação</a>
                                        <?php endif; ?>
                                        <form action="<?= BASE_URL ?>actions/post.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar esta publicação?');">
                                            <input type="hidden" name="action" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                            <input type="hidden" name="redirect_to" value="index.php">
                                            <button type="submit"><?= $is_admin && !$is_post_owner ? 'Bloquear/Apagar' : 'Apagar Publicação' ?></button>
                                        </form>
                                    <?php elseif ($item['item_type'] === 'video'): ?>
                                        <?php if ($is_post_owner): ?>
                                            <a href="<?= BASE_URL ?>edit_video.php?id=<?= htmlspecialchars($item['item_id']) ?>&redirect_to=index.php">Editar Vídeo</a>
                                        <?php endif; ?>
                                        <form action="<?= BASE_URL ?>actions/video.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar este vídeo?');">
                                            <input type="hidden" name="action" value="delete_video">
                                            <input type="hidden" name="video_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                            <input type="hidden" name="redirect_to" value="index.php">
                                            <button type="submit"><?= $is_admin && !$is_post_owner ? 'Bloquear/Apagar' : 'Apagar Vídeo' ?></button>
                                        </form>
                                    <?php elseif ($item['item_type'] === 'album'): ?>
                                        <?php if ($is_post_owner): ?>
                                            <a href="<?= BASE_URL ?>edit_album.php?id=<?= htmlspecialchars($item['item_id']) ?>&redirect_to=index.php">Editar Álbum</a>
                                        <?php endif; ?>
                                        <form action="<?= BASE_URL ?>actions/album.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar este álbum?');">
                                            <input type="hidden" name="action" value="delete_album">
                                            <input type="hidden" name="album_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                            <input type="hidden" name="redirect_to" value="index.php">
                                            <button type="submit"><?= $is_admin && !$is_post_owner ? 'Bloquear/Apagar' : 'Apagar Álbum' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="post-content">
                    <?php if ($isRepost && $sharedData && $sharedAuthor): ?>
                        <div class="original-content-container">
                            <?php
                            $paymentService = new \Massango\Services\PaymentService($pdo);
                            $hasAccessShared = $paymentService->hasAccess($current_user_id ?? 0, $sharedType, $sharedData['id'] ?? $sharedData['item_id'] ?? 0);
                            if ($is_admin) $hasAccessShared = true;
                            ?>
                            <?php if (isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']): ?>
                                <div class="paid-content-badge" style="background:  var(--primary-gradient); color: #3b3b3b; padding: 5px 10px; border-radius: 5px; font-size: 0.8em; font-weight: bold; display: inline-block; margin-left: 10px;">
                                    <i class="fa-regular fa-lock"></i> CONTEÚDO PAGO: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT
                                </div>
                            <?php else: ?>
                            <?php endif; ?>
                            <?php if ($sharedType === 'post'): ?>
                                <!-- Descricao da Foto (opcional) -->
                                <?php if (!empty($sharedData['content'])): ?>
                                    <div class="post-content">
                                        <p class="post-text"><?= nl2br(htmlspecialchars($sharedData['content'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasAccessShared): ?>
                                    <?php if (isset($sharedData['post_type']) && $sharedData['post_type'] === 'text'): ?>
                                    <?php else: ?>
                                        <!-- Post de Foto -->
                                        <?php if (!empty($sharedData["image_path"])): ?>
                                            <div class="media-wrapper-<?= htmlspecialchars($item["feed_item_id"]) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                                                <div class="post-image-container"
                                                    data-post-modal="<?= htmlspecialchars($item["feed_item_id"]) ?>"
                                                    style="cursor: pointer;">
                                                    <?php
                                                    $display_image = !empty($sharedData["thumbnail_path"]) ? $sharedData["thumbnail_path"] : $sharedData["image_path"];
                                                    ?>
                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($display_image) ?>" alt="Imagem do Post" class="post-image <?= $should_blur ? 'media-blur' : '' ?>" data-is-paid="<?= (isset($sharedData["is_for_sale"]) && $sharedData["is_for_sale"] ? 'true' : 'false') ?>">
                                                </div>
                                                <?php if ($should_blur): ?>
                                                    <div class="media-overlay-msg">
                                                        <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                        <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                                                        <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item["feed_item_id"]) ?>')">Ver mesmo assim</button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (($user_data['is_verified_creator'] ?? 0)): ?>
                                        <div class="post-locked" onclick="pageModalLoader.open('checkout.php?type=post&id=<?= $sharedId ?>')">
                                            <img src="<?= UPLOAD_URL . htmlspecialchars($sharedData['thumbnail_path'] ?: $sharedData['image_path']) ?>" alt="Imagem do Post" style="filter: blur(20px);" data-is-paid="<?= (isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']) ? 'true' : 'false' ?>">
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff;">
                                                <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                                                <p>Conteúdo Pago: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT</p>
                                                <span style="font-size: 0.8em; text-decoration: underline;">Clique para comprar</span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="post-locked" onclick="openVerificationInviteModal()">
                                            <img src="<?= UPLOAD_URL . htmlspecialchars($sharedData['thumbnail_path'] ?: $sharedData['image_path']) ?>" alt="Imagem do Post" style="filter: blur(20px);" data-is-paid="<?= (isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']) ? 'true' : 'false' ?>">
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff;">
                                                <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                                                <p>Conteúdo Pago: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT</p>
                                                <span style="font-size: 0.8em; text-decoration: underline;">Clique para comprar</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php elseif ($sharedType === 'video'): ?>
                                <?php if (!empty($sharedData['caption'])): ?>
                                    <div class="post-content">
                                        <p class="post-text"><?= nl2br(htmlspecialchars($sharedData['caption'] ?? '')) ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($sharedData['video_path'])): ?>
                                    <?php
                                    // CORREÇÃO: Verifica acesso usando $sharedId (ID do vídeo original)
                                    $paymentService = new \Massango\Services\PaymentService($pdo);
                                    $hasAccessShared = $paymentService->hasAccess($current_user_id ?? 0, 'video', $sharedId);

                                    // CORREÇÃO: Verifica se é vídeo pago
                                    $isForSaleShared = isset($sharedData['is_for_sale']) && $sharedData['is_for_sale'];
                                    ?>

                                    <?php if ($hasAccessShared || !$isForSaleShared): ?>
                                        <!-- VÍDEO COMPARTILHADO COM ACESSO - Estrutura idêntica ao content_data -->
                                        <div class="media-wrapper-<?= htmlspecialchars($item["feed_item_id"]) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                                            <div class="video-locked lightbox-trigger"
                                                data-type="video"
                                                data-id="<?= htmlspecialchars($item["feed_item_id"]) ?>"
                                                data-item-id="<?= htmlspecialchars($sharedId) ?>"
                                                data-item-type="video"
                                                data-src="<?= UPLOAD_URL . htmlspecialchars($sharedData["video_path"]) ?>"
                                                data-is-for-sale="<?= $isForSaleShared ? 'true' : 'false' ?>"
                                                data-price="<?= $sharedData["price"] ?? 0 ?>"
                                                data-has-access="true"
                                                data-thumbnail="<?= htmlspecialchars(get_video_thumb_url($sharedData["thumbnail_path"] ?? '')) ?>"
                                                data-ai-status="<?= htmlspecialchars($ai_analysis['status'] ?? '') ?>"
                                                data-ai-risk="<?= htmlspecialchars($ai_analysis['risk_level'] ?? '') ?>"
                                                data-ai-score="<?= htmlspecialchars($ai_analysis['explicit_percentage'] ?? 0) ?>"
                                                onclick="if(typeof sendViewRequest === 'function') sendViewRequest('video', <?= (int)$item["item_id"] ?>, <?= (int)$item["feed_item_id"] ?>)"

                                                style="position: relative; overflow: hidden; cursor: pointer;">
                                                <?php if (!empty($sharedData["thumbnail_path"])): ?>
                                                    <img src="<?= htmlspecialchars(get_video_thumb_url($sharedData["thumbnail_path"])) ?>" class="post-image <?= $should_blur ? 'media-blur' : '' ?>" style="display: block; width: 100%;">
                                                <?php else: ?>
                                                    <video src="<?= UPLOAD_URL . htmlspecialchars($sharedData["video_path"]) ?>"
                                                        class="post-image <?= $should_blur ? 'media-blur' : '' ?>"
                                                        style="width: 100%; display: block;"
                                                        preload="metadata"
                                                        data-item-type="video"
                                                        data-item-id="<?= (int)$item["item_id"] ?>"
                                                        muted
                                                        playsinline></video>
                                                <?php endif; ?>
                                                <?php if ($should_blur): ?>
                                                    <div class="media-overlay-msg">
                                                        <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                        <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                                                        <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item["feed_item_id"]) ?>')">Ver mesmo assim</button>
                                                    </div>
                                                <?php endif; ?>


                                                <!-- Overlay de Play -->
                                                <div class="video-play-overlay"
                                                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                            color: white; font-size: 3rem; opacity: 0.9; pointer-events: none;
                            background: rgba(0,0,0,0.3); border-radius: 50%; width: 80px; height: 80px;
                            display: flex; align-items: center; justify-content: center;">
                                                    <i class="fa-solid fa-play" style="margin-left: 8px;"></i>
                                                </div>

                                                <!-- Stats -->
                                                <div class="video-stats"
                                                    style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 12px 8px; 
                            color: white; font-size: 0.9rem; pointer-events: none; 
                            background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.4) 50%, transparent 100%); 
                            z-index: 10; display: flex; align-items: center; gap: 8px;">
                                                    <i class="fa-solid fa-eye"></i>
                                                    <span data-views-id="video-<?= (int)$item['item_id'] ?>"><?= number_format($sharedData['views_count'] ?? 0) ?> visualizacoes</span><?php if (!(isset($sharedData['is_for_sale']) && $sharedData['is_for_sale'])): ?><span style="margin-left: auto; z-index: 111000; background: rgba(0,255,0,0.3); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;"><i class="fa-solid fa-play-circle"></i> Grátis</span><?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- VÍDEO BLOQUEADO À VENDA -->
                                            <?php
                                            $isVerifiedCreator = isset($logged_in_user_data['is_verified_creator']) ? $logged_in_user_data['is_verified_creator'] : 0;
                                            $checkoutUrl = BASE_URL . 'checkout.php?type=video&id=' . $item['item_id'];
                                            ?>

                                            <!-- ✅ CORRETO -->
                                            <div class="lightbox-trigger video-locked"
                                                data-type="video"
                                                data-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
                                                data-item-id="<?= htmlspecialchars($sharedId) ?>"
                                                data-item-type="video"
                                                data-src="<?= UPLOAD_URL . htmlspecialchars($sharedData['video_path']) ?>"
                                                data-is-for-sale="true"
                                                data-price="<?= $sharedData['price'] ?? 0 ?>"
                                                data-has-access="false"
                                                data-thumbnail="<?= htmlspecialchars(get_video_thumb_url($sharedData['thumbnail_path'] ?? '')) ?>"
                                                data-duration="<?= $sharedData['duration'] ?? 248 ?>"
                                                data-is-verified="<?= $isVerifiedCreator ? 'true' : 'false' ?>"
                                                data-checkout-url="<?= htmlspecialchars($checkoutUrl) ?>"
                                                style="cursor: pointer; position: relative;">

                                                <img src="<?= htmlspecialchars(get_video_thumb_url($sharedData['thumbnail_path'] ?? '')) ?>"
                                                    class="album-cover-image"
                                                    style="filter: blur(10px); width: 100%; display: block;"
                                                    data-is-paid="true">

                                                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; pointer-events: none;">
                                                    <span>
                                                        <i class="fa-solid fa-play" style="margin-bottom: 10px; padding: 5px;"></i>
                                                        <i class="fas fa-lock" style="margin-bottom: 10px; padding: 5px;"></i>
                                                    </span>
                                                    <div style="margin-top: 8px; color: #65676b;">
                                                        <i class="fa-solid fa-eye" style="color: #65676b; font-size: 1rem; margin-left: 10px;"></i>
                                                        <?= number_format($sharedData['views_count'] ?? 0) ?> visualizações
                                                    </div>
                                                    <p>Vídeo Pago: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT</p>
                                                    <span style="font-size: 0.8em; text-decoration: underline;">
                                                        <?= $isVerifiedCreator ? 'Clique para comprar' : 'Verifique sua conta para comprar' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php elseif ($sharedType === 'album'): ?>
                                    <div class="post-content">
                                        <h2 class="album-title"><?= htmlspecialchars($sharedData['album_name'] ?? 'Álbum sem Nome') ?></h2>
                                        <?php if (!empty($sharedData['description'])): ?>
                                            <p class="post-text"><?= nl2br(htmlspecialchars($sharedData['description'] ?? '')) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                    $paymentService = new \Massango\Services\PaymentService($pdo);
                                    $hasAccessShared = $paymentService->hasAccess($current_user_id ?? 0, $sharedType, $sharedData['id'] ?? $sharedData['item_id'] ?? 0);
                                    if (!empty($sharedData['cover_photo_url']))
                                        if ($hasAccessShared) {
                                            $album_blur_class = $should_blur ? 'album-blur-container' : '';
                                            echo '<div class="' . $album_blur_class . '" style="position: relative; display: block;">';
                                            echo '<a href="' . BASE_URL . 'view_album.php?id=' . htmlspecialchars($sharedId) . '" class="album-placeholder-link album-cover-link" data-item-id="' . (int)$sharedId . '" data-item-type="album">';
                                            $album_thumb = !empty($sharedData['thumbnail_path']) ? $sharedData['thumbnail_path'] : $sharedData['cover_photo_url'];
                                            echo render_adult_content('<img src="' . get_protected_media_url($album_thumb) . '" alt="Capa do Álbum" class="album-cover-image ' . ($should_blur ? 'album-blur' : '') . '" style="height: 520px; object-fit: contain; width: 100%; display: block;">', $sharedData);
                                            echo '</a>';

                                            if ($should_blur) {
                                                echo '<div class="album-overlay-msg">
                                                                        <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                                        <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                                                                        <button onclick="event.stopPropagation(); unblurAlbum(this)">Ver mesmo assim</button>
                                                                      </div>';
                                            }
                                            echo '</div>';
                                        } else {
                                            if (($user_data['is_verified_creator'] ?? 0)) {
                                                echo '<div class="album-locked" style="position: relative; cursor: pointer;" onclick="pageModalLoader.open(\'checkout.php?type=album&id=' . $sharedId . '\')">';
                                                $album_thumb = !empty($sharedData['thumbnail_path']) ? $sharedData['thumbnail_path'] : $sharedData['cover_photo_url'];
                                                echo '<img src="' . UPLOAD_URL . htmlspecialchars($album_thumb) . '" alt="Capa do Álbum" class="album-cover-image" style="filter: blur(8px); max-height: 360px;">';
                                                echo '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; background: rgba(0,0,0,0.4); border-radius: 8px;">';
                                                echo '<i class="fa-regular fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>';
                                                echo '<p>Álbum : ' . number_format($sharedData['price'], 2, ',', '.') . ' MT</p>';
                                                echo '</div>';
                                                echo '</div>';
                                            } else {
                                                echo '<div class="album-locked" style="position: relative; cursor: pointer;" onclick="openVerificationInviteModal()">';
                                                $album_thumb = !empty($sharedData['thumbnail_path']) ? $sharedData['thumbnail_path'] : $sharedData['cover_photo_url'];
                                                echo '<img src="' . UPLOAD_URL . htmlspecialchars($album_thumb) . '" alt="Capa do Álbum" class="album-cover-image" style="filter: blur(8px); max-height: 360px;">';
                                                echo '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; background: rgba(0,0,0,0.4); border-radius: 8px;">';
                                                echo '<i class="fa-regular fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>';
                                                echo '<p>Álbum : ' . number_format($sharedData['price'], 2, ',', '.') . ' MT</p>';
                                                echo '</div>';
                                                echo '</div>';
                                            }
                                        }
                                    else {
                                        echo '<a href="' . BASE_URL . 'view_album.php?id=' . htmlspecialchars($sharedId) . '" class="album-placeholder-link">';
                                        echo '<span class="overlay-text"></span>';
                                        echo '</a>';
                                    }
                                    ?>

                                <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($content_data['is_for_sale']) && $content_data['is_for_sale']): ?>
                                        <div class="paid-content-badge" style="background:  var(--primary-gradient); color: #3b3b3b; padding: 5px 10px; border-radius: 5px; font-size: 0.8em; font-weight: bold; margin-bottom: 10px; display: inline-block; margin-left:10px;">
                                            <i class="fa-regular fa-lock"></i> CONTEÚDO PAGO: <?= number_format($content_data['price'], 2, ',', '.') ?> MT
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($item['item_type'] === 'post'): ?>
                                        <!-- Descricao da Foto (opcional) -->
                                        <?php if (!empty($content_data['content'])): ?>
                                            <div class="post-content">
                                                <p class="post-text"><?= nl2br(htmlspecialchars($content_data['content'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <?php
                                        $paymentService = new \Massango\Services\PaymentService($pdo);
                                        $hasAccess = $paymentService->hasAccess($current_user_id ?? 0, 'post', $item['item_id']);
                                        ?>
                                        <?php if ($hasAccess): ?>
                                            <?php if (isset($content_data['post_type']) && $content_data['post_type'] === 'text'): ?>
                                            <?php else: ?>
                                                <!-- Post de Foto -->
                                                <?php if (!empty($content_data["image_path"])): ?>
                                                    <div class="media-wrapper-<?= htmlspecialchars($item["feed_item_id"]) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                                                        <div class="post-image-container"
                                                            data-post-modal="<?= htmlspecialchars($item["feed_item_id"]) ?>"
                                                            style="cursor: pointer;">
                                                            <?php
                                                            $display_image = !empty($content_data["thumbnail_path"]) ? $content_data["thumbnail_path"] : $content_data["image_path"];
                                                            ?>
                                                            <img src="<?= UPLOAD_URL . htmlspecialchars($display_image) ?>" alt="Imagem do Post" class="post-image <?= $should_blur ? 'media-blur' : '' ?>" data-is-paid="<?= (isset($content_data["is_for_sale"]) && $content_data["is_for_sale"] ? 'true' : 'false') ?>">
                                                        </div>
                                                        <?php if ($should_blur): ?>
                                                            <div class="media-overlay-msg">
                                                                <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                                <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                                                                <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item["feed_item_id"]) ?>')">Ver mesmo assim</button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>

                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if (($user_data['is_verified_creator'] ?? 0)): ?>
                                                <div class="post-locked" onclick="pageModalLoader.open('checkout.php?type=post&id=<?= $item['item_id'] ?>')">
                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path'] ?: $content_data['image_path']) ?>" alt="Imagem do Post" style="filter: blur(20px);" data-is-paid="<?= (isset($content_data['is_for_sale']) && $content_data['is_for_sale']) ? 'true' : 'false' ?>">
                                                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff;">
                                                        <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                                                        <p>Conteúdo Pago: <?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
                                                        <span style="font-size: 0.8em; text-decoration: underline;">Clique para comprar</span>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="post-locked" onclick="openVerificationInviteModal()">
                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path'] ?: $content_data['image_path']) ?>" alt="Imagem do Post" style="filter: blur(20px);" data-is-paid="<?= (isset($content_data['is_for_sale']) && $content_data['is_for_sale']) ? 'true' : 'false' ?>">
                                                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff;">
                                                        <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                                                        <p>Conteúdo Pago: <?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
                                                        <span style="font-size: 0.8em; text-decoration: underline;">Clique para comprar</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php elseif ($item['item_type'] === 'video'): ?>
                                        <?php if (!empty($content_data['caption'])): ?>
                                            <div class="post-content">
                                                <p class="post-text"><?= nl2br(htmlspecialchars($content_data['caption'] ?? '')) ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($content_data['video_path'])): ?>
                                            <?php
                                            $paymentService = new \Massango\Services\PaymentService($pdo);
                                            $hasAccess = $paymentService->hasAccess($current_user_id ?? 0, 'video', $item['item_id']);
                                            ?>

                                            <?php if ($hasAccess): ?>
                                                <!-- VÍDEO COM ACESSO - Estrutura limpa para lightbox -->
                                                <div class="media-wrapper-<?= htmlspecialchars($item["feed_item_id"]) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                                                    <div class="post-video lightbox-trigger"
                                                        data-type="video"
                                                        data-id="<?= htmlspecialchars($item["feed_item_id"]) ?>"
                                                        data-item-id="<?= htmlspecialchars($item["item_id"]) ?>"
                                                        data-item-type="video"
                                                        data-src="<?= UPLOAD_URL . htmlspecialchars($content_data["video_path"]) ?>"
                                                        data-is-for-sale="<?= (isset($content_data["is_for_sale"]) && $content_data["is_for_sale"]) ? 'true' : 'false' ?>"
                                                        data-price="<?= $content_data["price"] ?? 0 ?>"
                                                        data-has-access="true"
                                                        data-thumbnail="<?= htmlspecialchars(get_video_thumb_url($content_data["thumbnail_path"] ?? '')) ?>"
                                                        data-ai-status="<?= htmlspecialchars($ai_analysis['status'] ?? '') ?>"
                                                        data-ai-risk="<?= htmlspecialchars($ai_analysis['risk_level'] ?? '') ?>"
                                                        data-ai-score="<?= htmlspecialchars($ai_analysis['explicit_percentage'] ?? 0) ?>"
                                                        onclick="if(typeof sendViewRequest === 'function') sendViewRequest('video', <?= (int)$item["item_id"] ?>, <?= (int)$item["feed_item_id"] ?>)"

                                                        style="position: relative; overflow: hidden; cursor: pointer;">
                                                        <?php if (!empty($content_data["thumbnail_path"])): ?>
                                                            <img src="<?= htmlspecialchars(get_video_thumb_url($content_data["thumbnail_path"])) ?>" class="post-video <?= $should_blur ? 'media-blur' : '' ?>" style="display: block; width: 100%;">
                                                        <?php else: ?>
                                                            <video src="<?= UPLOAD_URL . htmlspecialchars($content_data["video_path"]) ?>"
                                                                class="post-image <?= $should_blur ? 'media-blur' : '' ?>"
                                                                style="width: 100%; display: block;"
                                                                preload="metadata"
                                                                data-item-type="video"
                                                                data-item-id="<?= (int)$item["item_id"] ?>"
                                                                muted
                                                                playsinline></video>
                                                        <?php endif; ?>
                                                        <?php if ($should_blur): ?>
                                                            <div class="media-overlay-msg">
                                                                <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                                <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                                                                <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item["feed_item_id"]) ?>')">Ver mesmo assim</button>
                                                            </div>
                                                        <?php endif; ?>


                                                        <!-- Overlay de Play -->
                                                        <div class="video-play-overlay"
                                                            style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                            color: white; font-size: 3rem; opacity: 0.9; pointer-events: none;
                            background: rgba(0,0,0,0.3); border-radius: 50%; width: 80px; height: 80px;
                            display: flex; align-items: center; justify-content: center;">
                                                            <i class="fa-solid fa-play" style="margin-left: 8px;"></i>
                                                        </div>

                                                        <!-- Stats -->
                                                        <div class="video-stats"
                                                            style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 12px 8px; 
                            color: white; font-size: 0.9rem; pointer-events: none; 
                            background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.4) 50%, transparent 100%); 
                            z-index: 10; display: flex; align-items: center; gap: 8px;">
                                                            <i class="fa-solid fa-eye"></i>
                                                            <span data-views-id="video-<?= (int)$item['item_id'] ?>"><?= number_format($content_data['views_count'] ?? 0) ?> visualizacoes</span><?php if (!(isset($content_data['is_for_sale']) && $content_data['is_for_sale'])): ?><span style="margin-left: auto; z-index: 111000; background: rgba(0,255,0,0.3); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;"><i class="fa-solid fa-play-circle"></i> Grátis</span><?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- VÍDEO BLOQUEADO À VENDA -->
                                                    <?php
                                                    $isVerifiedCreator = isset($logged_in_user_data['is_verified_creator']) ? $logged_in_user_data['is_verified_creator'] : 0;
                                                    $checkoutUrl = BASE_URL . 'checkout.php?type=video&id=' . $item['item_id'];
                                                    ?>

                                                    <!-- ✅ CORRETO -->
                                                    <div class="lightbox-trigger video-locked"
                                                        data-type="video"
                                                        data-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
                                                        data-item-id="<?= htmlspecialchars($item['item_id']) ?>"
                                                        data-item-type="video"
                                                        data-src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                                                        data-is-for-sale="true"
                                                        data-price="<?= $content_data['price'] ?? 0 ?>"
                                                        data-has-access="false"
                                                        data-thumbnail="<?= htmlspecialchars(get_video_thumb_url($content_data['thumbnail_path'] ?? '')) ?>"
                                                        data-duration="<?= $content_data['duration'] ?? 248 ?>"
                                                        data-is-verified="<?= $isVerifiedCreator ? 'true' : 'false' ?>"
                                                        data-checkout-url="<?= htmlspecialchars($checkoutUrl) ?>"
                                                        style="cursor: pointer; position: relative;">

                                                        <img src="<?= htmlspecialchars(get_video_thumb_url($content_data['thumbnail_path'] ?? '')) ?>"
                                                            class="album-cover-image"
                                                            style="filter: blur(10px); width: 100%; display: block;"
                                                            data-is-paid="true">

                                                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; pointer-events: none;">
                                                            <span>
                                                                <i class="fa-solid fa-play" style="margin-bottom: 10px; padding: 5px;"></i>
                                                                <i class="fas fa-lock" style="margin-bottom: 10px; padding: 5px;"></i>
                                                            </span>
                                                            <div style="margin-top: 8px; color: #65676b;">
                                                                <i class="fa-solid fa-eye" style="color: #65676b; font-size: 1rem; margin-left: 10px;"></i>
                                                                <?= number_format($content_data['views_count'] ?? 0) ?> visualizações
                                                            </div>
                                                            <p>Vídeo Pago: <?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
                                                            <span style="font-size: 0.8em; text-decoration: underline;">
                                                                <?= $isVerifiedCreator ? 'Clique para comprar' : 'Verifique sua conta para comprar' ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php elseif ($item['item_type'] === 'album'): ?>
                                            <div class="post-content">
                                                <h2 class="album-title"><?= htmlspecialchars($content_data['name'] ?? 'Álbum sem Nome') ?></h2>
                                                <?php if (!empty($content_data['description'])): ?>
                                                    <p class="post-text"><?= nl2br(htmlspecialchars($content_data['description'] ?? '')) ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <?php
                                            $paymentService = new \Massango\Services\PaymentService($pdo);
                                            $hasAccess = $paymentService->hasAccess($current_user_id ?? 0, 'album', $item['item_id']);

                                            if (!empty($content_data['cover_photo_url'])) {
                                                $album_thumb = !empty($content_data['thumbnail_path']) ? $content_data['thumbnail_path'] : $content_data['cover_photo_url'];

                                                if ($hasAccess) {
                                                    // [AI ALBUM FIX] Proper blur wrapper structure
                                                    $album_blur_class = $should_blur ? 'album-blur-container' : '';

                                                    echo '<div class="' . $album_blur_class . '" style="position: relative; display: block;">';
                                                    echo '<a href="' . BASE_URL . 'view_album.php?id=' . htmlspecialchars($item['item_id']) . '" class="album-placeholder-link album-cover-link" data-item-id="' . (int)$item['item_id'] . '" data-item-type="album">';
                                                    echo render_adult_content('<img src="' . get_protected_media_url($album_thumb) . '" alt="Capa do Álbum" class="album-cover-image ' . ($should_blur ? 'album-blur' : '') . '" style="object-fit: contain; width: 100%; display: block;">', $content_data);
                                                    echo '</a>';

                                                    // [AI ALBUM FIX] Overlay inside the container, properly positioned
                                                    if ($should_blur): ?>
                                                        <div class="album-overlay-msg">
                                                            <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                            <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                                                            <button onclick="event.stopPropagation(); unblurAlbum(this)">Ver mesmo assim</button>
                                                        </div>
                                            <?php endif;
                                                    echo '</div>';
                                                } elseif (($user_data['is_verified_creator'] ?? 0)) {
                                                    echo '<div class="album-locked" data-track-type="album" data-track-id="' . (int)$item['item_id'] . '" onclick="pageModalLoader.open(\'checkout.php?type=album&id=' . $item['item_id'] . '\')">';
                                                    echo '<img src="' . UPLOAD_URL . htmlspecialchars($album_thumb) . '" alt="Capa do Álbum" class="album-cover-image" style="filter: blur(15px); max-height: 500px; object-fit: contain; width: 100%; display: block;">';
                                                    echo '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; background: rgba(0,0,0,0.4); ">';
                                                    echo '<i class="fa-regular fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>';
                                                    echo '<p>Álbum Pago: ' . number_format($content_data['price'], 2, ',', '.') . ' MT</p>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                } else {
                                                    echo '<div class="album-locked" data-track-type="album" data-track-id="' . (int)$item['item_id'] . '" onclick="openVerificationInviteModal()">';
                                                    echo '<img src="' . UPLOAD_URL . htmlspecialchars($album_thumb) . '" alt="Capa do Álbum" class="album-cover-image" style="filter: blur(15px); max-height: 350px; object-fit: contain; width: 100%; display: block;">';
                                                    echo '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; background: rgba(0,0,0,0.4); ">';
                                                    echo '<i class="fa-regular fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>';
                                                    echo '<p>Álbum Pago: ' . number_format($content_data['price'], 2, ',', '.') . ' MT</p>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                }
                                            } else {
                                                echo '<a href="' . BASE_URL . 'view_album.php?id=' . htmlspecialchars($item['item_id']) . '" class="album-placeholder-link album-cover-link" data-item-id="' . (int)$item['item_id'] . '" data-item-type="album">';
                                                echo '<span class="overlay-text">Ver Álbum</span>';
                                                echo '</a>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                                </div>
                                                <div class="post-footer">
                                                    <div class="post-actions">

                                                        <!-- Like / Dislike pill (estilo YouTube) -->
                                                        <div class="yt-like-pill">
                                                            <button class="yt-action-btn btn-like <?= ($user_vote === 'like' ? 'active' : '') ?>"
                                                                data-feed-item-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
                                                                data-action="like"
                                                                title="Gosto">
                                                                <i class="fa-regular fa-star"></i>
                                                                <span class="likes-count"><?= $like_info['likes'] ?></span>
                                                            </button>
                                                        </div>

                                                        <!-- Comentar -->
                                                        <a href="<?= BASE_URL ?>post.php?id=<?= htmlspecialchars($item['feed_item_id']) ?>"
                                                            class="yt-action-btn yt-pill"
                                                            title="Comentar">
                                                            <i class="fa-regular fa-message"></i>
                                                            <span class="comment-count-display"><?= htmlspecialchars($comment_count) ?></span>
                                                        </a>

                                                        <?php
                                                        // share_count já vem em batch do FeedController
                                                        $share_count = $item['share_count'] ?? 0;
                                                        $can_link   = $content_data['allow_share_link']   ?? 1;
                                                        $can_repost = $content_data['allow_share_repost'] ?? 1;
                                                        ?>

                                                        <!-- Partilhar -->
                                                        <div class="share-container" style="position: relative; display: inline-flex;">
                                                            <button type="button"
                                                                class="yt-action-btn yt-pill"
                                                                onclick="event.stopPropagation(); toggleShareMenu(<?= (int)$item['feed_item_id'] ?>)"
                                                                title="Partilhar">
                                                                <i class="fa-regular fa-paper-plane"></i>
                                                                <span id="share-count-<?= (int)$item['feed_item_id'] ?>"><?= (int)$share_count ?></span>
                                                            </button>

                                                            <div id="share-menu-<?= (int)$item['feed_item_id'] ?>"
                                                                class="share-dropdown"
                                                                style="display:none; position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%);
                                                                        background:var(--bg-surface,#1a1a1a); border:1px solid var(--border,#333);
                                                                        border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.5);
                                                                        z-index:9999; min-width:180px; padding:8px 0;">
                                                                <?php if ($can_link): ?>
                                                                    <button type="button"
                                                                        onclick="event.stopPropagation(); copyToClipboard('<?= BASE_URL ?>post.php?id=<?= (int)$item['feed_item_id'] ?>', <?= (int)$item['feed_item_id'] ?>)"
                                                                        class="share-option-btn"
                                                                        style="width:100%; text-align:left; padding:10px 16px; background:none; border:none; cursor:pointer;
                                                                                   color:var(--text-main,#fff); font-size:.9rem; display:flex; align-items:center; gap:10px; transition:background .2s;">
                                                                        <i class="fa-regular fa-link" style="width:16px;"></i> Copiar Link
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if ($can_repost): ?>
                                                                    <button type="button"
                                                                        onclick="event.stopPropagation(); handleRepost(<?= (int)$item['feed_item_id'] ?>)"
                                                                        class="share-option-btn"
                                                                        style="width:100%; text-align:left; padding:10px 16px; background:none; border:none; cursor:pointer;
                                                                                   color:var(--text-main,#fff); font-size:.9rem; display:flex; align-items:center; gap:10px; transition:background .2s;">
                                                                        <i class="fa-solid fa-retweet" style="width:16px;"></i> Repostar
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                    </div><!-- /.post-actions -->

                                                    <?php
                                                    $save_key   = $item['item_type'] . '_' . $item['item_id'];
                                                    $is_saved   = isset($saved_ids[$save_key]);
                                                    $save_label = $is_saved ? 'Guardado' : 'Guardar';
                                                    $save_icon  = $is_saved ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark';
                                                    $save_class = $is_saved ? 'btn-save active' : 'btn-save';
                                                    ?>
                                                    <!-- Guardar (isolado à direita) -->
                                                    <button class="yt-action-btn yt-pill <?= $save_class ?>"
                                                        data-item-type="<?= htmlspecialchars($item['item_type']) ?>"
                                                        data-item-id="<?= (int)$item['item_id'] ?>"
                                                        data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                                                        onclick="toggleSave(this)"
                                                        title="<?= $save_label ?>">
                                                        <i class="<?= $save_icon ?>"></i>
                                                        <span><?= $save_label ?></span>
                                                    </button>
                                                </div><!-- /.post-footer -->
                                                <div class="comment-section-full" data-feed-item-id="<?= htmlspecialchars($item['feed_item_id']) ?>" style="display: none;">
                                                    <!-- Comentários ocultos no feed, exibidos apenas no Lightbox -->
                                                </div>
            </article>
        <?php endforeach; ?>

    <?php else: ?>
        <p class="no-content-message" style="margin: 0 auto;">Nenhuma postagem encontrada. Seja o primeiro a postar!</p>
    <?php endif; ?>
</div>


<!-- Lightbox Premium para Feed (Facebook Reels Style) -->
<div id="feedLightbox" class="photo-lightbox-modal">
    <div class="close-lightbox" data-action="close-lightbox">
        <i class="fa-regular fa-eye-slash"></i>
    </div>

    <div class="photo-lightbox-content">
        <!-- Navegação Esquerda (Desktop) -->
        <div class="reels-scroll-nav">
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(-1)">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(1)">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>

        <!-- Área Central do Vídeo -->
        <div class="photo-display-area">
            <div id="lightboxScrollContainer">
                <!-- Reels items injected via JS -->
            </div>
        </div>

        <!-- Sidebar Direita (Comentários e Info) -->
        <div class="photo-sidebar">
            <div class="photo-sidebar-header">
                <h3>Comentários</h3>
                <button class="sidebar-close-btn" data-action="close-sidebar" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px;">
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
                            <input type="text" id="lightboxCommentInput" placeholder="Escreva um comentário..." autocomplete="off">
                            <button type="submit">Enviar</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="login-to-comment" style="padding: 10px; text-align: center; color: #b0b3b8; font-size: 14px;">
                        Faça <a href="<?= BASE_URL ?>login.php" style="color: var(--reels-accent); text-decoration: none; font-weight: 600;">login</a> para comentar.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>



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
            é necessário verificar sua conta primeiro.
            <br><br>
            A verificação ajuda a manter a comunidade segura e aumenta
            a confiança entre os usuários.
        </p>

        <button class="invite-verify-btn" onclick="proceedToVerification()">
            Fazer verificação
        </button>

    </div>

</div>
<?php require_once __DIR__ . '/../includes/verificationmodal.php'; ?>



<!-- ============================================
     SCRIPTS - ORDEM CORRETA E SEM DUPLICADOS
     ============================================ -->

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

<!-- 2. Scripts de dependência (sem defer) -->
<script src="<?= BASE_URL ?>assets/js/core/common_notifications.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/notifications.js"></script>

<!-- 3. Main.js PRIMEIRO (tem toggleShareMenu, handleRepost, etc) -->
<script src="<?= BASE_URL ?>assets/js/core/main.js"></script>

<!-- 4. Comments e tracking -->
<script src="<?= BASE_URL ?>assets/js/components/comments.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/track_views.js"></script>
<!-- 5. Premium lightbox DEPOIS do main.js (depende das funções globais) -->
<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>

<!-- 6. Home page JS (unblur, modal verificação, lightbox protection, modal hoist) -->
<script src="<?= BASE_URL ?>assets/js/pages/home.js"></script>

<script src="<?= BASE_URL ?>assets/js/components/save.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>