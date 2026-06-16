<?php
// includes/mobile-nav.php
use Massango\Models\Notification;

if (!is_logged_in()) return;

if (!isset($current_user_id)) $current_user_id = get_current_user_id();
if (!isset($user_data))       $user_data = \Massango\Models\User::getUserById($pdo, $current_user_id);
if (!isset($profile_pic))     $profile_pic = UPLOAD_URL . htmlspecialchars($user_data['profile_picture'] ?? 'profiles/default_profile.png');
if (!isset($unread_count))    $unread_count = Notification::getUnreadNotificationCount($pdo, $current_user_id);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* ─── Mobile Header ─────────────────────────────────────────────── */
    .mobile-header {
        display: none;
        align-items: center;
        width: 100%;
        height: var(--topbar-height, 52px);
        background: var(--bg-card, #fff);
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        padding: 0 6px;
        box-sizing: border-box;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 100;
        gap: 4px;
    }

    @media (max-width: 992px) {
        .mobile-header {
            display: flex;
        }

        /* Em sincronia com --header-h: 60px em variables.css */
        body {
            padding-top: var(--header-h, 60px);
        }
    }

    /* Hambúrguer */
    .mobile-menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-main, #1c1e21);
        flex-shrink: 0;
        transition: background 0.18s;
    }

    .mobile-menu-toggle:hover {
        background: var(--hover-bg, rgba(0, 0, 0, 0.06));
    }

    .mobile-menu-toggle i {
        font-size: 1rem;
    }

    /* Centro */
    .mobile-center {
        flex: 1;
        display: flex;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .mobile-nav {
        display: flex;
        align-items: center;
        justify-content: space-around;
        width: 80%;
    }

    .mobile-item {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        text-decoration: none;
        position: relative;
        transition: background 0.18s;
    }

    .mobile-item:hover {
        background: var(--hover-bg, rgba(0, 0, 0, 0.06));
    }

    .mobile-item.active::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 6px;
        right: 6px;
        background: var(--primary-gradient);
        border-radius: 2px;
    }

    .mobile-item i {
        font-size: 1rem;
        background: var(--text-main, #1c1e21);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        color: transparent;
    }

    /* Overlay pesquisa mobile */
    .mobile-search-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--topbar-height, 52px);
        display: flex;
        align-items: center;
        background: var(--bg-card, #fff);
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        opacity: 0;
        pointer-events: none;
        transform: translateY(-100%);
        transition: opacity 0.2s ease, transform 0.22s cubic-bezier(0.34, 1.3, 0.64, 1);
        z-index: 200;
        padding: 0 10px;
        gap: 6px;
        box-sizing: border-box;
    }

    .mobile-search-overlay.is-open {
        opacity: 1;
        pointer-events: all;
        transform: translateY(0);
    }

    .mobile-search-overlay form {
        flex: 1;
        display: flex;
        align-items: center;
        position: relative;
        background: var(--bg-main, #f0f2f5);
        border-radius: 20px;
        padding: 0 4px;
    }

    .mobile-search-overlay .search-icon-inner {
        position: absolute;
        left: 10px;
        color: var(--text-light, #8a8d91);
        font-size: 0.8rem;
        pointer-events: none;
    }

    .mobile-search-overlay input[type="text"] {
        width: 100%;
        padding: 8px 8px 8px 32px;
        border: none;
        background: transparent;
        font-size: 0.9rem;
        color: var(--text-main, #1c1e21);
        outline: none;
        font-family: inherit;
    }

    .mobile-search-overlay input::placeholder {
        color: var(--text-light, #8a8d91);
    }

    .mobile-search-close {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: none;
        background: var(--bg-main, #e4e6ea);
        cursor: pointer;
        color: var(--text-main, #1c1e21);
        font-size: 0.75rem;
        flex-shrink: 0;
        transition: background 0.15s;
    }

    .mobile-search-close:hover {
        background: var(--border, #ccc);
    }

    /* Direita */
    .mobile-right {
        display: flex;
        align-items: center;
        gap: 2px;
        flex-shrink: 0;
    }

    .mobile-search-trigger {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: transparent;
        cursor: pointer;
        color: var(--primary-gradient, #1c1e21);
        font-size: 0.82rem;
        transition: background 0.18s;
        flex-shrink: 0;
    }

    .mobile-search-trigger:hover {
        background: var(--bg-main, #e4e6ea);
    }

    /* Badge notificações */
    .mobile-notif-link {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        text-decoration: none;
        color: var(--text-main, #1c1e21);
        transition: background 0.18s;
    }

    .mobile-notif-link:hover {
        background: var(--hover-bg, rgba(0, 0, 0, 0.06));
    }

    .mobile-notif-link i {
        font-size: 1.05rem;
    }

    .mobile-notif-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        background: var(--danger, #e41e3f);
        color: #fff;
        font-size: 0.6rem;
        font-weight: 700;
        line-height: 16px;
        text-align: center;
        border-radius: 8px;
        border: 1.5px solid var(--bg-card, #fff);
        box-sizing: border-box;
        pointer-events: none;
        white-space: nowrap;
    }

    /* Avatar */
    .mobile-profile-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        text-decoration: none;
        transition: opacity 0.18s;
    }

    .mobile-profile-link:hover {
        opacity: 0.82;
    }

    .mobile-profile-pic {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        object-fit: cover;
        border: 1.5px solid rgba(0, 0, 0, 0.1);
        display: block;
    }

    @media (max-width: 360px) {
        .mobile-item {
            width: 36px;
            height: 36px;
        }

        .mobile-search-trigger,
        .mobile-notif-link,
        .mobile-profile-link {
            width: 32px;
            height: 32px;
        }

        .mobile-profile-pic {
            width: 22px;
            height: 22px;
        }
    }
</style>

<header class="mobile-header" role="banner" aria-label="Barra de navegação mobile">

    <button id="mobileMenuToggle" class="mobile-menu-toggle"
        aria-label="Abrir menu" aria-expanded="false" aria-controls="appSidebar">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>

    <!-- Trigger pesquisa (lado esquerdo) -->
    <button class="mobile-search-trigger" id="mobileSearchTrigger"
        aria-label="Pesquisar" aria-expanded="false"
        aria-controls="mobileSearchOverlay" title="Pesquisar">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
    </button>

    <div class="mobile-center">
        <nav class="mobile-nav" aria-label="Navegação principal">
            <a href="<?= BASE_URL ?>index.php"
                class="mobile-item <?= $current_page === 'index.php' ? 'active' : '' ?>"
                title="Home" aria-label="Home">
                <i class="fa-solid fa-house" aria-hidden="true"></i>
            </a>
            <a href="<?= BASE_URL ?>reels.php"
                class="mobile-item <?= $current_page === 'reels.php' ? 'active' : '' ?>"
                aria-label="Reels">
                <i class="fa-solid fa-clapperboard" aria-hidden="true"></i>
            </a>
            <a href="<?= BASE_URL ?>create-post.php"
                class="mobile-item <?= $current_page === 'create-post.php' ? 'active' : '' ?>"
                aria-label="Criar publicação">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
            </a>
            <a href="<?= BASE_URL ?>marketplace.php"
                class="mobile-item <?= $current_page === 'marketplace.php' ? 'active' : '' ?>"
                aria-label="Marketplace">
                <i class="fa-solid fa-store" aria-hidden="true"></i>
            </a>
        </nav>

        <!-- Overlay pesquisa (sobrepõe o nav) -->
        <div class="mobile-search-overlay" id="mobileSearchOverlay" role="search">
            <form action="<?= BASE_URL ?>pesquisar.php" method="GET">
                <i class="fa-solid fa-magnifying-glass search-icon-inner" aria-hidden="true"></i>
                <input type="text" name="q" id="mobileSearchInput"
                    placeholder="Pesquisar..." autocomplete="off" aria-label="Pesquisar">
            </form>
            <button type="button" class="mobile-search-close"
                id="mobileSearchClose" aria-label="Fechar pesquisa">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="mobile-right">

        <!-- Notificações -->
        <a href="<?= BASE_URL ?>notifications.php"
            class="mobile-notif-link <?= $current_page === 'notifications.php' ? 'active' : '' ?>"
            aria-label="Notificações<?= $unread_count > 0 ? ', ' . $unread_count . ' não lidas' : '' ?>">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <?php if ($unread_count > 0): ?>
                <span class="mobile-notif-badge" aria-hidden="true">
                    <?= $unread_count > 99 ? '99+' : $unread_count ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Avatar -->
        <a href="<?= BASE_URL ?>profile.php?id=<?= (int) $current_user_id ?>"
            class="mobile-profile-link"
            aria-label="Ver perfil de <?= htmlspecialchars($user_data['username']) ?>">
            <img src="<?= htmlspecialchars($profile_pic) ?>"
                alt="<?= htmlspecialchars($user_data['username']) ?>"
                class="mobile-profile-pic"
                loading="lazy" width="26" height="26">
        </a>
    </div>

</header>

<script>
    (function() {
        /* ── Pesquisa Mobile ──────────────────────────────────────────── */
        const mTrigger = document.getElementById('mobileSearchTrigger');
        const mOverlay = document.getElementById('mobileSearchOverlay');
        const mClose = document.getElementById('mobileSearchClose');
        const mInput = document.getElementById('mobileSearchInput');

        if (mTrigger && mOverlay && mClose && mInput) {
            const openM = () => {
                mOverlay.classList.add('is-open');
                mTrigger.setAttribute('aria-expanded', 'true');
                setTimeout(() => mInput.focus(), 80);
            };
            const closeM = () => {
                mOverlay.classList.remove('is-open');
                mTrigger.setAttribute('aria-expanded', 'false');
                mInput.value = '';
                mTrigger.focus();
            };

            mTrigger.addEventListener('click', openM);
            mClose.addEventListener('click', closeM);
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && mOverlay.classList.contains('is-open')) closeM();
            });
        }

        /* ── Sidebar (hambúrguer) ─────────────────────────────────────── */
        const menuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('appSidebar');
        const sOverlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            sidebar.classList.add('is-open');
            menuToggle.setAttribute('aria-expanded', 'true');
            if (sOverlay) {
                sOverlay.style.display = 'block';
                sOverlay.offsetHeight;
                sOverlay.classList.add('is-active');
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('is-open');
            menuToggle.setAttribute('aria-expanded', 'false');
            if (sOverlay) {
                sOverlay.classList.remove('is-active');
                setTimeout(() => {
                    sOverlay.style.display = '';
                }, 260);
            }
        }

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
            });
            sOverlay?.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && sidebar.classList.contains('is-open')) closeSidebar();
            });
        }
    })();
</script>