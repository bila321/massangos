<?php

namespace Massango\Controllers;

use Massango\Services\WalletService;
use Massango\Core\Auth;
use PDO;

/**
 * WalletController
 *
 * Responsabilidade única: autenticação, orquestração do Service,
 * e preparação das variáveis para a view. Sem HTML, sem SQL direto.
 */
class WalletController
{
    private WalletService $walletService;

    public function __construct(PDO $pdo)
    {
        $this->walletService = new WalletService($pdo);
    }

    /**
     * Carrega todos os dados necessários para a view da carteira.
     * Garante autenticação antes de qualquer acesso a dados.
     *
     * @return array Dados prontos para a view (nunca contém lógica de apresentação)
     */
    public function load(): array
    {
        Auth::requireAuth();

        $userId = Auth::id();

        $summary      = $this->walletService->getSummary($userId);
        $transactions = $this->walletService->getTransactions($userId, limit: 20);

        return [
            'balance'            => $summary['balance'],
            'earned'             => $summary['earned'],
            'spent'              => $summary['spent'],
            'partner_revenue'    => $summary['partner_revenue'],
            'total_transactions' => $summary['total_transactions'],
            'transactions'       => $transactions,
        ];
    }
}
