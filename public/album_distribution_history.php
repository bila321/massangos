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
<style>
    .history-container {
        max-width: 1000px;
        margin: 20px auto;
        padding: 20px;
    }

    .history-header {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: var(--shadow-light);
        display: flex;
        gap: 20px;
        align-items: center;
    }

    .album-info {
        flex: 1;
    }

    .album-info h1 {
        margin: 0 0 5px 0;
        color: var(--text-primary);
    }

    .album-info p {
        margin: 3px 0;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 15px;
        box-shadow: var(--shadow-light);
        text-align: center;
    }

    .stat-card h3 {
        margin: 0 0 8px 0;
        font-size: 12px;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary-color);
        margin: 0;
    }

    .content-section {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        box-shadow: var(--shadow-light);
        margin-bottom: 20px;
    }

    .content-section h2 {
        margin: 0 0 15px 0;
        font-size: 18px;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-light);
        padding-bottom: 10px;
    }

    .partner-item {
        padding: 15px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        gap: 15px;
        align-items: center;
    }

    .partner-item:last-child {
        border-bottom: none;
    }

    .partner-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .partner-info {
        flex: 1;
    }

    .partner-name {
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 3px 0;
    }

    .partner-stats {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
    }

    .partner-earnings {
        text-align: right;
    }

    .partner-earnings-value {
        font-weight: 600;
        color: var(--primary-color);
        font-size: 16px;
    }

    .partner-percentage {
        font-size: 12px;
        color: var(--text-secondary);
    }

    .distribution-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .distribution-table th {
        background: var(--surface-bg);
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-light);
        font-size: 13px;
    }

    .distribution-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border-light);
        color: var(--text-secondary);
        font-size: 13px;
    }

    .distribution-table tr:hover {
        background: var(--surface-bg);
    }

    .role-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .role-creator {
        background: rgba(76, 175, 80, 0.2);
        color: #4CAF50;
    }

    .role-partner {
        background: rgba(33, 150, 243, 0.2);
        color: #2196F3;
    }

    .amount-value {
        color: #4CAF50;
        font-weight: 600;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 10px;
    }

    .back-button {
        display: inline-block;
        padding: 8px 16px;
        background: var(--surface-bg);
        border: 1px solid var(--border-light);
        border-radius: 20px;
        text-decoration: none;
        color: var(--text-primary);
        margin-bottom: 20px;
        transition: all 0.2s;
    }

    .back-button:hover {
        background: var(--border-light);
    }

    @media (max-width: 768px) {
        .history-header {
            flex-direction: column;
            text-align: center;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .distribution-table {
            font-size: 12px;
        }

        .distribution-table th,
        .distribution-table td {
            padding: 8px;
        }
    }
</style>

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