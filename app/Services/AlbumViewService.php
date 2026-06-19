<?php
declare(strict_types=1);

namespace Massango\Services;

use Massango\Models\User;
use Massango\Models\Photo;
use Massango\Models\Album;
use Massango\Models\Comment;
use Massango\Models\FeedItem;
use Massango\Models\Like;
use PDO;

/**
 * AlbumViewService
 *
 * Encapsula toda a lógica de negócio e acesso a dados necessários
 * para a página de visualização de um álbum.
 *
 * Não emite headers, não escreve HTML, não usa redirect().
 * Devolve arrays simples que o Controller pode passar à view.
 */
class AlbumViewService
{
    public function __construct(private PDO $pdo) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Carregamento principal
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Carrega todos os dados necessários para a view.
     * Devolve null se o álbum não existir.
     *
     * @return array<string,mixed>|null
     */
    public function loadAlbum(int $album_id, int $current_user_id): ?array
    {
        // ── Linha base do álbum ───────────────────────────────────────────
        $stmt = $this->pdo->prepare(
            "SELECT user_id, views_count FROM albums WHERE id = ?"
        );
        $stmt->execute([$album_id]);
        $album_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$album_row) return null;

        $is_owner = ($album_row['user_id'] == $current_user_id);

        // ── FeedItem (cria automaticamente se não existir) ────────────────
        [$feed_item, $feed_item_id] = $this->resolveOrCreateFeedItem(
            $album_id,
            $album_row['user_id']
        );

        // ── Author & content ──────────────────────────────────────────────
        $author       = User::getUserById($this->pdo, $feed_item['user_id'] ?? $album_row['user_id']);
        $content_data = Album::getAlbumById($this->pdo, $album_id);
        $content_data['views_count'] = $album_row['views_count'];

        // ── Acesso pago ───────────────────────────────────────────────────
        $paymentService = new PaymentService($this->pdo);
        $has_access = isset($_SESSION['admin_id'])
            ? true
            : $paymentService->hasAccess($current_user_id, 'album', $album_id);

        // ── Análise AI do álbum ───────────────────────────────────────────
        $ai_analysis = $this->fetchAlbumAiAnalysis($album_id);

        // ── Fotos com análise individual + blur ───────────────────────────
        $photos = $this->loadPhotosWithBlur($album_id, $ai_analysis);

        // ── Flags de blur derivadas ───────────────────────────────────────
        $album_explicit_pct = $ai_analysis
            ? (float)($ai_analysis['explicit_percentage'] ?? $ai_analysis['score'] ?? 0)
            : 0.0;
        $album_risk_level   = $ai_analysis['risk_level'] ?? 'low';
        $analysis_done      = $ai_analysis && $ai_analysis['status'] === 'done';
        $is_high_risk       = $analysis_done && $album_risk_level === 'high';
        $is_medium_risk     = $analysis_done && $album_risk_level === 'medium';
        $should_blur        = ($is_high_risk || $is_medium_risk) && !isset($_SESSION['admin_id']);

        // ── Reações e comentários do álbum ────────────────────────────────
        $has_feed    = !empty($feed_item_id) && is_numeric($feed_item_id);
        $comment_tree = $has_feed
            ? Comment::getCommentsForFeedItem($this->pdo, $feed_item_id, $current_user_id)
            : [];
        $like_info   = $has_feed
            ? Like::getFeedItemLikesDislikesCount($this->pdo, $feed_item_id)
            : ['likes' => 0, 'dislikes' => 0];
        $user_vote   = $has_feed
            ? Like::getUserFeedItemVote($this->pdo, $feed_item_id, $current_user_id)
            : null;

        // ── Comentários individuais de fotos ──────────────────────────────
        $photo_comments_for_album = $this->loadPhotoComments($photos, $current_user_id);

        // ── Contagem total ─────────────────────────────────────────────────
        $comment_count_album  = $has_feed
            ? Comment::getCommentCountForFeedItem($this->pdo, $feed_item_id)
            : 0;
        $comment_count_photos = count($photo_comments_for_album);
        $comment_count        = $comment_count_album + $comment_count_photos;

        // ── Likes/saves por foto (para VA_PHOTOS JS) ──────────────────────
        [$photo_likes_map, $photo_comments_map, $photo_saves_map, $photo_saves_count]
            = $this->loadPhotoInteractions($photos, $current_user_id);

        // ── Foto de perfil do utilizador actual ───────────────────────────
        $me_pic = $this->resolveCurrentUserPic($current_user_id);

