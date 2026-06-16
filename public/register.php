<?php

// public/register.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

if (is_logged_in()) {
    redirect(BASE_URL);
}

// ── Carrossel: imagens do banco ou fallback de gradientes ─────────────────
$carousel_slides = [];
try {
    $stmt = $pdo->query("
        SELECT title, subtitle, image_url, cta_text
        FROM auth_carousel_slides
        WHERE is_active = 1
        ORDER BY sort_order ASC
        LIMIT 6
    ");
    $carousel_slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Tabela ainda não existe — usa slides padrão
}

// Slides padrão caso a tabela esteja vazia ou não exista
if (empty($carousel_slides)) {
    $carousel_slides = [
        [
            'title'    => 'Partilhe os seus <span>momentos</span>',
            'subtitle' => 'Publique fotos, vídeos e álbuns exclusivos para os seus seguidores.',
            'image_url' => '',
            'cta_text'  => '',
        ],
        [
            'title'    => 'Monetize o seu <span>conteúdo</span>',
            'subtitle' => 'Defina preços, venda acesso premium e receba via M-Pesa ou e-Mola.',
            'image_url' => '',
            'cta_text'  => '',
        ],
        [
            'title'    => 'Cresça a sua <span>audiência</span>',
            'subtitle' => 'Sistema de estrelas que impulsiona os criadores mais activos.',
            'image_url' => '',
            'cta_text'  => '',
        ],
    ];
}

// ── Resolve image_url para URL pública absoluta ───────────────────────────
// Na BD o image_url pode ser: vazio, um nome de ficheiro local
// (gravado em public/storage/uploads/carousel/) ou uma URL externa completa.
$CAROUSEL_UPLOAD_URL = rtrim(BASE_URL, '/') . '/storage/uploads/carousel/';

foreach ($carousel_slides as &$slide) {
    $img = $slide['image_url'] ?? '';
    if ($img === '') {
        continue;
    }
    if (!filter_var($img, FILTER_VALIDATE_URL)) {
        $slide['image_url'] = $CAROUSEL_UPLOAD_URL . rawurlencode(basename($img));
    }
}
unset($slide);

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
        $username         = SecurityManager::sanitizeInput($_POST['username'] ?? '');
        $email            = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $validation_rules = [
            'username'         => ['required' => true, 'min_length' => 3],
            'email'            => ['required' => true, 'type' => 'email'],
            'password'         => ['required' => true, 'min_length' => 6, 'type' => 'password'],
            'confirm_password' => ['required' => true],
        ];

        $validation_errors = SecurityManager::validateInput($_POST, $validation_rules);

        if (!empty($validation_errors)) {
            $errors = array_merge($errors, array_values($validation_errors));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        }

        if ($password !== $confirm_password) {
            $errors[] = 'As senhas não coincidem.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $errors[] = 'Nome de usuário ou e-mail já cadastrado.';
                }
            } catch (PDOException $e) {
                error_log("Erro ao verificar usuário: " . $e->getMessage());
                $errors[] = 'Erro interno. Tente novamente mais tarde.';
            }
        }

        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, profile_picture) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $password_hash, 'default_profile.png'])) {
                    set_message("Cadastro realizado com sucesso! Faça login para continuar.", "success");
                    redirect(BASE_URL . 'login.php');
                } else {
                    $errors[] = 'Erro ao cadastrar usuário. Tente novamente.';
                }
            } catch (PDOException $e) {
                error_log("Erro no registo: " . $e->getMessage());
                $errors[] = 'Erro interno. Tente novamente mais tarde.';
            }
        }
    }
}

