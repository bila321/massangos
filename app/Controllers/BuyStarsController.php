<?php
// app/Controllers/BuyStarsController.php

namespace Massango\Controllers;

class BuyStarsController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function handle(): void
    {
        if (!is_logged_in()) {
            redirect(BASE_URL . 'login.php');
        }

        $user_id = (int)get_current_user_id();

        $stmt = $this->pdo->prepare(
            "SELECT stars, stars_expiration FROM users WHERE id = ?"
        );
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Agrupar preços por nível de estrelas: [ stars => [monthly => row, yearly => row] ]
        $stmt = $this->pdo->prepare(
            "SELECT * FROM star_prices ORDER BY stars ASC, duration_type ASC"
        );
        $stmt->execute();
        $raw = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $star_packages = [];
        foreach ($raw as $row) {
            $star_packages[$row['stars']][$row['duration_type']] = $row;
        }

        require_once __DIR__ . '/../../includes/header.php';
        require __DIR__ . '/../../includes/views/checkout/buy_stars.view.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }
}
