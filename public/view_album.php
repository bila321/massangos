<?php
/**
 * public/view_album.php
 *
 * Ponto de entrada para a página de visualização de um álbum.
 * Responsabilidade única: bootstrap e delegar ao Controller.
 *
 * Toda a lógica está em:
 *   app/Controllers/AlbumViewController.php  → orquestração
 *   app/Services/AlbumViewService.php        → negócio / dados
 *   includes/views/album/                    → templates HTML
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../vendor/autoload.php';

(new \Massango\Controllers\AlbumViewController($pdo))->show();