$csrf_token  = SecurityManager::generateCSRFToken();
$slides_json = json_encode($carousel_slides, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#07c95b">
    <title><?= $page_title ?? 'Criar Conta — Massangos' ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/variables.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modules/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/login-register.css">

    <script>
        window.BASE_URL = <?= json_encode(BASE_URL) ?>;
        window.CAROUSEL_SLIDES = <?= $slides_json ?>;
    </script>
</head>

<body>

    <div class="auth-page">

        <!-- ════════════════════════════════════
         PAINEL ESQUERDO — Branding + Carrossel (desktop)
         ════════════════════════════════════ -->
        <div class="auth-left">
            <a href="<?= BASE_URL ?>" class="auth-left-logo">
                <div class="logo-icon">M</div>
                <span class="logo-text">massangos</span>
            </a>

            <!-- Carrossel desktop -->
            <div class="auth-carousel" id="desktopCarousel" aria-label="Destaques da plataforma">
                <!-- slides injectados via JS -->
            </div>

            <!-- Dots de navegação -->
            <div class="auth-carousel-dots" id="desktopDots" role="tablist" aria-label="Navegar slides"></div>

            <div class="auth-left-stats">
                <div class="auth-stat">
                    <span class="auth-stat__num">12K+</span>
                    <span class="auth-stat__label">Utilizadores</span>
                </div>
                <div class="auth-stat">
                    <span class="auth-stat__num">48K+</span>
                    <span class="auth-stat__label">Publicações</span>
                </div>
                <div class="auth-stat">
                    <span class="auth-stat__num">99%</span>
                    <span class="auth-stat__label">Satisfação</span>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════
         MOBILE HERO — carrossel compacto (≤768px)
         ════════════════════════════════════ -->
        <div class="auth-mobile-hero">
            <a href="<?= BASE_URL ?>" class="auth-mobile-hero-top">
                <div class="logo-icon">M</div>
                <span class="logo-text">massangos</span>
            </a>

            <!-- Carrossel mobile -->
            <div class="auth-carousel auth-carousel--mobile" id="mobileCarousel" aria-label="Destaques da plataforma">
                <!-- slides injectados via JS -->
            </div>

            <!-- Dots mobile -->
            <div class="auth-carousel-dots auth-carousel-dots--mobile" id="mobileDots" role="tablist" aria-label="Navegar slides"></div>
        </div>

        <!-- ════════════════════════════════════
         PAINEL DIREITO — Formulário
         ════════════════════════════════════ -->
        <div class="auth-right">
            <div class="auth-card">

                <div class="auth-header">
                    <h2>Criar conta 🚀</h2>
                    <p>Junte-se à comunidade massangos</p>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="auth-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <!-- Nome de usuário -->
                    <div class="form-group">
                        <label for="username">Nome de Usuário</label>
                        <div class="auth-input-wrap">
                            <i class="ti ti-user auth-input-icon" aria-hidden="true"></i>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-control"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                placeholder="Escolha um nome de usuário"
                                autocomplete="username"
                                required>
                        </div>
                    </div>

                    <!-- E-mail -->
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <div class="auth-input-wrap">
                            <i class="ti ti-mail auth-input-icon" aria-hidden="true"></i>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                placeholder="seu@email.com"
                                autocomplete="email"
                                required>
                        </div>
                    </div>

                    <!-- Senha -->
                    <div class="form-group">
                        <label for="password">Senha</label>
                        <div class="auth-input-wrap">
                            <i class="ti ti-lock auth-input-icon" aria-hidden="true"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="Mínimo 6 caracteres"
                                autocomplete="new-password"
                                required>
                            <button
                                type="button"
                                class="auth-password-toggle"
                                aria-label="Mostrar/ocultar senha"
                                onclick="
                                var f=document.getElementById('password');
                                var i=this.querySelector('i');
                                if(f.type==='password'){f.type='text';i.className='ti ti-eye-off';}
                                else{f.type='password';i.className='ti ti-eye';}
                            ">
                                <i class="ti ti-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Confirmar senha -->
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Senha</label>
                        <div class="auth-input-wrap">
                            <i class="ti ti-lock auth-input-icon" aria-hidden="true"></i>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control"
                                placeholder="Repita sua senha"
                                autocomplete="new-password"
                                required>
                            <button
                                type="button"
                                class="auth-password-toggle"
                                aria-label="Mostrar/ocultar senha"
                                onclick="
                                var f=document.getElementById('confirm_password');
                                var i=this.querySelector('i');
                                if(f.type==='password'){f.type='text';i.className='ti ti-eye-off';}
                                else{f.type='password';i.className='ti ti-eye';}
                            ">
                                <i class="ti ti-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit">Cadastrar</button>
                </form>

                <div class="auth-divider">ou cadastre-se com</div>

                <div class="social-auth-buttons">
                    <a href="#" class="btn-social">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4" />
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05" />
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                        </svg>
                        Google
                    </a>
                    <a href="#" class="btn-social">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                        Facebook
                    </a>
                </div>

                <div class="auth-footer">
                    <p>Já tem uma conta? <a href="<?= BASE_URL ?>login.php">Faça login</a></p>
                </div>

            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/js/pages/register.js"></script>

</body>

</html>