<?php
// public/album_distribution_history.php

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\Album;
use Massango\Models\SalesReport;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("Você precisa estar logado.", "danger");
    redirect(BASE_URL . 'login.php');
    exit();
}

$userId = get_current_user_id();
$albumId = (int)($_GET['album_id'] ?? 0);

if ($albumId <= 0) {
    set_message("Álbum não especificado.", "danger");
    redirect(BASE_URL . 'index.php');
    exit();
}

$album = Album::getAlbumById($pdo, $albumId);
if (!$album || $album['user_id'] != $userId) {
    set_message("Você não tem permissão para ver este histórico.", "danger");
    redirect(BASE_URL . 'index.php');
    exit();
}

// Obter dados
$distributionHistory = SalesReport::getCreatorSalesReport($pdo, $albumId, 100);
$albumStats = SalesReport::getAlbumSalesStats($pdo, $albumId);
$partnerPerformance = SalesReport::getAlbumPartnerPerformance($pdo, $albumId);

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/notifications.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/album_distribution_history.css">

<div class="main-layout-container">
    <main class="main-content-area">
        <?php get_and_clear_messages(); ?>

        <div class="history-container">
            <a href="<?= BASE_URL ?>edit_album.php?id=<?= $albumId ?>" class="back-button">← Voltar</a>

            <!-- Cabeçalho do álbum -->
            <div class="history-header">
                <img src="<?= UPLOAD_URL . htmlspecialchars($album['cover_photo_url']) ?>" alt="<?= htmlspecialchars($album['album_name']) ?>" class="album-cover">
                <div class="album-info">
                    <h1><?= htmlspecialchars($album['album_name']) ?></h1>
                    <p><?= htmlspecialchars($album['album_description'] ?? 'Sem descrição') ?></p>
                    <p>Preço: <strong>MZN <?= number_format($album['price'], 2) ?></strong></p>
                </div>
            </div>

            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total de Vendas</h3>
                    <p class="stat-value"><?= $albumStats['total_sales'] ?? 0 ?></p>
                </div>

                <div class="stat-card">
                    <h3>Receita do Criador</h3>
                    <p class="stat-value">MZN <?= number_format($albumStats['creator_total'] ?? 0, 2) ?></p>
                </div>

                <div class="stat-card">
                    <h3>Receita dos Parceiros</h3>
                    <p class="stat-value">MZN <?= number_format($albumStats['partners_total'] ?? 0, 2) ?></p>
                </div>

                <div class="stat-card">
                    <h3>Total Distribuído</h3>
                    <p class="stat-value">MZN <?= number_format($albumStats['total_distributed'] ?? 0, 2) ?></p>
                </div>
            </div>

            <!-- Desempenho dos parceiros -->
            <?php if (!empty($partnerPerformance)): ?>
                <div class="content-section">
                    <h2>👥 Desempenho dos Parceiros</h2>
                    <?php foreach ($partnerPerformance as $partner): ?>
                        <div class="partner-item">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($partner['profile_picture'] ?? 'default_profile.png') ?>" alt="<?= htmlspecialchars($partner['username']) ?>" class="partner-avatar">
                            <div class="partner-info">
                                <p class="partner-name">@<?= htmlspecialchars($partner['username']) ?></p>
                                <p class="partner-stats">
                                    <?= $partner['sales_count'] ?> vendas •
                                    Média: MZN <?= number_format($partner['average_per_sale'], 2) ?>
                                </p>
                            </div>
                            <div class="partner-earnings">
                                <p class="partner-earnings-value">MZN <?= number_format($partner['total_earned'], 2) ?></p>
                                <p class="partner-percentage"><?= number_format($partner['percentage'], 2) ?>% do valor</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Histórico de distribuições -->
            <div class="content-section">
                <h2>📊 Histórico de Distribuições</h2>
                <?php if (empty($distributionHistory)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📈</div>
                        <p>Nenhuma venda registrada ainda</p>
                    </div>
                <?php else: ?>
                    <table class="distribution-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Papel</th>
                                <th>Percentagem</th>
                                <th>Valor Recebido</th>
                                <th>Venda Total</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($distributionHistory as $distribution): ?>
                                <tr>
                                    <td>@<?= htmlspecialchars($distribution['username']) ?></td>
                                    <td>
                                        <span class="role-badge role-<?= $distribution['role'] ?>">
                                            <?= $distribution['role'] === 'creator' ? 'Criador' : 'Parceiro' ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($distribution['percentage'], 2) ?>%</td>
                                    <td class="amount-value">MZN <?= number_format($distribution['amount'], 2) ?></td>
                                    <td>MZN <?= number_format($distribution['total_sale_amount'], 2) ?></td>
                                    <td><?= format_datetime_ago($distribution['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>