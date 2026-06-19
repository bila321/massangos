<?php
namespace Massango\Controllers;

use Massango\Models\User;
use Massango\Services\PaymentService;

class ReelsController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function load(array $get): array
    {
        if (!is_logged_in()) {
            set_message("Você precisa estar logado para acessar o massangos.", "danger");
            redirect(BASE_URL . 'login.php');
        }

        $current_user_id     = get_current_user_id();
        $is_admin            = isset($_SESSION['admin_id']);
        $logged_in_user_data = User::getUserById($this->pdo, $current_user_id) ?? [];

        $filter_search    = trim($get['q']          ?? '');
        $filter_sale      = $get['sale']            ?? '';
        $filter_sensitive = $get['sensitive']       ?? '';
        $filter_duration  = $get['duration']        ?? '';
        $filter_price_min = is_numeric($get['price_min'] ?? '') ? (float)$get['price_min'] : '';
        $filter_price_max = is_numeric($get['price_max'] ?? '') ? (float)$get['price_max'] : '';
        $filter_quality   = $get['quality']         ?? '';
        $filter_sort      = $get['sort']            ?? 'recent';

        $where  = ["v.is_approved = 1"];
        $params = [];

        if ($filter_search !== '') {
            $where[]           = "(v.caption LIKE :search OR u.username LIKE :search2)";
            $params[':search']  = "%{$filter_search}%";
            $params[':search2'] = "%{$filter_search}%";
        }
        if ($filter_sale === '1')      $where[] = "v.is_for_sale = 1";
        elseif ($filter_sale === '0')  $where[] = "v.is_for_sale = 0";

        if ($filter_sensitive === '1') {
            $where[] = "(v.categoria = '18+' OR (ma.is_sensitive = 1 AND ma.risk_level IN ('medium','high')))";
        } elseif (!$is_admin) {
            $where[] = "(v.categoria != '18+' OR ma.is_sensitive IS NULL OR ma.risk_level NOT IN ('high'))";
        }

        if ($filter_duration === 'short')       $where[] = "(v.duration_seconds IS NOT NULL AND v.duration_seconds < 60)";
        elseif ($filter_duration === 'medium')  $where[] = "(v.duration_seconds IS NOT NULL AND v.duration_seconds >= 60 AND v.duration_seconds <= 300)";
        elseif ($filter_duration === 'long')    $where[] = "(v.duration_seconds IS NOT NULL AND v.duration_seconds > 300)";

        if ($filter_price_min !== '') { $where[] = "v.price >= :price_min"; $params[':price_min'] = $filter_price_min; }
        if ($filter_price_max !== '') { $where[] = "v.price <= :price_max"; $params[':price_max'] = $filter_price_max; }

        if ($filter_quality === 'sd')       $where[] = "(v.duration_seconds IS NULL OR v.duration_seconds < 30)";
        elseif ($filter_quality === 'hd')   $where[] = "(v.duration_seconds >= 30 AND v.duration_seconds < 120)";
        elseif ($filter_quality === 'fhd')  $where[] = "(v.duration_seconds >= 120)";

        $order_map = [
            'recent'     => 'v.created_at DESC',
            'popular'    => 'v.views_count DESC',
            'price_asc'  => 'v.price ASC',
            'price_desc' => 'v.price DESC',
        ];
        $order_sql = $order_map[$filter_sort] ?? 'v.created_at DESC';
        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT v.*,
                   u.username, u.profile_picture,
                   fi.id AS feed_item_id,
                   ma.is_sensitive, ma.risk_level, ma.score AS ai_score
            FROM videos v
            JOIN users u ON v.user_id = u.id
            LEFT JOIN feed_items fi ON fi.item_id = v.id AND fi.item_type = 'video'
            LEFT JOIN media_analysis ma ON ma.post_id = v.id AND ma.type = 'video'
            WHERE {$where_sql}
            ORDER BY {$order_sql}
            LIMIT 60
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $rawReels = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $rawReels = [];
            error_log("Reels query error: " . $e->getMessage());
        }

        $paymentService = new PaymentService($this->pdo);

        // --- Enriquecer cada reel com os dados/decisões que a View precisa ---
        // (acesso, urls, blur, etc. — nada disto deve ser calculado no template)
        $reels = $this->buildReelViewModels($rawReels, $current_user_id, $is_admin, $logged_in_user_data, $paymentService);

        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (\Exception $e) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }
        $csrf_token = $_SESSION['csrf_token'];

        $active_chip = '';
        if ($filter_sensitive === '1')     $active_chip = 'adult';
        elseif ($filter_sale === '1')      $active_chip = 'paid';
        elseif ($filter_sale === '0')      $active_chip = 'free';

        return compact(
            'current_user_id', 'is_admin', 'logged_in_user_data',
            'filter_search', 'filter_sale', 'filter_sensitive',
            'filter_duration', 'filter_price_min', 'filter_price_max',
            'filter_quality', 'filter_sort',
            'reels', 'csrf_token', 'active_chip'
        );
    }

    /**
     * Transforma as linhas cruas da query em view-models prontos para a View
     * imprimir, sem precisar de saber nada sobre acesso pago, blur de IA, etc.
     *
     * @param array            $rawReels
     * @param int|null         $currentUserId
     * @param bool             $isAdmin
     * @param array            $loggedInUserData
     * @param PaymentService   $paymentService
     * @return array
     */
    private function buildReelViewModels(
        array $rawReels,
        ?int $currentUserId,
        bool $isAdmin,
        array $loggedInUserData,
        PaymentService $paymentService
    ): array {
        // NOTA: isto reflete o comportamento original — usa o estado de
        // "verificado" do utilizador LOGADO, não do autor de cada reel.
        // Suspeito que seja um bug pré-existente (provavelmente devia ser
        // o autor do vídeo), mas mantive o comportamento idêntico ao atual.
        $viewerIsVerifiedCreator = (bool)($loggedInUserData['is_verified_creator'] ?? false);

        $viewModels = [];

        foreach ($rawReels as $reel) {
            $reelId      = (int)$reel['id'];
            $authorId    = (int)$reel['user_id'];
            $feedItemId  = (int)($reel['feed_item_id'] ?? $reelId);
            $isPostOwner = ($currentUserId && $authorId === (int)$currentUserId);

            $hasAccess = $isAdmin
                || $isPostOwner
                || $paymentService->hasAccess($currentUserId ?? 0, 'video', $reelId);

            $isPaid      = !empty($reel['is_for_sale']);
            $isSensitive = !empty($reel['is_sensitive']);

            $riskLevel    = $reel['risk_level'] ?? null;
            $isHighRisk   = ($riskLevel === 'high');
            $isMediumRisk = ($riskLevel === 'medium');
            $shouldBlur   = ($isHighRisk || $isMediumRisk) && !$isAdmin;

            $viewModels[] = array_merge($reel, [
                'feed_item_id'        => $feedItemId,
                'duration_seconds'    => (int)($reel['duration_seconds'] ?? 0),
                'views_count'         => (int)($reel['views_count'] ?? 0),

                // Estes três campos não existem no schema de `videos`;
                // ficam a 0 por omissão tal como no template original.
                'shares_count'        => (int)($reel['shares_count'] ?? 0),
                'video_width'         => (int)($reel['video_width'] ?? 0),
                'video_height'        => (int)($reel['video_height'] ?? 0),

                // URLs já resolvidas (a View só faz htmlspecialchars)
                'profile_pic_url'     => UPLOAD_URL . ($reel['profile_picture'] ?? 'profiles/default_profile.png'),
                'video_url'           => UPLOAD_URL . $reel['video_path'],
                'thumbnail_url'       => UPLOAD_URL . ($reel['thumbnail_path'] ?? ''),
                'checkout_url'        => BASE_URL . 'checkout.php?type=video&id=' . $reelId,

                // IA
                'ai_score'            => $reel['ai_score'] ?? 0,
                'ai_risk'             => $riskLevel ?? '',
                'ai_status'           => !empty($riskLevel) ? 'done' : '',

                // Permissões / estado calculado
                'is_post_owner'       => $isPostOwner,
                'has_access'          => $hasAccess,
                'is_paid'             => $isPaid,
                'is_sensitive'        => $isSensitive,
                'should_blur'         => $shouldBlur,
                'is_verified_creator' => $viewerIsVerifiedCreator,
            ]);
        }

        return $viewModels;
    }
}
