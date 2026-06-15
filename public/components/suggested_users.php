<?php
// public/components/suggested_users.php
if (!defined('SECURE_ACCESS')) exit;

use Massango\Models\User;

global $pdo;

$current_user_id = get_current_user_id();
$suggested_users_list = [];
if (is_logged_in()) {
    $suggested_users_list = User::getSuggestedUsers($pdo, $current_user_id, 5);
}

if (!empty($suggested_users_list)): ?>
    <style>
        /* =========================
   SUGGESTED USERS CARD
========================= */

        .suggested-users-card {
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            background: transparent;
            transition: all 0.3s ease;
        }



        /* Header */
        .suggested-users-card .card-header {
            padding: 5px 20px 10px;
        }

        .suggested-users-card h6 {
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        /* Scroll horizontal */
        .suggested-users-list {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding-bottom: 10px;
            scroll-behavior: smooth;
        }

        /* Scrollbar moderna */
        .suggested-users-list::-webkit-scrollbar {
            height: 6px;
        }

        .suggested-users-list::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .suggested-users-list::-webkit-scrollbar-track {
            background: transparent;
        }

        /* Item */
        .suggested-user-item {
            min-width: 110px;
            padding: 10px;
            border-radius: 12px;
            transition: all 0.25s ease;
            background: linear-gradient(180deg, var(--card-bg), var(--bg-main));
            position: relative;
        }

        /* Hover elegante */
        .suggested-user-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        /* Avatar */
        .suggested-user-item img {
            width: 65px;
            height: 65px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #eaeaea;
            transition: all 0.3s ease;
            margin: 4px auto;
        }

        /* Glow suave no hover */
        .suggested-user-item:hover img {
            border-color: #007bff;
            box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1);

        }

        /* Username */
        .suggested-user-item p {
            margin: 5px auto;
            font-size: 12px;
            font-weight: 500;
            color: #bababa;
        }

        /* Botão */
        .follow-btn-mini {
            margin: 5px auto;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            transition: all 0.25s ease;
            background: var(--primary-gradient);
            color: #303030;
        }

        /* Hover botão */
        .follow-btn-mini:hover {
            transform: scale(1.05);
            color: #bababa;
            text-decoration: none;
        }

        /* =========================
   FUTURO: BACKGROUND IMAGE
========================= */

        .suggested-user-item::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 12px;
            background-size: cover;
            background-position: center;
            opacity: 0;
            z-index: 0;
            transition: opacity 0.3s ease;
        }

        /* Quando ativares background no futuro */
        .suggested-user-item.has-bg::before {
            opacity: 0.15;
        }

        /* Garantir conteúdo acima */
        .suggested-user-item * {
            position: relative;
            z-index: 1;
        }
    </style>
    <div class="suggested-users-card card mb-4">
        <div class="card-header bg-transparent border-0 pb-0">
            <h6 class="mb-0 font-weight-bold"><i class="fas fa-user-plus mr-2 text-primary"></i> Sugestões para seguir</h6>
        </div>
        <div class="card-body">
            <div class="suggested-users-list d-flex overflow-auto pb-2" style="gap: 15px;">
                <?php foreach ($suggested_users_list as $s_user): ?>
                    <div class="suggested-user-item text-center" style="min-width: 100px;">
                        <a href="<?= BASE_URL ?>profile.php?id=<?= $s_user['id'] ?>" class="text-decoration-none text-dark">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($s_user['profile_picture'] ?? 'default_profile.png') ?>"
                                class="rounded-circle mb-2"
                                style="width: 60px; height: 60px; object-fit: cover; border: 2px solid #eee;">
                            <p class="small mb-1 text-truncate" style="max-width: 100px;"><?= htmlspecialchars($s_user['username']) ?></p>
                        </a>
                        <button class="btn btn-sm btn-primary follow-btn-mini"
                            onclick="toggleFollow(<?= (int)$s_user['id'] ?>, this)"
                            data-user-id="<?= (int)$s_user['id'] ?>">
                            Seguir
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>