<?php
/**
 * app/bootstrap.php
 *
 * Ponto único de inicialização da aplicação.
 * Substitui a sequência de requires repetida em cada entry point de public/.
 *
 * USO em qualquer ficheiro de public/:
 *   define('SECURE_ACCESS', true);
 *   require_once __DIR__ . '/../app/bootstrap.php';
 *
 * Depois disto, $pdo, autoload, sessão e segurança já estão prontos.
 * Não duplicar require_once de config.php/db.php/security.php nos entry points.
 */

defined('SECURE_ACCESS') or die('Acesso directo não permitido.');

// ── Guarda contra bootstrap duplicado (ex: incluído por header + index) ──
if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

$root = dirname(__DIR__);

// ── 1. Autoload (Composer + classes Massango\*) ──────────────────────────
require_once $root . '/vendor/autoload.php';

// ── 2. Configuração (constantes ENVIRONMENT, BASE_URL, etc.) ─────────────
require_once $root . '/includes/config.php';

// ── 3. Sessão (antes de qualquer output) ──────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 4. Base de dados ──────────────────────────────────────────────────────
// db.php ainda cria a variável global $pdo (legado). Mantemos por
// retrocompatibilidade enquanto o resto do código não for migrado,
// mas registamos a MESMA instância no Database::class para que o
// código novo (Controllers/Services) use sempre Database::getInstance()
// e nunca abra uma segunda ligação.
require_once $root . '/includes/db.php'; // cria $pdo
\Massango\Core\Database::setInstance($pdo);

// ── 5. Helpers globais legados (funções soltas) ──────────────────────────
require_once $root . '/includes/functions.php';
require_once $root . '/includes/security.php';
require_once $root . '/includes/auth.php';
require_once $root . '/includes/adult-content-helper.php';

\SecurityManager::initSecurity();

// ── 6. Helpers de autenticação para o código novo ─────────────────────────
// Disponibiliza o user_id autenticado de forma tipada para Controllers.
function current_user_id(): ?int
{
    return \Massango\Core\Auth::id();
}
