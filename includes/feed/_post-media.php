<?php
/**
 * Partial: _post-media.php
 * Renderiza o media de um post do tipo "post" (imagem).
 *
 * Variáveis esperadas no scope:
 *   $item           – array completo do feed item
 *   $content_data   – dados da publicação (image_path, thumbnail_path, post_type, is_for_sale, price)
 *   $should_blur    – bool  (conteúdo sensível detetado pela IA)
 *   $user_data      – array do utilizador autenticado (is_verified_creator)
 *
 * $item['has_access'] já vem calculado pelo FeedController.
 */

$hasAccess = $item['has_access'];
?>

<!-- Descrição / caption (opcional) -->
<?php if (!empty($content_data['content'])): ?>
    <div class="post-content">
        <p class="post-text"><?= nl2br(htmlspecialchars($content_data['content'])) ?></p>
    </div>
<?php endif; ?>

<!-- Badge de conteúdo pago -->
<?php if (!empty($content_data['is_for_sale'])): ?>
    <div class="paid-content-badge"
         style="background: var(--primary-gradient); color: #3b3b3b; padding: 5px 10px;
                border-radius: 5px; font-size: 0.8em; font-weight: bold;
                display: inline-block; margin-bottom: 10px; margin-left: 10px;">
        <i class="fa-regular fa-lock"></i> CONTEÚDO PAGO: <?= number_format($content_data['price'], 2, ',', '.') ?> MT
    </div>
<?php endif; ?>

<?php if (isset($content_data['post_type']) && $content_data['post_type'] === 'text'): ?>
    <?php /* Post de texto puro – sem media */ ?>

<?php elseif (!empty($content_data['image_path'])): ?>

    <?php if ($hasAccess): ?>
        <!-- ── ACESSO LIVRE / DESBLOQUEADO ── -->
        <div class="media-wrapper-<?= htmlspecialchars($item['feed_item_id']) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
            <div class="post-image-container"
                 data-post-modal="<?= htmlspecialchars($item['feed_item_id']) ?>"
                 style="cursor: pointer;">
                <?php
                $display_image = !empty($content_data['thumbnail_path'])
                    ? $content_data['thumbnail_path']
                    : $content_data['image_path'];
                ?>
                <img src="<?= UPLOAD_URL . htmlspecialchars($display_image) ?>"
                     alt="Imagem do Post"
                     class="post-image <?= $should_blur ? 'media-blur' : '' ?>"
                     data-is-paid="<?= !empty($content_data['is_for_sale']) ? 'true' : 'false' ?>">
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

    <?php else: ?>
        <!-- ── BLOQUEADO (conteúdo pago) ── -->
        <?php
        $thumb_locked = !empty($content_data['thumbnail_path'])
            ? $content_data['thumbnail_path']
            : $content_data['image_path'];
        $is_verified  = !empty($user_data['is_verified_creator']);
        ?>

        <?php if ($is_verified): ?>
            <div class="post-locked"
                 onclick="pageModalLoader.open('checkout.php?type=post&id=<?= $item['item_id'] ?>')">
        <?php else: ?>
            <div class="post-locked"
                 onclick="openVerificationInviteModal()">
        <?php endif; ?>

            <img src="<?= UPLOAD_URL . htmlspecialchars($thumb_locked) ?>"
                 alt="Imagem do Post"
                 style="filter: blur(20px);"
                 data-is-paid="<?= !empty($content_data['is_for_sale']) ? 'true' : 'false' ?>">

            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                        background: rgba(0,0,0,0.7); display: flex; flex-direction: column;
                        align-items: center; justify-content: center; color: #fff;">
                <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                <p>Conteúdo Pago: <?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
                <span style="font-size: 0.8em; text-decoration: underline;">
                    <?= $is_verified ? 'Clique para comprar' : 'Verifique sua conta para comprar' ?>
                </span>
            </div>
        </div>

    <?php endif; ?>
<?php endif; ?>
