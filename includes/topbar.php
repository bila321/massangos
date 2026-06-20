<?php
// includes/topbar.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\Notification;

if (!is_logged_in()) return;

// Garante acesso à conexão PDO quando incluído a partir de um método de classe
// (e.g. CreatePostController::show), contexto em que variáveis globais
// não são visíveis automaticamente em PHP.
global $pdo;

$current_user_id = get_current_user_id();
$user_data       = \Massango\Models\User::getUserById($pdo, $current_user_id);
$profile_pic     = UPLOAD_URL . htmlspecialchars($user_data['profile_picture'] ?? 'profiles/default_profile.png');
$unread_count    = Notification::getUnreadNotificationCount($pdo, $current_user_id);
$current_page    = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* ── Topbar shell ─────────────────────────────────────────────────── */
    .facebook-topbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--topbar-height, 52px);
        border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.08));
        background: var(--bg-card, #1c1e21);
        box-shadow: 0 1px 0 rgba(0, 0, 0, 0.18);
        display: flex;
        align-items: center;
        padding: 0 max(16px, calc((100vw - var(--container-max)) / 2 + 16px));
        gap: 4px;
        z-index: 1000;
    }

    @media (max-width: 991px) {
        .facebook-topbar {
            display: none !important;
        }
    }

    /* ── LEFT: logo + search ──────────────────────────────────────────── */
    .topbar-left {
        display: flex;
        align-items: center;
        gap: 8px;
        /* Não tem flex:1 nem min-width fixo — cresce com o input */
    }

    .topbar-logo {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 2px 8px var(--primary-glow, rgba(0, 0, 0, 0.3));
        flex-shrink: 0;
        display: block;
    }

    /* ── Search box (lupa + input expansível) ────────────────────────── */
    .topbar-search-box {
        display: flex;
        align-items: center;
        height: 36px;
        border-radius: 20px;
        background: var(--bg-main, rgba(255, 255, 255, 0.08));
        overflow: hidden;
        /* largura animada via max-width */
        max-width: 36px;
        transition: max-width 0.32s cubic-bezier(0.4, 0, 0.2, 1),
            box-shadow 0.22s ease,
            background 0.22s ease;
    }

    .topbar-search-box.is-open {
        max-width: 260px;
        background: var(--bg-main, rgba(255, 255, 255, 0.12));
        box-shadow: 0 0 0 2px var(--primary, #1eff8e);
    }

    /* Lupa — é o trigger; sempre visível */
    .topbar-search-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        flex-shrink: 0;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-secondary, #b0b3b8);
        font-size: 0.88rem;
        border-radius: 50%;
        transition: color 0.18s, background 0.18s;
    }

    .topbar-search-btn:hover {
        color: var(--text-main, #fff);
    }

    .topbar-search-box.is-open .topbar-search-btn {
        color: var(--primary, #1eff8e);
    }

    /* Input — invisível até abrir */
    .topbar-search-form {
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 0;
    }

    .topbar-search-input {
        width: 100%;
        padding: 0 4px;
        border: none;
        background: transparent;
        font-size: 0.9rem;
        color: var(--text-main, #e4e6eb);
        outline: none;
        font-family: inherit;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
        white-space: nowrap;
    }

    .topbar-search-input::placeholder {
        color: var(--text-light, #8a8d91);
    }

    .topbar-search-box.is-open .topbar-search-input {
        opacity: 1;
        pointer-events: all;
    }

    /* Botão "×" para fechar */
    .topbar-search-clear {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: none;
        background: rgba(255, 255, 255, 0.15);
        cursor: pointer;
        color: var(--text-main, #e4e6eb);
        font-size: 0.7rem;
        flex-shrink: 0;
        margin-right: 6px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease, background 0.15s;
    }

    .topbar-search-box.is-open .topbar-search-clear {
        opacity: 1;
        pointer-events: all;
    }

    .topbar-search-clear:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    /* ── CENTER: ícones nav ───────────────────────────────────────────── */
    .topbar-center {
        flex: 1;
        display: flex;
        justify-content: center;
        /* Comprime suavemente quando o search abre */
        transition: flex 0.32s cubic-bezier(0.4, 0, 0.2, 1);
        min-width: 0;
    }

    .topbar-nav {
        display: flex;
        align-items: center;
        justify-content: space-around;
        width: 50%;
    }

    .nav-item {
        display: flex;
        align-items: center;
        justify-content: space-around;
        text-decoration: none;
        color: var(--text-secondary, #b0b3b8);
        padding: 0 24px;
        height: var(--topbar-height);
        position: relative;
        border-radius: 8px;
        transition: background 0.18s;
        white-space: nowrap;
    }

    .nav-item i {
        font-size: 1.3rem;
        background: var(--text-main, #1c1e21);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        color: transparent;
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, 0.06);
        text-decoration: none;
    }

    .nav-item.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient, #1c1e21);
        border-radius: 3px 3px 0 0;
    }

    /* ── RIGHT: notif + avatar ────────────────────────────────────────── */
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }

    /* Notificações badge */
    .topbar-notif-link {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        text-decoration: none;
        color: var(--text-main, #e4e6eb);
        transition: background 0.18s;
    }

    .topbar-notif-link:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    .topbar-notif-link i {
        font-size: 1.15rem;
    }

    .topbar-notif-badge {
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
        border: 1.5px solid var(--bg-card, #1c1e21);
        box-sizing: border-box;
        pointer-events: none;
        white-space: nowrap;
    }

    /* Avatar */
    .user-profile-link {
        display: flex;
        align-items: center;
        text-decoration: none;
        padding: 4px;
        border-radius: 50%;
        transition: opacity 0.18s;
    }

    .user-profile-link:hover {
        opacity: 0.82;
    }

    .topbar-profile-pic {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 1.5px solid rgba(255, 255, 255, 0.15);
        display: block;
    }
</style>

<header class="facebook-topbar" role="banner" aria-label="Barra de navegação">

    <!-- LEFT: logo + search box expansível -->
    <div class="topbar-left">

        <a href="<?= BASE_URL ?>index.php" aria-label="Página inicial" style="flex-shrink:0;">
            <img
                src="<?= UPLOAD_URL ?>hedjo.png"
                alt="Massango"
                class="topbar-logo">
        </a>

        <!-- Search box: lupa sempre visível, input expande ao clicar -->
        <div class="topbar-search-box" id="topbarSearchBox" role="search">

            <!-- Lupa = trigger -->
            <button
                type="button"
                class="topbar-search-btn"
                id="topbarSearchTrigger"
                aria-label="Abrir pesquisa"
                aria-expanded="false"
                aria-controls="topbarSearchInput"
                title="Pesquisar">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>

            <!-- Form + input -->
            <form class="topbar-search-form" action="<?= BASE_URL ?>pesquisar.php" method="GET">
                <input
                    type="text"
                    name="q"
                    id="topbarSearchInput"
                    class="topbar-search-input"
                    placeholder="Pesquisar no Massango..."
                    autocomplete="off"
                    aria-label="Pesquisar"
                    tabindex="-1">
            </form>

            <!-- Fechar / limpar -->
            <button
                type="button"
                class="topbar-search-clear"
                id="topbarSearchClose"
                aria-label="Fechar pesquisa"
                tabindex="-1">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>

        </div>
    </div><!-- /.topbar-left -->

    <!-- CENTER: ícones de navegação -->
    <div class="topbar-center">
        <nav class="topbar-nav" aria-label="Navegação principal">
            <a href="<?= BASE_URL ?>index.php"
                class="nav-item <?= $current_page === 'index.php' ? 'active' : '' ?>"
                title="Home" aria-label="Home">
                <i class="fa-solid fa-house" aria-hidden="true"></i>
            </a>
            <a href="<?= BASE_URL ?>reels.php"
                class="nav-item <?= $current_page === 'reels.php' ? 'active' : '' ?>"
                title="Reels" aria-label="Reels">
                <i class="fa-solid fa-stop" aria-hidden="true"></i>
            </a>
            <a href="<?= BASE_URL ?>create-post.php"
                class="nav-item <?= $current_page === 'create-post.php' ? 'active' : '' ?>"
                title="Publicar" aria-label="Criar publicação">
                <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
            </a>
            <a href="<?= BASE_URL ?>marketplace.php"
                class="nav-item <?= $current_page === 'marketplace.php' ? 'active' : '' ?>"
                title="Mercado" aria-label="Marketplace">
                <i class="fa-solid fa-store" aria-hidden="true"></i>
            </a>
        </nav>
    </div>

    <!-- RIGHT: notificações + avatar -->
    <div class="topbar-right">

        <a href="<?= BASE_URL ?>notifications.php"
            class="topbar-notif-link <?= $current_page === 'notifications.php' ? 'active' : '' ?>"
            title="Notificações"
            aria-label="Notificações<?= $unread_count > 0 ? ', ' . $unread_count . ' não lidas' : '' ?>">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <?php if ($unread_count > 0): ?>
                <span class="topbar-notif-badge" aria-hidden="true">
                    <?= $unread_count > 99 ? '99+' : $unread_count ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="<?= BASE_URL ?>profile.php?id=<?= (int) $current_user_id ?>"
            class="user-profile-link"
            aria-label="Ver perfil de <?= htmlspecialchars($user_data['username']) ?>">
            <img
                src="<?= $profile_pic ?>"
                alt="<?= htmlspecialchars($user_data['username']) ?>"
                class="topbar-profile-pic"
                loading="lazy"
                width="32" height="32">
        </a>

    </div>

</header>

<script>
    (function() {
        const box = document.getElementById('topbarSearchBox');
        const trigger = document.getElementById('topbarSearchTrigger');
        const input = document.getElementById('topbarSearchInput');
        const close = document.getElementById('topbarSearchClose');

        if (!box || !trigger || !input || !close) return;

        function openSearch(e) {
            e.stopPropagation();
            box.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            input.setAttribute('tabindex', '0');
            close.setAttribute('tabindex', '0');
            setTimeout(() => input.focus(), 60);
        }

        function closeSearch() {
            box.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            input.setAttribute('tabindex', '-1');
            close.setAttribute('tabindex', '-1');
            input.value = '';
            trigger.focus();
        }

        // Clicar na lupa abre; se já estiver aberto faz submit
        trigger.addEventListener('click', (e) => {
            if (box.classList.contains('is-open')) {
                // já aberto: submete o form se tiver texto, senão fecha
                input.value.trim() ? input.closest('form').submit() : closeSearch();
            } else {
                openSearch(e);
            }
        });

        close.addEventListener('click', closeSearch);

        // Escape fecha
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && box.classList.contains('is-open')) closeSearch();
        });

        // Clicar fora fecha
        document.addEventListener('click', (e) => {
            if (box.classList.contains('is-open') && !box.contains(e.target)) closeSearch();
        });
    })();
</script>