<?php
/**
 * Partial: _album-media.php
 * Renderiza o media de um item do tipo "album".
 *
 * Variáveis esperadas no scope:
 *   $item         – array completo do feed item
 *   $content_data – dados do álbum (name, description, cover_photo_url, thumbnail_path, is_for_sale, price)
 *   $should_blur  – bool  (conteúdo sensível detetado pela IA)
 *   $user_data    – array do utilizador autenticado (is_verified_creator)
 *
 * $item['has_access'] já vem calculado pelo FeedController.
 */

$hasAccess   = $item['has_access'];
$is_verified = !empty($user_data['is_verified_creator']);
?>

<!-- Título e descrição do álbum -->
<div class="post-content">
    <h2 class="album-title"><?= htmlspecialchars($content_data['name'] ?? 'Álbum sem Nome') ?></h2>
    <?php if (!empty($content_data['description'])): ?>
        <p class="post-text"><?= nl2br(htmlspecialchars($content_data['description'])) ?></p>
    <?php endif; ?>
</div>

<!-- Badge de conteúdo pago -->
<?php if (!empty($content_data['is_for_sale'])): ?>
    <div class="paid-content-badge"
         style="background: var(--primary-gradient); color: #3b3b3b; padding: 5px 10px;
                border-radius: 5px; font-size: 0.8em; font-weight: bold;
                display: inline-block; margin-bottom: 10px; margin-left: 10px;">
        <i class="fa-regular fa-lock"></i> CONTEÚDO PAGO: <?= number_format($content_data['price'], 2, ',', '.') ?> MT
    </div>
<?php endif; ?>

<?php if (!empty($content_data['cover_photo_url'])): ?>
    <?php
    $album_thumb = !empty($content_data['thumbnail_path'])
        ? $content_data['thumbnail_path']
        : $content_data['cover_photo_url'];
    ?>

    <?php if ($hasAccess): ?>
        <!-- ── ACESSO LIVRE / DESBLOQUEADO ── -->
        <?php $album_blur_class = $should_blur ? 'album-blur-container' : ''; ?>

        <div class="<?= $album_blur_class ?>" style="position: relative; display: block;">
            <a href="<?= BASE_URL ?>view_album.php?id=<?= htmlspecialchars($item['item_id']) ?>"
               class="album-placeholder-link album-cover-link"
               data-item-id="<?= (int)$item['item_id'] ?>"
               data-item-type="album">
                <?= render_adult_content(
                    '<img src="' . get_protected_media_url($album_thumb) . '" '
                    . 'alt="Capa do Álbum" '
                    . 'class="album-cover-image ' . ($should_blur ? 'album-blur' : '') . '" '
                    . 'style="object-fit: contain; width: 100%; display: block;">',
                    $content_data
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

    <?php elseif ($is_verified): ?>
        <!-- ── BLOQUEADO – utilizador verificado pode comprar ── -->
        <div class="album-locked"
             data-track-type="album"
             data-track-id="<?= (int)$item['item_id'] ?>"
             onclick="pageModalLoader.open('checkout.php?type=album&id=<?= $item['item_id'] ?>')">
            <img src="<?= UPLOAD_URL . htmlspecialchars($album_thumb) ?>"
                 alt="Capa do Álbum"
                 class="album-cover-image"
                 style="filter: blur(15px); max-height: 500px; object-fit: contain; width: 100%; display: block;">
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                        display: flex; flex-direction: column; align-items: center;
                        justify-content: center; color: #fff; background: rgba(0,0,0,0.4);">
                <i class="fa-regular fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                <p>Álbum Pago: <?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
            </div>
        </div>

    <?php else: ?>
        <!-- ── BLOQUEADO – utilizador não verificado ── -->
        <div class="album-locked"
             data-track-type="album"
             data-track-id="<?= (int)$item['item_id'] ?>"
             onclick="openVerificationInviteModal()">
            <img src="<?= UPLOAD_URL . htmlspecialchars($album_thumb) ?>"
                 alt="Capa do Álbum"
                 class="album-cover-image"
                 style="filter: blur(15px); max-height: 350px; object-fit: contain; width: 100%; display: block;">
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                        display: flex; flex-direction: column; align-items: center;
                        justify-content: center; color: #fff; background: rgba(0,0,0,0.4);">
                <i class="fa-regular fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                <p>Álbum Pago: <?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
            </div>
        </div>

    <?php endif; ?>

<?php else: ?>
    <!-- Sem capa definida – link simples -->
    <a href="<?= BASE_URL ?>view_album.php?id=<?= htmlspecialchars($item['item_id']) ?>"
       class="album-placeholder-link album-cover-link"
       data-item-id="<?= (int)$item['item_id'] ?>"
       data-item-type="album">
        <span class="overlay-text">Ver Álbum</span>
    </a>
<?php endif; ?>
