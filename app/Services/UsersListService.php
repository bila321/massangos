<?php
declare(strict_types=1);

namespace Massango\Services;

use PDO;
use PDOException;

/**
 * UsersListService
 *
 * Encapsula a busca de utilizadores em destaque (ranking por estrelas).
 * Não emite HTML nem headers.
 */
class UsersListService
{
    public const LIMIT = 50;

    public function __construct(private PDO $pdo) {}

    /**
     * Devolve os utilizadores com mais estrelas, ou array vazio em caso de erro.
     */
    public function getTopUsers(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, username, profile_picture, stars
                 FROM users
                 ORDER BY stars DESC
                 LIMIT " . self::LIMIT
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[UsersListService] Erro ao buscar utilizadores: ' . $e->getMessage());
            return [];
        }
    }
}
