<?php

/**
 * public/settings.php
 *
 * Ponto de entrada — bootstrap + Controller.
 *
 * Lógica de negócio → app/Controllers/SettingsController.php
 *   ATENÇÃO: SettingsController é ainda um STUB (ver app/Controllers/SettingsController.php).
 *   A lógica complexa de upload/crop/NudeNet do POST de configurações
 *   continua por migrar — este ficheiro só limpa o bootstrap e o GET inicial.
 *   handle() não é chamado aqui porque ainda não faz nada útil; o POST
 *   de definições deve continuar a ser tratado como estava antes desta
 *   limpeza, até à migração real do Controller.
 *
 * Templates HTML → includes/views/settings/
 */

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';


SecurityManager::initSecurity();

use Massango\Controllers\FeedController;
use Massango\Models\User;
use Massango\Core\Database;

// Auth guard
if (!is_logged_in()) {
    set_message("Você precisa estar logado para acessar as configurações.", "danger");
    redirect(BASE_URL . 'login.php');
}

// Esta página já inclui o seu próprio modal de verificação via
// includes/views/settings/_modal_verification.php. O footer.php inclui
// verificationmodal.php (modal genérico global) em toda página logada
// — sem esta flag, ambos os modais com id="verificationModal" coexistem
// no DOM, duplicando o <script> que declara `let currentStream` e
// causando "Identifier 'currentStream' has already been declared".
$hide_verification_modal = true;

$pdo = Database::getInstance();

// ── Dados do utilizador via FeedController ────────────────────────────────────
$data = (new FeedController($pdo))->load();

// ── Desempacotar explicitamente (mais seguro que extract) ─────────────────────
$current_user_id            = $data['current_user_id'];
$feedItems                  = $data['feedItems'];
$notifications               = $data['notifications'];
$logged_in_user_data         = $data['logged_in_user_data'];
$user_data                   = $data['user_data'];
$logged_in_user_profile_pic  = $data['logged_in_user_profile_pic'];
$suggested_users             = $data['suggested_users'];
$recent_albums               = $data['recent_albums'];
$saved_ids                   = $data['saved_ids'];
$csrf_token                  = $data['csrf_token'];

// ── Dados extra para esta página ──────────────────────────────────────────────
$blocked_users = User::getBlockedUsers($pdo, $current_user_id);

// ── Render ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
require       __DIR__ . '/../includes/views/settings/settings.view.php';
require_once  __DIR__ . '/../includes/footer.php';
