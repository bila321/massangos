<?php /** @var array $user_data */ ?>
<!-- ── Cabeçalho do utilizador ── -->
<div class="user-info-small">
    <img src="<?= UPLOAD_URL . htmlspecialchars($user_data['profile_picture'] ?? 'profiles/default_profile.png') ?>"
        alt="Perfil">
    <div class="name"><?= htmlspecialchars($user_data['username']) ?></div>
</div>
