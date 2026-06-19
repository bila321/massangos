<?php
declare(strict_types=1);

namespace Massango\Services;

use PDO;
use PDOException;

/**
 * LoginService
 *
 * Encapsula toda a lógica de autenticação:
 * rate limit, lockout, verificação de password, sessão e log.
 * Não emite headers, redirects nem HTML.
 */
class LoginService
{
    public function __construct(private PDO $pdo) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Autenticação
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Tenta autenticar o utilizador.
     *
     * @return array{success: bool, errors: list<string>, redirect: string|null}
     */
    public function attempt(
        string $username_email,
        string $password,
        string $redirect_hint = ''
    ): array {
        // 1. Lockout global por username/email
        if (\SecurityManager::isLoginBlocked($username_email)) {
            $mins = LOGIN_LOCKOUT_TIME / 60;
            return $this->fail(
                "Muitas tentativas falhadas. Tente novamente em {$mins} minutos."
            );
        }

        // 2. Rate limit por IP
        if (!\SecurityManager::checkRateLimit(
            'login_' . $_SERVER['REMOTE_ADDR'],
            RATE_LIMIT_MAX_ATTEMPTS,
            RATE_LIMIT_TIME_WINDOW
        )) {
            return $this->fail('Muitas tentativas de login. Tente novamente em alguns minutos.');
        }

        // 3. Buscar utilizador
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, password_hash, profile_picture,
                       is_active, failed_login_attempts, locked_until
                FROM users
                WHERE (username = ? OR email = ?) AND is_active = 1
            ");
            $stmt->execute([$username_email, $username_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[LoginService] Buscar utilizador: ' . $e->getMessage());
            return $this->fail('Erro interno. Tente novamente mais tarde.');
        }

        if (!$user) {
            \SecurityManager::logLoginAttempt($username_email, false);
            return $this->fail('Credenciais inválidas ou conta inativa.');
        }

        // 4. Conta bloqueada temporariamente
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return $this->fail('Conta temporariamente bloqueada. Tente novamente mais tarde.');
        }

        // 5. Verificar password
        if (!\SecurityManager::verifyPassword($password, $user['password_hash'])) {
            return $this->handleFailedAttempt($user, $username_email);
        }

        // 6. Sucesso
        return $this->handleSuccess($user, $redirect_hint);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Handlers privados
    // ─────────────────────────────────────────────────────────────────────────

    private function handleFailedAttempt(array $user, string $username_email): array
    {
        \SecurityManager::logLoginAttempt($username_email, false);
        $failed = (int)$user['failed_login_attempts'] + 1;

        try {
            if ($failed >= MAX_LOGIN_ATTEMPTS) {
                $locked_until = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
                $this->pdo->prepare(
                    "UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?"
                )->execute([$failed, $locked_until, $user['id']]);

                $mins = LOGIN_LOCKOUT_TIME / 60;
                return $this->fail(
                    "Conta bloqueada por muitas tentativas. Tente novamente em {$mins} minutos."
                );
            }

            $this->pdo->prepare(
                "UPDATE users SET failed_login_attempts = ? WHERE id = ?"
            )->execute([$failed, $user['id']]);

            $remaining = MAX_LOGIN_ATTEMPTS - $failed;
            return $this->fail("Credenciais inválidas. Restam {$remaining} tentativa(s).");

        } catch (PDOException $e) {
            error_log('[LoginService] Registar falha: ' . $e->getMessage());
            return $this->fail('Erro interno. Tente novamente mais tarde.');
        }
    }

    private function handleSuccess(array $user, string $redirect_hint): array
    {
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['user_profile_picture'] = !empty($user['profile_picture'])
            ? UPLOAD_URL . htmlspecialchars($user['profile_picture'])
            : BASE_URL . 'assets/img/default_profile.png';

        try {
            $this->pdo->prepare(
                "UPDATE users
                 SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW()
                 WHERE id = ?"
            )->execute([$user['id']]);
        } catch (PDOException $e) {
            error_log('[LoginService] Reset falhas: ' . $e->getMessage());
        }

        \SecurityManager::logLoginAttempt($user['username'], true);
        set_message(
            "Bem-vindo de volta, " . htmlspecialchars($user['username']) . "!",
            "success"
        );

        // Validar e sanitizar o redirect
        $redirect = $redirect_hint;
        if (!$redirect
            || !filter_var($redirect, FILTER_VALIDATE_URL)
            || strpos($redirect, BASE_URL) !== 0
        ) {
            $redirect = BASE_URL;
        }

        return ['success' => true, 'errors' => [], 'redirect' => $redirect];
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'errors' => [$message], 'redirect' => null];
    }
}
