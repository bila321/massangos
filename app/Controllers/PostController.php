<?php
// app/Controllers/PostController.php

namespace Massango\Controllers;

use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Like;
use Massango\Models\Video;
use Massango\Models\Album;
use Massango\Models\FeedItem;

class PostController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function handle(): void
    {
        // ===== AUTENTICAÇÃO =====
        if (!is_logged_in()) {
            $this->ajaxOrRedirect(
                ['error' => 'Não autorizado'],
                BASE_URL . 'login.php',
                'Você precisa estar logado para acessar as publicações.'
            );
        }

        // ===== PARÂMETRO =====
        $feed_item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$feed_item_id) {
            $this->ajaxOrRedirect(
                ['error' => 'Item não especificado'],
                BASE_URL,
                'Item do feed não especificado.'
            );
        }

        // ===== FEED ITEM =====
        $feed_item = FeedItem::getFeedItemById($this->pdo, $feed_item_id);
        if (!$feed_item) {
            $this->ajaxOrRedirect(
                ['error' => 'Conteúdo não encontrado'],
                BASE_URL,
                'Conteúdo não encontrado no feed.'
            );
        }

        $item_type        = $feed_item['item_type'];
        $original_item_id = $feed_item['item_id'];

        // ===== CONTEÚDO ESPECÍFICO =====
        $content_data = match ($item_type) {
            'post'  => Post::getPostById($this->pdo, $original_item_id),
            'video' => Video::getVideoById($this->pdo, $original_item_id),
            'album' => Album::getAlbumById($this->pdo, $original_item_id),
            default => null,
        };

        if (!$content_data) {
            $this->ajaxOrRedirect(
                ['error' => 'Detalhes não encontrados'],
                BASE_URL,
                'Detalhes do conteúdo não encontrados.'
            );
        }

        // ===== AUTOR =====
        $author = User::getUserById($this->pdo, $feed_item['user_id']);
        if (!$author) {
            $this->ajaxOrRedirect(
                ['error' => 'Autor não encontrado'],
                BASE_URL,
                'Autor da postagem não encontrado.'
            );
        }

        // ===== UTILIZADOR ACTUAL =====
        $current_user_id = get_current_user_id();
        $is_post_owner   = ((int)$author['id'] === (int)$current_user_id);
        $is_admin        = isset($_SESSION['admin_id']);

        // ===== LIKES =====
        $like_info = Like::getFeedItemLikesDislikesCount($this->pdo, $feed_item_id);
        $user_vote = Like::getUserFeedItemVote($this->pdo, $feed_item_id, $current_user_id);

        // ===== AI ANALYSIS — blur de conteúdo explícito =====
        $stmt_ai = $this->pdo->prepare(
            "SELECT is_sensitive, explicit_percentage, risk_level
             FROM media_analysis
             WHERE post_id = ? AND type = ?
             ORDER BY id DESC LIMIT 1"
        );
        $stmt_ai->execute([$original_item_id, $item_type]);
        $ai_analysis     = $stmt_ai->fetch(\PDO::FETCH_ASSOC) ?: null;
        $ai_explicit_pct = $ai_analysis ? (float)($ai_analysis['explicit_percentage'] ?? 0) : 0;
        $show_blur       = (!$is_admin)
                         && ($ai_analysis && (bool)$ai_analysis['is_sensitive'] || $ai_explicit_pct >= 40);

        // ===== FOTO DE PERFIL DO UTILIZADOR LOGADO =====
        $logged_in_user_data        = User::getUserById($this->pdo, $current_user_id);
        $logged_in_user_profile_pic = (!empty($logged_in_user_data['profile_picture']))
            ? $logged_in_user_data['profile_picture']
            : 'profiles/default_profile.png';

        // ===== RENDERIZAR VIEW =====
        $data = compact(
            'feed_item',
            'feed_item_id',
            'item_type',
            'content_data',
            'author',
            'current_user_id',
            'is_post_owner',
            'is_admin',
            'like_info',
            'user_vote',
            'show_blur',
            'logged_in_user_profile_pic',
            'logged_in_user_data'
        );

        extract($data);
        require __DIR__ . '/../../includes/views/post/post.view.php';
    }

    // ===== HELPER — responde JSON em AJAX ou redireciona =====
    private function ajaxOrRedirect(array $json, string $url, string $msg = '', string $type = 'danger'): never
    {
        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode($json);
            exit;
        }
        if ($msg) {
            set_message($msg, $type);
        }
        redirect($url);
    }
}
