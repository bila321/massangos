<?php

/**
 * api/sidebar-user.php
 * Devolve JSON com os dados dinâmicos da sidebar esquerda:
 * username, profile_pic, unread_count, is_verified_creator, creator_html.
 *
 * Chamado via fetch() pelo sidebar.php após o primeiro paint.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Models\Notification;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
/* Cache curto: 30s — evita query por cada navegação mas mantém badges frescos */
header('Cache-Control: private, max-age=30');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

$current_user_id = get_current_user_id();
$user_data       = \Massango\Models\User::getUserById($pdo, $current_user_id);
$unread_count    = Notification::getUnreadNotificationCount($pdo, $current_user_id);
$is_creator      = !empty($user_data['is_verified_creator']);
$current_page    = $_GET['page'] ?? '';

$profile_pic = UPLOAD_URL . ($user_data['profile_picture'] ?? 'profiles/default_profile.png');
$username    = $user_data['username'] ?? '';

/* HTML da secção creator — gerado só se aplicável */
$creator_html = '';
if ($is_creator) {
    $base = BASE_URL;
    $balance_html = '';
    // $user_stats pode não existir neste contexto — omitimos o badge de saldo
    // (pode ser adicionado via outra query se necessário)

    $items = [
        ['wallet.php',           'fa-wallet',    'Carteira',    $balance_html],
        ['sales_performance.php', 'fa-chart-bar', 'Vendas',      ''],
        ['subscriptions.php',    'fa-star',      'Assinaturas', ''],
        ['ai-center.php',        'fa-robot',     'IA Center',   '<span class="sidebar-badge beta">BETA</span>'],
        ['upgrade.php',          'fa-crown',     'natura',      ''],
    ];

    $creator_html = '<ul class="sidebar-menu">';
    foreach ($items as [$page, $icon, $label, $extra]) {
        $active = ($current_page === $page) ? ' active' : '';
        $crown  = ($page === 'upgrade.php') ? ' crown-icon' : '';
        $creator_html .=
            '<li><a href="' . $base . $page . '" class="nav-link' . $active . '">' .
            '<i class="fa-solid ' . $icon . $crown . '"></i>' .
            '<span>' . $label . '</span>' . $extra .
            '</a></li>';
    }
    $creator_html .= '</ul>';
}

echo json_encode([
    'username'           => htmlspecialchars($username),
    'profile_pic'        => htmlspecialchars($profile_pic),
    'unread_count'       => (int) $unread_count,
    'is_verified_creator' => (bool) $is_creator,
    'creator_html'       => $creator_html,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
