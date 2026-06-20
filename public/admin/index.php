<?php
// public/admin/index.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/header.php';

$stats = get_detailed_stats($pdo);
$chartData = get_sales_chart_data($pdo);
?>

<div class="stats-grid">
    <div class="stat-card">
        <i class="fas fa-users" style="color: var(--admin-accent);"></i>
        <div class="stat-info">
            <h3>Total Utilizadores</h3>
            <p><?= number_format($stats['total_users']) ?></p>
            <small><?= $stats['users_today'] ?> novos hoje</small>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-photo-video" style="color: var(--admin-success);"></i>
        <div class="stat-info">
            <h3>Vídeos / Álbuns</h3>
            <p><?= number_format($stats['total_videos']) ?> / <?= number_format($stats['total_albums']) ?></p>
            <?php if ($stats['pending_approval'] > 0): ?>
                <small style="color: var(--admin-danger); font-weight: bold;"><i class="fas fa-exclamation-circle"></i> <?= $stats['pending_approval'] ?> pendentes</small>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-shopping-cart" style="color: var(--admin-warning);"></i>
        <div class="stat-info">
            <h3>Vendas (Mês)</h3>
            <p><?= number_format($stats['total_sales']) ?></p>
            <small><?= $stats['sales_today'] ?> hoje</small>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-wallet" style="color: var(--admin-danger);"></i>
        <div class="stat-info">
            <h3>Comissão (Mês)</h3>
            <p><?= number_format($stats['commission_month'], 2) ?> MT</p>
        </div>
    </div>
</div>

<div class="dashboard-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
    <div class="admin-card">
        <h3>Desempenho de Vendas (Últimos 7 dias)</h3>
        <canvas id="salesChart" height="100"></canvas>
    </div>

    <div class="admin-card">
        <h3>Ações Rápidas</h3>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="users.php" class="btn-admin btn-edit"><i class="fas fa-user-plus"></i> Gerir Utilizadores</a>
            <a href="content.php" class="btn-admin btn-edit"><i class="fas fa-shield-alt"></i> Moderar Conteúdo</a>
            <a href="sales.php" class="btn-admin btn-edit"><i class="fas fa-list"></i> Ver Vendas</a>
            <a href="verifications.php" class="btn-admin btn-edit"><i class="fas fa-id-card"></i> Verificações Pendentes</a>
            <a href="reports.php" class="btn-admin btn-edit"><i class="fas fa-file-invoice-dollar"></i> Relatórios Financeiros</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($chartData, 'label')) ?>,
            datasets: [{
                label: 'Volume de Vendas (MT)',
                data: <?= json_encode(array_column($chartData, 'total')) ?>,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>