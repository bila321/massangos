<?php

namespace Massango\Services;

use PDO;

/**
 * WalletService
 *
 * Responsabilidade única: acesso a dados financeiros do utilizador.
 * Sem HTML, sem sessão, sem headers — só queries e lógica de negócio.
 */
class WalletService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Carrega o resumo financeiro do utilizador (saldo + totais).
     */
    public function getSummary(int $userId): array
    {
        // ── Saldo actual ──────────────────────────────────────────────────────
        $stmt = $this->pdo->prepare(
            "SELECT balance FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $balance = (float) ($stmt->fetchColumn() ?? 0.0);

        // ── Total ganho (como vendedor) ───────────────────────────────────────
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(seller_amount), 0)
             FROM sales
             WHERE seller_id = ? AND status = 'completed'"
        );
        $stmt->execute([$userId]);
        $earned = (float) $stmt->fetchColumn();

        // ── Total gasto (como comprador) ──────────────────────────────────────
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM sales
             WHERE buyer_id = ? AND status = 'completed'"
        );
        $stmt->execute([$userId]);
        $spent = (float) $stmt->fetchColumn();

        // ── Revenue de parcerias ──────────────────────────────────────────────
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM revenue_distributions
             WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $partnerRevenue = (float) $stmt->fetchColumn();

        // ── Contagem total de transações ──────────────────────────────────────
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM sales
             WHERE buyer_id = ? OR seller_id = ?"
        );
        $stmt->execute([$userId, $userId]);
        $totalTransactions = (int) $stmt->fetchColumn();

        return [
            'balance'            => $balance,
            'earned'             => $earned,
            'spent'              => $spent,
            'partner_revenue'    => $partnerRevenue,
            'total_transactions' => $totalTransactions,
        ];
    }

    /**
     * Carrega o histórico paginado de transações.
     *
     * @param int $userId
     * @param int $limit  Número de registos a devolver (default: 20)
     * @param int $offset Offset para paginação futura (default: 0)
     */
    public function getTransactions(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                s.id,
                s.created_at,
                s.content_type,
                s.amount,
                s.seller_amount,
                s.status,
                s.payment_method,
                s.transaction_id,
                CASE WHEN s.buyer_id = :uid1 THEN 'Compra' ELSE 'Venda' END AS tipo,
                CASE WHEN s.buyer_id = :uid2 THEN s.amount ELSE s.seller_amount END AS valor_display,
                u_outro.username AS outro_user
            FROM sales s
            LEFT JOIN users u_outro ON (
                CASE
                    WHEN s.buyer_id = :uid3 THEN u_outro.id = s.seller_id
                    ELSE u_outro.id = s.buyer_id
                END
            )
            WHERE s.buyer_id = :uid4 OR s.seller_id = :uid5
            ORDER BY s.created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':uid1',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid3',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid4',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid5',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
