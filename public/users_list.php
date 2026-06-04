<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
}

// Buscar usuários ordenados por estrelas
try {
    $stmt = $pdo->query("SELECT id, username, profile_picture, stars 
                         FROM users 
                         ORDER BY stars DESC 
                         LIMIT 50");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}
?>

<div class="users-list-container">
    <div class="modal-header-custom">
        <h2>Utilizadores em Destaque</h2>
        <p>Os perfis com mais estrelas na plataforma.</p>
    </div>
    <div class="users-grid">
        <?php if (empty($users)): ?>
            <p class="empty-msg">Nenhum utilizador encontrado.</p>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <div class="user-card">
                    <div class="user-avatar">
                        <img src="<?= UPLOAD_URL . htmlspecialchars($user['profile_picture'] ?? 'profiles/default_profile.png') ?>" alt="<?= htmlspecialchars($user['username']) ?>">
                    </div>
                    <div class="user-info">
                        <h3><?= htmlspecialchars($user['username']) ?></h3>
                        <div class="user-stars">
                            <i class="fa-solid fa-star" style="color: #f59e0b;"></i>
                            <span><?= number_format($user['stars'] ?? 0, 0, ',', '.') ?> estrelas</span>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>profile.php?id=<?= $user['id'] ?>" class="btn-view-profile">Ver Perfil</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.users-list-container { padding: 20px; }
.modal-header-custom { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
.modal-header-custom h2 { font-size: 1.5rem; color: #1c1e21; margin-bottom: 5px; }
.modal-header-custom p { color: #65676b; font-size: 0.9rem; }
.users-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
.user-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 15px; text-align: center; transition: transform 0.2s, box-shadow 0.2s; }
.user-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
.user-avatar { margin-bottom: 12px; }
.user-avatar img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #f0f2f5; }
.user-info h3 { font-size: 1rem; color: #1c1e21; margin-bottom: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-stars { display: flex; align-items: center; justify-content: center; gap: 5px; font-size: 0.85rem; color: #65676b; margin-bottom: 15px; }
.btn-view-profile { display: block; background: #e7f3ff; color: #1877f2; text-decoration: none; padding: 8px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; transition: background 0.2s; }
.btn-view-profile:hover { background: #dbe7f2; }
.empty-msg { text-align: center; grid-column: 1 / -1; padding: 40px; color: #666; }
</style>

<?php
if (!$is_ajax) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>
