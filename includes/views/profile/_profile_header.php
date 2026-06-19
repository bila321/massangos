<?php
/**
 * @var array  $profile_data
 * @var string $account_type
 * @var bool   $is_owner
 * @var bool   $is_blocked_by_me
 * @var float  $star_rating
 * @var int    $total_visits
 * @var int    $followers_count
 * @var int    $following_count
 * @var int    $profile_user_id
 * @var string $follow_label
 * @var string $follow_class
 * @var string $follow_icon
 */

$block_confirm = $is_blocked_by_me
    ? 'Deseja desbloquear este usuário?'
    : 'Tem certeza que deseja bloquear este usuário?';
$block_action  = $is_blocked_by_me ? 'unblock' : 'block';
$block_title   = $is_blocked_by_me ? 'Desbloquear' : 'Bloquear';
$block_icon    = $is_blocked_by_me ? 'fa-user-check' : 'fa-ellipsis';
?>

<!-- ============================================================
     Cabeçalho do Perfil
     ============================================================ -->
<div class="profile-header card">

    <!-- Foto de Capa -->
    <div class="profile-cover-area">
        <?php if (!empty($profile_data['cover_photo'])): ?>
            <img src="<?= UPLOAD_URL . htmlspecialchars($profile_data['cover_photo']) ?>"
                alt="Foto de Capa"
                class="profile-cover-img">
        <?php endif; ?>
        <?php if ($is_owner): ?>
            <a href="<?= BASE_URL ?>settings.php?tab=cover" class="btn-edit-cover">
                <i class="fa-solid fa-camera"></i>
                <span>Editar capa</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="profile-avatar-row">

        <!-- Avatar -->
        <div class="profile-avatar-wrap">
            <img src="<?= UPLOAD_URL . htmlspecialchars($profile_data['profile_picture'] ?? 'default_profile.png') ?>"
                alt="Foto de Perfil"
                class="profile-avatar">

            <?php if ($account_type === 'professional'): ?>
                <div class="avatar-badge" title="Profissional">
                    <i class="fas fa-check-circle" style="color:#4f46e5;"></i>
                </div>
            <?php elseif ($account_type === 'premium'): ?>
                <div class="avatar-badge" title="Premium">
                    <i class="fas fa-crown" style="color:#ffd700;"></i>
                </div>
            <?php endif; ?>

            <?php if ($is_owner): ?>
                <a href="<?= BASE_URL ?>settings.php?tab=avatar" class="btn-edit-avatar" title="Alterar foto">
                    <i class="fa-solid fa-camera"></i>
                </a>
            <?php endif; ?>
        </div>

        <!-- Ações do perfil -->
        <div class="profile-header-actions">
            <?php if ($is_owner): ?>

                <button class="btn-add-post"
                    onclick="typeof openPublicationModal === 'function' ? openPublicationModal() : (window.location.href='<?= BASE_URL ?>create.php')"
                    title="Adicionar publicação">
                    <i class="fa-solid fa-plus"></i>
                    <span>Adicionar</span>
                </button>
                <a href="<?= BASE_URL ?>settings.php" class="btn-profile-more" title="Configurações">
                    <i class="fa-solid fa-gear"></i>
                </a>

            <?php elseif (is_logged_in()): ?>

                <?php if (!$is_blocked_by_me): ?>
                    <button class="btn-follow-profile <?= $follow_class ?> follow-btn-mini"
                        onclick="App.toggleFollow(<?= (int)$profile_user_id ?>, this)"
                        data-user-id="<?= (int)$profile_user_id ?>">
                        <i class="fa-solid <?= $follow_icon ?>"></i>
                        <span><?= $follow_label ?></span>
                    </button>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>actions/block.php" method="POST" style="margin:0;"
                    onsubmit="return confirm('<?= $block_confirm ?>');">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$profile_user_id) ?>">
                    <input type="hidden" name="action" value="<?= $block_action ?>">
                    <button type="submit" class="btn-profile-more" title="<?= $block_title ?>">
                        <i class="fa-solid <?= $block_icon ?>"></i>
                    </button>
                </form>

            <?php endif; ?>
        </div>
    </div>

    <!-- Info: Nome, Stars, Stats, Bio -->
    <div class="profile-info-main">
        <div class="name-and-meta">
            <h1>
                <?= htmlspecialchars($profile_data['username']) ?>
                <?php if ($account_type === 'professional'): ?>
                    <i class="fas fa-check-circle" style="color:#4f46e5;font-size:0.65em;" title="Profissional"></i>
                <?php elseif ($account_type === 'premium'): ?>
                    <i class="fas fa-crown" style="color:#ffd700;font-size:0.65em;" title="Premium"></i>
                <?php endif; ?>
            </h1>

            <?php if ($star_rating > 0): ?>
                <div class="profile-rating">
                    <div class="star-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa fa-star"
                                style="color:<?= $i <= $star_rating ? '#fbbf24' : 'inherit' ?>;font-size:0.8rem;"></i>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-stats">
            <?php if ($is_owner): ?>
                <div class="stat-item">
                    <strong><?= number_format($total_visits, 0, ',', '.') ?></strong>
                    <small>visitas</small>
                </div>
                <div class="stat-divider"></div>
            <?php endif; ?>
            <div class="stat-item">
                <a href="<?= BASE_URL ?>followers.php?id=<?= htmlspecialchars((string)$profile_user_id) ?>">
                    <strong><?= $followers_count ?></strong>
                    <small>seguidores</small>
                </a>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <a href="<?= BASE_URL ?>following.php?id=<?= htmlspecialchars((string)$profile_user_id) ?>">
                    <strong><?= $following_count ?></strong>
                    <small>seguindo</small>
                </a>
            </div>
        </div>

        <p class="profile-bio"><?= htmlspecialchars($profile_data['bio'] ?? 'Nenhuma biografia ainda.') ?></p>
    </div>

</div><!-- /.profile-header -->
