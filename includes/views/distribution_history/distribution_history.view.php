<?php
/**
 * includes/views/album/distribution_history.view.php
 *
 * View pura: só apresentação.
 * Variáveis disponíveis (injectadas pelo entry point público):
 *   array $album
 *   array $distribution_history
 *   array $album_stats
 *   array $partner_performance
 *   int   $album_id
 */
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/notifications.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/album_distribution_history.css">

<div class="main-layout-container">
    <main class="main-content-area">
        <?php get_and_clear_messages(); ?>

        <div class="history-container">
            <a href="<?= BASE_URL ?>edit_album.php?id=<?= $album_id ?>" class="back-button">← Voltar</a>

            <!-- Cabeçalho do álbum -->
            <div class="history-header">
                <img src="<?= UPLOAD_URL . htmlspecialchars($album['cover_photo_url']) ?>"
                     alt="<?= htmlspecialchars($album['album_name']) ?>"
                     class="album-cover">
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
                    <p class="stat-value"><?= $album_stats['total_sales'] ?? 0 ?></p>
                </div>

                <div class="stat-card">
                    <h3>Receita do Criador</h3>
                    <p class="stat-value">MZN <?= number_format($album_stats['creator_total'] ?? 0, 2) ?></p>
                </div>

                <div class="stat-card">
                    <h3>Receita dos Parceiros</h3>
                    <p class="stat-value">MZN <?= number_format($album_stats['partners_total'] ?? 0, 2) ?></p>
                </div>

                <div class="stat-card">
                    <h3>Total Distribuído</h3>
                    <p class="stat-value">MZN <?= number_format($album_stats['total_distributed'] ?? 0, 2) ?></p>
                </div>
            </div>

            <!-- Desempenho dos parceiros -->
            <?php if (!empty($partner_performance)): ?>
                <div class="content-section">
                    <h2>👥 Desempenho dos Parceiros</h2>
                    <?php foreach ($partner_performance as $partner): ?>
                        <div class="partner-item">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($partner['profile_picture'] ?? 'default_profile.png') ?>"
                                 alt="<?= htmlspecialchars($partner['username']) ?>"
                                 class="partner-avatar">
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
                <?php if (empty($distribution_history)): ?>
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
                            <?php foreach ($distribution_history as $distribution): ?>
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
