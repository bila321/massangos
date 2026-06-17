<?php

/**
 * includes/header.php — Header unificado
 * Substitui header.php, header2.php e header3.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

use Massango\Models\User;
use Massango\Models\Notification;

if (session_status() === PHP_SESSION_NONE) {
    SecurityManager::initSecurity();
}

if (!is_logged_in()) {
    set_message("Voce precisa estar logado para acessar o massangos.", "danger");
    redirect(BASE_URL . 'login.php');
}

if (!function_exists('display_site_messages')) {
    function display_site_messages(): void
    {
        $messages = get_and_clear_messages();
        if (!empty($messages)) {
            echo '<div class="alert-container" style="position: fixed; top: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 12px;">';
            foreach ($messages as $message) {
                $type = htmlspecialchars($message['type'] ?? 'info');
                $bg   = $type === 'danger' ? 'var(--danger)' : ($type === 'success' ? 'var(--success)' : 'var(--info)');
                echo '<div class="alert" style="background: ' . $bg . '; color: white; padding: 14px 24px; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 12px; min-width: 320px; animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);">';
                echo '<span style="font-weight: 500; font-size: 0.95rem;">' . htmlspecialchars($message['content'] ?? '') . '</span>';
                echo '<button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:auto;font-size:1.5rem;line-height:1;">&times;</button>';
                echo '</div>';
            }
            echo '</div>';
            echo '<style>@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }</style>';
            echo '<script>setTimeout(() => { document.querySelectorAll(".alert-container .alert").forEach(a => { a.style.opacity = "0"; a.style.transform = "translateX(20px)"; a.style.transition = "all 0.5s ease"; }); setTimeout(() => document.querySelector(".alert-container")?.remove(), 500); }, 5000);</script>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#0f172a">
    <title><?= $page_title ?? 'Massango | Modern Social Platform' ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/_consolidated/skeleton.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/media.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/protection.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modules/publication-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modules/page-transition.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/sidebar.css">
    <?php if (!empty($extra_css)): ?>
        <?php foreach ((array)$extra_css as $css): ?>
            <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/<?= htmlspecialchars($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        window.BASE_URL = <?= json_encode(BASE_URL) ?>;
        window.CURRENT_USER_ID = <?= json_encode(is_logged_in() ? (int) get_current_user_id() : null) ?>;
    </script>

    <script src="<?= BASE_URL ?>assets/js/components/lazy-videos.js" defer></script>
    <script src="<?= BASE_URL ?>assets/js/core/page-transition.js"></script>
    <script src="<?= BASE_URL ?>assets/js/protection/content-protection.js" defer></script>
    <script src="<?= BASE_URL ?>assets/js/protection/advanced-media-protection.js" defer></script>
    <?php if (!empty($extra_head_js)): ?>
        <?php foreach ((array)$extra_head_js as $js): ?>
            <script src="<?= BASE_URL ?>assets/js/<?= htmlspecialchars($js) ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($extra_head_html)) echo $extra_head_html; ?>
</head>

<body data-user-info="<?= is_logged_in() ? htmlspecialchars($_SESSION['username'] . ' (' . $_SERVER['REMOTE_ADDR'] . ')') : htmlspecialchars($_SERVER['REMOTE_ADDR']) ?>">
    <?php include_once __DIR__ . '/topbar.php'; ?>
    <div class="app-container">
        <?php include_once __DIR__ . '/mobile-nav.php'; ?>
        <?php if (empty($hide_sidebar)): ?>
            <?php include_once __DIR__ . '/sidebar.php'; ?>
        <?php endif; ?>
        <div id="sidebarOverlay" role="presentation"></div>
        <main class="main-content" role="main">
            <div class="content-wrapper">
                <?php if (empty($hide_feed_container)): ?>
                    <div class="feed-container">
                    <?php endif; ?>
                    <?php display_site_messages(); ?>