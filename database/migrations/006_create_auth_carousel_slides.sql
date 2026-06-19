-- 006_create_auth_carousel_slides.sql
-- Extraido de includes/db.php (estava a correr em CADA request).
-- Executar uma unica vez via phpMyAdmin ou: mysql -u root amassangos < 006_create_auth_carousel_slides.sql

CREATE TABLE IF NOT EXISTS `auth_carousel_slides` (
    `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)     NOT NULL DEFAULT '',
    `subtitle`    TEXT             DEFAULT NULL,
    `image_url`   VARCHAR(512)     DEFAULT NULL,
    `cta_text`    VARCHAR(100)     DEFAULT NULL,
    `sort_order`  TINYINT(3)       NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Slides do carrossel nas paginas de autenticacao';

INSERT INTO `auth_carousel_slides` (`title`, `subtitle`, `image_url`, `sort_order`, `is_active`)
SELECT * FROM (SELECT
    'A rede social que <span>te conecta</span>' AS title,
    'Partilhe momentos, descubra conteudos e fique mais perto de quem importa.' AS subtitle,
    '' AS image_url, 1 AS sort_order, 1 AS is_active
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `auth_carousel_slides`) LIMIT 1;
