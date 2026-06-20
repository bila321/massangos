<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Services\PasswordResetService;
use PDO;

/**
 * ForgotPasswordController
 *
 * Orquestra o fluxo de 3 etapas (request → sent → reset).
 * Não contém SQL nem HTML.
 */
class ForgotPasswordController
{
    private PasswordResetService $service;

    public function __construct(private PDO $pdo)
    {
        $this->service = new PasswordResetService($pdo);
    }

    public function handle(): void
    {
        if (is_logged_in()) {
            redirect(BASE_URL);
        }

        $step      = 'request';
        $errors    = [];
        $token_row = null;
        $raw_token = '';

        // ── Detectar token na URL (?token=) ────────────────────────────────────
        if (!empty($_GET['token'])) {
            $raw_token = trim($_GET['token']);
            $result    = $this->service->validateToken($raw_token);

            $token_row = $result['token_row'];
            if ($result['error']) {
                $errors[] = $result['error'];
            } else {
                $step = 'reset';
            }
        }

        $csrf_token = \SecurityManager::generateCSRFToken();

        // ── POST — Etapa 1: solicitar e-mail ────────────────────────────────────
        if ($this->isPost('request')) {
            [$step, $errors] = $this->handleRequestStep($errors);
        }

        // ── POST — Etapa 3: definir nova senha ───────────────────────────────────
        if ($this->isPost('reset')) {
            [$step, $errors, $token_row] = $this->handleResetStep($errors);
            if ($step === 'reset' && $token_row === null && empty($errors)) {
                // segurança: nunca renderizar 'reset' sem token_row válido
                $step = 'request';
            }
        }

        // ── Render ────────────────────────────────────────────────────────────────
        require __DIR__ . '/../../includes/views/auth/forgot_password.view.php';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────────────

    private function isPost(string $action): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === $action;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function handleRequestStep(array $errors): array
    {
        $step = 'request';

        if (!\SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Token de segurança inválido. Tente novamente.';
            return [$step, $errors];
        }

        if (!\SecurityManager::checkRateLimit('forgot_' . $_SERVER['REMOTE_ADDR'], 5, 900)) {
            $errors[] = 'Demasiadas tentativas. Aguarde 15 minutos antes de tentar novamente.';
            return [$step, $errors];
        }

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Introduza um endereço de e-mail válido.';
            return [$step, $errors];
        }

        $result = $this->service->requestReset($email);
        if ($result['success']) {
            $step = 'sent';
        } else {
            $errors[] = $result['error'];
        }

        return [$step, $errors];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: array|null}
     */
    private function handleResetStep(array $errors): array
    {
        $raw_token = trim($_POST['token'] ?? '');

        if (!\SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Token de segurança inválido. Tente novamente.';
            return ['request', $errors, null];
        }

        $result    = $this->service->validateToken($raw_token);
        $token_row = $result['token_row'];

        if (!$token_row) {
            $errors[] = 'Link inválido ou expirado. Solicite um novo.';
            return ['request', $errors, null];
        }

        $password         = $_POST['password']         ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $validation_errors = $this->service->validatePasswordRules($password, $confirm_password);
        if (!empty($validation_errors)) {
            return ['reset', array_merge($errors, $validation_errors), $token_row];
        }

        $result = $this->service->resetPassword((int)$token_row['user_id'], (int)$token_row['id'], $password);
        if ($result['success']) {
            set_message("Senha alterada com sucesso! Faça login com a sua nova senha.", "success");
            redirect(BASE_URL . 'login.php');
        }

        $errors[] = $result['error'];
        return ['reset', $errors, $token_row];
    }
}
