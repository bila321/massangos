<?php
// public/partner_sales_dashboard.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';
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
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/partner_sales_dashboard.css">

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