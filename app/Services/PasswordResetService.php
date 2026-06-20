<?php
declare(strict_types=1);

namespace Massango\Services;

use PDO;
use PDOException;

/**
 * PasswordResetService
 *
 * Encapsula toda a lógica de recuperação de senha:
 * geração/validação de token, regras de senha forte, envio de e-mail
 * (PHPMailer com fallback para mail() nativo).
 * Não emite HTML nem headers.
 */
class PasswordResetService
{
    public function __construct(private PDO $pdo)
    {
        if (!defined('PASSWORD_RESET_EXPIRY')) {
            define('PASSWORD_RESET_EXPIRY', 3600);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validar token (GET ?token= ou revalidação no POST de reset)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{token_row: array|null, error: string|null}
     */
    public function validateToken(string $raw_token): array
    {
        try {
            $stmt = $this->pdo->prepare("
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
            error_log('[PasswordResetService] verificar token: ' . $e->getMessage());
            return ['token_row' => null, 'error' => 'Erro interno. Tente novamente mais tarde.'];
        }

        if (!$token_row) {
            return ['token_row' => null, 'error' => 'Link inválido ou inexistente. Solicite um novo.'];
        }
        if ($token_row['used']) {
            return ['token_row' => null, 'error' => 'Este link já foi utilizado. Solicite um novo.'];
        }
        if (strtotime($token_row['expires_at']) < time()) {
            return ['token_row' => null, 'error' => 'Este link expirou. Solicite um novo.'];
        }

        return ['token_row' => $token_row, 'error' => null];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Etapa 1: solicitar e-mail
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Gera (se o e-mail existir) um novo token e envia o e-mail de recuperação.
     * Não revela ao chamador se o e-mail existe ou não — sempre "sucesso".
     *
     * @return array{success: bool, error: string|null}
     */
    public function requestReset(string $email): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email
                FROM   users
                WHERE  email     = ?
                  AND  is_active = 1
                LIMIT  1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Invalida tokens anteriores do mesmo utilizador
                $this->pdo->prepare("
                    UPDATE email_tokens
                    SET    used = 1
                    WHERE  user_id = ?
                      AND  type    = 'password_reset'
                      AND  used    = 0
                ")->execute([$user['id']]);

                $token      = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);

                $this->pdo->prepare("
                    INSERT INTO email_tokens (user_id, token, type, expires_at)
                    VALUES (?, ?, 'password_reset', ?)
                ")->execute([$user['id'], $token, $expires_at]);

                $this->sendResetEmail($user['email'], $user['username'], $token);
            }

            // Sempre "sucesso" — não revelar se o e-mail existe
            return ['success' => true, 'error' => null];
        } catch (PDOException $e) {
            error_log('[PasswordResetService] gerar token: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro interno. Tente novamente mais tarde.'];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Etapa 3: validar regras + gravar nova senha
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return list<string>  lista de erros de validação (vazia se senha válida)
     */
    public function validatePasswordRules(string $password, string $confirm_password): array
    {
        $errors  = [];
        $min_len = defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 8;

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

        return $errors;
    }

    /**
     * @return array{success: bool, error: string|null}
     */
    public function resetPassword(int $user_id, int $token_id, string $password): array
    {
        try {
            $hash = method_exists('SecurityManager', 'hashPassword')
                ? \SecurityManager::hashPassword($password)
                : password_hash($password, PASSWORD_DEFAULT);

            $this->pdo->prepare("
                UPDATE users
                SET    password_hash         = ?,
                       failed_login_attempts = 0,
                       locked_until          = NULL
                WHERE  id = ?
            ")->execute([$hash, $user_id]);

            $this->pdo->prepare(
                "UPDATE email_tokens SET used = 1 WHERE id = ?"
            )->execute([$token_id]);

            return ['success' => true, 'error' => null];
        } catch (PDOException $e) {
            error_log('[PasswordResetService] redefinir senha: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro interno. Tente novamente mais tarde.'];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // E-mail de recuperação (PHPMailer com fallback para mail() nativo)
    // ─────────────────────────────────────────────────────────────────────────

    private function sendResetEmail(string $to_email, string $to_name, string $token): void
    {
        $reset_link = BASE_URL . 'forgot_password.php?token=' . $token;
        $expiry_min = (int)(PASSWORD_RESET_EXPIRY / 60);
        $from_email = 'noreply@massangos.com';
        $from_name  = 'massangos';
        $subject    = 'Redefinição de senha — massangos';
        $app_name   = defined('APP_NAME') ? APP_NAME : $from_name;

        $body_text = $this->buildPlainTextBody($to_name, $reset_link, $expiry_min, $app_name);
        $body_html = $this->buildHtmlBody($to_name, $reset_link, $expiry_min, $app_name);

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
            } catch (\Throwable $e) {
                error_log('[PasswordResetService] PHPMailer falhou: ' . $e->getMessage());
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

    private function buildPlainTextBody(string $to_name, string $reset_link, int $expiry_min, string $app_name): string
    {
        return "Olá, {$to_name}!\n\n"
            . "Recebemos um pedido de redefinição de senha para a sua conta.\n\n"
            . "Clique no link abaixo (válido por {$expiry_min} minutos):\n\n"
            . "{$reset_link}\n\n"
            . "Se não solicitou isso, ignore este e-mail — a sua senha permanece inalterada.\n\n"
            . "— Equipa {$app_name}";
    }

    private function buildHtmlBody(string $to_name, string $reset_link, int $expiry_min, string $app_name): string
    {
        $safe_name = htmlspecialchars($to_name);
        $year      = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Inter,Segoe UI,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">

        <tr><td style="background:#07c95b;padding:32px 40px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:24px;font-weight:700;letter-spacing:-0.5px;">{$app_name}</h1>
        </td></tr>

        <tr><td style="padding:40px;">
          <h2 style="margin:0 0 16px;color:#0f172a;font-size:20px;font-weight:600;">Redefinição de senha</h2>
          <p style="margin:0 0 12px;color:#475569;font-size:15px;line-height:1.6;">
            Olá, <strong style="color:#0f172a;">{$safe_name}</strong>!
          </p>
          <p style="margin:0 0 28px;color:#475569;font-size:15px;line-height:1.6;">
            Recebemos um pedido de redefinição de senha. Clique no botão abaixo para criar uma nova senha.
            O link é válido por <strong>{$expiry_min} minutos</strong>.
          </p>

          <table cellpadding="0" cellspacing="0" style="margin:0 auto 28px;">
            <tr><td style="background:#07c95b;border-radius:8px;">
              <a href="{$reset_link}" style="display:inline-block;padding:14px 32px;color:#fff;font-size:15px;font-weight:600;text-decoration:none;letter-spacing:0.2px;">
                Redefinir senha
              </a>
            </td></tr>
          </table>

          <p style="margin:0 0 8px;color:#94a3b8;font-size:13px;line-height:1.5;">
            Ou copie e cole este link no seu navegador:
          </p>
          <p style="margin:0 0 28px;word-break:break-all;">
            <a href="{$reset_link}" style="color:#07c95b;font-size:13px;">{$reset_link}</a>
          </p>

          <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 24px;">

          <p style="margin:0;color:#94a3b8;font-size:13px;line-height:1.6;">
            Se não solicitou a redefinição de senha, ignore este e-mail. A sua conta permanece segura.
          </p>
        </td></tr>

        <tr><td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#94a3b8;font-size:12px;">
            © {$year} {$app_name} · Maputo, Moçambique
          </p>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
