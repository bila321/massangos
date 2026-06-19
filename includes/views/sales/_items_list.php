<?php
/**
 * @var array  $item_sales
 * @var int    $max_sales
 * @var string $period
 */

$type_icons = ['video' => '🎬', 'album' => '🖼️', 'post' => '📝'];
?>
<!-- ── Lista de itens ── -->
<div class="items-section">
    <div class="items-header">
        <h3 class="items-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3"  y="3"  width="7" height="7" />
                <rect x="14" y="3"  width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" />
                <rect x="3"  y="14" width="7" height="7" />
            </svg>
            Performance por Publicação
        </h3>
        <div class="items-controls">
            <div class="search-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input type="text" id="itemSearch" class="search-input" placeholder="Pesquisar...">
            </div>
            <select id="typeFilter" class="sp-btn"
                style="cursor:pointer;appearance:none;padding-right:20px;">
                <option value="all">Todos</option>
                <option value="video">Vídeos</option>
                <option value="album">Álbuns</option>
                <option value="post">Posts</option>
            </select>
        </div>
    </div>

    <?php if (empty($item_sales)): ?>
        <div class="items-empty">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3"  y="3"  width="7" height="7" />
                <rect x="14" y="3"  width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" />
                <rect x="3"  y="14" width="7" height="7" />
            </svg>
            <p>Nenhuma venda encontrada para este período.</p>
        </div>
    <?php else: ?>
        <div id="itemsList">
            <?php foreach ($item_sales as $item):
                $type = $item['content_type'];
                $pct  = $max_sales > 0 ? round(($item['sales_count'] / $max_sales) * 100) : 0;
                $icon = $type_icons[$type] ?? '📦';
                $last = $item['last_sale']
                    ? (new DateTime($item['last_sale']))->format('d/m/Y')
                    : '—';
            ?>
                <div class="item-card"
                    data-type="<?= htmlspecialchars($type) ?>"
                    data-title="<?= htmlspecialchars(strtolower($item['title'])) ?>">

                    <?php if (!$item['is_approved']): ?>
                        <span class="item-not-approved">Pendente</span>
                    <?php endif; ?>

                    <div class="item-type-icon item-type-<?= $type ?>"><?= $icon ?></div>

                    <div class="item-body">
                        <div class="item-title-row">
                            <span class="item-title">
                                <?= htmlspecialchars(mb_strimwidth($item['title'], 0, 70, '…')) ?>
                            </span>
                            <span class="type-pill <?= $type ?>"><?= ucfirst($type) ?></span>
                        </div>

                        <div class="item-progress-row">
                            <div class="item-progress-track">
                                <div class="item-progress-fill" style="width:<?= $pct ?>%"></div>
                            </div>
                            <span class="item-progress-label">
                                <?= $item['sales_count'] ?> venda<?= $item['sales_count'] != 1 ? 's' : '' ?>
                            </span>
                            <span class="item-last-sale">· última: <?= $last ?></span>
                        </div>

                        <?php if ($item['price'] > 0): ?>
                            <div style="font-size:11px;color:var(--text-light);">
                                Preço:
                                <strong style="color:var(--text-main)">
                                    <?= number_format($item['price'], 2, ',', '.') ?> MZN
                                </strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="item-meta">
                        <div class="item-revenue">
                            <?= number_format($item['item_net'], 2, ',', '.') ?>
                            <span class="item-revenue-unit">MZN</span>
                        </div>
                        <div class="item-sales-count">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2.5">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                            </svg>
                            <?= $item['sales_count'] ?>
                        </div>
                        <a href="?type=<?= urlencode($type) ?>&id=<?= (int)$item['content_id'] ?>&period=<?= htmlspecialchars($period) ?>"
                            class="sp-btn" style="font-size:11px;padding:5px 12px;">
                            Detalhe
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
