<?php
// ═══════════════════════════════════════════════════════════
// public/edit_post.php
// ═══════════════════════════════════════════════════════════
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';

(new \Massango\Controllers\EditController($pdo, 'post'))->handle();


// ═══════════════════════════════════════════════════════════
// public/edit_video.php  (ficheiro separado, mesmo conteúdo)
// ═══════════════════════════════════════════════════════════
// <?php
// define('SECURE_ACCESS', true);
// define('ENVIRONMENT', 'development');
// require_once __DIR__ . '/../app/bootstrap.php';
// (new \Massango\Controllers\EditController($pdo, 'video'))->handle();


// ═══════════════════════════════════════════════════════════
// public/edit_album.php  (ficheiro separado, mesmo conteúdo)
// ═══════════════════════════════════════════════════════════
// <?php
// define('SECURE_ACCESS', true);
// define('ENVIRONMENT', 'development');
// require_once __DIR__ . '/../app/bootstrap.php';
// (new \Massango\Controllers\EditController($pdo, 'album'))->handle();
