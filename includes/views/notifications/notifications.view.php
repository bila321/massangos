<?php
// includes/views/notifications/notifications.view.php
// Variáveis disponíveis (injectadas pelo NotificationController):
//   $notifications    — array com todas as notificações do utilizador
//   $is_ajax          — bool, true se o pedido foi AJAX
//
// Helpers disponíveis via NotificationService (métodos estáticos):
//   NotificationService::badge($type)             → [$badge_class, $badge_icon]
//   NotificationService::group($created_at)        → 'hoje' | 'anteriores'
//   NotificationService::senderAvatarUrl($n)       → string URL
//   NotificationService::thumbUrl($n)              → string URL (ou '')
//   NotificationService::notifLink($n)             → string URL
//   NotificationService::messageWithoutPrefix($n)  → string

use Massango\Services\NotificationService;
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/notifications.css">

<div class="notif-page">

    <!-- Cabeçalho -->
    <div class="notif-page-head">
        <h2><i class="fa-solid fa-bell"></i> Notificações</h2>
        <div class="notif-head-actions">
            <button class="notif-btn danger btn-clear-notifications" type="button">
                <i class="fa-solid fa-trash-can"></i> Limpar lidas
            </button>
            <button class="notif-btn" type="button" onclick="location.reload()">
                <i class="fa-solid fa-rotate"></i> Atualizar
            </button>
        </div>
    </div>

    <!-- Lista -->
    <div class="notif-list" id="notificationsList">

        <?php if (!empty($notifications)): ?>
            <?php
            $last_group = null;
            foreach ($notifications as $n):
                $is_unread          = !(bool) $n['is_read'];
                $is_follow_request  = ($n['type'] === 'follow_request');
                $is_partner_request = ($n['type'] === 'album_partnership_request');

                $group                      = NotificationService::group($n['created_at']);
                [$badge_class, $badge_icon] = NotificationService::badge($n['type']);

                $sender_avatar   = NotificationService::senderAvatarUrl($n);
                $sender_username = htmlspecialchars($n['sender_username'] ?? 'Utilizador');

                $post_thumb     = NotificationService::thumbUrl($n);
                $post_thumb_alt = htmlspecialchars($n['post_title'] ?? '');

                $link           = NotificationService::notifLink($n);
                $rest_msg       = NotificationService::messageWithoutPrefix($n);
            ?>

                <?php if ($group !== $last_group): $last_group = $group; ?>
                    <div class="notif-group-label">
                        <?= $group === 'hoje' ? 'Hoje' : 'Anteriores' ?>
                    </div>
                <?php endif; ?>

                <div class="notif-item <?= $is_unread ? 'is-unread' : '' ?>"
                     data-notification-id="<?= (int) $n['id'] ?>">

                    <!-- Avatar + badge de tipo -->
                    <div class="notif-avatar-wrap <?= $is_unread ? 'is-unread' : '' ?>">
                        <img class="notif-avatar"
                             src="<?= $sender_avatar ?>"
                             alt="<?= $sender_username ?>"
                             loading="lazy">
                        <span class="notif-type-badge <?= $badge_class ?>" aria-hidden="true">
                            <i class="fa-solid <?= $badge_icon ?>"></i>
                        </span>
                    </div>

                    <!-- Corpo -->
                    <div class="notif-body">
                        <p class="notif-msg">
                            <strong><?= $sender_username ?></strong>
                            <?= htmlspecialchars($rest_msg) ?>
                        </p>

                        <div class="notif-meta">
                            <span class="notif-time <?= $is_unread ? 'fresh' : '' ?>">
                                <?= format_datetime_ago($n['created_at']) ?>
                            </span>
                        </div>

                        <!-- Ações: pedido de seguimento -->
                        <?php if ($is_follow_request && $is_unread): ?>
                            <div class="notif-actions">
                                <form action="<?= BASE_URL ?>actions/follow_request.php" method="POST">
                                    <input type="hidden" name="follower_id" value="<?= (int) $n['sender_id'] ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" class="notif-btn primary">Aceitar</button>
                                </form>
                                <form action="<?= BASE_URL ?>actions/follow_request.php" method="POST">
                                    <input type="hidden" name="follower_id" value="<?= (int) $n['sender_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="notif-btn">Recusar</button>
                                </form>
                            </div>

                        <!-- Ações: pedido de parceria -->
                        <?php elseif ($is_partner_request && $is_unread): ?>
                            <div class="notif-actions">
                                <form action="<?= BASE_URL ?>process_partnership.php" method="POST">
                                    <input type="hidden" name="partner_id" value="<?= (int) $n['entity_id'] ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" class="notif-btn primary">
                                        <i class="fa-solid fa-handshake"></i> Aceitar
                                    </button>
                                </form>
                                <form action="<?= BASE_URL ?>process_partnership.php" method="POST">
                                    <input type="hidden" name="partner_id" value="<?= (int) $n['entity_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="notif-btn">Recusar</button>
                                </form>
                            </div>

                        <!-- Marcar como lida (restantes) -->
                        <?php elseif ($is_unread): ?>
                            <div class="notif-actions">
                                <button type="button"
                                        class="notif-btn btn-mark-read"
                                        style="font-size:.75rem;padding:4px 10px;">
                                    <i class="fa-solid fa-check"></i> Marcar como lida
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Thumbnail da publicação -->
                    <?php if ($post_thumb && !$is_follow_request && !$is_partner_request): ?>
                        <a href="<?= $link ?>" tabindex="-1" aria-hidden="true">
                            <img class="notif-thumb"
                                 src="<?= $post_thumb ?>"
                                 alt="<?= $post_thumb_alt ?>"
                                 loading="lazy">
                        </a>
                    <?php elseif (!$is_follow_request && !$is_partner_request): ?>
                        <a href="<?= $link ?>" tabindex="-1" aria-hidden="true">
                            <div class="notif-thumb-placeholder">
                                <i class="fa-regular fa-image"></i>
                            </div>
                        </a>
                    <?php endif; ?>

                </div><!-- /notif-item -->

            <?php endforeach; ?>

        <?php else: ?>
            <div class="notif-empty">
                <i class="fa-solid fa-bell-slash"></i>
                <p>Ainda não tens notificações.</p>
            </div>
        <?php endif; ?>

    </div><!-- /notif-list -->
</div><!-- /notif-page -->

<script>
    window.BASE_URL = '<?= addslashes(BASE_URL) ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/core/notifications.js" defer></script>
