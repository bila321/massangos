<?php

namespace Massango\Controllers;

use Massango\Models\SalesReport;
use PDO;

/**
 * PartnerSalesDashboardController
 *
 * Responsabilidade única: orquestrar o Model SalesReport para o
 * dashboard de vendas de um parceiro. Não faz SQL directo.
 */
class PartnerSalesDashboardController
{
    private PDO $pdo;

    /** Períodos válidos aceites no filtro do dashboard. */
    private const VALID_PERIODS = ['all', 'week', 'month', 'year'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Carrega todos os dados do dashboard para o utilizador e período dados.
     *
     * @param int    $userId
     * @param string $period 'all' | 'week' | 'month' | 'year' (qualquer outro valor cai em 'all')
     */
    public function load(int $userId, string $period): array
    {
        $period = $this->sanitizePeriod($period);

        return [
            'period'        => $period,
            'stats'         => SalesReport::getPartnerSalesStats($this->pdo, $userId, $period),
            'sales_report'  => SalesReport::getPartnerSalesReport($this->pdo, $userId, 20),
            'top_albums'    => SalesReport::getTopAlbumsForPartner($this->pdo, $userId, 5),
            'top_creators'  => SalesReport::getTopCreatorsForPartner($this->pdo, $userId, 5),
        ];
    }

    /**
     * Valida o período recebido via query string. Evita passar
     * input arbitrário do utilizador directamente para o Model.
     */
    private function sanitizePeriod(string $period): string
    {
        return in_array($period, self::VALID_PERIODS, true) ? $period : 'all';
    }
}
