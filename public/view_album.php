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

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

if (session_status() === PHP_SESSION_NONE) session_start();


(new \Massango\Controllers\AlbumViewController($pdo))->show();
