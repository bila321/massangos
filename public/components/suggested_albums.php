<?php
// public/components/suggested_albums.php
if (!defined('SECURE_ACCESS')) exit;

use Massango\Models\Album;

// Obter álbuns recentes que devem aparecer no feed
global $pdo;
$suggested_albums_list = Album::getRecentAlbums($pdo, 5);
// Filtrar apenas os que têm show_in_feed = 1
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
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
            padding-top: 10px;
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
            flex-shrink: 0;
            transition: all 0.25s ease;
        }

        /* Hover geral */
        .suggested-album-item:hover {
            transform: translateY(-4px);
        }

        /* Wrapper da capa — mesmo padrão do right-sidebar */
        .suggested-album-item .album-cover-wrapper {
            position: relative;
            aspect-ratio: 1;
            border-radius: var(--radius-md, 12px);
            overflow: hidden;
            border: 1px solid var(--border);
            max-height: 130px;
        }

        .suggested-album-item .album-cover-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s ease, filter 0.3s ease;
        }

        /* Zoom elegante */
        .suggested-album-item:hover .album-cover-wrapper img {
            transform: scale(1.08);
            filter: brightness(0.95);
        }

        /* Nome do álbum — fora do card */
        .suggested-album-item .album-name {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted, #c3c3c3);
            margin-top: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Badge "Adquirido" */
        .suggested-album-item .album-acquired-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0, 0, 0, .6);
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.6rem;
        }

        /* Overlay de bloqueio */
        .suggested-album-item .album-locked-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            gap: 6px;
        }

        .suggested-album-item .album-locked-overlay i {
            font-size: 1.5rem;
        }

        .suggested-album-item .album-locked-overlay span {
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* Overlay de conteúdo sensível (blur IA) */
        .suggested-album-item .album-overlay-msg {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-align: center;
            padding: 8px;
        }

        .suggested-album-item .album-overlay-msg i {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }

        .suggested-album-item .album-overlay-msg p {
            font-size: 11px;
            margin: 0 0 6px;
        }

        .suggested-album-item .album-overlay-msg button {
            font-size: 10px;
            padding: 2px 8px;
            border: 1px solid #fff;
            background: transparent;
            color: #fff;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>

    <div class="suggested-albums-card card mb-4">
        <div class="card-header bg-transparent border-0 pb-0">
            <h6 class="mb-0 font-weight-bold"><i class="fas fa-images mr-2 text-danger"></i> Álbuns em destaque</h6>
        </div>
        <div class="card-body">
            <div class="suggested-albums-list d-flex overflow-auto pb-2">
                <?php foreach ($suggested_albums_list as $s_album): ?>
                    <?php
                    $paymentService   = new \Massango\Services\PaymentService($pdo);
                    $current_user_id  = $_SESSION['user_id'] ?? 0;
                    $album_id         = (int)($s_album['id'] ?? 0);
                    $hasAccess        = $paymentService->hasAccess($current_user_id, 'album', $album_id);
                    $isForSale        = !empty($s_album['is_for_sale']);
                    $should_blur      = !empty($s_album['risk_level']) && $s_album['risk_level'] === 'high';
                    $cover            = $s_album['cover_photo_url'] ?? 'default_album.png';
                    $album_thumb      = !empty($s_album['thumbnail_path'])
                        ? $s_album['thumbnail_path']
                        : 'albums/thumbnails/' . basename($cover);
                    $album_name       = htmlspecialchars($s_album['name'] ?? 'Álbum');
                    $album_price      = number_format((float)($s_album['price'] ?? 0), 2, ',', '.');
                    $thumb_url_prot   = get_protected_media_url($album_thumb);
                    $thumb_url_pub    = UPLOAD_URL . htmlspecialchars($album_thumb);
                    $is_creator       = !empty($user_data['is_verified_creator']);
                    ?>

                    <div class="suggested-album-item">

                        <!-- Capa -->
                        <div class="album-cover-wrapper <?= $should_blur ? 'album-blur-container' : '' ?>">

                            <?php if ($hasAccess): ?>

                                <!-- 🔓 TEM ACESSO -->
                                <a href="<?= BASE_URL ?>view_album.php?id=<?= $album_id ?>"
                                    class="album-cover-link"
                                    style="display:block;width:100%;height:100%;"
                                    data-item-id="<?= $album_id ?>"
                                    data-item-type="album">

                                    <?= render_adult_content(
                                        '<img src="' . $thumb_url_prot . '" alt="Capa do Álbum" class="' . ($should_blur ? 'album-blur' : '') . '">',
                                        $s_album
                                    ); ?>

                                </a>

                                <?php if ($isForSale): ?>
                                    <div class="album-acquired-badge">
                                        <i class="fas fa-check-circle"></i> Adquirido
                                    </div>
                                <?php endif; ?>

                                <?php if ($should_blur): ?>
                                    <div class="album-overlay-msg">
                                        <i class="fas fa-eye-slash"></i>
                                        <p>Conteúdo sensível</p>
                                        <button onclick="event.stopPropagation(); unblurAlbum(this)">Ver mesmo assim</button>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($isForSale): ?>

                                <!-- 🔒 BLOQUEADO (PAGO) -->
                                <?php
                                $click_action = $is_creator
                                    ? "window.location.href='" . BASE_URL . "checkout.php?type=album&id={$album_id}'"
                                    : "pageModalLoader.open('checkout.php?type=album&id={$album_id}')";
                                ?>
                                <div onclick="<?= $click_action ?>"
                                    style="cursor:pointer;width:100%;height:100%;"
                                    data-track-type="album"
                                    data-track-id="<?= $album_id ?>">

                                    <img src="<?= $thumb_url_pub ?>"
                                        alt="Capa do Álbum"
                                        style="filter:blur(8px);">

                                    <div class="album-locked-overlay">
                                        <i class="fas fa-lock"></i>
                                        <span><?= $album_price ?> MT</span>
                                    </div>

                                </div>

                            <?php else: ?>

                                <!-- 🆓 NORMAL -->
                                <a href="<?= BASE_URL ?>view_album.php?id=<?= $album_id ?>"
                                    class="album-cover-link"
                                    style="display:block;width:100%;height:100%;">
                                    <img src="<?= $thumb_url_pub ?>" alt="Capa do Álbum">
                                </a>

                            <?php endif; ?>

                        </div>
                        <!-- /album-cover-wrapper -->

                        <!-- Nome fora do card -->
                        <p class="album-name"><?= $album_name ?></p>

                    </div>
                    <!-- /suggested-album-item -->

                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>