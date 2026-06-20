<?php
/**
 * Partial: um card de utilizador no ranking.
 * Variável disponível do foreach em users_list.view.php:
 *
 * @var array $user
 */
?>
<div class="user-card">
    <div class="user-avatar">
        <img src="<?= UPLOAD_URL . htmlspecialchars($user['profile_picture'] ?? 'profiles/default_profile.png') ?>"
            alt="<?= htmlspecialchars($user['username']) ?>">
    </div>
    <div class="user-info">
        <h3><?= htmlspecialchars($user['username']) ?></h3>
        <div class="user-stars">
            <i class="fa-solid fa-star user-star-icon"></i>
            <span><?= number_format($user['stars'] ?? 0, 0, ',', '.') ?> estrelas</span>
        </div>
    </div>
    <a href="<?= BASE_URL ?>profile.php?id=<?= (int)$user['id'] ?>" class="btn-view-profile">
        Ver Perfil
    </a>
</div>
