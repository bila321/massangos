<?php
declare(strict_types=1);

namespace Massango\Services;

use DateTime;
use DateInterval;
use DatePeriod;
use PDO;

/**
 * SalesPerformanceService
 *
 * Encapsula todas as queries e transformações de dados
 * da página de performance de vendas.
 * Não emite HTML nem headers.
 */
class SalesPerformanceService
{
    public const ALLOWED_PERIODS = ['all', '7', '30', '90'];

    public function __construct(private PDO $pdo) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Ponto de entrada
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Carrega todos os dados necessários para a view.
     *
     * @return array{
     *   stats: array,
     *   chart_days: array,
     *   chart_revenue: array,
     *   chart_counts: array,
     *   item_sales: array,
     *   max_sales: int,
     *   top_buyer: array|false,
     *   commission_rate: float
     * }
     */
    public function load(
        int     $user_id,
        string  $period,
        ?string $filter_type,
        ?int    $filter_id
    ): array {
        [$period_sql, $period_params] = $this->buildPeriodClause($period);
        [$extra_sql,  $extra_params]  = $this->buildItemFilter($filter_type, $filter_id);

        $p_main = array_merge([$user_id], $period_params, $extra_params);

        $stats       = $this->fetchStats($p_main, $period_sql, $extra_sql);
        $chart       = $this->fetchChartData($user_id);
        $item_sales  = $this->fetchItemSales($p_main, $period_sql, $extra_sql);
        $max_sales   = $this->calcMaxSales($item_sales);
        $top_buyer   = $this->fetchTopBuyer($user_id, $period_sql, $period_params);
        $commission_rate = (float)PricingRuleService::getSetting($this->pdo, 'commission_rate', 15);

        return [
            'stats'           => $stats,
            'chart_days'      => $chart['days'],
            'chart_revenue'   => $chart['revenue'],
            'chart_counts'    => $chart['counts'],
            'item_sales'      => $item_sales,
            'max_sales'       => $max_sales,
            'top_buyer'       => $top_buyer,
            'commission_rate' => $commission_rate,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Queries privadas
    // ─────────────────────────────────────────────────────────────────────────

    private function fetchStats(array $params, string $period_sql, string $extra_sql): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*)                           AS total_sales,
                COALESCE(SUM(amount),0)            AS total_gross,
                COALESCE(SUM(seller_amount),0)     AS total_net,
                COALESCE(SUM(commission_amount),0) AS total_commission,
                COUNT(DISTINCT content_id)         AS unique_items,
                COUNT(DISTINCT buyer_id)           AS unique_buyers
            FROM sales s
            WHERE seller_id = ? AND status = 'completed'
            $period_sql $extra_sql
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function fetchChartData(int $user_id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(created_at) AS day,
                   COUNT(*)                        AS cnt,
                   COALESCE(SUM(seller_amount), 0) AS revenue
            FROM sales
            WHERE seller_id = ? AND status = 'completed'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY day ASC
        ");
        $stmt->execute([$user_id]);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = array_column($raw, null, 'day');

        $days    = [];
        $revenue = [];
        $counts  = [];

        $range = new DatePeriod(
            new DateTime('-29 days'),
            new DateInterval('P1D'),
            (new DateTime('today'))->modify('+1 day')
        );

        foreach ($range as $dt) {
            $k         = $dt->format('Y-m-d');
            $days[]    = $dt->format('d/m');
            $revenue[] = isset($map[$k]) ? (float)$map[$k]['revenue'] : 0.0;
            $counts[]  = isset($map[$k]) ? (int)$map[$k]['cnt']     : 0;
        }

        return compact('days', 'revenue', 'counts');
    }

    private function fetchItemSales(array $params, string $period_sql, string $extra_sql): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                s.content_type,
                s.content_id,
                COUNT(*)                         AS sales_count,
                COALESCE(SUM(s.amount),0)        AS item_gross,
                COALESCE(SUM(s.seller_amount),0) AS item_net,
                MAX(s.created_at)                AS last_sale
            FROM sales s
            WHERE s.seller_id = ? AND s.status = 'completed'
            $period_sql $extra_sql
            GROUP BY s.content_type, s.content_id
            ORDER BY sales_count DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enriquecer com título, preço e estado de aprovação
        foreach ($rows as &$row) {
            $meta = $this->fetchItemMeta($row['content_type'], (int)$row['content_id']);
            $row['title']       = $meta['title']       ?? 'Item Removido';
            $row['price']       = (float)($meta['price'] ?? 0);
            $row['is_approved'] = (int)($meta['is_approved'] ?? 1);
        }
        unset($row);

        return $rows;
    }

    private function fetchItemMeta(string $type, int $id): array|false
    {
        $sql = match ($type) {
            'video' => "SELECT caption AS title, price, is_approved FROM videos WHERE id = ?",
            'album' => "SELECT name    AS title, price, is_approved FROM albums WHERE id = ?",
            default => "SELECT SUBSTRING(content,1,80) AS title, price, 1 AS is_approved FROM posts WHERE id = ?",
        };
        $s = $this->pdo->prepare($sql);
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC);
    }

    private function fetchTopBuyer(int $user_id, string $period_sql, array $period_params): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT u.username, COUNT(*) AS cnt
            FROM sales s
            JOIN users u ON u.id = s.buyer_id
            WHERE s.seller_id = ? AND s.status = 'completed' $period_sql
            GROUP BY s.buyer_id
            ORDER BY cnt DESC
            LIMIT 1
        ");
        $stmt->execute(array_merge([$user_id], $period_params));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{0: string, 1: array} */
    private function buildPeriodClause(string $period): array
    {
        if (in_array($period, ['7', '30', '90'], true)) {
            return [
                " AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [(int)$period],
            ];
        }
        return ['', []];
    }

    /** @return array{0: string, 1: array} */
    private function buildItemFilter(?string $type, ?int $id): array
    {
        if ($type && $id) {
            return [" AND content_type = ? AND content_id = ?", [$type, $id]];
        }
        return ['', []];
    }

    private function calcMaxSales(array $item_sales): int
    {
        $max = 1;
        foreach ($item_sales as $item) {
            if ((int)$item['sales_count'] > $max) {
                $max = (int)$item['sales_count'];
            }
        }
        return $max;
    }
}
