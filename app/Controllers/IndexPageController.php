<?php
namespace Massango\Controllers;

use Massango\Models\FeedItem;
use Massango\Models\Notification;
use PDO;








class IndexPageController
{
    /**
     * @var PDO
     */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Fetches all necessary data for the index page.
     * @return array An associative array containing 'feedItems' and 'notifications'.
     */
    public function getData(): array
    {
        $feedItems = FeedItem::getAllFeedItems($this->pdo);
        $notifications = [];

        if (is_logged_in()) {
            $user_id = get_current_user_id();
            $notifications = Notification::getNotificationsByUserId($this->pdo, $user_id, false, 15);
        }

        return compact('feedItems', 'notifications');
    }
}

