<?php
// public/admin/users.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/header.php';

// Lógica para ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id > 0) {
        switch ($action) {
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['admin_message'] = "Status do utilizador atualizado.";
                $_SESSION['admin_message_type'] = "success";
                break;
            case 'add_star':
                $stmt = $pdo->prepare("UPDATE users SET stars = stars + 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['admin_message'] = "Estrela adicionada.";
                $_SESSION['admin_message_type'] = "success";
                break;
            case 'remove_star':
                $stmt = $pdo->prepare("UPDATE users SET stars = GREATEST(0, stars - 1) WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['admin_message'] = "Estrela removida.";
                $_SESSION['admin_message_type'] = "success";
                break;
            case 'toggle_verification':
                $stmt = $pdo->prepare("UPDATE users SET is_verified_creator = 1 - is_verified_creator, verification_status = IF(is_verified_creator = 0, 'approved', 'none') WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['admin_message'] = "Status de verificação atualizado.";
                $_SESSION['admin_message_type'] = "success";
                break;
            case 'change_role':
                if ($_SESSION['admin_role'] !== 'superadmin') {
                    $_SESSION['admin_message'] = "Apenas SuperAdmins podem alterar cargos.";
                    $_SESSION['admin_message_type'] = "danger";
                } else {
                    $new_role = $_POST['role'] ?? 'user';
                    if (in_array($new_role, ['user', 'admin', 'superadmin'])) {
                        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $stmt->execute([$new_role, $user_id]);
                        $_SESSION['admin_message'] = "Cargo atualizado.";
                        $_SESSION['admin_message_type'] = "success";
                    }
                }
                break;
        }
    }
    header("Location: users.php" . (isset($_GET['search']) ? "?search=" . urlencode($_GET['search']) : ""));
    exit();
}

$search = $_GET['search'] ?? '';
$query = "
    SELECT u.*, 
    (SELECT status FROM user_verifications WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as pending_v_status,
    (SELECT id FROM user_verifications WHERE user_id = u.id AND status = 'pending' LIMIT 1) as pending_v_id
    FROM users u";
$params = [];

if (!empty($search)) {
    $query .= " WHERE u.username LIKE ? OR u.email LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$query .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    :root {
        --premium-gold: #d4af37;
        --premium-dark: #1a1a1a;
        --premium-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
    }

    .premium-admin-card {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        padding: 25px;
        margin-bottom: 30px;
        border: 1px solid #f0f0f0;
    }

    .premium-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 12px;
    }

    .premium-table thead th {
        background: transparent;
        color: #888;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
        padding: 10px 20px;
        border: none;
    }

    .premium-table tbody tr {
        background: #fff;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
    }

    .premium-table tbody tr:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        background: #fafafa;
    }

    .premium-table td {
        padding: 15px 20px;
        vertical-align: middle;
        border-top: 1px solid #f9f9f9;
        border-bottom: 1px solid #f9f9f9;
    }

    .premium-table td:first-child {
        border-left: 1px solid #f9f9f9;
        border-top-left-radius: 10px;
        border-bottom-left-radius: 10px;
    }

    .premium-table td:last-child {
        border-right: 1px solid #f9f9f9;
        border-top-right-radius: 10px;
        border-bottom-right-radius: 10px;
    }

    .user-profile-bundle {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    }

    .user-meta h4 {
        margin: 0;
        font-size: 0.95rem;
        color: #333;
        font-weight: 700;
    }

    .user-meta span {
        font-size: 0.75rem;
        color: #999;
    }

    .premium-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
    }


    @keyframes pulse {
        0% {
            opacity: 1;
        }

        50% {
            opacity: 0.6;
        }

        100% {
            opacity: 1;
        }
    }

    .star-counter {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fff9e6;
        padding: 5px 12px;
        border-radius: 8px;
        border: 1px solid #ffeeba;
    }

    .action-btn-group {
        display: flex;
        gap: 8px;
    }

    .btn-premium-action {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }

    .btn-verify-now {
        background: #27ae60;
        color: white;
        width: auto;
        padding: 0 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .btn-verify-now:hover {
        background: #219150;
        transform: scale(1.05);
    }

    .btn-view-profile {
        background: #f0f2f5;
        color: #555;
    }

    .btn-view-profile:hover {
        background: #e4e6e9;
    }

    .search-container-premium {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 12px;
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 25px;
    }

    .premium-input {
        flex: 1;
        border: 1px solid #e0e0e0;
        padding: 10px 15px;
        border-radius: 8px;
        outline: none;
        transition: border 0.3s;
    }

    .premium-input:focus {
        border-color: var(--admin-primary);
    }
</style>

<div class="premium-admin-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: #2c3e50;">Diretório de Utilizadores</h2>
            <p style="color: #95a5a6; margin: 5px 0 0 0; font-size: 0.9rem;">Gerencie perfis, estrelas e verificações de criadores.</p>
        </div>
        <div style="text-align: right;">
            <span class="premium-badge" style="background: #f0f2f5; color: #555;">Total: <?= count($users) ?></span>
        </div>
    </div>

    <form method="GET" class="search-container-premium">
        <i class="fas fa-search" style="color: #ccc;"></i>
        <input type="text" name="search" class="premium-input" value="<?= htmlspecialchars($search) ?>" placeholder="Pesquisar por nome, @username ou email...">
        <button type="submit" class="btn-admin btn-edit" style="padding: 10px 25px; border-radius: 8px;">Filtrar</button>
    </form>

    <div class="table-responsive">
        <table class="premium-table">
            <thead>
                <tr>
                    <th>Utilizador</th>
                    <th>Status de Verificação</th>
                    <th>Reputação (Estrelas)</th>
                    <th>Financeiro</th>
                    <th>Cargo</th>
                    <th>Estado</th>
                    <th>Ações Rápidas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="user-profile-bundle">
                                <img src="<?= UPLOAD_URL . ($user['profile_picture'] ?: 'profiles/default_profile.png') ?>" class="user-avatar">
                                <div class="user-meta">
                                    <h4><?= htmlspecialchars($user['username']) ?></h4>
                                    <span><?= htmlspecialchars($user['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($user['is_verified_creator']): ?>
                                <span class="premium-badge badge-verified"><i class="fas fa-check-circle"></i> Verificado</span>
                            <?php elseif ($user['pending_v_status'] === 'pending'): ?>
                                <a href="verifications.php" class="premium-badge badge-pending" style="text-decoration: none;">
                                    <i class="fas fa-clock"></i> Pendente
                                </a>
                            <?php else: ?>
                                <span class="premium-badge" style="background: #f5f5f5; color: #999;">Não Verificado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="star-counter">
                                <i class="fas fa-star" style="color: #f1c40f;"></i>
                                <span style="font-weight: 800;"><?= $user['stars'] ?></span>
                                <form method="POST" style="display: flex; gap: 2px; margin-left: 5px;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="action" value="add_star" class="btn-premium-action" style="background: #e8f5e9; color: #2e7d32; width: 20px; height: 20px; font-size: 10px;">+</button>
                                    <button type="submit" name="action" value="remove_star" class="btn-premium-action" style="background: #ffebee; color: #c62828; width: 20px; height: 20px; font-size: 10px;">-</button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #2c3e50;">
                                <?= number_format($user['balance'], 2, ',', '.') ?> <small>MT</small>
                            </div>
                        </td>
                        <td>
                            <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="action" value="change_role">
                                    <select name="role" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 6px; border: 1px solid #eee; font-size: 0.8rem; background: #fafafa;">
                                        <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="superadmin" <?= $user['role'] == 'superadmin' ? 'selected' : '' ?>>SuperAdmin</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="premium-badge" style="background: #eee; color: #666;"><?= ucfirst($user['role']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button type="submit" name="action" value="toggle_status" style="background: transparent; border: none; cursor: pointer;">
                                    <?php if ($user['is_active']): ?>
                                        <span class="premium-badge" style="background: #e8f5e9; color: #2e7d32;">Ativo</span>
                                    <?php else: ?>
                                        <span class="premium-badge" style="background: #ffebee; color: #c62828;">Bloqueado</span>
                                    <?php endif; ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="action-btn-group">
                                <a href="<?= BASE_URL ?>profile.php?id=<?= $user['id'] ?>" target="_blank" class="btn-premium-action btn-view-profile" title="Ver Perfil Público">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>

                                <?php if ($user['pending_v_status'] === 'pending'): ?>
                                    <a href="verifications.php" class="btn-premium-action btn-verify-now" title="Analisar Documentos">
                                        <i class="fas fa-user-check"></i> Verificar Agora
                                    </a>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" name="action" value="toggle_verification" class="btn-premium-action" style="background: <?= $user['is_verified_creator'] ? '#fff3e0' : '#f0f2f5' ?>; color: <?= $user['is_verified_creator'] ? '#ef6c00' : '#555' ?>;" title="<?= $user['is_verified_creator'] ? 'Remover Selo' : 'Dar Selo de Verificado' ?>">
                                            <i class="fas fa-certificate"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>