<?php
// public/login.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

if (is_logged_in()) {
    redirect(BASE_URL);
}

$all_messages = get_and_clear_messages();
$errors = [];
$success_message = '';

if (is_array($all_messages)) {
    foreach ($all_messages as $msg) {
        if (is_array($msg) && isset($msg['type']) && isset($msg['content'])) {
            if ($msg['type'] === 'success') {
                $success_message = $msg['content'];
            } elseif ($msg['type'] === 'danger' || $msg['type'] === 'error') {
                $errors[] = $msg['content'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de segurança inválido. Tente novamente.';
    } else {
        $username_email = SecurityManager::sanitizeInput($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? '';

        $validation_rules = [
            'username_email' => ['required' => true, 'min_length' => 3],
            'password' => ['required' => true, 'type' => 'password']
        ];

        $validation_errors = SecurityManager::validateInput($_POST, $validation_rules);

        if (!empty($validation_errors)) {
            $errors = array_merge($errors, array_values($validation_errors));
        } else {
            if (SecurityManager::isLoginBlocked($username_email)) {
                $errors[] = 'Muitas tentativas de login falharam. Tente novamente em ' . (LOGIN_LOCKOUT_TIME / 60) . ' minutos.';
            } else {
                if (!SecurityManager::checkRateLimit('login_' . $_SERVER['REMOTE_ADDR'], RATE_LIMIT_MAX_ATTEMPTS, RATE_LIMIT_TIME_WINDOW)) {
                    $errors[] = 'Muitas tentativas de login. Tente novamente em alguns minutos.';
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, profile_picture, is_active, failed_login_attempts, locked_until FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
                        $stmt->execute([$username_email, $username_email]);
                        $user = $stmt->fetch();

                        if ($user) {
                            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                                $errors[] = 'Conta temporariamente bloqueada. Tente novamente mais tarde.';
                            } elseif (SecurityManager::verifyPassword($password, $user['password_hash'])) {
                                session_regenerate_id(true);

                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['username'] = $user['username'];
                                $_SESSION['user_email'] = $user['email'];
                                $_SESSION['login_time'] = time();

                                if (!empty($user['profile_picture'])) {
                                    $_SESSION['user_profile_picture'] = UPLOAD_URL . htmlspecialchars($user['profile_picture']);
                                } else {
                                    $_SESSION['user_profile_picture'] = BASE_URL . 'assets/img/default_profile.png';
                                }

                                $stmt_reset = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
                                $stmt_reset->execute([$user['id']]);

                                SecurityManager::logLoginAttempt($username_email, true);

                                set_message("Bem-vindo de volta, " . htmlspecialchars($user['username']) . "!", "success");

                                $redirect_to = $_GET['redirect'] ?? BASE_URL;
                                if (!filter_var($redirect_to, FILTER_VALIDATE_URL) || strpos($redirect_to, BASE_URL) !== 0) {
                                    $redirect_to = BASE_URL;
                                }
                                redirect($redirect_to);
                            } else {
                                SecurityManager::logLoginAttempt($username_email, false);

                                $failed_attempts = $user['failed_login_attempts'] + 1;

                                if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
                                    $locked_until = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
                                    $stmt_lock = $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
                                    $stmt_lock->execute([$failed_attempts, $locked_until, $user['id']]);
                                    set_message('Conta bloqueada devido a muitas tentativas de login falhadas. Tente novamente em ' . (LOGIN_LOCKOUT_TIME / 60) . ' minutos.', 'error');
                                    redirect(BASE_URL . 'login.php');
                                } else {
                                    $stmt_fail = $pdo->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?");
                                    $stmt_fail->execute([$failed_attempts, $user['id']]);
                                    $remaining = MAX_LOGIN_ATTEMPTS - $failed_attempts;
                                    $errors[] = "Credenciais inválidas. Restam {$remaining} tentativas.";
                                }
                            }
                        } else {
                            $errors[] = "Credenciais inválidas ou conta inativa.";
                            SecurityManager::logLoginAttempt($username_email, false);
                        }
                    } catch (PDOException $e) {
                        error_log("Erro no login: " . $e->getMessage());
                        $errors[] = "Erro interno. Tente novamente mais tarde.";
                    }
                }
            }
        }
    }
}

$csrf_token = SecurityManager::generateCSRFToken();

?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth_premium.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Bem-vindo</h2>
            <p>Acesse sua conta no massangos</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" style="background: var(--success); color: white; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="background: var(--danger); color: white; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="auth-form">
            <div class="form-group">
                <label for="username_email">Usuário ou E-mail</label>
                <input type="text" id="username_email" name="username_email" class="form-control" value="<?= htmlspecialchars($_POST['username_email'] ?? '') ?>" placeholder="Seu usuário ou e-mail" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Sua senha" required>
            </div>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" class="btn-auth-submit">Entrar</button>
        </form>

        <div class="auth-divider">ou continue com</div>

        <div class="social-auth-buttons">
            <a href="#" class="btn-social">
                <i class="fab fa-google" style="color: #ea4335;"></i> Google
            </a>
            <a href="#" class="btn-social">
                <i class="fab fa-facebook-f" style="color: #1877f2;"></i> Facebook
            </a>
        </div>

        <div class="auth-footer">
            <p>Não tem uma conta? <a href="<?= BASE_URL ?>register.php">Cadastre-se</a></p>
            <p style="margin-top: 10px;"><a href="<?= BASE_URL ?>forgot_password.php">Esqueceu a senha?</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>