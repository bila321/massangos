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


<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/users-list.css">


<?php
if (!$is_ajax) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>