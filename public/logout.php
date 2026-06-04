<?php
// C:\xampp\htdocs\massangos\public\logout.php

// Inicia a sessão no início (importante para session_status())
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclua config.php para ter BASE_URL
require_once __DIR__ . '/../includes/config.php';

// Inclua functions.php para ter redirect() e set_message() (se usar)
require_once __DIR__ . '/../includes/functions.php';

// Inclua auth.php para ter a função logout()
require_once __DIR__ . '/../includes/auth.php';

// Chama a função de logout
logout();

// O 'exit;' dentro de logout() já cuidará do resto.
