<?php
// public/forgot_password.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

if (is_logged_in()) {
    redirect(BASE_URL);
}

// ─────────────────────────────────────────────────────────────────────────────
// Constante de fallback caso não esteja em security.php
// (idealmente definir PASSWORD_RESET_EXPIRY = 3600 em config/security.php)
// ─────────────────────────────────────────────────────────────────────────────
if (!defined('PASSWORD_RESET_EXPIRY')) {
    define('PASSWORD_RESET_EXPIRY', 3600);
}

// ─────────────────────────────────────────────────────────────────────────────
// Estado da página
// 'request' → formulário de e-mail
// 'sent'    → confirmação de envio
// 'reset'   → formulário de nova senha (chegou via ?token=)
// ─────────────────────────────────────────────────────────────────────────────
$step       = 'request';
$errors     = [];
$token_row  = null;
$raw_token  = '';

// ── Detectar token na URL ─────────────────────────────────────────────────────
if (!empty($_GET['token'])) {
    $raw_token = trim($_GET['token']);

    try {
        $stmt = $pdo->prepare("
            SELECT et.id, et.user_id, et.token, et.expires_at, et.used,
                   u.username, u.email
            FROM   email_tokens et
            JOIN   users u ON u.id = et.user_id
            WHERE  et.token = ?
              AND  et.type  = 'password_reset'
            LIMIT  1
        ");
        $stmt->execute([$raw_token]);
        $token_row = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("forgot_password — verificar token: " . $e->getMessage());
    }

    if (!$token_row) {
        $errors[] = "Link inválido ou inexistente. Solicite um novo.";
    } elseif ($token_row['used']) {
        $errors[] = "Este link já foi utilizado. Solicite um novo.";
        $token_row = null;
    } elseif (strtotime($token_row['expires_at']) < time()) {
        $errors[] = "Este link expirou. Solicite um novo.";
        $token_row = null;
    } else {
        $step = 'reset';
    }
}

$csrf_token = SecurityManager::generateCSRFToken();

// ─────────────────────────────────────────────────────────────────────────────
// POST — Etapa 1: solicitar e-mail
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {

    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de segurança inválido. Tente novamente.';
    } elseif (!SecurityManager::checkRateLimit('forgot_' . $_SERVER['REMOTE_ADDR'], 5, 900)) {
        $errors[] = 'Demasiadas tentativas. Aguarde 15 minutos antes de tentar novamente.';
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Introduza um endereço de e-mail válido.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT id, username, email
                    FROM   users
                    WHERE  email     = ?
                      AND  is_active = 1
                    LIMIT  1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // Sempre avançar para 'sent' — não revelar se e-mail existe
                if ($user) {
                    // Invalida tokens anteriores do mesmo utilizador
                    $pdo->prepare("
                        UPDATE email_tokens
                        SET    used = 1
                        WHERE  user_id = ?
                          AND  type    = 'password_reset'
                          AND  used    = 0
                    ")->execute([$user['id']]);

                    $token      = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);

                    $pdo->prepare("
                        INSERT INTO email_tokens (user_id, token, type, expires_at)
                        VALUES (?, ?, 'password_reset', ?)
                    ")->execute([$user['id'], $token, $expires_at]);

                    _send_reset_email($user['email'], $user['username'], $token);
                }

                $step = 'sent';
            } catch (PDOException $e) {
                error_log("forgot_password — gerar token: " . $e->getMessage());
                $errors[] = 'Erro interno. Tente novamente mais tarde.';
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — Etapa 3: definir nova senha
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {

    $raw_token = trim($_POST['token'] ?? '');

    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de segurança inválido. Tente novamente.';
        $step = 'request';
    } else {
        // Revalidar token
        try {
            $stmt = $pdo->prepare("
                SELECT et.id, et.user_id, et.expires_at, et.used, u.username
                FROM   email_tokens et
                JOIN   users u ON u.id = et.user_id
                WHERE  et.token = ?
                  AND  et.type  = 'password_reset'
                LIMIT  1
            ");
            $stmt->execute([$raw_token]);
            $token_row = $stmt->fetch();
        } catch (PDOException $e) {
            $token_row = null;
        }

        if (!$token_row || $token_row['used'] || strtotime($token_row['expires_at']) < time()) {
            $errors[] = 'Link inválido ou expirado. Solicite um novo.';
            $step = 'request';
            $token_row = null;
        } else {
            $step = 'reset'; // manter formulário visível em caso de erro de validação

            $password         = $_POST['password']         ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $min_len          = defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 8;

            if (strlen($password) < $min_len) {
                $errors[] = "A senha deve ter pelo menos {$min_len} caracteres.";
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = 'A senha deve conter pelo menos uma letra maiúscula.';
            }
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = 'A senha deve conter pelo menos um número.';
            }
            if ($password !== $confirm_password) {
                $errors[] = 'As senhas não coincidem.';
            }

            if (empty($errors)) {
                try {
                    // SecurityManager::hashPassword se existir; fallback para password_hash
                    $hash = method_exists('SecurityManager', 'hashPassword')
                        ? SecurityManager::hashPassword($password)
                        : password_hash($password, PASSWORD_DEFAULT);

                    $pdo->prepare("
                        UPDATE users
                        SET    password_hash           = ?,
                               failed_login_attempts   = 0,
                               locked_until            = NULL
                        WHERE  id = ?
                    ")->execute([$hash, $token_row['user_id']]);

                    $pdo->prepare("
                        UPDATE email_tokens SET used = 1 WHERE id = ?
                    ")->execute([$token_row['id']]);

                    set_message("Senha alterada com sucesso! Faça login com a sua nova senha.", "success");
                    redirect(BASE_URL . 'login.php');
                } catch (PDOException $e) {
                    error_log("forgot_password — redefinir senha: " . $e->getMessage());
                    $errors[] = 'Erro interno. Tente novamente mais tarde.';
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: enviar e-mail de recuperação
// Tenta PHPMailer (já tens vendor/autoload.php); fallback para mail() nativo.
// ─────────────────────────────────────────────────────────────────────────────
function _send_reset_email(string $to_email, string $to_name, string $token): void
{
    $reset_link = BASE_URL . 'forgot_password.php?token=' . $token;
    $expiry_min = PASSWORD_RESET_EXPIRY / 60;
    $from_email = 'noreply@massangos.com';
    $from_name  = 'massangos';
    $subject    = 'Redefinição de senha — massangos';
    $app_name   = defined('APP_NAME') ? APP_NAME : $from_name;

    $body_text = "Olá, {$to_name}!\n\n"
        . "Recebemos um pedido de redefinição de senha para a sua conta.\n\n"
        . "Clique no link abaixo (válido por {$expiry_min} minutos):\n\n"
        . "{$reset_link}\n\n"
        . "Se não solicitou isso, ignore este e-mail — a sua senha permanece inalterada.\n\n"
        . "— Equipa {$app_name}";

    $body_html = '
<!DOCTYPE html>
<html lang="pt">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Inter,Segoe UI,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">

        <!-- Header -->
        <tr><td style="background:#07c95b;padding:32px 40px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:24px;font-weight:700;letter-spacing:-0.5px;">' . $app_name . '</h1>
        </td></tr>

        <!-- Body -->
        <tr><td style="padding:40px;">
          <h2 style="margin:0 0 16px;color:#0f172a;font-size:20px;font-weight:600;">Redefinição de senha</h2>
          <p style="margin:0 0 12px;color:#475569;font-size:15px;line-height:1.6;">
            Olá, <strong style="color:#0f172a;">' . htmlspecialchars($to_name) . '</strong>!
          </p>
          <p style="margin:0 0 28px;color:#475569;font-size:15px;line-height:1.6;">
            Recebemos um pedido de redefinição de senha. Clique no botão abaixo para criar uma nova senha.
            O link é válido por <strong>' . $expiry_min . ' minutos</strong>.
          </p>

          <!-- CTA -->
          <table cellpadding="0" cellspacing="0" style="margin:0 auto 28px;">
            <tr><td style="background:#07c95b;border-radius:8px;">
              <a href="' . $reset_link . '" style="display:inline-block;padding:14px 32px;color:#fff;font-size:15px;font-weight:600;text-decoration:none;letter-spacing:0.2px;">
                Redefinir senha
              </a>
            </td></tr>
          </table>

          <p style="margin:0 0 8px;color:#94a3b8;font-size:13px;line-height:1.5;">
            Ou copie e cole este link no seu navegador:
          </p>
          <p style="margin:0 0 28px;word-break:break-all;">
            <a href="' . $reset_link . '" style="color:#07c95b;font-size:13px;">' . $reset_link . '</a>
          </p>

          <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 24px;">

          <p style="margin:0;color:#94a3b8;font-size:13px;line-height:1.6;">
            Se não solicitou a redefinição de senha, ignore este e-mail. A sua conta permanece segura.
          </p>
        </td></tr>

        <!-- Footer -->
        <tr><td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#94a3b8;font-size:12px;">
            © ' . date('Y') . ' ' . $app_name . ' · Maputo, Moçambique
          </p>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>';

    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isMail();
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to_email, $to_name);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $body_html;
            $mail->AltBody = $body_text;
            $mail->send();
            return;
        } catch (\Exception $e) {
            error_log("PHPMailer error: " . $e->getMessage());
        } catch (\Throwable $e) {
            error_log("PHPMailer fatal: " . $e->getMessage());
        }
    }

    // Fallback: mail() nativo
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from_name} <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    mail($to_email, $subject, $body_html, $headers);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#07c95b">
    <title>Recuperar Senha — massangos</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/variables.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modules/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth_premium.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        /* ═══════════════════════════════════════════════════════════════
           LAYOUT BASE — Centralização premium + Responsividade
           (sobrepõe/reforça regras de auth_premium.css / style.css)
           ═══════════════════════════════════════════════════════════════ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif;
        }

        .auth-container {
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(16px, 4vw, 48px);
        }

        .auth-card {
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border, #e2e8f0);
            border-radius: var(--radius-xl, 1rem);
            box-shadow:
                0 1px 2px rgba(15, 23, 42, .04),
                0 12px 32px -8px rgba(15, 23, 42, .12),
                0 24px 64px -24px rgba(7, 201, 91, .08);
            padding: clamp(28px, 5vw, 48px);
            animation: auth-fade-up .5s ease both;
        }

        @keyframes auth-fade-up {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-header {
            text-align: center;
            margin-bottom: var(--space-lg, 24px);
        }

        .auth-header h2 {
            margin: 0 0 8px;
            font-size: clamp(1.35rem, 1.1rem + 1vw, 1.75rem);
            font-weight: 800;
            color: var(--text-main, #0f172a);
            letter-spacing: -0.02em;
        }

        .auth-header p {
            margin: 0;
            font-size: var(--text-sm, .9rem);
            color: var(--text-muted, #64748b);
            line-height: 1.6;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: var(--space-md, 18px);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            text-align: left;
        }

        .form-group label {
            font-size: var(--text-sm, .85rem);
            font-weight: 600;
            color: var(--text-main, #0f172a);
        }

        .form-control {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid var(--border, #e2e8f0);
            border-radius: var(--radius-md, 12px);
            font-size: 1rem;
            font-family: inherit;
            color: var(--text-main, #0f172a);
            background: var(--bg-surface, #f8fafc);
            transition: border-color .2s, box-shadow .2s, background .2s;
            -webkit-appearance: none;
            appearance: none;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary, #07c95b);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(7, 201, 91, .12);
        }

        .form-control::placeholder {
            color: var(--text-muted, #94a3b8);
        }

        .btn-auth-submit {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border: none;
            border-radius: var(--radius-md, 12px);
            background: var(--primary, #07c95b);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            font-family: inherit;
            letter-spacing: .01em;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 8px 20px -8px rgba(7, 201, 91, .55);
        }

        .btn-auth-submit:hover {
            background: var(--primary-hover, #027e21);
            transform: translateY(-1px);
            box-shadow: 0 12px 24px -8px rgba(7, 201, 91, .6);
        }

        .btn-auth-submit:active {
            transform: translateY(0);
        }

        .auth-footer {
            margin-top: var(--space-lg, 24px);
            text-align: center;
            font-size: var(--text-sm, .85rem);
            color: var(--text-muted, #64748b);
            line-height: 1.7;
        }

        .auth-footer a {
            color: var(--primary, #07c95b);
            font-weight: 600;
            text-decoration: none;
            transition: color .2s;
        }

        .auth-footer a:hover {
            color: var(--primary-hover, #027e21);
            text-decoration: underline;
        }

        .auth-card>.auth-back-link:first-child {
            margin-top: 0;
        }

        /* ═══════════════════════════════════════════════════════════════
           RESPONSIVIDADE — Breakpoints premium
           ═══════════════════════════════════════════════════════════════ */

        /* Tablets e telas médias */
        @media (max-width: 768px) {
            .auth-card {
                max-width: 420px;
                border-radius: var(--radius-lg, 0.75rem);
            }
        }

        /* Smartphones */
        @media (max-width: 480px) {
            .auth-container {
                padding: 12px;
                align-items: stretch;
            }

            .auth-card {
                max-width: 100%;
                margin: auto 0;
                border-radius: 16px;
                padding: 28px 20px;
                box-shadow:
                    0 1px 2px rgba(15, 23, 42, .04),
                    0 8px 24px -8px rgba(15, 23, 42, .10);
            }

            .auth-header h2 {
                font-size: 1.35rem;
            }

            .auth-icon-wrap {
                width: 56px;
                height: 56px;
            }

            .auth-icon-wrap i {
                font-size: 1.4rem;
            }

            .form-control,
            .btn-auth-submit,
            .btn-secondary {
                font-size: 0.95rem;
            }

            .sent-email-box {
                font-size: 0.8rem;
                padding: 10px 12px;
            }
        }

        /* Telas muito pequenas */
        @media (max-width: 360px) {
            .auth-card {
                padding: 22px 16px;
            }

            .auth-header h2 {
                font-size: 1.2rem;
            }
        }

        /* Modo paisagem em mobile — evita overflow vertical */
        @media (max-height: 600px) and (orientation: landscape) {
            .auth-container {
                align-items: flex-start;
                padding-top: 24px;
                padding-bottom: 24px;
            }

            .auth-icon-wrap {
                width: 48px;
                height: 48px;
                margin-bottom: 12px;
            }

            .auth-header {
                margin-bottom: 16px;
            }
        }

        /* Telas grandes (desktop wide) */
        @media (min-width: 1440px) {
            .auth-card {
                max-width: 480px;
                padding: 52px;
            }
        }

        /* Acessibilidade: reduzir movimento */
        @media (prefers-reduced-motion: reduce) {
            .auth-card {
                animation: none;
            }
        }

        /* ── Extras: forgot_password ─────────────────────────────────── */
        .auth-back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: var(--text-sm);
            color: var(--text-muted);
            text-decoration: none;
            margin-bottom: var(--space-lg);
            transition: color .2s;
        }

        .auth-back-link:hover {
            color: var(--primary);
        }

        .auth-icon-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--primary-soft);
            margin: 0 auto var(--space-lg);
        }

        .auth-icon-wrap i {
            font-size: 1.6rem;
            color: var(--primary);
        }

        .auth-icon-wrap.success-icon {
            background: rgba(16, 185, 129, .12);
        }

        .auth-icon-wrap.success-icon i {
            color: var(--success);
        }

        /* Caixa de e-mail na tela "sent" */
        .sent-email-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 10px 16px;
            margin: var(--space-sm) auto var(--space-lg);
            font-weight: var(--weight-semibold);
            font-size: var(--text-sm);
            color: var(--text-main);
            word-break: break-all;
        }

        .sent-email-box i {
            color: var(--primary);
            flex-shrink: 0;
        }

        .sent-hint {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: var(--leading-relaxed);
            text-align: center;
            margin-bottom: var(--space-lg);
        }

        /* Botão secundário */
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            font-weight: var(--weight-semibold);
            background: var(--bg-surface);
            color: var(--text-main);
            border: 1px solid var(--border);
            cursor: pointer;
            text-decoration: none;
            transition: background .2s, border-color .2s;
        }

        .btn-secondary:hover {
            background: var(--border);
            border-color: transparent;
        }

        /* Input com toggle de visibilidade */
        .input-pw-wrap {
            position: relative;
        }

        .input-pw-wrap .form-control {
            padding-right: 44px;
        }

        .pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            padding: 4px;
            line-height: 1;
            transition: color .2s;
        }

        .pw-toggle:hover {
            color: var(--primary);
        }

        /* Barra de força */
        .strength-bar {
            height: 4px;
            border-radius: var(--radius-full);
            background: var(--border);
            margin-top: 8px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: var(--radius-full);
            transition: width .3s ease, background .3s ease;
        }

        .strength-label {
            font-size: var(--text-xs);
            color: var(--text-muted);
            margin-top: 4px;
            min-height: 16px;
        }

        /* Requisitos */
        .pw-requirements {
            list-style: none;
            padding: 0;
            margin: var(--space-sm) 0 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .pw-requirements li {
            font-size: var(--text-xs);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color .2s;
        }

        .pw-requirements li i {
            font-size: 0.6rem;
            width: 14px;
            text-align: center;
        }

        .pw-requirements li.valid {
            color: var(--success);
        }

        /* Match label */
        .match-label {
            font-size: var(--text-xs);
            margin-top: 6px;
            min-height: 16px;
        }

        /* Alertas */
        .alert {
            border-radius: var(--radius-md);
            padding: 12px 14px;
            margin-bottom: var(--space-lg);
            font-size: var(--text-sm);
            line-height: var(--leading-normal);
        }

        .alert p {
            margin: 0;
        }

        .alert p+p {
            margin-top: 4px;
        }

        .alert-danger {
            background: var(--danger);
            color: #fff;
        }

        .alert-success {
            background: var(--success);
            color: #fff;
        }
    </style>
</head>

<body>

    <div class="auth-container">
        <div class="auth-card">

            <a href="<?= BASE_URL ?>login.php" class="auth-back-link">
                <i class="ti ti-arrow-left"></i> Voltar ao login
            </a>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?>
                        <p><?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


            <?php /* ══════════════════════════════════════════
           ETAPA 1 — Solicitar e-mail
           ══════════════════════════════════════════ */ ?>
            <?php if ($step === 'request'): ?>

                <div class="auth-icon-wrap">
                    <i class="ti ti-lock-open"></i>
                </div>

                <div class="auth-header">
                    <h2>Esqueceu a senha?</h2>
                    <p>Sem problema — introduza o seu e-mail e enviaremos um link de recuperação.</p>
                </div>

                <form action="" method="POST" class="auth-form" novalidate>
                    <input type="hidden" name="action" value="request">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="form-group">
                        <label for="email">Endereço de E-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            placeholder="o-seu@email.com"
                            autocomplete="email"
                            required
                            autofocus>
                    </div>

                    <button type="submit" class="btn-auth-submit">
                        <i class="ti ti-send" style="margin-right:8px;"></i>Enviar link de recuperação
                    </button>
                </form>

                <div class="auth-footer">
                    <p>Lembrou-se da senha? <a href="<?= BASE_URL ?>login.php">Entrar</a></p>
                    <p style="margin-top:8px;">Não tem conta? <a href="<?= BASE_URL ?>register.php">Cadastre-se</a></p>
                </div>


                <?php /* ══════════════════════════════════════════
           ETAPA 2 — Confirmação de envio
           ══════════════════════════════════════════ */ ?>
            <?php elseif ($step === 'sent'): ?>

                <div class="auth-icon-wrap success-icon">
                    <i class="ti ti-mail-check"></i>
                </div>

                <div class="auth-header">
                    <h2>Verifique o seu e-mail</h2>
                    <p>Enviámos um link de recuperação para:</p>
                </div>

                <div class="sent-email-box">
                    <i class="ti ti-mail"></i>
                    <?= htmlspecialchars($_POST['email'] ?? '') ?>
                </div>

                <p class="sent-hint">
                    O link é válido por <?= PASSWORD_RESET_EXPIRY / 60 ?> minutos.<br>
                    Não recebeu? Verifique a pasta de spam ou reenvie abaixo.
                </p>

                <form action="" method="POST" style="text-align:center; margin-bottom: var(--space-md);" novalidate>
                    <input type="hidden" name="action" value="request">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <button type="submit" class="btn-secondary">
                        <i class="ti ti-rotate-clockwise"></i> Reenviar e-mail
                    </button>
                </form>

                <div class="auth-footer">
                    <p><a href="<?= BASE_URL ?>login.php">Voltar ao login</a></p>
                </div>


                <?php /* ══════════════════════════════════════════
           ETAPA 3 — Nova senha
           ══════════════════════════════════════════ */ ?>
            <?php elseif ($step === 'reset' && $token_row): ?>

                <div class="auth-icon-wrap">
                    <i class="ti ti-key"></i>
                </div>

                <div class="auth-header">
                    <h2>Criar nova senha</h2>
                    <p>Olá, <strong><?= htmlspecialchars($token_row['username']) ?></strong>. Escolha uma senha segura para a sua conta.</p>
                </div>

                <form action="" method="POST" class="auth-form" id="resetForm" novalidate>
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($raw_token) ?>">

                    <!-- Nova senha -->
                    <div class="form-group">
                        <label for="password">Nova Senha</label>
                        <div class="input-pw-wrap">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="Mínimo <?= defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 8 ?> caracteres"
                                autocomplete="new-password"
                                required
                                autofocus>
                            <button type="button" class="pw-toggle" onclick="togglePw('password',this)" aria-label="Mostrar senha">
                                <i class="ti ti-eye"></i>
                            </button>
                        </div>

                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <p class="strength-label" id="strengthLabel"></p>

                        <ul class="pw-requirements" id="pwReqs">
                            <li id="req-len"> <i class="ti ti-circle"></i> Mínimo <?= defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 8 ?> caracteres</li>
                            <li id="req-upper"><i class="ti ti-circle"></i> Uma letra maiúscula (A–Z)</li>
                            <li id="req-num"> <i class="ti ti-circle"></i> Um número (0–9)</li>
                        </ul>
                    </div>

                    <!-- Confirmar senha -->
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nova Senha</label>
                        <div class="input-pw-wrap">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control"
                                placeholder="Repita a nova senha"
                                autocomplete="new-password"
                                required>
                            <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)" aria-label="Mostrar senha">
                                <i class="ti ti-eye"></i>
                            </button>
                        </div>
                        <p class="match-label" id="matchLabel"></p>
                    </div>

                    <button type="submit" class="btn-auth-submit">
                        <i class="ti ti-shield-check" style="margin-right:8px;"></i>Guardar nova senha
                    </button>
                </form>

                <div class="auth-footer">
                    <p><a href="<?= BASE_URL ?>login.php">Cancelar e voltar ao login</a></p>
                </div>

            <?php endif; ?>

        </div><!-- /.auth-card -->
    </div><!-- /.auth-container -->

    <script>
        /* ── Toggle visibilidade ─────────────────────────────────────────── */
        function togglePw(id, btn) {
            const inp = document.getElementById(id);
            const icon = btn.querySelector('i');
            const hide = inp.type === 'password';
            inp.type = hide ? 'text' : 'password';
            icon.className = hide ? 'ti ti-eye-off' : 'ti ti-eye';
            btn.setAttribute('aria-label', hide ? 'Ocultar senha' : 'Mostrar senha');
        }

        /* ── Medidor de força + requisitos ──────────────────────────────── */
        (function() {
            const pwd = document.getElementById('password');
            const conf = document.getElementById('confirm_password');
            if (!pwd) return;

            const fill = document.getElementById('strengthFill');
            const lbl = document.getElementById('strengthLabel');
            const match = document.getElementById('matchLabel');

            const reqLen = document.getElementById('req-len');
            const reqUpper = document.getElementById('req-upper');
            const reqNum = document.getElementById('req-num');

            const minLen = <?= defined('MIN_PASSWORD_LENGTH') ? (int)MIN_PASSWORD_LENGTH : 8 ?>;

            const levels = [{
                    pct: 0,
                    bg: 'transparent',
                    text: ''
                },
                {
                    pct: 25,
                    bg: '#ef4444',
                    text: 'Muito fraca'
                },
                {
                    pct: 50,
                    bg: '#f59e0b',
                    text: 'Fraca'
                },
                {
                    pct: 75,
                    bg: '#3b82f6',
                    text: 'Razoável'
                },
                {
                    pct: 100,
                    bg: '#07c95b',
                    text: 'Forte'
                },
            ];

            function setReq(el, ok) {
                el.classList.toggle('valid', ok);
                el.querySelector('i').className = ok ? 'ti ti-circle-check' : 'ti ti-circle';
            }

            function updateMatch() {
                if (!conf.value) {
                    match.textContent = '';
                    match.style.color = '';
                    return;
                }
                const ok = pwd.value === conf.value;
                match.textContent = ok ? '✓ As senhas coincidem' : '✗ As senhas não coincidem';
                match.style.color = ok ? 'var(--success)' : 'var(--danger)';
            }

            pwd.addEventListener('input', function() {
                const v = this.value;
                const hasLen = v.length >= minLen;
                const hasUpper = /[A-Z]/.test(v);
                const hasNum = /[0-9]/.test(v);
                const hasSpec = /[^A-Za-z0-9]/.test(v);

                setReq(reqLen, hasLen);
                setReq(reqUpper, hasUpper);
                setReq(reqNum, hasNum);

                const score = [hasLen, hasUpper, hasNum, hasSpec].filter(Boolean).length;
                const lvl = levels[score] ?? levels[0];
                fill.style.width = lvl.pct + '%';
                fill.style.background = lvl.bg;
                lbl.textContent = lvl.text;
                lbl.style.color = lvl.bg;

                updateMatch();
            });

            conf.addEventListener('input', updateMatch);

            /* Bloqueia submit se senhas não coincidem */
            const form = document.getElementById('resetForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (pwd.value !== conf.value) {
                        e.preventDefault();
                        match.textContent = '✗ As senhas não coincidem';
                        match.style.color = 'var(--danger)';
                        conf.focus();
                    }
                });
            }
        })();
    </script>

</body>

</html>