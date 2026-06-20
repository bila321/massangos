<?php
declare(strict_types=1);

namespace Massango\Services;

use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Video;
use Massango\Models\Album;
use PDO;

/**
 * SearchService
 *
 * Encapsula toda a lógica de busca de utilizadores e conteúdo
 * (posts, vídeos, álbuns), incluindo os filtros de privacidade,
 * bloqueio, aprovação e preço.
 * Não emite HTML nem headers.
 */
class SearchService
{
    public const ALLOWED_TYPES  = ['all', 'profile', 'album', 'video', 'photo'];
    public const ALLOWED_PRICES = ['all', 'free', 'paid'];

    public function __construct(private PDO $pdo) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Ponto de entrada
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{users: array, items: array}
     */
    public function search(string $query, string $type, string $price_filter, int $current_user_id): array
    {
        $should_search = ($query !== '' || $type !== 'all' || $price_filter !== 'all');

        if (!$should_search) {
            return ['users' => [], 'items' => []];
        }

        $users = ($type === 'all' || $type === 'profile') && $query !== ''
            ? $this->searchUsers($query, $current_user_id)
            : [];

        $items = ($type !== 'profile')
            ? $this->searchContent($query, $type, $price_filter, $current_user_id)
            : [];

        return ['users' => $users, 'items' => $items];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Busca de utilizadores
    // ─────────────────────────────────────────────────────────────────────────

    private function searchUsers(string $query, int $current_user_id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, profile_picture, bio
             FROM users
             WHERE username LIKE ? OR bio LIKE ?
             LIMIT 20"
        );
        $term = "%{$query}%";
        $stmt->execute([$term, $term]);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filtered = [];
        foreach ($raw as $u) {
            if (is_logged_in() && $current_user_id !== (int)$u['id']) {
                if ($this->isBlockedEitherWay($current_user_id, (int)$u['id'])) {
                    continue;
                }
            }
            $filtered[] = $u;
        }

        return $filtered;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Busca de conteúdo
    // ─────────────────────────────────────────────────────────────────────────

    private function searchContent(
        string $query,
        string $type,
        string $price_filter,
        int $current_user_id
    ): array {
        $raw_items = $this->fetchFeedItems($type);
        $results   = [];

        foreach ($raw_items as $item) {
            $detailed = $this->enrichItem($item);
            if (!$detailed) continue;

            if (!$this->matchesQuery($detailed, $item, $query)) continue;
            if (!$this->matchesPriceFilter($detailed, $price_filter)) continue;

            $is_owner = $current_user_id > 0 && (int)$item['user_id'] === $current_user_id;
            $is_admin = isset($_SESSION['admin_id']);

            if (!$this->isApproved($detailed, $is_owner, $is_admin)) continue;
            if (!$this->isVisibleByLinkPrivacy($detailed, $item, $is_owner, $is_admin, $current_user_id)) continue;

            $author = User::getUserById($this->pdo, $item['user_id']);

            if (!$this->isVisibleByAuthorPrivacy($author, $is_owner, $is_admin, $current_user_id)) continue;
            if (!$is_owner && is_logged_in() && $this->isBlockedEitherWay($current_user_id, (int)$author['id'])) continue;

            $results[] = array_merge($item, $detailed, ['author' => $author]);
        }

        return $results;
    }

    private function fetchFeedItems(string $type): array
    {
        $sql = "
            SELECT fi.id AS feed_item_id, fi.item_type, fi.item_id, fi.user_id, fi.created_at
            FROM feed_items fi
            JOIN users u ON fi.user_id = u.id
            WHERE fi.show_in_feed = 1
        ";
        $params = [];

        if ($type !== 'all') {
            $sql .= " AND fi.item_type = ?";
            $params[] = ($type === 'photo') ? 'post' : $type;
        }

        $sql .= " ORDER BY fi.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function enrichItem(array $item): ?array
    {
        switch ($item['item_type']) {
            case 'post':
                $d = Post::getPostById($this->pdo, $item['item_id']);
                if (!$d) return null;
                $d['search_text'] = $d['content'] ?? '';
                $d['is_paid']     = 0;
                return $d;

            case 'video':
                $d = Video::getVideoById($this->pdo, $item['item_id']);
                if (!$d) return null;
                $d['search_text'] = $d['caption'] ?? '';
                $d['is_paid']     = (int)($d['is_for_sale'] ?? 0);
                return $d;

            case 'album':
                $d = Album::getAlbumById($this->pdo, $item['item_id']);
                if (!$d) return null;
                $d['search_text'] = trim(($d['album_name'] ?? '') . ' ' . ($d['album_description'] ?? ''));
                $d['is_paid']     = (int)($d['is_for_sale'] ?? 0);
                return $d;

            default:
                return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtros individuais
    // ─────────────────────────────────────────────────────────────────────────

    private function matchesQuery(array $detailed, array $item, string $query): bool
    {
        if ($query === '') return true;

        return stripos($detailed['search_text'], $query) !== false
            || stripos($item['item_type'], $query) !== false;
    }

    private function matchesPriceFilter(array $detailed, string $price_filter): bool
    {
        if ($price_filter === 'free' && $detailed['is_paid']) return false;
        if ($price_filter === 'paid' && !$detailed['is_paid']) return false;
        return true;
    }

    private function isApproved(array $detailed, bool $is_owner, bool $is_admin): bool
    {
        $is_approved = (int)($detailed['is_approved'] ?? 1);
        return $is_approved || $is_owner || $is_admin;
    }

    /**
     * Conteúdo com show_in_feed = 0 (link privado) só é visível para
     * o dono, admin, ou quem já tem acesso pago.
     */
    private function isVisibleByLinkPrivacy(
        array $detailed,
        array $item,
        bool $is_owner,
        bool $is_admin,
        int $current_user_id
    ): bool {
        $show_in_feed = (int)($detailed['show_in_feed'] ?? 1);
        if ($show_in_feed !== 0 || $is_owner || $is_admin) {
            return true;
        }

        $paymentService = new PaymentService($this->pdo);
        return $paymentService->hasAccess($current_user_id, $item['item_type'], $item['item_id']);
    }

    /**
     * Perfis com profile_privacy = 'followers' só são visíveis para
     * seguidores, mútuos, o próprio dono ou admin.
     */
    private function isVisibleByAuthorPrivacy(
        array $author,
        bool $is_owner,
        bool $is_admin,
        int $current_user_id
    ): bool {
        $privacy = $author['profile_privacy'] ?? 'public';
        if ($privacy !== 'followers' || $is_owner || $is_admin) {
            return true;
        }

        if (!is_logged_in()) return false;

        return User::isFollowing($this->pdo, $current_user_id, $author['id'])
            || User::isMutualFollower($this->pdo, $current_user_id, $author['id']);
    }

    private function isBlockedEitherWay(int $user_a, int $user_b): bool
    {
        return User::isBlocking($this->pdo, $user_a, $user_b)
            || User::isBlocking($this->pdo, $user_b, $user_a);
    }
}
