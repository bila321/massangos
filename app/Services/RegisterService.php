<?php
declare(strict_types=1);

namespace Massango\Services;

use PDO;
use PDOException;

/**
 * RegisterService
 *
 * Encapsula toda a lógica de negócio e acesso a dados
 * da página de registo de utilizador.
 * Não emite headers, redirects nem HTML.
 */
class RegisterService
{
    private string $carousel_upload_url;

    public function __construct(private PDO $pdo)
    {
        $this->carousel_upload_url = rtrim(BASE_URL, '/') . '/storage/uploads/carousel/';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Carrossel
    // ─────────────────────────────────────────────────────────────────────────

    public function loadCarouselSlides(): array
    {
        $slides = $this->fetchSlidesFromDb();
        if (empty($slides)) {
            $slides = $this->defaultSlides();
        }
        return array_map([$this, 'resolveSlideImageUrl'], $slides);
    }

    private function fetchSlidesFromDb(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT title, subtitle, image_url, cta_text
                FROM auth_carousel_slides
                WHERE is_active = 1
                ORDER BY sort_order ASC
                LIMIT 6
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception) {
            return [];
        }
    }

    private function defaultSlides(): array
    {
        return [
            [
                'title'     => 'Partilhe os seus <span>momentos</span>',
                'subtitle'  => 'Publique fotos, vídeos e álbuns exclusivos para os seus seguidores.',
                'image_url' => '',
                'cta_text'  => '',
            ],
            [
                'title'     => 'Monetize o seu <span>conteúdo</span>',
                'subtitle'  => 'Defina preços, venda acesso premium e receba via M-Pesa ou e-Mola.',
                'image_url' => '',
                'cta_text'  => '',
            ],
            [
                'title'     => 'Cresça a sua <span>audiência</span>',
                'subtitle'  => 'Sistema de estrelas que impulsiona os criadores mais activos.',
                'image_url' => '',
                'cta_text'  => '',
            ],
        ];
    }

    private function resolveSlideImageUrl(array $slide): array
    {
        $img = $slide['image_url'] ?? '';
        if ($img !== '' && !filter_var($img, FILTER_VALIDATE_URL)) {
            $slide['image_url'] = $this->carousel_upload_url . rawurlencode(basename($img));
        }
        return $slide;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Registo
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{success: bool, errors: list<string>}
     */
    public function register(
        string $username,
        string $email,
        string $password,
        string $confirm_password
    ): array {
        $errors = [];

        $rules = [
            'username'         => ['required' => true, 'min_length' => 3],
            'email'            => ['required' => true, 'type' => 'email'],
            'password'         => ['required' => true, 'min_length' => 6, 'type' => 'password'],
            'confirm_password' => ['required' => true],
        ];
        $validation_errors = \SecurityManager::validateInput(
            compact('username', 'email', 'password', 'confirm_password'),
            $rules
        );
        if (!empty($validation_errors)) {
            $errors = array_merge($errors, array_values($validation_errors));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        }

        if ($password !== $confirm_password) {
            $errors[] = 'As senhas não coincidem.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Verificar duplicado
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'errors' => ['Nome de usuário ou e-mail já cadastrado.']];
            }
        } catch (PDOException $e) {
            error_log('[RegisterService] Verificar utilizador: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Erro interno. Tente novamente mais tarde.']];
        }

        // Inserir
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (username, email, password_hash, profile_picture)
                 VALUES (?, ?, ?, ?)"
            );
            if ($stmt->execute([$username, $email, $hash, 'default_profile.png'])) {
                return ['success' => true, 'errors' => []];
            }
            return ['success' => false, 'errors' => ['Erro ao cadastrar usuário. Tente novamente.']];
        } catch (PDOException $e) {
            error_log('[RegisterService] Inserir utilizador: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Erro interno. Tente novamente mais tarde.']];
        }
    }
}
