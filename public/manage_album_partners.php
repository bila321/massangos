<?php

/**
 * public/manage_album_partners.php
 *
 * Ponto de entrada — bootstrap + Controller.
 *
 * Lógica de negócio → app/Services/AlbumPartnersPageService.php
 * Orquestração      → app/Controllers/AlbumPartnersPageController.php
 * Templates HTML    → includes/views/album_partners/
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';


SecurityManager::initSecurity();

(new \Massango\Controllers\AlbumPartnersPageController($pdo))->show();
