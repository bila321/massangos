<?php
// includes/views/follow_list/follow_list.view.php
// Variáveis disponíveis (injectadas pelo FollowListController):
//   $profile_user     — array com dados do utilizador do perfil
//   $users            — array com a lista de seguidores ou seguidos
//   $current_user_id  — int|null
//   $user_id          — int  (ID do perfil)
//   $mode             — 'followers' | 'following'
//   $pdo              — PDO (necessário para User::isFollowing() no loop)

use Massango\Models\User;

$is_followers = $mode === 'followers';
$title        = $is_followers
    ? 'Seguidores de ' . htmlspecialchars($profile_user['username'])
    : htmlspecialchars($profile_user['username']) . ' está a seguir';

$empty_msg = $is_followers
    ? 'Este utilizador ainda não tem seguidores.'
    : 'Este utilizador não está a seguir ninguém.';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/users-list.css">

<div class="user-list-container">
    <h2><?= $title ?></h2>

    <?php if (!empty($users)): ?>
        <?php foreach ($users as $u): ?>
            <?php
            $uid              = (int)$u['id'];
            $uname            = htmlspecialchars($u['username']);
            $avatar           = UPLOAD_URL . htmlspecialchars($u['profile_picture'] ?? 'default_profile.png');
            $profile_url      = BASE_URL . 'profile.php?id=' . $uid;
            $is_self          = ($current_user_id && $current_user_id === $uid);
            $is_following     = (!$is_self && $current_user_id)
                                && User::isFollowing($pdo, $current_user_id, $uid);
            $back_url         = htmlspecialchars($_SERVER['REQUEST_URI']);
            ?>
            <div class="user-list-item">
                <img src="<?= $avatar ?>" alt="Foto de perfil de <?= $uname ?>">

                <div class="user-info">
                    <a href="<?= $profile_url ?>"><?= $uname ?></a>
                </div>

                <?php if ($current_user_id && !$is_self): ?>
                    <form action="" method="POST" style="display:inline;">
                        <input type="hidden" name="action"
                               value="<?= $is_following ? 'unfollow' : 'follow' ?>">
                        <input type="hidden" name="target_user_id" value="<?= $uid ?>">
                        <input type="hidden" name="redirect_url"   value="<?= $back_url ?>">
                        <button type="submit"
                                class="follow-button <?= $is_following ? 'btn-danger' : 'btn-primary' ?>">
                            <?= $is_following ? 'Deixar de Seguir' : 'Seguir' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    <?php else: ?>
        <p><?= $empty_msg ?></p>
    <?php endif; ?>

</div>
