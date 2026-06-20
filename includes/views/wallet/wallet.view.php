<?php

/**
 * includes/views/wallet/wallet.view.php
 *
 * View pura: só apresentação.
 * Variáveis disponíveis (injectadas pelo entry point público):
 *   float  $balance
 *   float  $earned
 *   float  $spent
 *   float  $partner_revenue
 *   int    $total_transactions
 *   array  $transactions
 */
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/wallet.css">
<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>

<div class="wallet-page">
    <div class="wallet-inner">

        <!-- ── Hero ── -->
        <div class="wallet-hero">
            <div class="wallet-hero-bg"></div>
            <div class="wallet-hero-grid"></div>

            <div class="wallet-hero-label">Saldo Disponível</div>

            <div class="wallet-hero-amount">
                <?= number_format($balance, 2, ',', '.') ?>
                <span class="wallet-hero-currency">MZN</span>
            </div>

            <div class="wallet-hero-sub">
                <?= $total_transactions ?> transaç<?= $total_transactions === 1 ? 'ão' : 'ões' ?>
                &nbsp;·&nbsp;
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
            <?php if ($total_transactions > 0): ?>
                <div class="tx-count-pill"><?= $total_transactions ?> no total</div>
            <?php endif; ?>
        </div>

        <div class="tx-card">
            <?php if (empty($transactions)): ?>

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
                            <?php foreach ($transactions as $tx):
                                $isVenda = $tx['tipo'] === 'Venda';
                            ?>
                                <tr>
                                    <td>
                                        <?php $d = new \DateTime($tx['created_at']); ?>
                                        <div class="tx-date">
                                            <span class="tx-date-day"><?= $d->format('d/m/Y') ?></span>
                                            <span class="tx-date-time"><?= $d->format('H:i') ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="tipo-badge <?= $isVenda ? 'tipo-venda' : 'tipo-compra' ?>">
                                            <?php if ($isVenda): ?>
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                                                </svg>
                                            <?php else: ?>
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                    <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
                                                </svg>
                                            <?php endif; ?>
                                            <?= $tx['tipo'] ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="tx-content">
                                            <div class="tx-content-icon">
                                                <?= match ($tx['content_type']) {
                                                    'video' => '🎬',
                                                    'album' => '🖼️',
                                                    'post'  => '📝',
                                                    default => '📦'
                                                } ?>
                                            </div>
                                            <div class="tx-content-name"><?= ucfirst($tx['content_type']) ?></div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="tx-content-user">
                                            <?= htmlspecialchars($tx['outro_user'] ?? '—') ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php
                                        $metodo = strtolower($tx['payment_method'] ?? '');
                                        [$method_class, $method_icon, $method_label] = match ($metodo) {
                                            'mpesa'  => [
                                                'method-mpesa',
                                                '<svg width="14" height="14" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="20" r="20" fill="#ED1C24"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="11" font-weight="bold" font-family="Arial">M</text></svg>',
                                                'M-Pesa',
                                            ],
                                            'emola'  => [
                                                'method-emola',
                                                '<svg width="14" height="14" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="20" r="20" fill="#FF6600"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="9" font-weight="bold" font-family="Arial">eM</text></svg>',
                                                'e-Mola',
                                            ],
                                            default  => [
                                                'method-unknown',
                                                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
                                                '—',
                                            ],
                                        };
                                        ?>
                                        <span class="method-badge <?= $method_class ?>">
                                            <?= $method_icon ?>
                                            <?= $method_label ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="tx-valor <?= $isVenda ? 'positivo' : 'negativo' ?>">
                                            <?= $isVenda ? '+' : '−' ?>
                                            <?= number_format((float) $tx['valor_display'], 2, ',', '.') ?>
                                            <span class="tx-valor-currency">MZN</span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="status-badge status-<?= $tx['status'] ?>">
                                            <?= match ($tx['status']) {
                                                'completed' => 'Concluído',
                                                'pending'   => 'Pendente',
                                                'failed'    => 'Falhado',
                                                default     => htmlspecialchars($tx['status'])
                                            } ?>
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