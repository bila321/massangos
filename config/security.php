<?php
define('PASSWORD_RESET_EXPIRY', 3600);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600);
define('SESSION_REGENERATION_INTERVAL', 300);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);
define('RATE_LIMIT_MAX_ATTEMPTS', 10);
define('RATE_LIMIT_TIME_WINDOW', 60);
define('SECURITY_SALT', 'm4ss4ng0_s3cur3_54lt_2024_!@#');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
