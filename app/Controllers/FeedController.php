<?php
namespace Massango\Controllers;

use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Album;
use Massango\Models\FeedItem;
use Massango\Models\Notification;

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
            set_message("Você precisa estar logado para acessar o massangos.", "danger");
            redirect(BASE_URL . 'login.php');
        }

        $current_user_id = get_current_user_id();

        $feedItems     = FeedItem::getAllFeedItems($this->pdo);
        $notifications = $current_user_id
            ? Notification::getNotificationsByUserId($this->pdo, $current_user_id, false, 15)
            : [];

        $logged_in_user_data        = User::getUserById($this->pdo, $current_user_id);
        $user_data                  = $logged_in_user_data;
        $logged_in_user_profile_pic = $logged_in_user_data['profile_picture'] ?? 'profiles/default_profile.png';

        $suggested_users = User::getSuggestedUsers($this->pdo, $current_user_id, 3);
        $recent_albums   = Album::getRecentAlbums($this->pdo, 3);
        $saved_ids       = Post::getSavedIds($this->pdo, $current_user_id);

        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (\Exception $e) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }
        $csrf_token = $_SESSION['csrf_token'];

        return compact(
            'current_user_id', 'feedItems', 'notifications',
            'logged_in_user_data', 'user_data', 'logged_in_user_profile_pic',
            'suggested_users', 'recent_albums', 'saved_ids', 'csrf_token'
        );
    }
}