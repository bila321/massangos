<?php
// public/components/suggested_albums.php
if (!defined('SECURE_ACCESS')) exit;

use Massango\Models\Album;

// Obter Ã¡lbuns recentes que devem aparecer no feed
global $pdo;
$suggested_albums_list = Album::getRecentAlbums($pdo, 5);
// Filtrar apenas os que tÃªm show_in_feed = 1
$suggested_albums_list = array_filter($suggested_albums_list, function ($album) {
    return isset($album['show_in_feed']) && (int)$album['show_in_feed'] === 1;
});

if (!empty($suggested_albums_list)): ?>

    <style>
        /* =========================
   SUGGESTED ALBUMS CARD
========================= */

        .suggested-albums-card {
            border-radius: 16px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            background: var(--bg-main);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        /* Header */
        .suggested-albums-card .card-header {
            padding: 15px 20px 5px;
        }

        .suggested-albums-card h6 {
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        /* Scroll horizontal */
        .suggested-albums-list {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding-bottom: 10px;
            scroll-behavior: smooth;
        }

        /* Scrollbar moderna */
        .suggested-albums-list::-webkit-scrollbar {
            height: 6px;
        }

        .suggested-albums-list::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .suggested-albums-list::-webkit-scrollbar-track {
            background: transparent;
        }

        /* Item */
        .suggested-album-item {
            min-width: 130px;
            min-height: 130px;
            transition: all 0.25s ease;
        }

        /* Hover geral */
        .suggested-album-item:hover {
            transform: translateY(-4px);
        }

        /* Wrapper da capa */
        .album-cover-wrapper {
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            height: 130px;
        }

        /* Imagem */
        .suggested-album-item img {
            width: 130px;
            height: 130px;
            object-fit: cover;
            transition: transform 0.35s ease, filter 0.3s ease;
            border-radius: 12px;
        }

        /* Zoom elegante */
        .suggested-album-item:hover img {
            transform: scale(1.08);
            filter: brightness(0.95);
        }

        /* Overlay suave (preparado para futuro) */
        .album-cover-wrapper::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to top,
                    rgba(0, 0, 0, 0.35),
                    rgba(0, 0, 0, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .suggested-album-item:hover .album-cover-wrapper::after {
            opacity: 1;
        }

        /* Badge preÃ§o */
        .album-cover-wrapper .badge {
            font-size: 10px;
            padding: 4px 6px;
            border-radius: 6px;
            backdrop-filter: blur(4px);
        }

        /* Nome do Ã¡lbum */
        .suggested-album-item p {
            font-size: 12px;
            font-weight: 600;
            color: #c3c3c3;
        }

        /* =========================
   FUTURO: BACKGROUND IMAGE
========================= */

        .suggested-album-item.has-bg .album-cover-wrapper::before {
            content: "";
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0.15;
            z-index: 0;
        }

        /* BLOQUEIO (PAGO) */
        .album-locked-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 12px;
            text-align: center;
        }

        .album-locked-overlay i {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .album-locked-overlay p {
            font-size: 12px;
            font-weight: bold;
        }

        /* IMAGEM PADRÃƒO */
        .album-cover-image-mini {
            object-fit: cover;
            border-radius: 12px;
        }
    </style>

    <div class="suggested-albums-card card mb-4">
        <div class="card-header bg-transparent border-0 pb-0">
            <h6 class="mb-0 font-weight-bold"><i class="fas fa-compact-disc mr-2 text-danger"></i> Ãlbuns em destaque</h6>
        </div>
        <div class="card-body">
            <div class="suggested-albums-list d-flex overflow-auto pb-2" style="gap: 15px;">
                <?php foreach ($suggested_albums_list as $s_album): ?>
                    <?php
                    $paymentService = new \Massango\Services\PaymentService($pdo);
                    $current_user_id = $_SESSION['user_id'] ?? 0;

                    // SeguranÃ§a: garantir ID
                    $album_id = (int)($s_album['id'] ?? 0);

                    // Verificar acesso pago
                    $hasAccess = $paymentService->hasAccess($current_user_id, 'album', $album_id);

                    // Verificar se Ã© pago
                    $isForSale = !empty($s_album['is_for_sale']);

                    // Blur IA (compatÃ­vel com teu sistema)
                    $should_blur = !empty($s_album['risk_level']) && $s_album['risk_level'] === 'high';

                    // Thumbnail seguro
                    $cover = $s_album['cover_photo_url'] ?? 'default_album.png';
                    $album_thumb = !empty($s_album['thumbnail_path'])
                        ? $s_album['thumbnail_path']
                        : 'albums/thumbnails/' . basename($cover);
                    ?>

                    <div class="suggested-album-item">

                        <?php if ($hasAccess): ?>

                            <!-- ðŸ”“ TEM ACESSO -->
                            <div class="<?= $should_blur ? 'album-blur-container' : '' ?>" style="position: relative;">

                                <a href="<?= BASE_URL ?>view_album.php?id=<?= $album_id ?>"
                                    class="album-cover-link"
                                    data-item-id="<?= $album_id ?>"
                                    data-item-type="album">

                                    <?= render_adult_content(
                                        '<img src="' . get_protected_media_url($album_thumb) . '" 
                          alt="Capa do Ãlbum" 
                          class="album-cover-image-mini ' . ($should_blur ? 'album-blur' : '') . '">',
                                        $s_album
                                    ); ?>

                                </a>

                                <?php if ($should_blur): ?>
                                    <div class="album-overlay-msg">
                                        <i class="fas fa-eye-slash"></i>
                                        <p>ConteÃºdo sensÃ­vel</p>
                                        <button onclick="event.stopPropagation(); unblurAlbum(this)">Ver mesmo assim</button>
                                    </div>
                                <?php endif; ?>

                            </div>

                        <?php elseif ($isForSale): ?>

                            <!-- ðŸ”’ BLOQUEADO (PAGO) -->
                            <div class="album-locked"
                                data-track-type="album"
                                data-track-id="<?= $album_id ?>"
                                onclick="pageModalLoader.open('checkout.php?type=album&id=<?= $album_id ?>')"
                                style="position: relative; cursor: pointer;">

                                <img src="<?= UPLOAD_URL . htmlspecialchars($album_thumb) ?>"
                                    alt="Capa do Ãlbum"
                                    class="album-cover-image"
                                    style="filter: blur(15px);">

                                <div class="album-locked-overlay">
                                    <i class="fas fa-lock"></i>
                                    <p><?= number_format($s_album['price'] ?? 0, 2, ',', '.') ?> MT</p>
                                </div>

                            </div>

                        <?php else: ?>

                            <!-- ðŸ†“ NORMAL -->
                            <a href="<?= BASE_URL ?>view_album.php?id=<?= $album_id ?>"
                                class="album-cover-link">

                                <img src="<?= UPLOAD_URL . htmlspecialchars($album_thumb) ?>"
                                    alt="Capa do Ãlbum"
                                    class="album-cover-image">

                            </a>

                        <?php endif; ?>

                        <!-- Nome -->
                        <p class="album-name text-truncate">
                            <?= htmlspecialchars($s_album['name'] ?? 'Ãlbum') ?>
                        </p>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>