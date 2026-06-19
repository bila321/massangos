<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Services\RegisterService;
use PDO;

/**
 * RegisterController
 *
 * Orquestra o fluxo GET/POST da página de registo.
 * Não contém SQL nem HTML.
 */
class RegisterController
{
    private RegisterService $service;

    public function __construct(private PDO $pdo)
    {
        $this->service = new RegisterService($pdo);
    }

    public function handle(): void
    {
        // Redirecionar utilizadores já autenticados
        if (is_logged_in()) {
            redirect(BASE_URL);
        }

        $errors          = [];
        $success_message = '';

        // ── Mensagens de sessão anteriores ───────────────────────────────────
        $all_messages = get_and_clear_messages();
        if (is_array($all_messages)) {
            foreach ($all_messages as $msg) {
                if (!is_array($msg) || !isset($msg['type'], $msg['content'])) continue;
                if ($msg['type'] === 'success') {
                    $success_message = $msg['content'];
                } elseif (in_array($msg['type'], ['danger', 'error'], true)) {
                    $errors[] = $msg['content'];
                }
            }
        }

        // ── POST ──────────────────────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!\SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $errors[] = 'Token de segurança inválido. Tente novamente.';
            } else {
                $username         = \SecurityManager::sanitizeInput($_POST['username'] ?? '');
                $email            = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
                $password         = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                $result = $this->service->register(
                    $username, $email, $password, $confirm_password
                );

                if ($result['success']) {
                    set_message("Cadastro realizado com sucesso! Faça login para continuar.", "success");
                    redirect(BASE_URL . 'login.php');
                }

                $errors = array_merge($errors, $result['errors']);
            }
        }

        // ── Preparar dados para a view ────────────────────────────────────────
        $carousel_slides = $this->service->loadCarouselSlides();
        $csrf_token      = \SecurityManager::generateCSRFToken();
        $slides_json     = json_encode(
            $carousel_slides,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        // ── Render ────────────────────────────────────────────────────────────
        require __DIR__ . '/../../includes/views/auth/register.view.php';
    }
}
