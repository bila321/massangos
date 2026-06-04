<?php
// public/admin/header.php
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}
require_once __DIR__ . '/admin_functions.php';
check_admin_access();
?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - massangos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>

<body>
    <aside class="admin-sidebar">
        <div class="sidebar-header">massangos Admin</div>
        <nav class="sidebar-nav">
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> Utilizadores</a>
            <a href="content.php" class="<?= basename($_SERVER['PHP_SELF']) == 'content.php' ? 'active' : '' ?>"><i class="fas fa-photo-video"></i> Conteúdos</a>
            <a href="sales.php" class="<?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i> Vendas</a>
            <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i> Relatórios</a>
            <a href="verifications_review.php" class="<?= basename($_SERVER['PHP_SELF']) == 'verifications_review.php' ? 'active' : '' ?>"><i class="fas fa-check-circle"></i> Revisão de Verificações</a>
            <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Configurações</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" style="color: rgba(255,255,255,0.5); text-decoration: none;"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </div>
    </aside>

    <main class="admin-main">
        <?php if (isset($_SESSION['admin_message'])): ?>
            <div class="toast-container">
                <div class="toast <?= $_SESSION['admin_message_type'] ?? 'info' ?>">
                    <i class="fas <?= ($_SESSION['admin_message_type'] ?? '') === 'success' ? 'fa-check-circle' : 'fa-info-circle' ?>"></i>
                    <span><?= $_SESSION['admin_message'] ?></span>
                </div>
            </div>
            <script>
                setTimeout(() => {
                    const toast = document.querySelector('.toast-container');
                    if (toast) toast.style.display = 'none';
                }, 5000);
            </script>
            <?php
            unset($_SESSION['admin_message']);
            unset($_SESSION['admin_message_type']);
            ?>
        <?php endif; ?>

        <header class="admin-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="mobile-toggle"><i class="fas fa-bars"></i></button>
                <h2 style="margin: 0;"><?= ucfirst(str_replace('.php', '', basename($_SERVER['PHP_SELF']))) ?></h2>
            </div>
            <div class="admin-user">
                <span>Olá, <strong><?= $_SESSION['admin_role'] === 'superadmin' ? 'SuperAdmin' : 'Admin' ?></strong></span>
                <a href="../index.php" target="_blank" style="margin-left: 15px; color: var(--admin-accent); text-decoration: none;"><i class="fas fa-external-link-alt"></i> <span class="hide-mobile">Ver Site</span></a>
            </div>
        </header>

        <?php if (isset($_SESSION['admin_message'])): ?>
            <div class="alert alert-<?= $_SESSION['admin_message_type'] ?? 'info' ?>">
                <?= $_SESSION['admin_message'];
                unset($_SESSION['admin_message']); ?>
            </div>
        <?php endif; ?>

        <script src="assets/js/admin-main.js"></script>