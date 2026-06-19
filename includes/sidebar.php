<?php
// includes/sidebar.php

// includes/topbar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\Notification;

if (!is_logged_in()) return;

$current_user_id = get_current_user_id();
$user_data       = \Massango\Models\User::getUserById($pdo, $current_user_id);
$profile_pic     = UPLOAD_URL . htmlspecialchars($user_data['profile_picture'] ?? 'profiles/default_profile.png');
$unread_count    = Notification::getUnreadNotificationCount($pdo, $current_user_id);
$current_page    = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar" id="appSidebar" role="navigation" aria-label="Menu principal">

    <nav class="sidebar-nav">

        <ul class="sidebar-menu">
            <li>
                <a href="<?= BASE_URL ?>profile.php?id=<?= (int)$current_user_id ?>" class="nav-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-circle-user"></i><span><?= htmlspecialchars($user_data['username'] ?? '') ?></span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>" class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-house"></i><span>Início</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>reels.php" class="nav-link <?= $current_page === 'reels.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-stop"></i><span>Reels</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>messages.php" class="nav-link <?= $current_page === 'messages.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-paper-plane"></i><span>Mensagens</span>
                    <span class="notification-dot"></span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>notifications.php" class="nav-link <?= $current_page === 'notifications.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-bell"></i><span>Notificações</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-count"><?= min($unread_count, 99) ?><?= $unread_count > 99 ? '+' : '' ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>saved.php" class="nav-link <?= $current_page === 'saved.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-bookmark"></i><span>Salvos</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>history.php" class="nav-link <?= $current_page === 'history.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i><span>Histórico</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>create-post.php" class="nav-link <?= $current_page === 'create-post.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-circle-plus"></i><span>Publicar</span>
                </a>
            </li>
        </ul>

        <?php if (!empty($user_data['is_verified_creator'])): ?>
            <ul class="sidebar-menu">
                <li>
                    <a href="<?= BASE_URL ?>wallet.php" class="nav-link <?= $current_page === 'wallet.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-wallet"></i><span>Carteira</span>
                        <?php if (!empty($user_stats['balance'])): ?>
                            <span class="sidebar-badge" style="background:rgba(0,245,118,.15);color:#00f576;">
                                <?= number_format($user_stats['balance'], 0) ?> MT
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>sales_performance.php" class="nav-link <?= $current_page === 'sales_performance.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-bar"></i><span>Vendas</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>subscriptions.php" class="nav-link <?= $current_page === 'subscriptions.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-star"></i><span>Assinaturas</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>ai-center.php" class="nav-link <?= $current_page === 'ai-center.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-robot"></i><span>IA Center</span>
                        <span class="sidebar-badge beta">BETA</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>upgrade.php" class="nav-link <?= $current_page === 'upgrade.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-crown crown-icon"></i><span>natura</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>

        <ul class="sidebar-menu">
            <li>
                <a href="<?= BASE_URL ?>settings.php" class="nav-link <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-sliders"></i><span>Configurações</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>support.php" class="nav-link <?= $current_page === 'support.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-headset"></i><span>Suporte</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>logout.php" class="nav-link logout-link">
                    <i class="fa-solid fa-right-from-bracket"></i><span>Sair</span>
                </a>
            </li>
        </ul>


    </nav>

    <!-- Mini Profile -->
    <div class="sidebar-footer">
        <div class="mini-profile">
            <img src="<?= $profile_pic ?>"
                alt="<?= htmlspecialchars($user_data['username'] ?? '') ?>"
                width="50" height="50"
                loading="lazy">
            <div class="mini-profile-info">
                <strong><?= htmlspecialchars($user_data['username'] ?? '') ?></strong>
                <span>@<?= htmlspecialchars($user_data['username'] ?? '') ?></span>
            </div>
        </div>
    </div>

</aside>

<script>
    (function() {
        var btn = document.getElementById('sidebarVerMais');
        var extra = document.getElementById('sidebarExtra');
        var label = btn ? btn.querySelector('.ver-mais-label') : null;

        if (!btn || !extra) return;

        btn.addEventListener('click', function() {
            var expanded = extra.classList.toggle('open');
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            if (label) label.textContent = expanded ? 'Ver menos' : 'Ver mais';
        });
    })();
</script>