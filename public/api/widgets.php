<?php

namespace Massango\Controllers;


/**
 * api/widgets.php
 * Endpoint interno — devolve o HTML dos widgets da right-sidebar.
 * Chamado via fetch() pelo right-sidebar.php após o primeiro paint.
 *
 * Devolve fragmento HTML puro (sem <html>/<body>).
 * Não há redireccionamento — devolve 401 se não autenticado.
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Models\Notification;
use Massango\Services\PaymentService;

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

$current_user_id = get_current_user_id();
$user_data       = \Massango\Models\User::getUserById($pdo, $current_user_id);
$is_admin        = !empty($user_data['is_admin']);
$is_creator      = !empty($user_data['is_verified_creator']);

$suggested_users = \Massango\Models\User::getSuggestedUsers($pdo, $current_user_id, 4);
$recent_albums   = \Massango\Models\Album::getRecentAlbums($pdo, 4);

// Serviço de pagamentos (necessário para hasAccess nos álbuns)
$paymentService = new \Massango\Services\PaymentService($pdo);
?>

<!-- Sugestões de Utilizadores -->
<section class="widget-card">
    <h2 class="widget-title">
        <i class="fa-solid fa-fire" style="color:var(--danger);"></i> Sugestões
    </h2>

    <div class="widget-content-list">
        <?php if (!empty($suggested_users)): ?>
            <?php foreach ($suggested_users as $sug_user): ?>
                <div style="display:flex;align-items:center;gap:12px;">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($sug_user['profile_picture'] ?? 'profiles/default_profile.png') ?>"
                        alt="<?= htmlspecialchars($sug_user['username']) ?>"
                        width="40" height="40"
                        loading="lazy"
                        style="border-radius:50%;object-fit:cover;border:1px solid var(--border);flex-shrink:0;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:0.875rem;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($sug_user['username']) ?>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">Sugerido</div>
                    </div>
                    <a href="<?= BASE_URL ?>profile.php?id=<?= (int)$sug_user['id'] ?>"
                        class="btn btn-secondary btn-sm"
                        style="padding:4px 12px;flex-shrink:0;">Ver</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="font-size:0.85rem;color:var(--text-muted);text-align:center;padding:var(--space-md) 0;">
                Nenhuma sugestão no momento.
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- Álbuns Recentes -->
<section class="widget-card">
    <h2 class="widget-title">
        Álbuns Recentes <i class="fa-solid fa-images" style="color:var(--primary);font-size:0.8rem;"></i>
    </h2>

    <div class="widget-grid">
        <?php if (!empty($recent_albums)): ?>
            <?php foreach ($recent_albums as $album):
                if ($is_admin || ($current_user_id && (int)$album['user_id'] === $current_user_id)) {
                    $hasAccess = true;
                } else {
                    $hasAccess = $paymentService->hasAccess($current_user_id, 'album', (int)$album['id']);
                }

                $album_thumb = !empty($album['thumbnail_path'])
                    ? $album['thumbnail_path']
                    : ($album['cover_photo_url'] ?? 'default.jpg');

                $is_paid     = !empty($album['is_for_sale']);
                $album_id    = (int)$album['id'];
                $album_price = number_format((float)($album['price'] ?? 0), 2, ',', '.');
                $album_name  = htmlspecialchars($album['name']);
                $thumb_url   = UPLOAD_URL . htmlspecialchars($album_thumb);
            ?>
                <div style="position:relative;aspect-ratio:1;border-radius:var(--radius-md);overflow:hidden;border:1px solid var(--border);max-height:170px;">

                    <?php if ($hasAccess): ?>
                        <a href="<?= BASE_URL ?>view_album.php?id=<?= $album_id ?>"
                            style="display:block;width:100%;height:100%;">
                            <img src="<?= $thumb_url ?>"
                                alt="<?= $album_name ?>"
                                style="width:100%;height:100%;object-fit:cover;"
                                loading="lazy">
                            <?php if ($is_paid): ?>
                                <div style="position:absolute;top:5px;right:5px;background:rgba(0,0,0,.6);color:#fff;padding:2px 6px;border-radius:4px;font-size:0.6rem;">
                                    <i class="fas fa-check-circle"></i> Adquirido
                                </div>
                            <?php endif; ?>
                        </a>

                    <?php else:
                        $click_action = $is_creator
                            ? "window.location.href='" . BASE_URL . "checkout.php?type=album&id={$album_id}'"
                            : "openVerificationInviteModal()";
                    ?>
                        <div onclick="<?= $click_action ?>" style="cursor:pointer;width:100%;height:100%;">
                            <img src="<?= $thumb_url ?>"
                                alt="<?= $album_name ?>"
                                style="width:100%;height:100%;object-fit:cover;filter:blur(8px);"
                                loading="lazy">
                            <div style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;gap:6px;">
                                <i class="fas fa-lock" style="font-size:1.5rem;"></i>
                                <span style="font-size:0.75rem;font-weight:700;"><?= $album_price ?> MT</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,.8));padding:8px 4px;color:#fff;font-size:0.65rem;font-weight:600;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none;">
                        <?= $album_name ?>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column:span 2;font-size:0.85rem;color:var(--text-muted);text-align:center;padding:var(--space-md) 0;">
                Nenhum álbum recente.
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- Rodapé -->
<footer class="widget-footer">
    <div class="widget-footer-links">
        <a href="#">Sobre</a>
        <a href="#">Privacidade</a>
        <a href="#">Termos</a>
    </div>
    <p>&copy; <?= date('Y') ?> massangos. Feito com <i class="fa-solid fa-heart" style="color:var(--danger);font-size:0.6rem;"></i></p>
</footer>