        return [
            // Identificadores
            'album_id'          => $album_id,
            'feed_item_id'      => $feed_item_id,
            'current_user_id'   => $current_user_id,

            // Flags de controlo
            'is_owner'          => $is_owner,
            'has_access'        => $has_access,
            'has_feed'          => $has_feed,
            'is_approved'       => (int)($content_data['is_approved'] ?? 1),

            // Conteúdo
            'author'            => $author,
            'content_data'      => $content_data,
            'feed_item'         => $feed_item,
            'photos'            => $photos,
            'album_row'         => $album_row,

            // Análise AI
            'ai_analysis'       => $ai_analysis,
            'album_explicit_pct'=> $album_explicit_pct,
            'album_risk_level'  => $album_risk_level,
            'should_blur'       => $should_blur,
            'album_is_sensitive'=> $ai_analysis && (bool)$ai_analysis['is_sensitive'],

            // Reações
            'comment_tree'      => $comment_tree,
            'like_info'         => $like_info,
            'user_vote'         => $user_vote,
            'comment_count'     => $comment_count,

            // Comentários de fotos
            'photo_comments_for_album' => $photo_comments_for_album,

            // Interações por foto (para JSON JS)
            'photo_likes_map'   => $photo_likes_map,
            'photo_comments_map'=> $photo_comments_map,
            'photo_saves_map'   => $photo_saves_map,
            'photo_saves_count' => $photo_saves_count,

            // Utilizador actual
            'me_pic'            => $me_pic,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Privacidade
    // ─────────────────────────────────────────────────────────────────────────

    public function checkPrivacy(array $data, int $current_user_id): bool
    {
        $owner_privacy = $data['author']['profile_privacy'] ?? 'public';

        if ($owner_privacy !== 'followers') return true;
        if ($data['is_owner']) return true;
        if (isset($_SESSION['admin_id'])) return true;

        return User::isFollowing($this->pdo, $current_user_id, $data['content_data']['user_id'])
            || User::isMutualFollower($this->pdo, $current_user_id, $data['content_data']['user_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Registo de visualização (throttle 1 h / sessão)
    // ─────────────────────────────────────────────────────────────────────────

    public function registerView(int $album_id, int $current_user_id, bool $is_owner): void
    {
        if ($is_owner) return;

        $key = 'last_view_album_' . $album_id;
        $now = time();

        if (!isset($_SESSION[$key]) || ($now - $_SESSION[$key]) > 3600) {
            $this->pdo->prepare(
                "UPDATE albums SET views_count = views_count + 1 WHERE id = ?"
            )->execute([$album_id]);
            $_SESSION[$key] = $now;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Análise AI — enfileirar se necessário
    // ─────────────────────────────────────────────────────────────────────────

    public function maybeQueueAiAnalysis(int $album_id, array $data): void
    {
        $ai_analysis = $data['ai_analysis'];
        $needs_queue = !$ai_analysis || ($ai_analysis['status'] === 'failed');

        if (!$needs_queue) return;

        // Verificar se já está na fila
        $stmt = $this->pdo->prepare(
            "SELECT id FROM media_queue
             WHERE post_id = ? AND item_type = 'album' AND status IN ('pending','processing')
             LIMIT 1"
        );
        $stmt->execute([$album_id]);
        if ($stmt->fetchColumn()) return;

        $cover = $data['content_data']['thumbnail_path']
            ?? $data['content_data']['cover_photo_url']
            ?? 'album_placeholder';

        try {
            // Limpar registo anterior com falha para evitar conflito de UNIQUE KEY
            if ($ai_analysis && $ai_analysis['status'] === 'failed') {
                $this->pdo->prepare(
                    "DELETE FROM media_analysis WHERE post_id = ? AND type = 'album'"
                )->execute([$album_id]);
            }

            $this->pdo->prepare(
                "INSERT INTO media_queue (post_id, file_path, item_type, status, created_at)
                 VALUES (?, ?, 'album', 'pending', NOW())"
            )->execute([$album_id, $cover]);
        } catch (\Exception $e) {
            error_log("[AlbumViewService] Falha ao enfileirar album {$album_id}: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados de suporte
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve o FeedItem existente ou cria um novo (para álbuns antigos).
     * Devolve [array $feed_item, int|null $feed_item_id].
     */
    private function resolveOrCreateFeedItem(int $album_id, int $owner_id): array
    {
        $feed_item    = FeedItem::getFeedItemByContentId($this->pdo, $album_id, 'album');
        $feed_item_id = $feed_item['id'] ?? null;

        if ($feed_item_id) {
            return [$feed_item, $feed_item_id];
        }

        // Criar automaticamente para que comments/reações funcionem
        try {
            $album = Album::getAlbumById($this->pdo, $album_id);
            $ins   = $this->pdo->prepare(
                "INSERT INTO feed_items (user_id, item_type, item_id, created_at, show_in_feed)
                 VALUES (?, 'album', ?, ?, 0)"
            );
            $ins->execute([
                $owner_id,
                $album_id,
                $album['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $feed_item_id = (int)$this->pdo->lastInsertId();
            $feed_item = [
                'id'         => $feed_item_id,
                'user_id'    => $owner_id,
                'item_type'  => 'album',
                'item_id'    => $album_id,
                'created_at' => $album['created_at'] ?? date('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            // Race condition — tentar ler novamente
            $feed_item    = FeedItem::getFeedItemByContentId($this->pdo, $album_id, 'album');
            $feed_item_id = $feed_item['id'] ?? null;
        }

        return [$feed_item, $feed_item_id];
    }

    /**
     * Analise AI ao nível do álbum.
     */
    private function fetchAlbumAiAnalysis(int $album_id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT is_sensitive, score,
                    COALESCE(explicit_percentage, score, 0) AS explicit_percentage,
                    risk_level, triggered_by, status
             FROM media_analysis
             WHERE post_id = ? AND type = 'album'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$album_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Carrega as fotos do álbum e anota cada uma com os dados de análise AI
     * (análise individual ou fallback do álbum) e o flag show_blur.
     */
    private function loadPhotosWithBlur(int $album_id, ?array $ai_analysis): array
    {
        $photos = Photo::getPhotosByAlbumId($this->pdo, $album_id);

        if (empty($photos)) return [];

        // Análise individual por foto
        $photo_ids    = array_column($photos, 'id');
        $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT post_id, is_sensitive,
                    COALESCE(explicit_percentage, score, 0) AS explicit_percentage,
                    risk_level, status
             FROM media_analysis
             WHERE post_id IN ($placeholders)
               AND type IN ('image', 'photo', 'album_photo', 'album')
               AND status = 'done'
             ORDER BY id DESC"
        );
        $stmt->execute($photo_ids);

        $photo_analysis_map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($photo_analysis_map[$row['post_id']])) {
                $photo_analysis_map[$row['post_id']] = $row;
            }
        }

        // Fallback: dados do álbum para fotos sem análise individual
        $album_done_and_risky = $ai_analysis
            && $ai_analysis['status'] === 'done'
            && in_array($ai_analysis['risk_level'] ?? 'low', ['medium', 'high']);
        $album_fallback_pct  = $album_done_and_risky
            ? (float)($ai_analysis['explicit_percentage'] ?? $ai_analysis['score'] ?? 0)
            : 0.0;
        $album_fallback_risk = $album_done_and_risky
            ? ($ai_analysis['risk_level'] ?? 'low')
            : 'low';

        foreach ($photos as &$photo) {
            $pa = $photo_analysis_map[$photo['id']] ?? null;

            if ($pa) {
                $photo['ai_risk_level']   = $pa['risk_level'] ?? 'low';
                $photo['ai_explicit_pct'] = (float)$pa['explicit_percentage'];
                $photo['ai_is_sensitive'] = (bool)$pa['is_sensitive'];
                $photo['show_blur']       = in_array($pa['risk_level'], ['medium', 'high'])
                    && !isset($_SESSION['admin_id']);
            } elseif ($album_done_and_risky) {
                $photo['ai_risk_level']   = $album_fallback_risk;
                $photo['ai_explicit_pct'] = $album_fallback_pct;
                $photo['ai_is_sensitive'] = (bool)($ai_analysis['is_sensitive'] ?? false);
                $photo['show_blur']       = !isset($_SESSION['admin_id']);
            } else {
                $photo['ai_risk_level']   = null;
                $photo['ai_explicit_pct'] = 0.0;
                $photo['ai_is_sensitive'] = false;
                $photo['show_blur']       = false;
            }
        }
        unset($photo);

        return $photos;
    }

    /**
     * Carrega todos os photo_comments do álbum, organiza em árvore.
     */
    private function loadPhotoComments(array $photos, int $current_user_id): array
    {
        if (empty($photos)) return [];

        $photo_index_map = [];
        foreach ($photos as $idx => $p) {
            $photo_index_map[$p['id']] = $idx + 1;
        }

        $photo_ids    = array_column($photos, 'id');
        $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));

        $stmt = $this->pdo->prepare("
            SELECT pc.id, pc.photo_id, pc.user_id, pc.content, pc.created_at,
                   pc.parent_comment_id,
                   u.username, u.profile_picture,
                   COALESCE(lk.likes_count, 0) AS likes_count,
                   COALESCE(lk.user_liked, 0)  AS user_liked
            FROM photo_comments pc
            JOIN users u ON u.id = pc.user_id
            LEFT JOIN (
                SELECT comment_id,
                       COUNT(*) AS likes_count,
                       MAX(CASE WHEN user_id = ? THEN 1 ELSE 0 END) AS user_liked
                FROM photo_comment_likes
                GROUP BY comment_id
            ) lk ON lk.comment_id = pc.id
            WHERE pc.photo_id IN ($placeholders)
            ORDER BY pc.created_at ASC
        ");
        $stmt->execute(array_merge([$current_user_id], $photo_ids));
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Montar árvore
        $by_id = [];
        foreach ($raw as $pc) {
            $pc['photo_index']  = $photo_index_map[$pc['photo_id']] ?? null;
            $pc['photo_js_idx'] = $pc['photo_index'] !== null ? $pc['photo_index'] - 1 : null;
            $pc['replies']      = [];
            $by_id[$pc['id']]   = $pc;
        }
        foreach ($by_id as $id => &$ref) {
            $pid = $ref['parent_comment_id'];
            if ($pid && isset($by_id[$pid])) {
                $by_id[$pid]['replies'][] = &$by_id[$id];
            }
        }
        unset($ref);

        $roots = [];
        foreach ($by_id as $pc) {
            if (empty($pc['parent_comment_id'])) {
                $roots[] = $pc;
            }
        }

        return $roots;
    }

    /**
     * Carrega likes, contagens de comentários e saves por foto.
     * Devolve [$likes_map, $comments_map, $saves_map, $saves_count].
     */
    private function loadPhotoInteractions(array $photos, int $current_user_id): array
    {
        $likes_map    = [];
        $comments_map = [];
        $saves_map    = [];
        $saves_count  = [];

        if (empty($photos)) {
            return [$likes_map, $comments_map, $saves_map, $saves_count];
        }

        $photo_ids    = array_column($photos, 'id');
        $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));

        // Likes
        $lk = $this->pdo->prepare("
            SELECT photo_id,
                   COUNT(*) AS likes_count,
                   MAX(CASE WHEN user_id = ? THEN 1 ELSE 0 END) AS user_liked
            FROM photo_likes
            WHERE photo_id IN ($placeholders)
            GROUP BY photo_id
        ");
        $lk->execute(array_merge([$current_user_id], $photo_ids));
        foreach ($lk->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $likes_map[$row['photo_id']] = [
                'likes_count' => (int)$row['likes_count'],
                'user_liked'  => (bool)$row['user_liked'],
            ];
        }

        // Comentários raiz
        $cm = $this->pdo->prepare("
            SELECT photo_id, COUNT(*) AS comments_count
            FROM photo_comments
            WHERE photo_id IN ($placeholders)
              AND parent_comment_id IS NULL
            GROUP BY photo_id
        ");
        $cm->execute($photo_ids);
        foreach ($cm->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $comments_map[$row['photo_id']] = (int)$row['comments_count'];
        }

        // Saves do utilizador
        $sv = $this->pdo->prepare("
            SELECT item_id AS photo_id
            FROM saved_posts
            WHERE user_id = ?
              AND item_type = 'photo'
              AND item_id IN ($placeholders)
        ");
        $sv->execute(array_merge([$current_user_id], $photo_ids));
        foreach ($sv->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $saves_map[(int)$pid] = true;
        }

        // Total de saves
        $svc = $this->pdo->prepare("
            SELECT item_id AS photo_id, COUNT(*) AS total
            FROM saved_posts
            WHERE item_type = 'photo'
              AND item_id IN ($placeholders)
            GROUP BY item_id
        ");
        $svc->execute($photo_ids);
        foreach ($svc->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $saves_count[(int)$row['photo_id']] = (int)$row['total'];
        }

        return [$likes_map, $comments_map, $saves_map, $saves_count];
    }

    /**
     * Foto de perfil do utilizador actual.
     */
    private function resolveCurrentUserPic(int $current_user_id): string
    {
        if (!is_logged_in()) return 'profiles/default_profile.png';

        $me = User::getUserById($this->pdo, $current_user_id);
        return (!empty($me['profile_picture'])) ? $me['profile_picture'] : 'profiles/default_profile.png';
    }
}
