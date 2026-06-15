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
        $username_email = SecurityManager::sanitizeInput($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? '';

        $validation_rules = [
            'username_email' => ['required' => true, 'min_length' => 3],
            'password'       => ['required' => true, 'type' => 'password']
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

                                $_SESSION['user_id']      = $user['id'];
                                $_SESSION['username']     = $user['username'];
                                $_SESSION['user_email']   = $user['email'];
                                $_SESSION['login_time']   = time();

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
                                    $stmt_lock    = $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
                                    $stmt_lock->execute([$failed_attempts, $locked_until, $user['id']]);
                                    set_message('Conta bloqueada por muitas tentativas. Tente novamente em ' . (LOGIN_LOCKOUT_TIME / 60) . ' minutos.', 'error');
                                    redirect(BASE_URL . 'login.php');
                                } else {
                                    $stmt_fail = $pdo->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?");
                                    $stmt_fail->execute([$failed_attempts, $user['id']]);
                                    $remaining = MAX_LOGIN_ATTEMPTS - $failed_attempts;
                                    $errors[]  = "Credenciais inválidas. Restam {$remaining} tentativa(s).";
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
$slides_json = json_encode($carousel_slides, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#07c95b">
    <title><?= $page_title ?? 'Massangos' ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/variables.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modules/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth_premium.css">

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
                    <h2>Bem-vindo de volta 👋</h2>
                    <p>Acesse sua conta para continuar</p>
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

                    <!-- Usuário / E-mail -->
                    <div class="form-group">
                        <label for="username_email">Usuário ou E-mail</label>
                        <div class="auth-input-wrap">
                            <i class="ti ti-user auth-input-icon" aria-hidden="true"></i>
                            <input
                                type="text"
                                id="username_email"
                                name="username_email"
                                class="form-control"
                                value="<?= htmlspecialchars($_POST['username_email'] ?? '') ?>"
                                placeholder="seu@email.com ou @usuario"
                                autocomplete="username"
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
                                placeholder="Sua senha"
                                autocomplete="current-password"
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
                        <a href="<?= BASE_URL ?>forgot_password.php" class="auth-forgot">Esqueceu a senha?</a>
                    </div>

                    <button type="submit" class="btn-auth-submit">Entrar</button>
                </form>

                <div class="auth-divider">ou continue com</div>

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
                    <p>Não tem uma conta? <a href="<?= BASE_URL ?>register.php">Cadastre-se grátis</a></p>
                </div>

            </div>

        </div>

        <script>
            /* ── Carrossel Massangos ───────────────────────────────────────
       Partilhado entre desktop (#desktopCarousel) e mobile
       (#mobileCarousel). Cada instância é independente.
    ────────────────────────────────────────────────────────────── */
            (function() {
                'use strict';

                /* Paleta de gradientes usada quando não há imagem */
                const GRADIENTS = [
                    'linear-gradient(135deg,#07c95b 0%,#00a844 100%)',
                    'linear-gradient(135deg,#0ea5e9 0%,#0369a1 100%)',
                    'linear-gradient(135deg,#a855f7 0%,#7e22ce 100%)',
                    'linear-gradient(135deg,#f97316 0%,#c2410c 100%)',
                    'linear-gradient(135deg,#ec4899 0%,#be185d 100%)',
                    'linear-gradient(135deg,#14b8a6 0%,#0f766e 100%)',
                ];

                const slides = window.CAROUSEL_SLIDES || [];
                if (!slides.length) return;

                /* ── Helpers ── */
                function buildSlide(data, idx) {
                    const grad = GRADIENTS[idx % GRADIENTS.length];
                    const div = document.createElement('div');
                    div.className = 'carousel-slide';
                    div.setAttribute('role', 'tabpanel');
                    div.setAttribute('aria-label', 'Slide ' + (idx + 1));

                    if (data.image_url) {
                        /* Fundo via imagem + overlay escuro */
                        div.style.backgroundImage =
                            'linear-gradient(to bottom,rgba(11,22,16,.35) 0%,rgba(11,22,16,.80) 100%),' +
                            'url(' + JSON.stringify(data.image_url) + ')';
                        div.style.backgroundSize = 'cover';
                        div.style.backgroundPosition = 'center';
                    } else {
                        /* Fundo via gradiente de cor */
                        div.style.background = grad;
                    }

                    div.innerHTML =
                        '<div class="carousel-slide-body">' +
                        '<p class="carousel-slide-title">' + (data.title || '') + '</p>' +
                        '<p class="carousel-slide-sub">' + (data.subtitle || '') + '</p>' +
                        '</div>';
                    return div;
                }

                function buildDot(idx, total, onClick) {
                    const btn = document.createElement('button');
                    btn.className = 'carousel-dot';
                    btn.setAttribute('role', 'tab');
                    btn.setAttribute('aria-label', 'Ir para slide ' + (idx + 1) + ' de ' + total);
                    btn.addEventListener('click', onClick);
                    return btn;
                }

                /* ── Inicializa uma instância de carrossel ── */
                function initCarousel(trackEl, dotsEl, autoDelay) {
                    if (!trackEl) return;
                    trackEl.innerHTML = '';
                    dotsEl.innerHTML = '';

                    const total = slides.length;
                    let current = 0;
                    let timer = null;

                    /* Renderiza slides */
                    slides.forEach(function(s, i) {
                        trackEl.appendChild(buildSlide(s, i));
                    });

                    const slideEls = trackEl.querySelectorAll('.carousel-slide');

                    /* Renderiza dots */
                    const dotEls = slides.map(function(_, i) {
                        const dot = buildDot(i, total, function() {
                            goTo(i, true);
                        });
                        dotsEl.appendChild(dot);
                        return dot;
                    });

                    function activate(idx) {
                        slideEls.forEach(function(el, i) {
                            el.classList.toggle('is-active', i === idx);
                            el.classList.toggle('is-prev', i === (idx - 1 + total) % total);
                        });
                        dotEls.forEach(function(d, i) {
                            d.classList.toggle('is-active', i === idx);
                            d.setAttribute('aria-selected', i === idx ? 'true' : 'false');
                        });
                        current = idx;
                    }

                    function goTo(idx, resetTimer) {
                        activate((idx + total) % total);
                        if (resetTimer) {
                            clearInterval(timer);
                            timer = setInterval(advance, autoDelay);
                        }
                    }

                    function advance() {
                        goTo(current + 1, false);
                    }

                    /* Swipe / drag support */
                    var startX = 0;
                    trackEl.addEventListener('touchstart', function(e) {
                        startX = e.touches[0].clientX;
                    }, {
                        passive: true
                    });
                    trackEl.addEventListener('touchend', function(e) {
                        var diff = startX - e.changedTouches[0].clientX;
                        if (Math.abs(diff) > 40) goTo(current + (diff > 0 ? 1 : -1), true);
                    }, {
                        passive: true
                    });

                    activate(0);
                    timer = setInterval(advance, autoDelay);
                }

                initCarousel(
                    document.getElementById('desktopCarousel'),
                    document.getElementById('desktopDots'),
                    5000
                );

                initCarousel(
                    document.getElementById('mobileCarousel'),
                    document.getElementById('mobileDots'),
                    4000
                );
            })();
        </script>
        <!-- Rodapé Minimalista -->


</body>

</html>