-- Adicionar colunas para expira├º├úo de estrelas na tabela users
ALTER TABLE `users` ADD COLUMN `stars_expiration` DATETIME DEFAULT NULL AFTER `stars`;

-- Criar tabela para os pre├ºos das estrelas
CREATE TABLE IF NOT EXISTS `star_prices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stars` INT NOT NULL,
    `duration_type` ENUM('monthly', 'yearly') NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_star_duration` (`stars`, `duration_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inserir pre├ºos padr├úo
INSERT IGNORE INTO `star_prices` (`stars`, `duration_type`, `price`) VALUES
(1, 'monthly', 100.00),
(1, 'yearly', 1000.00),
(2, 'monthly', 250.00),
(2, 'yearly', 2500.00),
(3, 'monthly', 500.00),
(3, 'yearly', 5000.00),
(4, 'monthly', 1000.00),
(4, 'yearly', 10000.00),
(5, 'monthly', 2500.00),
(5, 'yearly', 25000.00);
