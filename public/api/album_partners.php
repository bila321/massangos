<?php
// public/api/album_partners.php

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Models\AlbumPartner;
use Massango\Models\Album;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$userId = get_current_user_id();

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'add_partner':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }

            $albumId = (int)($_POST['album_id'] ?? 0);
            $partnerId = (int)($_POST['partner_id'] ?? 0);
            $percentage = (float)($_POST['percentage'] ?? 0);

            // Validar que o usuário é o dono do álbum
            $album = Album::getAlbumById($pdo, $albumId);
            if (!$album || $album['user_id'] != $userId) {
                throw new Exception('Você não tem permissão para adicionar parceiros a este álbum');
            }

            if ($percentage <= 0 || $percentage > 100) {
                throw new Exception('A percentagem deve estar entre 0 e 100');
            }

            if ($partnerId <= 0) {
                throw new Exception('ID do parceiro inválido');
            }

            $result = AlbumPartner::addPartner($pdo, $albumId, $partnerId, $percentage);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Parceiro adicionado com sucesso',
                    'partner_id' => $result
                ]);
            } else {
                throw new Exception('Erro ao adicionar parceiro');
            }
            break;

        case 'remove_partner':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }

            $partnerId = (int)($_POST['partner_id'] ?? 0);

            // Validar que o usuário é o dono do álbum
            $stmt = $pdo->prepare("
                SELECT ap.id, a.user_id 
                FROM album_partners ap
                JOIN albums a ON ap.album_id = a.id
                WHERE ap.id = ?
            ");
            $stmt->execute([$partnerId]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$partner || $partner['user_id'] != $userId) {
                throw new Exception('Você não tem permissão para remover este parceiro');
            }

            if (AlbumPartner::removePartner($pdo, $partnerId)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Parceiro removido com sucesso'
                ]);
            } else {
                throw new Exception('Erro ao remover parceiro');
            }
            break;

        case 'update_percentage':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }

            $partnerId = (int)($_POST['partner_id'] ?? 0);
            $percentage = (float)($_POST['percentage'] ?? 0);

            // Validar que o usuário é o dono do álbum
            $stmt = $pdo->prepare("
                SELECT ap.id, a.user_id 
                FROM album_partners ap
                JOIN albums a ON ap.album_id = a.id
                WHERE ap.id = ?
            ");
            $stmt->execute([$partnerId]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$partner || $partner['user_id'] != $userId) {
                throw new Exception('Você não tem permissão para atualizar este parceiro');
            }

            if ($percentage <= 0 || $percentage > 100) {
                throw new Exception('A percentagem deve estar entre 0 e 100');
            }

            if (AlbumPartner::updatePartnerPercentage($pdo, $partnerId, $percentage)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Percentagem atualizada com sucesso'
                ]);
            } else {
                throw new Exception('Erro ao atualizar percentagem');
            }
            break;

        case 'accept_partnership':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $partnerId = (int)($_POST['partner_id'] ?? 0);

            if ($partnerId <= 0) {
                throw new Exception('ID da parceria inválido');
            }

            // Validar que o usuário é o parceiro
            $stmt = $pdo->prepare("SELECT ap.id, ap.user_id, ap.album_id, a.name as album_name FROM album_partners ap JOIN albums a ON ap.album_id = a.id WHERE ap.id = ?");
            $stmt->execute([$partnerId]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$partner) {
                throw new Exception('Parceria não encontrada');
            }

            if ($partner['user_id'] != $userId) {
                throw new Exception('Você não tem permissão para aceitar esta parceria');
            }

            if (AlbumPartner::acceptPartnership($pdo, $partnerId)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Parceria aceita com sucesso',
                    'partner_id' => $partnerId,
                    'album_id' => $partner['album_id'],
                    'album_name' => $partner['album_name']
                ]);
            } else {
                throw new Exception('Erro ao aceitar parceria');
            }
            break;

        case 'reject_partnership':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $partnerId = (int)($_POST['partner_id'] ?? 0);

            if ($partnerId <= 0) {
                throw new Exception('ID da parceria inválido');
            }

            // Validar que o usuário é o parceiro
            $stmt = $pdo->prepare("SELECT ap.id, ap.user_id, ap.album_id, a.name as album_name FROM album_partners ap JOIN albums a ON ap.album_id = a.id WHERE ap.id = ?");
            $stmt->execute([$partnerId]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$partner) {
                throw new Exception('Parceria não encontrada');
            }

            if ($partner['user_id'] != $userId) {
                throw new Exception('Você não tem permissão para rejeitar esta parceria');
            }

            if (AlbumPartner::rejectPartnership($pdo, $partnerId)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Parceria rejeitada',
                    'partner_id' => $partnerId,
                    'album_id' => $partner['album_id'],
                    'album_name' => $partner['album_name']
                ]);
            } else {
                throw new Exception('Erro ao rejeitar parceria');
            }
            break;

        default:
            throw new Exception('Ação não reconhecida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
