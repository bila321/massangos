<?php
namespace Massango\Controllers;

use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Album;
use Massango\Models\FeedItem;
use Massango\Models\Notification;
use Massango\Services\PaymentService;
use Massango\Services\PricingRuleService;

class ProfileController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function load(?string $profile_user_id_param): array
    {
        $current_user_id = is_logged_in() ? get_current_user_id() : null;

        if (!is_logged_in()) {
            set_message("Você precisa estar logado para acessar o perfil.", "danger");
            redirect(BASE_URL . 'login.php');
        }

        $profile_user_id = $profile_user_id_param ?? $current_user_id;

        if (!$profile_user_id) {
            set_message("Utilizador não especificado.", "danger");
            redirect(BASE_URL);
        }

        $profile_data = User::getUserById($this->pdo, $profile_user_id);

        if (!$profile_data) {
            set_message("Perfil não encontrado.", "danger");
            redirect(BASE_URL);
        }

        $is_owner = ($current_user_id && $profile_user_id == $current_user_id);

        $is_blocked_by_me = false;
        $am_i_blocked     = false;
        if (!$is_owner && $current_user_id) {
            $is_blocked_by_me = User::isBlocking($this->pdo, $current_user_id, $profile_user_id);
            $am_i_blocked     = User::isBlocking($this->pdo, $profile_user_id, $current_user_id);
        }

        $is_following       = false;
        $has_pending_request = false;
        if (!$is_owner && $current_user_id) {
            $is_following = User::isFollowing($this->pdo, $current_user_id, $profile_user_id);
            if (!$is_following) {
                $has_pending_request = User::hasPendingFollowRequest($this->pdo, $current_user_id, $profile_user_id);
            }
        }

        $profile_privacy  = $profile_data['profile_privacy'] ?? 'public';
        $can_view_content = true;
        if (!$is_owner && $profile_privacy === 'followers') {
            $can_view_content = $current_user_id && $is_following;
        }

        if (!$is_owner) {
            $visitor_identifier = $current_user_id ? 'user_' . $current_user_id : 'session_' . session_id();
            User::recordProfileVisit($this->pdo, $profile_user_id, $profile_data, $visitor_identifier);
            $profile_data = User::getUserById($this->pdo, $profile_user_id);
        }

        $followers_count = User::getFollowersCount($this->pdo, $profile_user_id);
        $following_count = User::getFollowingCount($this->pdo, $profile_user_id);
        $total_visits    = $profile_data['total_profile_views'] ?? 0;
        $daily_visits    = $profile_data['last_daily_visit_count'] ?? 0;

        $star_rating = (int)($profile_data['stars'] ?? 0);
        if ($star_rating <= 0) {
            $s5 = (int)PricingRuleService::getSetting($this->pdo, 'star_5_visits', 6400);
            $s4 = (int)PricingRuleService::getSetting($this->pdo, 'star_4_visits', 1600);
            $s3 = (int)PricingRuleService::getSetting($this->pdo, 'star_3_visits', 400);
            $s2 = (int)PricingRuleService::getSetting($this->pdo, 'star_2_visits', 100);
            $s1 = (int)PricingRuleService::getSetting($this->pdo, 'star_1_visits', 25);
            if ($daily_visits >= $s5)      $star_rating = 5;
            elseif ($daily_visits >= $s4)  $star_rating = 4;
            elseif ($daily_visits >= $s3)  $star_rating = 3;
            elseif ($daily_visits >= $s2)  $star_rating = 2;
            elseif ($daily_visits >= $s1)  $star_rating = 1;
            else                           $star_rating = 0;
        }

        $paymentService   = new PaymentService($this->pdo);
        $all_user_content = FeedItem::getFeedItemsByUserId($this->pdo, $profile_user_id);
        foreach ($all_user_content as &$item) {
            $item['has_access'] = $paymentService->hasAccess($current_user_id ?? 0, $item['item_type'], $item['item_id']);
        }
        unset($item);

        $notifications  = $current_user_id
            ? Notification::getNotificationsByUserId($this->pdo, $current_user_id, false, 15)
            : [];

        $suggested_users = is_logged_in()
            ? User::getSuggestedUsers($this->pdo, $current_user_id, 3)
            : [];

        $recent_albums      = Album::getRecentAlbums($this->pdo, 3);
        $logged_in_user_data = User::getUserById($this->pdo, $current_user_id);
        $saved_ids          = is_logged_in() ? Post::getSavedIds($this->pdo, $current_user_id) : [];

        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16));
            }
        }
        $csrf_token = $_SESSION['csrf_token'];

        return compact(
            'profile_user_id', 'profile_data', 'current_user_id',
            'is_owner', 'is_blocked_by_me', 'am_i_blocked',
            'is_following', 'has_pending_request',
            'can_view_content', 'profile_privacy',
            'followers_count', 'following_count',
            'total_visits', 'daily_visits', 'star_rating',
            'all_user_content', 'notifications', 'suggested_users',
            'recent_albums', 'logged_in_user_data',
            'saved_ids', 'csrf_token'
        );
    }
}