<?php

/**
 * public/profile.php
 *
 * Ponto de entrada — responsabilidade única: bootstrap + arrancar o Controller.
 *
 * Lógica de negócio → app/Controllers/ProfileController.php
 * Templates HTML    → includes/views/profile/
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

use Massango\Controllers\ProfileController;
use Massango\Core\Database;

// ── Controller: toda a lógica de negócio ─────────────────────────────────────
$data = (new ProfileController(Database::getInstance()))->load($_GET['id'] ?? null);

// ── Desempacotar variáveis explicitamente (mais seguro que extract) ───────────
$profile_user_id     = $data['profile_user_id'];
$profile_data        = $data['profile_data'];
$current_user_id     = $data['current_user_id'];
$logged_in_user_data = $data['logged_in_user_data'];
$user_data           = $data['user_data'];
$is_admin            = $data['is_admin'];
$is_owner            = $data['is_owner'];
$am_i_blocked        = $data['am_i_blocked'];
$is_blocked_by_me    = $data['is_blocked_by_me'];
$is_following        = $data['is_following'];
$has_pending_request = $data['has_pending_request'];
$can_view_content    = $data['can_view_content'];
$followers_count     = $data['followers_count'];
$following_count     = $data['following_count'];
$total_visits        = $data['total_visits'];
$star_rating         = $data['star_rating'];
$enriched_feed       = $data['enriched_feed'];
$notifications       = $data['notifications'];
$csrf_token          = $data['csrf_token'];
$saved_ids           = $data['saved_ids'];
$redirect_context    = $data['redirect_context'];

// ── Variáveis calculadas uma vez (usadas em múltiplos sítios da view) ─────────
$account_type  = $profile_data['account_type'] ?? 'standard';
$follow_label  = $is_following ? 'Seguindo' : ($has_pending_request ? 'Pedido Enviado' : 'Seguir');
$follow_class  = ($is_following || $has_pending_request) ? 'following' : '';
$follow_icon   = $is_following ? 'fa-user-check' : 'fa-user-plus';

// ── Bloco de acesso restrito ──────────────────────────────────────────────────
if ($am_i_blocked || $is_blocked_by_me) {
    require_once __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/views/profile/_blocked.php';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── CSS extra para o header ───────────────────────────────────────────────────
$extra_css = ['components/premium_lightbox.css'];
require_once __DIR__ . '/../includes/header.php';

// ── View principal ────────────────────────────────────────────────────────────
require __DIR__ . '/../includes/views/profile/profile.view.php';

require_once __DIR__ . '/../includes/profile-footer.php';
