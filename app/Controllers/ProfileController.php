<?php

namespace Massango\Controllers;

use Massango\Models\Post;
use Massango\Models\Notification;
use Massango\Services\PaymentService;

class ProfileController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // Ponto de entrada
    // =========================================================================

    public function load(?string $rawId): array
    {
        // ── Resolver utilizador do perfil ─────────────────────────────────────
        $profile_user_id = $rawId ? (int)$rawId : null;

        if (!$profile_user_id) {
            redirect(BASE_URL . 'index.php');
        }

        $profile_data = $this->fetchUserById($profile_user_id);
        if (!$profile_data) {
            redirect(BASE_URL . '404.php');
        }

        // ── Utilizador autenticado ────────────────────────────────────────────
        $current_user_id     = is_logged_in() ? get_current_user_id() : null;
        $logged_in_user_data = $current_user_id ? $this->fetchUserById($current_user_id) : null;
        $user_data           = $logged_in_user_data;
        $is_admin            = isset($_SESSION['admin_id']);
        $is_owner            = $current_user_id && $current_user_id === $profile_user_id;

        // ── Relação: bloqueios ────────────────────────────────────────────────
        $am_i_blocked     = $current_user_id && $this->checkBlocked($profile_user_id, $current_user_id);
        $is_blocked_by_me = $current_user_id && $this->checkBlocked($current_user_id, $profile_user_id);

        // ── Relação: follow ───────────────────────────────────────────────────
        $is_following        = $current_user_id && $this->checkFollowing($current_user_id, $profile_user_id);
        $has_pending_request = $current_user_id && $this->checkPendingFollow($current_user_id, $profile_user_id);

        // ── Permissão para ver conteúdo ───────────────────────────────────────
        $profile_privacy  = $profile_data['profile_privacy'] ?? 'public';
        $can_view_content = $is_owner || $is_admin
            || $profile_privacy === 'public'
            || ($profile_privacy === 'followers' && $is_following);

        // ── Estatísticas do perfil ────────────────────────────────────────────
        $followers_count = $this->fetchFollowersCount($profile_user_id);
        $following_count = $this->fetchFollowingCount($profile_user_id);
        $total_visits    = $is_owner ? $this->fetchVisitCount($profile_user_id) : 0;
        $star_rating     = $this->fetchStarRating($profile_user_id);

        // ── Notificações (para o header) ──────────────────────────────────────
        $notifications = $current_user_id
            ? Notification::getNotificationsByUserId($this->pdo, $current_user_id, false, 15)
            : [];

        // ── CSRF ──────────────────────────────────────────────────────────────
        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (\Exception $e) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }
        $csrf_token = $_SESSION['csrf_token'];

        // ── Saved IDs (para o botão Guardar no footer) ────────────────────────
        $saved_ids = $current_user_id ? Post::getSavedIds($this->pdo, $current_user_id) : [];

        // ── Conteúdo do perfil (feed items raw) ───────────────────────────────
        $all_user_content = [];
        $enriched_feed    = [];

        if ($can_view_content) {
            $all_user_content = $this->fetchUserContent($profile_user_id);
            $enriched_feed    = $this->enrichItems($all_user_content, $current_user_id, $is_admin, $saved_ids);
        }

        // ── Contexto de redirect para os dropdowns de editar/apagar ──────────
        $redirect_context = 'profile.php?id=' . $profile_user_id;

        return compact(
            // Perfil
            'profile_user_id',
            'profile_data',
            'profile_privacy',
            // Utilizador autenticado
            'current_user_id',
            'logged_in_user_data',
            'user_data',
            'is_admin',
            'is_owner',
            // Relações
            'am_i_blocked',
            'is_blocked_by_me',
            'is_following',
            'has_pending_request',
            'can_view_content',
            // Estatísticas
            'followers_count',
            'following_count',
            'total_visits',
            'star_rating',
            // Feed
            'all_user_content',    // raw – ainda usado para o grid de fotos/vídeos
            'enriched_feed',       // enriquecido – usado pelo loop do feed (partials)
            // Auxiliares
            'notifications',
            'csrf_token',
            'saved_ids',
            'redirect_context'
        );
    }

    // =========================================================================
    // Feed items raw do perfil
    // =========================================================================

    /**
     * Devolve todos os feed_items do utilizador, com os dados das tabelas
     * posts/videos/albums já merged numa única linha (como o profile.php fazia
     * manualmente com $all_user_content).
     */
    private function fetchUserContent(int $profileUserId): array
    {
        // Apenas os campos necessários para o enrichItems identificar e processar
        // cada item. Os dados completos de conteúdo são carregados via fetchByIds
        // (batch) dentro do enrichItems.
        $stmt = $this->pdo->prepare("
            SELECT
                fi.id          AS feed_item_id,
                fi.item_type,
                fi.item_id,
                fi.user_id,
                fi.created_at,
                -- campos do post necessários para detectar reposts antes do batch
                p.is_repost,
                p.shared_post_id,
                p.shared_item_type
            FROM feed_items fi
            LEFT JOIN posts p ON fi.item_type = 'post' AND fi.item_id = p.id
            WHERE fi.user_id = :uid
            ORDER BY fi.created_at DESC
        ");
        $stmt->execute([':uid' => $profileUserId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // Enriquecimento (batch, igual ao FeedController)
    // =========================================================================

    private function enrichItems(
        array $rawItems,
        ?int  $currentUserId,
        bool  $isAdmin,
        array $savedIds
    ): array {
        if (empty($rawItems)) return [];

        // ── Recolher IDs ──────────────────────────────────────────────────────
        $feedItemIds = [];
        $postIds     = [];
        $videoIds    = [];
        $albumIds    = [];
        $userIds     = [];

        foreach ($rawItems as $item) {
            $feedItemIds[] = (int)$item['feed_item_id'];
            $userIds[]     = (int)$item['user_id'];
            match ($item['item_type']) {
                'post'  => $postIds[]  = (int)$item['item_id'],
                'video' => $videoIds[] = (int)$item['item_id'],
                'album' => $albumIds[] = (int)$item['item_id'],
                default => null,
            };
        }
        $userIds = array_unique($userIds);

        // ── Batch queries ─────────────────────────────────────────────────────
        $postsMap         = $this->fetchByIds('posts',  'id', $postIds);
        $videosMap        = $this->fetchByIds('videos', 'id', $videoIds);
        $albumsMap        = $this->fetchByIds('albums', 'id', $albumIds);
        $usersMap         = $this->fetchByIds('users',  'id', $userIds);
        $likesMap         = $this->fetchLikesCounts($feedItemIds);
        $votesMap         = $this->fetchUserVotes($feedItemIds, $currentUserId);
        $commentCountsMap = $this->fetchCommentCounts($feedItemIds);
        $shareCountsMap   = $this->fetchShareCounts($postIds);
        $aiMap            = $this->fetchAiAnalysis($rawItems);
        $followingSet     = $currentUserId ? $this->fetchFollowingSet($currentUserId) : [];
        $pendingSet       = $currentUserId ? $this->fetchPendingSet($currentUserId)   : [];

        // ── Batch: conteúdo partilhado (reposts) ─────────────────────────────
        [$sharedPostsMap, $sharedVideosMap, $sharedAlbumsMap, $sharedAuthorsMap]
            = $this->fetchSharedContent($rawItems, $usersMap);

        // ── PaymentService ────────────────────────────────────────────────────
        $paymentService = new PaymentService($this->pdo);

        // ── Enriquecer cada item ──────────────────────────────────────────────
        $enriched = [];

        foreach ($rawItems as $item) {
            $feedItemId = (int)$item['feed_item_id'];
            $itemType   = $item['item_type'];
            $itemId     = (int)$item['item_id'];
            $authorId   = (int)$item['user_id'];

            // Conteúdo específico
            $content_data = match ($itemType) {
                'post'  => $postsMap[$itemId]  ?? null,
                'video' => $videosMap[$itemId] ?? null,
                'album' => $albumsMap[$itemId] ?? null,
                default => null,
            };
            if (!$content_data) continue;

            $author = $usersMap[$authorId] ?? null;
            if (!$author) continue;

            // Aprovação
            $is_approved = isset($content_data['is_approved']) ? (int)$content_data['is_approved'] : 1;
            $is_post_owner = $currentUserId && $authorId == $currentUserId;
            if (!$is_approved && !$is_post_owner && !$isAdmin) continue;

            // show_in_feed
            $show_in_feed = isset($content_data['show_in_feed']) ? (int)$content_data['show_in_feed'] : 1;
            if ($show_in_feed === 0 && !$isAdmin) continue;

            // AI / blur
            $ai_analysis    = $aiMap[$feedItemId] ?? null;
            $is_high_risk   = $ai_analysis && $ai_analysis['status'] === 'done' && $ai_analysis['risk_level'] === 'high';
            $is_medium_risk = $ai_analysis && $ai_analysis['status'] === 'done' && $ai_analysis['risk_level'] === 'medium';
            $should_blur    = ($is_high_risk || $is_medium_risk) && !$isAdmin;

            // Repost
            $isRepost     = false;
            $sharedData   = null;
            $sharedType   = null;
            $sharedAuthor = null;
            $sharedId     = null;

            if (
                $itemType === 'post'
                && !empty($content_data['is_repost'])
                && !empty($content_data['shared_post_id'])
                && !empty($content_data['shared_item_type'])
            ) {
                $sharedType = $content_data['shared_item_type'];
                $sharedId   = (int)$content_data['shared_post_id'];
                $sharedData = match ($sharedType) {
                    'post'  => $sharedPostsMap[$sharedId]  ?? null,
                    'video' => $sharedVideosMap[$sharedId] ?? null,
                    'album' => $sharedAlbumsMap[$sharedId] ?? null,
                    default => null,
                };
                if ($sharedData) {
                    $isRepost     = true;
                    $sharedAuthor = $sharedAuthorsMap[(int)$sharedData['user_id']] ?? null;
                }
            }

            // Acesso a conteúdo pago
            $has_access = !isset($content_data['is_for_sale'])
                || !$content_data['is_for_sale']
                || $is_post_owner
                || $isAdmin
                || $paymentService->hasAccess($currentUserId ?? 0, $itemType, $itemId);

            $has_access_shared = true;
            if ($isRepost && $sharedData && !empty($sharedData['is_for_sale'])) {
                $is_shared_owner   = isset($sharedData['user_id']) && $sharedData['user_id'] == $currentUserId;
                $has_access_shared = $is_shared_owner || $isAdmin
                    || $paymentService->hasAccess($currentUserId ?? 0, $sharedType, $sharedId);
            }

            // Follow state
            $is_following   = isset($followingSet[$authorId]);
            $has_follow_req = isset($pendingSet[$authorId]);
            $follow_label   = $is_following ? 'Seguindo' : ($has_follow_req ? 'Pendente' : 'Seguir');
            $follow_class   = $is_following ? 'following' : ($has_follow_req ? 'pending' : '');

            // Share count
            $share_count = 0;
            if ($itemType === 'post') {
                $resolvedShareId = (!empty($content_data['is_repost']) && !empty($content_data['shared_post_id']))
                    ? (int)$content_data['shared_post_id']
                    : $itemId;
                $share_count = $shareCountsMap[$resolvedShareId] ?? 0;
            }

            // Like info & voto
            $like_info     = $likesMap[$feedItemId]     ?? ['likes' => 0, 'dislikes' => 0];
            $user_vote     = $votesMap[$feedItemId]     ?? null;
            $comment_count = $commentCountsMap[$feedItemId] ?? 0;

            // Save state
            $save_key = $itemType . '_' . $itemId;
            $is_saved = isset($savedIds[$save_key]);

            // Badge "À Venda" (visível apenas para dono/admin)
            $is_shared_owner_flag = $isRepost && isset($sharedData['user_id'])
                && $sharedData['user_id'] == $currentUserId;
            $can_see_sale_indicator =
                (isset($item['is_for_sale']) && $item['is_for_sale'] && ($is_post_owner || $isAdmin))
                || ($isRepost && isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']
                    && ($is_shared_owner_flag || $isAdmin));

            $enriched[] = array_merge($item, [
                // Conteúdo
                'content_data'           => $content_data,
                'author'                 => $author,
                // Repost
                'isRepost'               => $isRepost,
                'sharedData'             => $sharedData,
                'sharedType'             => $sharedType,
                'sharedAuthor'           => $sharedAuthor,
                'sharedId'               => $sharedId,
                // Permissões
                'is_post_owner'          => $is_post_owner,
                'is_admin'               => $isAdmin,
                'has_access'             => $has_access,
                'has_access_shared'      => $has_access_shared,
                'can_see_sale_indicator' => $can_see_sale_indicator,
                // AI
                'ai_analysis'            => $ai_analysis,
                'should_blur'            => $should_blur,
                // Social
                'like_info'              => $like_info,
                'user_vote'              => $user_vote,
                'comment_count'          => $comment_count,
                'share_count'            => $share_count,
                // Follow
                'is_following'           => $is_following,
                'has_follow_req'         => $has_follow_req,
                'follow_label'           => $follow_label,
                'follow_class'           => $follow_class,
                // Save
                'is_saved'               => $is_saved,
                'save_label'             => $is_saved ? 'Guardado' : 'Guardar',
                'save_icon'              => $is_saved ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark',
                'save_class'             => $is_saved ? 'btn-save active' : 'btn-save',
            ]);
        }

        return $enriched;
    }

    // =========================================================================
    // Batch: conteúdo partilhado em reposts
    // =========================================================================

    /**
     * @return array [sharedPostsMap, sharedVideosMap, sharedAlbumsMap, sharedAuthorsMap]
     */
    private function fetchSharedContent(array $items, array $usersMap): array
    {
        $postIds  = [];
        $videoIds = [];
        $albumIds = [];

        foreach ($items as $item) {
            if (
                $item['item_type'] === 'post'
                && !empty($item['is_repost'])
                && !empty($item['shared_post_id'])
                && !empty($item['shared_item_type'])
            ) {
                $sid = (int)$item['shared_post_id'];
                match ($item['shared_item_type']) {
                    'post'  => $postIds[]  = $sid,
                    'video' => $videoIds[] = $sid,
                    'album' => $albumIds[] = $sid,
                    default => null,
                };
            }
        }

        $sharedPostsMap  = $this->fetchByIds('posts',  'id', $postIds);
        $sharedVideosMap = $this->fetchByIds('videos', 'id', $videoIds);
        $sharedAlbumsMap = $this->fetchByIds('albums', 'id', $albumIds);

        // Autores do conteúdo partilhado
        $sharedUserIds = array_unique(array_filter(array_merge(
            array_column($sharedPostsMap,  'user_id'),
            array_column($sharedVideosMap, 'user_id'),
            array_column($sharedAlbumsMap, 'user_id')
        )));
        // Reutilizar autores já carregados; buscar apenas os que faltam
        $missingUserIds = array_filter($sharedUserIds, fn($id) => !isset($usersMap[(int)$id]));
        $extraUsersMap  = $this->fetchByIds('users', 'id', array_values($missingUserIds));
        $sharedAuthorsMap = $usersMap + $extraUsersMap;

        return [$sharedPostsMap, $sharedVideosMap, $sharedAlbumsMap, $sharedAuthorsMap];
    }

    // =========================================================================
    // Helpers de perfil
    // =========================================================================

    // =========================================================================
    // Helpers de utilizador (PDO directo — sem depender de User model)
    // =========================================================================

    private function fetchUserById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Verifica se $blockerId bloqueou $blockedId. */
    private function checkBlocked(int $blockerId, int $blockedId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1"
        );
        $stmt->execute([$blockerId, $blockedId]);
        return (bool)$stmt->fetchColumn();
    }

    /** Verifica se $followerId segue $followedId (follow aceite). */
    private function checkFollowing(int $followerId, int $followedId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ? LIMIT 1"
        );
        $stmt->execute([$followerId, $followedId]);
        return (bool)$stmt->fetchColumn();
    }

    /** Verifica se existe follow request pendente. */
    private function checkPendingFollow(int $followerId, int $followedId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM follow_requests WHERE follower_id = ? AND followed_id = ? LIMIT 1"
        );
        $stmt->execute([$followerId, $followedId]);
        return (bool)$stmt->fetchColumn();
    }

    private function fetchFollowersCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM follows WHERE followed_id = ?"
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function fetchFollowingCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM follows WHERE follower_id = ?"
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function fetchVisitCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT total_profile_views FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function fetchStarRating(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT stars FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    // =========================================================================
    // Helpers de batch query (espelhados do FeedController)
    // =========================================================================

    private function fetchByIds(string $table, string $idCol, array $ids): array
    {
        if (empty($ids)) return [];
        $ids          = array_values(array_unique($ids));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt         = $this->pdo->prepare("SELECT * FROM `$table` WHERE `$idCol` IN ($placeholders)");
        $stmt->execute($ids);
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row[$idCol]] = $row;
        }
        return $result;
    }

    private function fetchLikesCounts(array $feedItemIds): array
    {
        if (empty($feedItemIds)) return [];
        $placeholders = implode(',', array_fill(0, count($feedItemIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT feed_item_id,
                   SUM(CASE WHEN type='like'    THEN 1 ELSE 0 END) AS likes,
                   SUM(CASE WHEN type='dislike' THEN 1 ELSE 0 END) AS dislikes
            FROM feed_item_likes
            WHERE feed_item_id IN ($placeholders)
            GROUP BY feed_item_id
        ");
        $stmt->execute(array_values($feedItemIds));
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['feed_item_id']] = [
                'likes'    => (int)$row['likes'],
                'dislikes' => (int)$row['dislikes'],
            ];
        }
        return $result;
    }

    private function fetchUserVotes(array $feedItemIds, ?int $userId): array
    {
        if (empty($feedItemIds) || !$userId) return [];
        $placeholders = implode(',', array_fill(0, count($feedItemIds), '?'));
        $params       = array_values($feedItemIds);
        $params[]     = $userId;
        $stmt = $this->pdo->prepare("
            SELECT feed_item_id, type
            FROM feed_item_likes
            WHERE feed_item_id IN ($placeholders) AND user_id = ?
        ");
        $stmt->execute($params);
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['feed_item_id']] = $row['type'];
        }
        return $result;
    }

    private function fetchCommentCounts(array $feedItemIds): array
    {
        if (empty($feedItemIds)) return [];
        $placeholders = implode(',', array_fill(0, count($feedItemIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT feed_item_id, COUNT(*) AS total
            FROM comments
            WHERE feed_item_id IN ($placeholders)
            GROUP BY feed_item_id
        ");
        $stmt->execute(array_values($feedItemIds));
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['feed_item_id']] = (int)$row['total'];
        }
        return $result;
    }

    private function fetchShareCounts(array $postIds): array
    {
        if (empty($postIds)) return [];
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT post_id, COUNT(*) AS total
            FROM post_shares
            WHERE post_id IN ($placeholders)
            GROUP BY post_id
        ");
        $stmt->execute(array_values($postIds));
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['post_id']] = (int)$row['total'];
        }
        return $result;
    }

    /**
     * Análise de IA — indexada por feed_item_id.
     * No perfil os items não têm o campo `analysis_type` separado,
     * por isso derivamos o tipo a partir do item_type.
     */
    private function fetchAiAnalysis(array $items): array
    {
        if (empty($items)) return [];

        $typeMap     = ['post' => 'image', 'video' => 'video', 'album' => 'album'];
        $pairs       = [];
        $feedIdToKey = [];

        foreach ($items as $item) {
            $feedItemId   = (int)$item['feed_item_id'];
            // Para reposts, analisa o conteúdo original
            $analysisId   = (!empty($item['is_repost']) && !empty($item['shared_post_id']))
                ? (int)$item['shared_post_id']
                : (int)$item['item_id'];
            $analysisType = $typeMap[$item['item_type']] ?? $item['item_type'];
            $key          = $analysisId . ':' . $analysisType;
            $pairs[$key]  = [$analysisId, $analysisType];
            $feedIdToKey[$feedItemId] = $key;
        }

        $analysisResults = [];
        foreach ($pairs as $key => [$aid, $atype]) {
            $stmt = $this->pdo->prepare(
                "SELECT risk_level, status, explicit_percentage
                 FROM media_analysis
                 WHERE post_id = ? AND type = ?
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$aid, $atype]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) $analysisResults[$key] = $row;
        }

        $result = [];
        foreach ($feedIdToKey as $feedItemId => $key) {
            if (isset($analysisResults[$key])) {
                $result[$feedItemId] = $analysisResults[$key];
            }
        }
        return $result;
    }

    private function fetchFollowingSet(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT followed_id FROM follows WHERE follower_id = ?"
        );
        $stmt->execute([$userId]);
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $result[(int)$id] = true;
        }
        return $result;
    }

    private function fetchPendingSet(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT followed_id FROM follow_requests WHERE follower_id = ?"
        );
        $stmt->execute([$userId]);
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $result[(int)$id] = true;
        }
        return $result;
    }
}
