<?php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';

SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int) $_SESSION['user_id'];

$bal = (float) $pdo->query("SELECT balance FROM users WHERE id = $uid")->fetchColumn();

$earned = (float) $pdo->query("
    SELECT COALESCE(SUM(seller_amount), 0)
    FROM sales WHERE seller_id = $uid AND status = 'completed'
")->fetchColumn();

$spent = (float) $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM sales WHERE buyer_id = $uid AND status = 'completed'
")->fetchColumn();

$partner_revenue = (float) $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM revenue_distributions WHERE user_id = $uid
")->fetchColumn();

$total_txs = (int) $pdo->query("
    SELECT COUNT(*) FROM sales WHERE buyer_id = $uid OR seller_id = $uid
")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.created_at,
        s.content_type,
        s.amount,
        s.seller_amount,
        s.status,
        s.payment_method,
        s.transaction_id,
        CASE WHEN s.buyer_id = ? THEN 'Compra' ELSE 'Venda' END AS tipo,
        CASE WHEN s.buyer_id = ? THEN s.amount ELSE s.seller_amount END AS valor_display,
        u_outro.username AS outro_user
    FROM sales s
    LEFT JOIN users u_outro ON (
        CASE WHEN s.buyer_id = ? THEN u_outro.id = s.seller_id
             ELSE u_outro.id = s.buyer_id END
    )
    WHERE s.buyer_id = ? OR s.seller_id = ?
    ORDER BY s.created_at DESC
    LIMIT 20
");
$stmt->execute([$uid, $uid, $uid, $uid, $uid]);
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Carteira";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* ── Layout ── */
    .wallet-page {
        min-height: 150vh;
        background: var(--bg-main);
        padding-top: var(--space-xl);
        width: 100%;
    }

    .wallet-inner {
        max-width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* ── Hero Card ── */
    .wallet-hero {
        position: relative;
        border-radius: var(--radius-xl);
        overflow: hidden;
        padding: 40px 32px 36px;
        text-align: center;
        background: #0a0f1d;
        border: 1px solid rgba(7, 201, 91, 0.18);
    }

    .wallet-hero-bg {
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 60% 50% at 10% 110%, rgba(7, 201, 91, 0.13) 0%, transparent 60%),
            radial-gradient(ellipse 50% 40% at 90% -10%, rgba(0, 209, 178, 0.10) 0%, transparent 60%);
        pointer-events: none;
    }

    .wallet-hero-grid {
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(7, 201, 91, 0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(7, 201, 91, 0.04) 1px, transparent 1px);
        background-size: 32px 32px;
        pointer-events: none;
    }

    .wallet-hero-label {
        position: relative;
        font-size: 11px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--primary);
        margin-bottom: 14px;
        font-weight: var(--weight-semibold);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .wallet-hero-label::before,
    .wallet-hero-label::after {
        content: '';
        width: 28px;
        height: 1px;
        background: var(--primary);
        opacity: 0.4;
    }

    .wallet-hero-amount {
        position: relative;
        font-size: 58px;
        font-weight: var(--weight-extrabold);
        color: #ffffff;
        letter-spacing: -2px;
        line-height: 1;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .wallet-hero-currency {
        font-size: 22px;
        font-weight: var(--weight-medium);
        color: rgba(255, 255, 255, 0.4);
        margin-left: 6px;
        letter-spacing: 0;
    }

    .wallet-hero-sub {
        position: relative;
        margin-top: 10px;
        font-size: 13px;
        color: rgba(255, 255, 255, 0.3);
    }

    .wallet-hero-sub span {
        color: var(--primary);
        font-weight: var(--weight-semibold);
    }

    .wallet-btn-saque {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 28px;
        padding: 13px 30px;
        border-radius: var(--radius-full);
        background: rgba(255, 255, 255, 0.05);
        color: rgba(255, 255, 255, 0.3);
        font-size: 13px;
        font-weight: var(--weight-semibold);
        border: 1px solid rgba(255, 255, 255, 0.08);
        cursor: not-allowed;
        letter-spacing: 0.4px;
        transition: none;
    }

    .wallet-btn-saque svg {
        opacity: 0.4;
    }

    .soon-pill {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1px;
        padding: 2px 8px;
        border-radius: 20px;
        text-transform: uppercase;
    }

    /* ── Stats Grid ── */
    .wallet-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
    }

    .stat-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 20px 18px;
        border: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        gap: 10px;
        transition: border-color 0.2s;
    }

    .stat-card:hover {
        border-color: var(--primary);
    }

    .stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .stat-label {
        font-size: 11px;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: var(--weight-semibold);
    }

    .stat-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stat-icon.green {
        background: rgba(7, 201, 91, 0.12);
    }

    .stat-icon.red {
        background: rgba(239, 68, 68, 0.12);
    }

    .stat-icon.blue {
        background: rgba(59, 130, 246, 0.12);
    }

    .stat-value {
        font-size: 22px;
        font-weight: var(--weight-extrabold);
        color: var(--text-main);
        line-height: 1;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .stat-value.green {
        color: var(--success);
    }

    .stat-value.red {
        color: var(--danger);
    }

    .stat-value.blue {
        color: var(--info);
    }

    .stat-currency {
        font-size: 11px;
        color: var(--text-light);
        font-weight: var(--weight-normal);
        margin-left: 3px;
    }

    .stat-footer {
        font-size: 12px;
        color: var(--text-muted);
    }

    /* ── Transactions ── */
    .wallet-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .wallet-section-title {
        font-size: 15px;
        font-weight: var(--weight-bold);
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .wallet-section-title svg {
        color: var(--primary);
    }

    .tx-count-pill {
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: var(--radius-full);
    }

    .tx-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        overflow: hidden;
    }

    /* ── Empty state ── */
    .tx-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 56px 20px;
        gap: 12px;
        color: var(--text-light);
    }

    .tx-empty svg {
        opacity: 0.3;
    }

    .tx-empty p {
        font-size: 14px;
        margin: 0;
    }

    /* ── Table ── */
    .tx-table {
        width: 100%;
        border-collapse: collapse;
    }

    .tx-table th {
        text-align: left;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: var(--text-light);
        font-weight: var(--weight-semibold);
        padding: 14px 16px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-surface);
        white-space: nowrap;
    }

    .tx-table td {
        padding: 15px 16px;
        font-size: 13px;
        color: var(--text-main);
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .tx-table tbody tr:last-child td {
        border-bottom: none;
    }

    .tx-table tbody tr {
        transition: background 0.15s;
    }

    .tx-table tbody tr:hover td {
        background: var(--bg-surface);
    }

    /* Date */
    .tx-date {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .tx-date-day {
        font-weight: var(--weight-semibold);
        font-size: 13px;
    }

    .tx-date-time {
        font-size: 11px;
        color: var(--text-light);
    }

    /* Tipo badge */
    .tipo-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: var(--radius-full);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    .tipo-venda {
        background: rgba(7, 201, 91, 0.12);
        color: var(--success);
    }

    .tipo-compra {
        background: rgba(239, 68, 68, 0.12);
        color: var(--danger);
    }

    /* Conteúdo */
    .tx-content {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tx-content-icon {
        width: 30px;
        height: 30px;
        border-radius: var(--radius-md);
        background: var(--bg-surface);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    .tx-content-name {
        font-size: 13px;
        color: var(--text-main);
        font-weight: var(--weight-medium);
    }

    .tx-content-user {
        font-size: 11px;
        color: var(--text-light);
    }

    /* Método de pagamento */
    .method-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: var(--radius-md);
        font-size: 11px;
        font-weight: 700;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .method-mpesa {
        background: rgba(237, 28, 36, 0.08);
        color: #ed1c24;
        border-color: rgba(237, 28, 36, 0.15);
    }

    .method-emola {
        background: rgba(255, 102, 0, 0.08);
        color: #ff6600;
        border-color: rgba(255, 102, 0, 0.15);
    }

    .method-unknown {
        background: var(--bg-surface);
        color: var(--text-light);
        border-color: var(--border);
    }

    /* Valor */
    .tx-valor {
        font-size: 14px;
        font-weight: var(--weight-bold);
        font-family: 'Plus Jakarta Sans', sans-serif;
        white-space: nowrap;
    }

    .tx-valor.positivo {
        color: var(--success);
    }

    .tx-valor.negativo {
        color: var(--danger);
    }

    .tx-valor-currency {
        font-size: 10px;
        font-weight: var(--weight-normal);
        opacity: 0.6;
        margin-left: 2px;
    }

    /* Status badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: var(--radius-full);
        font-size: 11px;
        font-weight: 700;
    }

    .status-badge::before {
        content: '';
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: currentColor;
    }

    .status-completed {
        background: rgba(7, 201, 91, 0.12);
        color: var(--success);
    }

    .status-pending {
        background: rgba(245, 158, 11, 0.12);
        color: var(--warning);
    }

    .status-failed {
        background: rgba(239, 68, 68, 0.12);
        color: var(--danger);
    }

    /* ── Responsive ── */
    @media (max-width: 680px) {
        .wallet-hero-amount {
            font-size: 40px;
        }

        .wallet-stats {
            grid-template-columns: 1fr 1fr;
        }

        .wallet-stats .stat-card:last-child {
            grid-column: span 2;
        }

        .tx-table th:nth-child(4),
        .tx-table td:nth-child(4) {
            display: none;
        }
    }

    @media (max-width: 480px) {
        .wallet-hero {
            padding: 28px 20px 24px;
        }

        .wallet-stats {
            grid-template-columns: 1fr;
        }

        .wallet-stats .stat-card:last-child {
            grid-column: span 1;
        }

        .tx-table th:nth-child(5),
        .tx-table td:nth-child(5) {
            display: none;
        }
    }
</style>

<div class="wallet-page">
    <div class="wallet-inner">

        <!-- ── Hero ── -->
        <div class="wallet-hero">
            <div class="wallet-hero-bg"></div>
            <div class="wallet-hero-grid"></div>

            <div class="wallet-hero-label">Saldo Disponível</div>

            <div class="wallet-hero-amount">
                <?= number_format($bal, 2, ',', '.') ?>
                <span class="wallet-hero-currency">MZN</span>
            </div>

            <div class="wallet-hero-sub">
                <?= $total_txs ?> transaç<?= $total_txs === 1 ? 'ão' : 'ões' ?> &nbsp;·&nbsp;
                Ganhos: <span><?= number_format($earned, 2, ',', '.') ?> MZN</span>
            </div>

            <div class="wallet-btn-saque">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
                Levantar Dinheiro
                <span class="soon-pill">Em breve</span>
            </div>
        </div>

        <!-- ── Stats ── -->
        <div class="wallet-stats">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Total Ganho</div>
                    <div class="stat-icon green">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#07c95b" stroke-width="2.5">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                            <polyline points="17 6 23 6 23 12" />
                        </svg>
                    </div>
                </div>
                <div class="stat-value green">
                    <?= number_format($earned, 2, ',', '.') ?>
                    <span class="stat-currency">MZN</span>
                </div>
                <div class="stat-footer">Vendas concluídas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Total Gasto</div>
                    <div class="stat-icon red">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5">
                            <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
                            <polyline points="17 18 23 18 23 12" />
                        </svg>
                    </div>
                </div>
                <div class="stat-value red">
                    <?= number_format($spent, 2, ',', '.') ?>
                    <span class="stat-currency">MZN</span>
                </div>
                <div class="stat-footer">Compras realizadas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Parcerias</div>
                    <div class="stat-icon blue">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                </div>
                <div class="stat-value blue">
                    <?= number_format($partner_revenue, 2, ',', '.') ?>
                    <span class="stat-currency">MZN</span>
                </div>
                <div class="stat-footer">Revenue distribuído</div>
            </div>
        </div>

        <!-- ── Transactions ── -->
        <div class="wallet-section-header">
            <div class="wallet-section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="5" width="20" height="14" rx="2" />
                    <line x1="2" y1="10" x2="22" y2="10" />
                </svg>
                Histórico de Transações
            </div>
            <?php if ($total_txs > 0): ?>
                <div class="tx-count-pill"><?= $total_txs ?> no total</div>
            <?php endif; ?>
        </div>

        <div class="tx-card">
            <?php if (empty($transacoes)): ?>
                <div class="tx-empty">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="2" y="5" width="20" height="14" rx="2" />
                        <line x1="2" y1="10" x2="22" y2="10" />
                    </svg>
                    <p>Ainda não tens transações.</p>
                </div>

            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="tx-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Conteúdo</th>
                                <th>Contraparte</th>
                                <th>Método</th>
                                <th>Valor</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transacoes as $tx):

                                // Ícone de conteúdo
                                $icone = match ($tx['content_type']) {
                                    'video' => '🎬',
                                    'album' => '🖼️',
                                    'post' => '📝',
                                    default => '📦'
                                };

                                // Tipo
                                $isVenda     = $tx['tipo'] === 'Venda';
                                $tipo_class  = $isVenda ? 'tipo-venda' : 'tipo-compra';
                                $tipo_arrow  = $isVenda
                                    ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>'
                                    : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/></svg>';
                                $valor_class = $isVenda ? 'positivo' : 'negativo';
                                $sinal       = $isVenda ? '+' : '−';

                                // Status
                                $status_class = 'status-' . $tx['status'];
                                $status_label = match ($tx['status']) {
                                    'completed' => 'Concluído',
                                    'pending' => 'Pendente',
                                    'failed' => 'Falhado',
                                    default => $tx['status']
                                };

                                // Método — ícone SVG inline
                                $metodo = strtolower($tx['payment_method'] ?? '');
                                if ($metodo === 'mpesa') {
                                    $method_class = 'method-mpesa';
                                    $method_icon  = '<svg width="14" height="14" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="20" r="20" fill="#ED1C24"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="11" font-weight="bold" font-family="Arial">M</text></svg>';
                                    $method_label = 'M-Pesa';
                                } elseif ($metodo === 'emola') {
                                    $method_class = 'method-emola';
                                    $method_icon  = '<svg width="14" height="14" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="20" r="20" fill="#FF6600"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="9" font-weight="bold" font-family="Arial">eM</text></svg>';
                                    $method_label = 'e-Mola';
                                } else {
                                    $method_class = 'method-unknown';
                                    $method_icon  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
                                    $method_label = '—';
                                }

                                $data_obj = new DateTime($tx['created_at']);
                            ?>
                                <tr>
                                    <td>
                                        <div class="tx-date">
                                            <span class="tx-date-day"><?= $data_obj->format('d/m/Y') ?></span>
                                            <span class="tx-date-time"><?= $data_obj->format('H:i') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="tipo-badge <?= $tipo_class ?>">
                                            <?= $tipo_arrow ?> <?= $tx['tipo'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="tx-content">
                                            <div class="tx-content-icon"><?= $icone ?></div>
                                            <div>
                                                <div class="tx-content-name"><?= ucfirst($tx['content_type']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="tx-content-user">
                                            <?= htmlspecialchars($tx['outro_user'] ?? '—') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="method-badge <?= $method_class ?>">
                                            <?= $method_icon ?>
                                            <?= $method_label ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="tx-valor <?= $valor_class ?>">
                                            <?= $sinal ?> <?= number_format((float)$tx['valor_display'], 2, ',', '.') ?>
                                            <span class="tx-valor-currency">MZN</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $status_class ?>">
                                            <?= $status_label ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>