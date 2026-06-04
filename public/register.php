<?php
// public/register.php
define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
$extra_css = ['premium_lightbox.css'];
require_once __DIR__ . '/../includes/header.php';

if (is_logged_in()) {
    redirect(BASE_URL);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username)) {
        $errors[] = "O nome de usuário é obrigatório.";
    }
    if (empty($email)) {
        $errors[] = "O e-mail é obrigatório.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "E-mail inválido.";
    }
    if (empty($password)) {
        $errors[] = "A senha é obrigatória.";
    }
    if (strlen($password) < 6) {
        $errors[] = "A senha deve ter pelo menos 6 caracteres.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "As senhas não coincidem.";
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Nome de usuário ou e-mail já cadastrado.";
        }
    } catch (PDOException $e) {
        $errors[] = "Erro ao verificar usuário: " . $e->getMessage();
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, profile_picture) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $password_hash, 'default_profile.png'])) {
                set_message("Cadastro realizado com sucesso! Faça login para continuar.", "success");
                redirect(BASE_URL . 'login.php');
            } else {
                set_message("Erro ao cadastrar usuário. Tente novamente.", "danger");
            }
        } catch (PDOException $e) {
            set_message("Erro ao cadastrar usuário: " . $e->getMessage(), "danger");
        }
    } else {
        foreach ($errors as $error) {
            set_message($error, "danger");
        }
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth_premium.css">

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Criar Conta</h2>
            <p>Junte-se à comunidade massangos</p>
        </div>

        <?php display_site_messages(); ?>

        <form action="" method="POST" class="auth-form">
            <div class="form-group">
                <label for="username">Nome de Usuário</label>
                <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Escolha um nome de usuário" required>
            </div>
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Seu melhor e-mail" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmar Senha</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repita sua senha" required>
            </div>
            <button type="submit" class="btn-auth-submit">Cadastrar</button>
        </form>

        <div class="auth-divider">ou cadastre-se com</div>

        <div class="social-auth-buttons">
            <a href="https://accounts.google.com/o/oauth2/auth?client_id=<?= GOOGLE_CLIENT_ID ?>&redirect_uri=<?= urlencode(GOOGLE_REDIRECT_URI) ?>&response_type=code&scope=email%20profile" class="btn-social btn-google">
                <i class="fab fa-google"></i> Google
            </a>
            <a href="https://www.facebook.com/v12.0/dialog/oauth?client_id=<?= FACEBOOK_APP_ID ?>&redirect_uri=<?= urlencode(FACEBOOK_REDIRECT_URI) ?>&scope=email" class="btn-social btn-facebook">
                <i class="fab fa-facebook-f"></i> Facebook
            </a>
        </div>

        <div class="auth-footer">
            <p>Já tem uma conta? <a href="<?= BASE_URL ?>login.php">Faça login</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>