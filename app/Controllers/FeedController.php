<?php

namespace Massango\Controllers;

use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Video;
use Massango\Models\Album;
use Massango\Models\Comment;
use Massango\Models\Like;
use Massango\Models\FeedItem;
use Massango\Models\Notification;
use Massango\Services\PaymentService;

class FeedController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function load(): array
    {
        if (!is_logged_in()) {
            set_message("VocÃª precisa estar logado para acessar o massangos.", "danger");
            redirect(BASE_URL . 'login.php');
        }

        $current_user_id = get_current_user_id();

        // --- Dados globais da sessÃ£o ---
        $logged_in_user_data        = User::getUserById($this->pdo, $current_user_id);
        $user_data                  = $logged_in_user_data;
        $logged_in_user_profile_pic = $logged_in_user_data['profile_picture'] ?? 'profiles/default_profile.png';
        $notifications              = Notification::getNotificationsByUserId($this->pdo, $current_user_id, false, 15);
        $suggested_users            = User::getSuggestedUsers($this->pdo, $current_user_id, 3);
        $recent_albums              = Album::getRecentAlbums($this->pdo, 3);
        $saved_ids                  = Post::getSavedIds($this->pdo, $current_user_id);
        $paymentService             = new PaymentService($this->pdo);

        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (\Exception $e) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }
        $csrf_token = $_SESSION['csrf_token'];

        // --- Feed raw ---
        $rawFeed = FeedItem::getAllFeedItems($this->pdo);

        // --- Separar itens especiais (suggested_users, admin_ad, etc.) dos itens de conteÃºdo ---
        $contentItems = [];
        foreach ($rawFeed as $item) {
            if (!isset($item['type'])) {
                $contentItems[] = $item;
            }
        }

        // ======================================================================
        // BATCH: recolher todos os IDs necessÃ¡rios de uma vez
        // ======================================================================
        $postIds  = [];
        $videoIds = [];
        $albumIds = [];
        $feedItemIds = [];
        $userIds  = [];

        foreach ($contentItems as $item) {
            $feedItemIds[] = (int)$item['feed_item_id'];
            $userIds[]     = (int)$item['user_id'];

            switch ($item['item_type']) {
                case 'post':
                    $postIds[]  = (int)$item['item_id'];
                    break;
                case 'video':
                    $videoIds[] = (int)$item['item_id'];
                    break;
                case 'album':
                    $albumIds[] = (int)$item['item_id'];
                    break;
            }
        }

        // --- Batch: buscar conteÃºdos ---
        $postsMap  = $this->fetchByIds('posts',  'id', $postIds);
        $videosMap = $this->fetchByIds('videos', 'id', $videoIds);
        $albumsMap = $this->fetchByIds('albums', 'id', $albumIds);

        // --- Batch: buscar autores ---
        $usersMap = $this->fetchByIds('users', 'id', array_unique($userIds));

        // --- Batch: likes/dislikes counts ---
        $likesMap = $this->fetchLikesCounts($feedItemIds);

        // --- Batch: votos do utilizador logado ---
        $votesMap = $this->fetchUserVotes($feedItemIds, $current_user_id);

        // --- Batch: contagem de comentÃ¡rios ---
        $commentCountsMap = $this->fetchCommentCounts($feedItemIds);

        // --- Batch: anÃ¡lise de IA ---
        $aiMap = $this->fetchAiAnalysis($contentItems);

        // --- Batch: share counts ---
        $shareCountsMap = $this->fetchShareCounts($postIds);

        // --- Batch: IDs de autores que o utilizador segue / tem pedido pendente ---
        $followingSet     = $this->fetchFollowingSet($current_user_id);
        $pendingSet       = $this->fetchPendingSet($current_user_id);

        // --- Batch: IDs bloqueados (pelo utilizador ou que bloquearam o utilizador) ---
        $blockedSet       = $this->fetchBlockedSet($current_user_id);

        // ======================================================================
        // Enriquecer cada item do feed
        // ======================================================================
        $is_admin = isset($_SESSION['admin_id']);
        $feedItems = [];

        foreach ($rawFeed as $item) {
            // Itens especiais passam directamente
            if (isset($item['type'])) {
                $feedItems[] = $item;
                continue;
            }

            $feedItemId = (int)$item['feed_item_id'];
            $itemType   = $item['item_type'];
            $itemId     = (int)$item['item_id'];
            $authorId   = (int)$item['user_id'];

            // --- ConteÃºdo especÃ­fico ---
            switch ($itemType) {
                case 'post':
                    $content_data = $postsMap[$itemId]  ?? null;
                    break;
                case 'video':
                    $content_data = $videosMap[$itemId] ?? null;
                    break;
                case 'album':
                    $content_data = $albumsMap[$itemId] ?? null;
                    break;
                default:
                    error_log("Tipo desconhecido: $itemType para feed_item_id: $feedItemId");
                    continue 2;
            }

            if (!$content_data) {
                error_log("Sem conteÃºdo para feed_item_id: $feedItemId tipo: $itemType id: $itemId");
                continue;
            }

            $author = $usersMap[$authorId] ?? null;
            if (!$author) {
                error_log("Sem autor para feed_item_id: $feedItemId user_id: $authorId");
                continue;
            }

            // --- Filtros ---
            $is_post_owner = ($current_user_id && $authorId == $current_user_id);

            // AprovaÃ§Ã£o
            $is_approved = isset($content_data['is_approved']) ? (int)$content_data['is_approved'] : 1;
            if (!$is_approved && !$is_post_owner && !$is_admin) continue;

            // Privacidade de perfil
            $author_privacy = $author['profile_privacy'] ?? 'public';
            if ($author_privacy === 'followers' && !$is_post_owner && !$is_admin) {
                if (!$current_user_id) continue;
                if (!isset($followingSet[$authorId])) continue;
            }

            // Bloqueio
            if ($current_user_id && !$is_post_owner) {
                if (isset($blockedSet[$authorId])) continue;
            }

            // show_in_feed
            $show_in_feed = isset($content_data['show_in_feed']) ? (int)$content_data['show_in_feed'] : 1;
            if ($show_in_feed === 0 && !$is_admin) continue;

            // --- AI analysis ---
            $ai_analysis = $aiMap[$feedItemId] ?? null;
            $is_high_risk   = ($ai_analysis && $ai_analysis['status'] === 'done' && $ai_analysis['risk_level'] === 'high');
            $is_medium_risk = ($ai_analysis && $ai_analysis['status'] === 'done' && $ai_analysis['risk_level'] === 'medium');
            $should_blur    = ($is_high_risk || $is_medium_risk) && !$is_admin;

            // --- Repost ---
            $isRepost    = false;
            $sharedData  = null;
            $sharedType  = null;
            $sharedAuthor = null;
            $sharedId    = null;

            if ($itemType === 'post' && !empty($content_data['is_repost']) && !empty($content_data['shared_post_id']) && !empty($content_data['shared_item_type'])) {
                $sharedType = $content_data['shared_item_type'];
                $sharedId   = (int)$content_data['shared_post_id'];

                switch ($sharedType) {
                    case 'post':
                        $sharedData = $postsMap[$sharedId]  ?? Post::getPostById($this->pdo, $sharedId);
                        break;
                    case 'video':
                        $sharedData = $videosMap[$sharedId] ?? Video::getVideoById($this->pdo, $sharedId);
                        break;
                    case 'album':
                        $sharedData = $albumsMap[$sharedId] ?? Album::getAlbumById($this->pdo, $sharedId);
                        break;
                }

                if ($sharedData) {
                    $isRepost     = true;
                    $sharedUserId = (int)$sharedData['user_id'];
                    $sharedAuthor = $usersMap[$sharedUserId] ?? User::getUserById($this->pdo, $sharedUserId);
                }
            }

            // --- Acesso a conteÃºdo pago ---
            $has_access        = !isset($content_data['is_for_sale']) || !$content_data['is_for_sale']
                || $is_post_owner || $is_admin
                || $paymentService->hasAccess($current_user_id ?? 0, $itemType, $itemId);
            $has_access_shared = true;
            if ($isRepost && $sharedData && isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']) {
                $is_shared_owner   = isset($sharedData['user_id']) && $sharedData['user_id'] == $current_user_id;
                $has_access_shared = $is_shared_owner || $is_admin
                    || $paymentService->hasAccess($current_user_id ?? 0, $sharedType, $sharedId);
            }

            // --- Follow state para o autor (para botÃ£o Seguir no card) ---
            $is_following    = isset($followingSet[$authorId]);
            $has_follow_req  = isset($pendingSet[$authorId]);
            $follow_label    = $is_following ? 'Seguindo' : ($has_follow_req ? 'Pendente' : 'Seguir');
            $follow_class    = $is_following ? 'following' : ($has_follow_req ? 'pending' : '');

            // --- Share count ---
            $share_count = 0;
            if ($itemType === 'post') {
                $resolvedShareId = (!empty($content_data['is_repost']) && !empty($content_data['shared_post_id']))
                    ? (int)$content_data['shared_post_id']
                    : $itemId;
                $share_count = $shareCountsMap[$resolvedShareId] ?? 0;
            }

            // --- Like info & voto ---
            $like_info   = $likesMap[$feedItemId]  ?? ['likes' => 0, 'dislikes' => 0];
            $user_vote   = $votesMap[$feedItemId]   ?? null;
            $comment_count = $commentCountsMap[$feedItemId] ?? 0;

            // --- Save state ---
            $save_key   = $itemType . '_' . $itemId;
            $is_saved   = isset($saved_ids[$save_key]);

            // --- Monta item enriquecido ---
            $feedItems[] = array_merge($item, [
                // ConteÃºdo
                'content_data'      => $content_data,
                'author'            => $author,
                // Repost
                'isRepost'          => $isRepost,
                'sharedData'        => $sharedData,
                'sharedType'        => $sharedType,
                'sharedAuthor'      => $sharedAuthor,
                'sharedId'          => $sharedId,
                // PermissÃµes
                'is_post_owner'     => $is_post_owner,
                'is_admin'          => $is_admin,
                'has_access'        => $has_access,
                'has_access_shared' => $has_access_shared,
                // AI
                'ai_analysis'       => $ai_analysis,
                'should_blur'       => $should_blur,
                // Social
                'like_info'         => $like_info,
                'user_vote'         => $user_vote,
                'comment_count'     => $comment_count,
                'share_count'       => $share_count,
                // Follow
                'is_following'      => $is_following,
                'has_follow_req'    => $has_follow_req,
                'follow_label'      => $follow_label,
                'follow_class'      => $follow_class,
                // Save
                'is_saved'          => $is_saved,
                'save_label'        => $is_saved ? 'Guardado' : 'Guardar',
                'save_icon'         => $is_saved ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark',
                'save_class'        => $is_saved ? 'btn-save active' : 'btn-save',
            ]);
        }

        return compact(
            'current_user_id',
            'feedItems',
            'notifications',
            'logged_in_user_data',
            'user_data',
            'logged_in_user_profile_pic',
            'suggested_users',
            'recent_albums',
            'saved_ids',
            'csrf_token'
        );
    }

    // ==========================================================================
    // Helpers de batch query
    // ==========================================================================

    /** Busca registos por IDs numa tabela, devolve array indexado por ID. */
    private function fetchByIds(string $table, string $idCol, array $ids): array
    {
        if (empty($ids)) return [];
        $ids = array_unique($ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM `$table` WHERE `$idCol` IN ($placeholders)");
        $stmt->execute(array_values($ids));
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row[$idCol]] = $row;
        }
        return $result;
    }

    /** Batch: likes e dislikes por feed_item_id. */
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

    /** Batch: voto do utilizador logado por feed_item_id. */
    private function fetchUserVotes(array $feedItemIds, ?int $userId): array
    {
        if (empty($feedItemIds) || !$userId) return [];
        $placeholders = implode(',', array_fill(0, count($feedItemIds), '?'));
        $params = array_values($feedItemIds);
        $params[] = $userId;
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

    /** Batch: contagem de comentÃ¡rios por feed_item_id. */
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

    /** Batch: anÃ¡lise de IA â€” Ãºltima anÃ¡lise por (post_id, type). */
    private function fetchAiAnalysis(array $items): array
    {
        if (empty($items)) return [];

        // Monta pares (item_id, analysis_type) Ãºnicos
        $typeMap = ['post' => 'image', 'video' => 'video', 'album' => 'album'];
        $pairs   = [];
        $feedIdToKey = []; // feed_item_id => "id:type"

        foreach ($items as $item) {
            $feedItemId   = (int)$item['feed_item_id'];
            $analysisId   = (int)$item['item_id'];
            $analysisType = $typeMap[$item['item_type']] ?? $item['item_type'];
            $key = $analysisId . ':' . $analysisType;
            $pairs[$key] = [$analysisId, $analysisType];
            $feedIdToKey[$feedItemId] = $key;
        }

        if (empty($pairs)) return [];

        // Busca todas as anÃ¡lises relevantes
        $analysisResults = []; // key => row
        foreach ($pairs as $key => [$aid, $atype]) {
            $stmt = $this->pdo->prepare(
                "SELECT risk_level, status, explicit_percentage FROM media_analysis
                 WHERE post_id = ? AND type = ? ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$aid, $atype]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) $analysisResults[$key] = $row;
        }

        // Indexa por feed_item_id
        $result = [];
        foreach ($feedIdToKey as $feedItemId => $key) {
            if (isset($analysisResults[$key])) {
                $result[$feedItemId] = $analysisResults[$key];
            }
        }
        return $result;
    }

    /** Batch: share counts por post_id. */
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

    /** IDs que o utilizador segue (set para lookup O(1)). */
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

    /** IDs com follow request pendente. */
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

    /** IDs bloqueados (pelo utilizador ou que bloquearam o utilizador). */
    private function fetchBlockedSet(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT blocked_id FROM blocks WHERE blocker_id = ?
             UNION
             SELECT blocker_id FROM blocks WHERE blocked_id = ?"
        );
        $stmt->execute([$userId, $userId]);
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $result[(int)$id] = true;
        }
        return $result;
    }
}
