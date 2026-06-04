<?php
// public/partner_sales_dashboard.php

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\SalesReport;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("Você precisa estar logado.", "danger");
    redirect(BASE_URL . 'login.php');
    exit();
}

$userId = get_current_user_id();
$period = $_GET['period'] ?? 'all';

// Obter estatísticas
$stats = SalesReport::getPartnerSalesStats($pdo, $userId, $period);
$salesReport = SalesReport::getPartnerSalesReport($pdo, $userId, 20);
$topAlbums = SalesReport::getTopAlbumsForPartner($pdo, $userId, 5);
$topCreators = SalesReport::getTopCreatorsForPartner($pdo, $userId, 5);

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/notifications.css">
<style>
    .dashboard-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
    }

    .dashboard-header {
        margin-bottom: 30px;
    }

    .dashboard-header h1 {
        margin: 0 0 10px 0;
        color: var(--text-primary);
    }

    .period-filter {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .period-filter a {
        padding: 8px 16px;
        border-radius: 20px;
        text-decoration: none;
        background: var(--surface-bg);
        color: var(--text-primary);
        border: 1px solid var(--border-light);
        transition: all 0.2s;
    }

    .period-filter a.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .period-filter a:hover {
        border-color: var(--primary-color);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        box-shadow: var(--shadow-light);
    }

    .stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--primary-color);
        margin: 0;
    }

    .stat-subtitle {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 5px;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    .content-section {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        box-shadow: var(--shadow-light);
    }

    .content-section h2 {
        margin: 0 0 15px 0;
        font-size: 18px;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-light);
        padding-bottom: 10px;
    }

    .album-item,
    .creator-item {
        padding: 12px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .album-item:last-child,
    .creator-item:last-child {
        border-bottom: none;
    }

    .album-thumbnail {
        width: 50px;
        height: 50px;
        border-radius: 4px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .creator-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .item-info {
        flex: 1;
    }

    .item-name {
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 3px 0;
    }

    .item-stats {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
    }

    .item-earnings {
        text-align: right;
        font-weight: 600;
        color: var(--primary-color);
    }

    .sales-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .sales-table th {
        background: var(--surface-bg);
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-light);
    }

    .sales-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border-light);
        color: var(--text-secondary);
    }

    .sales-table tr:hover {
        background: var(--surface-bg);
    }

    .album-link {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
    }

    .album-link:hover {
        text-decoration: underline;
    }

    .amount-positive {
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

    @media (max-width: 768px) {
        .content-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .sales-table {
            font-size: 12px;
        }

        .sales-table th,
        .sales-table td {
            padding: 8px;
        }
    }
</style>

<div class="main-layout-container">
    <main class="main-content-area">
        <?php get_and_clear_messages(); ?>

        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1>💰 Dashboard de Vendas</h1>
                <p style="color: var(--text-secondary); margin: 5px 0 0 0;">Acompanhe seus ganhos como parceiro</p>
            </div>

            <!-- Filtro de período -->
            <div class="period-filter">
                <a href="?period=all" class="<?= $period === 'all' ? 'active' : '' ?>">Tudo</a>
                <a href="?period=week" class="<?= $period === 'week' ? 'active' : '' ?>">Esta Semana</a>
                <a href="?period=month" class="<?= $period === 'month' ? 'active' : '' ?>">Este Mês</a>
                <a href="?period=year" class="<?= $period === 'year' ? 'active' : '' ?>">Este Ano</a>
            </div>

            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total de Vendas</h3>
                    <p class="stat-value"><?= $stats['total_sales'] ?? 0 ?></p>
                    <p class="stat-subtitle">vendas realizadas</p>
                </div>

                <div class="stat-card">
                    <h3>Ganhos Totais</h3>
                    <p class="stat-value">MZN <?= number_format($stats['total_earned'] ?? 0, 2) ?></p>
                    <p class="stat-subtitle">receita total</p>
                </div>

                <div class="stat-card">
                    <h3>Média por Venda</h3>
                    <p class="stat-value">MZN <?= number_format($stats['average_per_sale'] ?? 0, 2) ?></p>
                    <p class="stat-subtitle">valor médio</p>
                </div>

                <div class="stat-card">
                    <h3>Álbuns Ativos</h3>
                    <p class="stat-value"><?= $stats['total_albums'] ?? 0 ?></p>
                    <p class="stat-subtitle">álbuns parceiros</p>
                </div>
            </div>

            <!-- Conteúdo principal -->
            <div class="content-grid">
                <!-- Álbuns mais lucrativos -->
                <div class="content-section">
                    <h2>🏆 Álbuns Mais Lucrativos</h2>
                    <?php if (empty($topAlbums)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📀</div>
                            <p>Nenhuma venda registrada ainda</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($topAlbums as $album): ?>
                            <div class="album-item">
                                <img src="<?= UPLOAD_URL . htmlspecialchars($album['cover_photo_url']) ?>" alt="<?= htmlspecialchars($album['name']) ?>" class="album-thumbnail">
                                <div class="item-info">
                                    <p class="item-name"><?= htmlspecialchars($album['name']) ?></p>
                                    <p class="item-stats">
                                        <?= $album['sales_count'] ?> vendas •
                                        Média: MZN <?= number_format($album['average_per_sale'], 2) ?>
                                    </p>
                                </div>
                                <div class="item-earnings">
                                    MZN <?= number_format($album['total_earned'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Criadores mais lucrativos -->
                <div class="content-section">
                    <h2>👥 Criadores Mais Lucrativos</h2>
                    <?php if (empty($topCreators)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">👤</div>
                            <p>Nenhuma venda registrada ainda</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($topCreators as $creator): ?>
                            <div class="creator-item">
                                <img src="<?= UPLOAD_URL . htmlspecialchars($creator['profile_picture'] ?? 'default_profile.png') ?>" alt="<?= htmlspecialchars($creator['username']) ?>" class="creator-avatar">
                                <div class="item-info">
                                    <p class="item-name">@<?= htmlspecialchars($creator['username']) ?></p>
                                    <p class="item-stats">
                                        <?= $creator['albums_count'] ?> álbuns •
                                        <?= $creator['sales_count'] ?> vendas
                                    </p>
                                </div>
                                <div class="item-earnings">
                                    MZN <?= number_format($creator['total_earned'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabela de vendas recentes -->
            <div class="content-section">
                <h2>📊 Vendas Recentes</h2>
                <?php if (empty($salesReport)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📈</div>
                        <p>Nenhuma venda registrada ainda</p>
                    </div>
                <?php else: ?>
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Álbum</th>
                                <th>Criador</th>
                                <th>Percentagem</th>
                                <th>Valor Recebido</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salesReport as $sale): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>view_album.php?id=<?= $sale['album_id'] ?>" class="album-link">
                                            <?= htmlspecialchars($sale['album_name']) ?>
                                        </a>
                                    </td>
                                    <td>@<?= htmlspecialchars($sale['creator_username']) ?></td>
                                    <td><?= number_format($sale['percentage'], 2) ?>%</td>
                                    <td class="amount-positive">MZN <?= number_format($sale['amount'], 2) ?></td>
                                    <td><?= format_datetime_ago($sale['created_at']) ?></td>
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