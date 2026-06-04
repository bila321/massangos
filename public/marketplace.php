<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
}

// Buscar publicações à venda
try {
    $stmt = $pdo->query("SELECT p.*, u.username, u.profile_picture 
                         FROM posts p 
                         JOIN users u ON p.user_id = u.id 
                         WHERE p.is_for_sale = 1 AND p.is_approved = 1 
                         ORDER BY p.created_at DESC 
                         LIMIT 30");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $items = [];
}
?>

<div class="marketplace-container">
    <div class="modal-header-custom">
        <h2>Marketplace</h2>
        <p>Encontre os melhores conteúdos e produtos exclusivos.</p>
    </div>
    <div class="marketplace-grid">
        <?php if (empty($items)): ?>
            <p class="empty-msg">Nenhum item à venda no momento.</p>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="market-card">
                    <div class="market-image">
                        <img src="<?= UPLOAD_URL . htmlspecialchars($item['image_path'] ?? 'posts/default_post.png') ?>" alt="<?= htmlspecialchars($item['content']) ?>">
                    </div>
                    <div class="market-details">
                        <div class="market-price"><?= number_format($item['price'] ?? 0, 2, ',', '.') ?> MT</div>
                        <div class="market-title"><?= htmlspecialchars(substr($item['content'], 0, 50)) ?><?= strlen($item['content']) > 50 ? '...' : '' ?></div>
                        <div class="market-seller">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($item['profile_picture'] ?? 'profiles/default_profile.png') ?>" alt="<?= htmlspecialchars($item['username']) ?>">
                            <span>@<?= htmlspecialchars($item['username']) ?></span>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>post.php?id=<?= $item['id'] ?>" class="btn-buy-item">Comprar Agora</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.marketplace-container { padding: 20px; }
.modal-header-custom { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
.modal-header-custom h2 { font-size: 1.5rem; color: #1c1e21; margin-bottom: 5px; }
.modal-header-custom p { color: #65676b; font-size: 0.9rem; }
.marketplace-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
.market-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s; }
.market-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
.market-image { height: 180px; overflow: hidden; background: #f0f2f5; }
.market-image img { width: 100%; height: 100%; object-fit: cover; }
.market-details { padding: 15px; flex-grow: 1; }
.market-price { font-size: 1.1rem; font-weight: 800; color: #1c1e21; margin-bottom: 5px; }
.market-title { font-size: 0.9rem; color: #65676b; margin-bottom: 12px; line-height: 1.4; height: 2.8em; overflow: hidden; }
.market-seller { display: flex; align-items: center; gap: 8px; border-top: 1px solid #f0f2f5; padding-top: 10px; }
.market-seller img { width: 24px; height: 24px; border-radius: 50%; }
.market-seller span { font-size: 0.8rem; color: #65676b; font-weight: 600; }
.btn-buy-item { display: block; background: #1877f2; color: #fff; text-decoration: none; padding: 10px; text-align: center; font-size: 0.9rem; font-weight: 600; transition: background 0.2s; }
.btn-buy-item:hover { background: #166fe5; }
.empty-msg { text-align: center; grid-column: 1 / -1; padding: 40px; color: #666; }
</style>

<?php
if (!$is_ajax) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>
