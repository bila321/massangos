<?php
// includes/auth.php

// ── Guarda contra inclusão múltipla ───────────────────────────────────────
// Resolve: "Fatal error: Cannot redeclare get_current_username()"
// causado pelo auth.php ser incluído via index.php E via header.php
if (defined('AUTH_PHP_LOADED')) {
    return;
}
define('AUTH_PHP_LOADED', true);
// ─────────────────────────────────────────────────────────────────────────

function get_current_username(): string
{
    if (is_logged_in()) {
        return $_SESSION['username'] ?? 'Convidado';
    }
    return 'Convidado';
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_message("Você precisa fazer login para acessar esta página.", "warning");
        redirect(BASE_URL . 'login.php');
    }
}

function logout(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    session_unset();
    session_destroy();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 142000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    redirect(BASE_URL . 'login.php');
    exit;
}
