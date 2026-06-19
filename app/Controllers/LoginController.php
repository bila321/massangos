<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Services\LoginService;
use Massango\Services\RegisterService; // reutiliza loadCarouselSlides()
use PDO;

/**
 * LoginController
 *
 * Orquestra o fluxo GET/POST da página de login.
 * Não contém SQL nem HTML.
 */
class LoginController
{
    private LoginService    $loginService;
    private RegisterService $carouselService; // partilhado com register

    public function __construct(private PDO $pdo)
    {
        $this->loginService    = new LoginService($pdo);
        $this->carouselService = new RegisterService($pdo);
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
                $username_email = \SecurityManager::sanitizeInput($_POST['username_email'] ?? '');
                $password       = $_POST['password'] ?? '';

                // Validação básica de campos
                $validation_errors = \SecurityManager::validateInput($_POST, [
                    'username_email' => ['required' => true, 'min_length' => 3],
                    'password'       => ['required' => true, 'type' => 'password'],
                ]);

                if (!empty($validation_errors)) {
                    $errors = array_merge($errors, array_values($validation_errors));
                } else {
                    $result = $this->loginService->attempt(
                        $username_email,
                        $password,
                        $_GET['redirect'] ?? ''
                    );

                    if ($result['success']) {
                        redirect($result['redirect']);
                    }

                    $errors = array_merge($errors, $result['errors']);
                }
            }
        }

        // ── Preparar dados para a view ────────────────────────────────────────
        $carousel_slides = $this->carouselService->loadCarouselSlides();
        $csrf_token      = \SecurityManager::generateCSRFToken();
        $slides_json     = json_encode(
            $carousel_slides,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        // ── Render ────────────────────────────────────────────────────────────
        require __DIR__ . '/../../includes/views/auth/login.view.php';
    }
}
