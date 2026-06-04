<?php
define('APP_ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/massangos/public/');
define('UPLOAD_URL', BASE_URL . 'media-proxy.php?file=');
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
define('MAX_FILE_COUNT_ALBUM', 10);
define('MAX_POST_LENGTH', 5000);
define('MAX_COMMENT_LENGTH', 1000);
define('MAX_BIO_LENGTH', 500);
define('MIN_PASSWORD_LENGTH', 8);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/ogg']);
define('FFMPEG_PATH', 'C:\ffmpeg\bin\ffmpeg.exe');
define('FFPROBE_PATH', 'C:\ffmpeg\bin\ffprobe.exe');
date_default_timezone_set('Africa/Maputo');