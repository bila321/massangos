<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Controllers\ReelsController;
use Massango\Models\User;

// reels usa layout próprio, sem .feed-container
$hide_feed_container = true;
$hide_sidebar        = false;

$data = (new ReelsController($pdo))->load($_GET);
extract($data);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/views/reels/reels.view.php';
require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/reels_lightbox.php';
