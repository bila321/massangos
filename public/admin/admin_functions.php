<?php
// public/admin/admin_functions.php

// Definir constante de acesso seguro para permitir inclusão de arquivos protegidos
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Verifica se o usuário logado é um administrador.
 */
function check_admin_access() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Se não estiver logado, redireciona para o login do admin
    if (!isset($_SESSION['admin_id'])) {
        // Se estiver logado como user comum mas não como admin, verifica se tem cargo admin
        if (isset($_SESSION['user_id'])) {
            $pdo = \Massango\Core\Database::getInstance();
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $role = $stmt->fetchColumn();
            
            if ($role === 'admin' || $role === 'superadmin') {
                $_SESSION['admin_id'] = $_SESSION['user_id'];
                $_SESSION['admin_role'] = $role;
                return true;
            }
        }
        
        header("Location: login.php");
        exit();
    }

    return true;
}

/**
 * Obtém estatísticas detalhadas.
 */
function get_detailed_stats($pdo) {
    $stats = [];
    try {
        $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
        $stats['users_today'] = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
        $stats['total_posts'] = $pdo->query("SELECT COUNT(*) FROM feed_items")->fetchColumn() ?: 0;
        $stats['total_videos'] = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn() ?: 0;
        $stats['total_albums'] = $pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn() ?: 0;
        $stats['pending_approval'] = (
            ($pdo->query("SELECT COUNT(*) FROM posts WHERE is_approved = 0")->fetchColumn() ?: 0) +
            ($pdo->query("SELECT COUNT(*) FROM videos WHERE is_approved = 0")->fetchColumn() ?: 0) +
            ($pdo->query("SELECT COUNT(*) FROM albums WHERE is_approved = 0")->fetchColumn() ?: 0)
        );
        
        // Verificar se a tabela sales existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'sales'")->rowCount();
        if ($tableCheck > 0) {
            $stats['total_sales'] = $pdo->query("SELECT COUNT(*) FROM sales WHERE status = 'completed'")->fetchColumn() ?: 0;
            $stats['total_revenue'] = $pdo->query("SELECT SUM(amount) FROM sales WHERE status = 'completed'")->fetchColumn() ?: 0;
            $stats['total_commission'] = $pdo->query("SELECT SUM(commission_amount) FROM sales WHERE status = 'completed'")->fetchColumn() ?: 0;
            $stats['sales_today'] = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetchColumn() ?: 0;
            $stats['revenue_month'] = $pdo->query("SELECT SUM(amount) FROM sales WHERE MONTH(created_at) = MONTH(CURDATE()) AND status = 'completed'")->fetchColumn() ?: 0;
            $stats['commission_month'] = $pdo->query("SELECT SUM(commission_amount) FROM sales WHERE MONTH(created_at) = MONTH(CURDATE()) AND status = 'completed'")->fetchColumn() ?: 0;
        } else {
            $stats['total_sales'] = 0;
            $stats['total_revenue'] = 0;
            $stats['total_commission'] = 0;
            $stats['sales_today'] = 0;
            $stats['revenue_month'] = 0;
            $stats['commission_month'] = 0;
        }
    } catch (Exception $e) {
        return [
            'total_users' => 0, 'total_posts' => 0, 'total_videos' => 0, 'total_albums' => 0,
            'total_sales' => 0, 'total_revenue' => 0, 'total_commission' => 0,
            'users_today' => 0, 'sales_today' => 0, 'revenue_month' => 0, 'commission_month' => 0
        ];
    }
    return $stats;
}

/**
 * Obtém dados para o gráfico de vendas dos últimos 7 dias.
 */
function get_sales_chart_data($pdo) {
    $data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $label = date('d/m', strtotime($date));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM sales WHERE DATE(created_at) = ? AND status = 'completed'");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $data[] = [
            'label' => $label,
            'count' => $row['count'] ?: 0,
            'total' => $row['total'] ?: 0
        ];
    }
    return $data;
}
?>