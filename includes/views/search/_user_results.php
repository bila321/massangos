<?php /** @var array $user_results */ ?>
<?php if (empty($user_results)) return; ?>

<!-- ── Perfis encontrados ── -->
<h3 class="section-title"><i class="fas fa-users"></i> Perfis Encontrados</h3>
<div class="user-results-grid">
    <?php foreach ($user_results as $u): ?>
        <div class="user-search-card">
            <img src="<?= UPLOAD_URL . htmlspecialchars($u['profile_picture'] ?? 'profiles/default_profile.png') ?>"
                alt="<?= htmlspecialchars($u['username']) ?>">
            <h4><?= htmlspecialchars($u['username']) ?></h4>
            <p><?= htmlspecialchars($u['bio'] ?? 'Sem biografia disponível.') ?></p>
            <a href="<?= BASE_URL ?>profile.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-primary btn-block">
                Ver Perfil
            </a>
        </div>
    <?php endforeach; ?>
</div>
