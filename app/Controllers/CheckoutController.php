<?php
// app/Controllers/CheckoutController.php

namespace Massango\Controllers;

use Massango\Services\PaymentService;

/**
 * Trata dois fluxos de checkout:
 *   'content' — compra de vídeo / álbum / post  (ex-checkout.php)
 *   'stars'   — compra de estrelas              (ex-checkout_stars.php)
 *
 * Instanciar com o modo correspondente:
 *   new CheckoutController($pdo, 'content')
 *   new CheckoutController($pdo, 'stars')
 */
class CheckoutController
{
    private \PDO $pdo;
    private string $mode;

    public function __construct(\PDO $pdo, string $mode)
    {
        $this->pdo  = $pdo;
        $this->mode = $mode;
    }

    public function handle(): void
    {
        if (!is_logged_in()) {
            redirect(BASE_URL . 'login.php');
        }

        $this->mode === 'stars'
            ? $this->handleStars()
            : $this->handleContent();
    }

    // ═══════════════════════════════════════════════════════════
    // MODO: compra de conteúdo (video / album / post)
    // ═══════════════════════════════════════════════════════════
    private function handleContent(): void
    {
        $type = $_GET['type'] ?? null;
        $id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$type || !$id) {
            set_message('Conteúdo inválido.', 'danger');
            redirect(BASE_URL . 'index.php');
        }

        // ── Buscar conteúdo ──────────────────────────────────────────────────
        [$content, $title] = $this->fetchContent($type, $id);

        if (!$content || empty($content['is_for_sale'])) {
            set_message('Este conteúdo não está à venda.', 'warning');
            redirect(BASE_URL . 'index.php');
        }

        $price     = $content['price'];
        $seller_id = (int)$content['user_id'];
        $buyer_id  = (int)get_current_user_id();

        if ($seller_id === $buyer_id) {
            set_message('Não pode comprar o seu próprio conteúdo.', 'info');
            redirect(BASE_URL . 'index.php');
        }

        // ── Verificar acesso já existente ────────────────────────────────────
        $paymentService = new PaymentService($this->pdo);
        if ($paymentService->hasAccess($buyer_id, $type, $id)) {
            $redirect = $type === 'video'
                ? 'post.php?id=' . $id
                : 'view_album.php?id=' . $id;
            set_message('Já tens acesso a este conteúdo.', 'info');
            redirect(BASE_URL . $redirect);
        }

        // ── Render ───────────────────────────────────────────────────────────
        $is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

        if (!$is_ajax) {
            require_once __DIR__ . '/../../includes/header.php';
        }

        require __DIR__ . '/../../includes/views/checkout/checkout_content.view.php';

        if (!$is_ajax) {
            require_once __DIR__ . '/../../includes/footer.php';
        }
    }

    // ═══════════════════════════════════════════════════════════
    // MODO: compra de estrelas
    // ═══════════════════════════════════════════════════════════
    private function handleStars(): void
    {
        $stars    = filter_input(INPUT_GET, 'stars', FILTER_VALIDATE_INT);
        $duration = $_GET['duration'] ?? '';

        if (!$stars || !in_array($duration, ['monthly', 'yearly'], true)) {
            set_message('Seleção inválida.', 'danger');
            redirect(BASE_URL . 'buy_stars.php');
        }

        $price_data = $this->fetchStarPrice($stars, $duration);

        if (!$price_data) {
            set_message('Preço não encontrado.', 'danger');
            redirect(BASE_URL . 'buy_stars.php');
        }

        $price    = $price_data['price'];
        $buyer_id = (int)get_current_user_id();

        require_once __DIR__ . '/../../includes/header.php';
        require __DIR__ . '/../../includes/views/checkout/checkout_stars.view.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function fetchContent(string $type, int $id): array
    {
        $table = match ($type) {
            'video' => 'videos',
            'album' => 'albums',
            'post'  => 'posts',
            default => null,
        };

        if (!$table) {
            return [null, ''];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $title = match ($type) {
            'video' => $row['caption']  ?? 'Vídeo',
            'album' => $row['name']     ?? 'Álbum',
            'post'  => $row['content']  ?? 'Foto',
            default => '',
        };

        return [$row ?: null, $title];
    }

    private function fetchStarPrice(int $stars, string $duration): array|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM star_prices WHERE stars = ? AND duration_type = ?"
        );
        $stmt->execute([$stars, $duration]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
