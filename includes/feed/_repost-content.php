<?php
/**
 * Partial: _repost-content.php
 * Renderiza o conteúdo original dentro de um card de repost.
 *
 * Variáveis esperadas no scope:
 *   $item                 – array completo do feed item (o repost em si)
 *   $sharedData           – dados do conteúdo original
 *   $sharedType           – 'post' | 'video' | 'album'
 *   $sharedAuthor         – array do autor original
 *   $sharedId             – int  (ID do conteúdo original)
 *   $should_blur          – bool
 *   $ai_analysis          – array|null
 *   $user_data            – array do utilizador autenticado
 *   $logged_in_user_data  – idem (para is_verified_creator)
 *
 * $item['has_access_shared'] já vem calculado pelo FeedController.
 */

$hasAccessShared   = $item['has_access_shared'];
$isForSaleShared   = !empty($sharedData['is_for_sale']);
$isVerifiedCreator = !empty($logged_in_user_data['is_verified_creator']);
?>

<div class="original-content-container">

    <!-- Badge de conteúdo pago do original -->
    <?php if ($isForSaleShared): ?>
        <div class="paid-content-badge"
             style="background: var(--primary-gradient); color: #3b3b3b; padding: 5px 10px;
                    border-radius: 5px; font-size: 0.8em; font-weight: bold;
                    display: inline-block; margin-left: 10px;">
            <i class="fa-regular fa-lock"></i> CONTEÚDO PAGO: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT
        </div>
    <?php endif; ?>

    <?php /* ====================================================
              REPOST DE FOTO (post)
           ==================================================== */ ?>
    <?php if ($sharedType === 'post'): ?>

        <?php if (!empty($sharedData['content'])): ?>
            <div class="post-content">
                <p class="post-text"><?= nl2br(htmlspecialchars($sharedData['content'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($hasAccessShared): ?>
            <?php if (!(isset($sharedData['post_type']) && $sharedData['post_type'] === 'text') && !empty($sharedData['image_path'])): ?>
                <div class="media-wrapper-<?= htmlspecialchars($item['feed_item_id']) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                    <div class="post-image-container"
                         data-post-modal="<?= htmlspecialchars($item['feed_item_id']) ?>"
                         style="cursor: pointer;">
                        <?php
                        $display_image = !empty($sharedData['thumbnail_path'])
                            ? $sharedData['thumbnail_path']
                            : $sharedData['image_path'];
                        ?>
                        <img src="<?= UPLOAD_URL . htmlspecialchars($display_image) ?>"
                             alt="Imagem do Post"
                             class="post-image <?= $should_blur ? 'media-blur' : '' ?>"
                             data-is-paid="<?= $isForSaleShared ? 'true' : 'false' ?>">
                    </div>
                    <?php if ($should_blur): ?>
                        <div class="media-overlay-msg">
                            <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                            <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item['feed_item_id']) ?>')">
                                Ver mesmo assim
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Bloqueado -->
            <?php
            $thumb_locked = !empty($sharedData['thumbnail_path'])
                ? $sharedData['thumbnail_path']
                : $sharedData['image_path'];
            ?>
            <?php if ($isVerifiedCreator): ?>
                <div class="post-locked"
                     onclick="pageModalLoader.open('checkout.php?type=post&id=<?= $sharedId ?>')">
            <?php else: ?>
                <div class="post-locked"
                     onclick="openVerificationInviteModal()">
            <?php endif; ?>
                <img src="<?= UPLOAD_URL . htmlspecialchars($thumb_locked) ?>"
                     alt="Imagem do Post"
                     style="filter: blur(20px);"
                     data-is-paid="<?= $isForSaleShared ? 'true' : 'false' ?>">
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                            background: rgba(0,0,0,0.7); display: flex; flex-direction: column;
                            align-items: center; justify-content: center; color: #fff;">
                    <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                    <p>Conteúdo Pago: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT</p>
                    <span style="font-size: 0.8em; text-decoration: underline;">Clique para comprar</span>
                </div>
            </div>
        <?php endif; ?>

    <?php /* ====================================================
              REPOST DE VÍDEO
           ==================================================== */ ?>
    <?php elseif ($sharedType === 'video' && !empty($sharedData['video_path'])): ?>

        <?php if (!empty($sharedData['caption'])): ?>
            <div class="post-content">
                <p class="post-text"><?= nl2br(htmlspecialchars($sharedData['caption'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($hasAccessShared || !$isForSaleShared): ?>
            <!-- Vídeo acessível -->
            <div class="media-wrapper-<?= htmlspecialchars($item['feed_item_id']) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                <div class="video-locked lightbox-trigger"
                     data-type="video"
                     data-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
                     data-item-id="<?= htmlspecialchars($sharedId) ?>"
                     data-item-type="video"
                     data-src="<?= UPLOAD_URL . htmlspecialchars($sharedData['video_path']) ?>"
                     data-is-for-sale="<?= $isForSaleShared ? 'true' : 'false' ?>"
                     data-price="<?= $sharedData['price'] ?? 0 ?>"
                     data-has-access="true"
                     data-thumbnail="<?= htmlspecialchars(get_video_thumb_url($sharedData['thumbnail_path'] ?? '')) ?>"
                     data-ai-status="<?= htmlspecialchars($ai_analysis['status'] ?? '') ?>"
                     data-ai-risk="<?= htmlspecialchars($ai_analysis['risk_level'] ?? '') ?>"
                     data-ai-score="<?= htmlspecialchars($ai_analysis['explicit_percentage'] ?? 0) ?>"
                     onclick="if(typeof sendViewRequest === 'function') sendViewRequest('video', <?= (int)$item['item_id'] ?>, <?= (int)$item['feed_item_id'] ?>)"
                     style="position: relative; overflow: hidden; cursor: pointer;">

                    <?php if (!empty($sharedData['thumbnail_path'])): ?>
                        <img src="<?= htmlspecialchars(get_video_thumb_url($sharedData['thumbnail_path'])) ?>"
                             class="post-image <?= $should_blur ? 'media-blur' : '' ?>"
                             style="display: block; width: 100%;">
                    <?php else: ?>
                        <video src="<?= UPLOAD_URL . htmlspecialchars($sharedData['video_path']) ?>"
                               class="post-image <?= $should_blur ? 'media-blur' : '' ?>"
                               style="width: 100%; display: block;"
                               preload="metadata"
                               data-item-type="video"
                               data-item-id="<?= (int)$item['item_id'] ?>"
                               muted playsinline></video>
                    <?php endif; ?>

                    <?php if ($should_blur): ?>
                        <div class="media-overlay-msg">
                            <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                            <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item['feed_item_id']) ?>')">
                                Ver mesmo assim
                            </button>
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
                                background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.4) 50%, transparent 100%);
                                z-index: 10; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-eye"></i>
                        <span data-views-id="video-<?= (int)$item['item_id'] ?>">
                            <?= number_format($sharedData['views_count'] ?? 0) ?> visualizacoes
                        </span>
                        <?php if (!$isForSaleShared): ?>
                            <span style="margin-left: auto; z-index: 111000; background: rgba(0,255,0,0.3);
                                         padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">
                                <i class="fa-solid fa-play-circle"></i> Grátis
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Vídeo bloqueado -->
            <?php $checkoutUrl = BASE_URL . 'checkout.php?type=video&id=' . $item['item_id']; ?>
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
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                            background: rgba(0,0,0,0.7); display: flex; flex-direction: column;
                            align-items: center; justify-content: center; color: #fff; pointer-events: none;">
                    <span>
                        <i class="fa-solid fa-play" style="margin-bottom: 10px; padding: 5px;"></i>
                        <i class="fas fa-lock"      style="margin-bottom: 10px; padding: 5px;"></i>
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

    <?php /* ====================================================
              REPOST DE ÁLBUM
           ==================================================== */ ?>
    <?php elseif ($sharedType === 'album'): ?>

        <div class="post-content">
            <h2 class="album-title"><?= htmlspecialchars($sharedData['album_name'] ?? 'Álbum sem Nome') ?></h2>
            <?php if (!empty($sharedData['description'])): ?>
                <p class="post-text"><?= nl2br(htmlspecialchars($sharedData['description'])) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($sharedData['cover_photo_url'])): ?>
            <?php
            $album_thumb = !empty($sharedData['thumbnail_path'])
                ? $sharedData['thumbnail_path']
                : $sharedData['cover_photo_url'];
            ?>

            <?php if ($hasAccessShared): ?>
                <?php $album_blur_class = $should_blur ? 'album-blur-container' : ''; ?>
                <div class="<?= $album_blur_class ?>" style="position: relative; display: block;">
                    <a href="<?= BASE_URL ?>view_album.php?id=<?= htmlspecialchars($sharedId) ?>"
                       class="album-placeholder-link album-cover-link"
                       data-item-id="<?= (int)$sharedId ?>"
                       data-item-type="album">
                        <?= render_adult_content(
                            '<img src="' . get_protected_media_url($album_thumb) . '" '
                            . 'alt="Capa do Álbum" '
                            . 'class="album-cover-image ' . ($should_blur ? 'album-blur' : '') . '" '
                            . 'style="height: 520px; object-fit: contain; width: 100%; display: block;">',
                            $sharedData
                        ) ?>
                    </a>
                    <?php if ($should_blur): ?>
                        <div class="album-overlay-msg">
                            <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                            <button onclick="event.stopPropagation(); unblurAlbum(this)">Ver mesmo assim</button>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($isVerifiedCreator): ?>
                <div class="album-locked"
                     style="position: relative; cursor: pointer;"
                     onclick="pageModalLoader.open('checkout.php?type=album&id=<?= $sharedId ?>')">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($album_thumb) ?>"
                         alt="Capa do Álbum"
                         class="album-cover-image"
                         style="filter: blur(8px); max-height: 360px;">
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                                display: flex; flex-direction: column; align-items: center;
                                justify-content: center; color: #fff; background: rgba(0,0,0,0.4); border-radius: 8px;">
                        <i class="fa-regular fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                        <p>Álbum: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT</p>
                    </div>
                </div>

            <?php else: ?>
                <div class="album-locked"
                     style="position: relative; cursor: pointer;"
                     onclick="openVerificationInviteModal()">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($album_thumb) ?>"
                         alt="Capa do Álbum"
                         class="album-cover-image"
                         style="filter: blur(8px); max-height: 360px;">
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                                display: flex; flex-direction: column; align-items: center;
                                justify-content: center; color: #fff; background: rgba(0,0,0,0.4); border-radius: 8px;">
                        <i class="fa-regular fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                        <p>Álbum: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT</p>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <a href="<?= BASE_URL ?>view_album.php?id=<?= htmlspecialchars($sharedId) ?>"
               class="album-placeholder-link">
                <span class="overlay-text"></span>
            </a>
        <?php endif; ?>

    <?php endif; /* fim switch sharedType */ ?>

</div><!-- /.original-content-container -->
