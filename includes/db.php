<?php
/**
 * includes/db.php
 * Conexão PDO centralizada para o projecto Massangos.
 *
 * Carregue este ficheiro logo após config.php:
 *   require_once __DIR__ . '/db.php';
 *
 * A variável $pdo fica disponível globalmente.
 */

defined('SECURE_ACCESS') or die('Acesso directo não permitido.');

/* ── Credenciais ────────────────────────────────────────────
   Defina estas constantes em config.php (ou num .env).
   Nunca escreva senhas aqui directamente em produção.
   ────────────────────────────────────────────────────────── */
if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_PORT'))    define('DB_PORT',    '3306');
if (!defined('DB_NAME'))    define('DB_NAME',    'amassangos');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');        // altere em produção
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/* ── DSN ────────────────────────────────────────────────── */
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    DB_HOST,
    DB_PORT,
    DB_NAME,
    DB_CHARSET
);

/* ── Opções PDO ─────────────────────────────────────────── */
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lança PDOException em erros
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // arrays associativos por defeito
    PDO::ATTR_EMULATE_PREPARES   => false,                     // prepared statements reais
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

/* ── Ligação ────────────────────────────────────────────── */
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
} catch (PDOException $e) {
    /* Em produção não exponha detalhes; registe no log do servidor */
    error_log('[Massangos DB] Falha na ligação: ' . $e->getMessage());

    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die('<pre style="color:red;font-family:monospace">
<b>Erro de base de dados (modo desenvolvimento)</b>
' . htmlspecialchars($e->getMessage()) . '
</pre>');
    }

    /* Resposta genérica para o utilizador em produção */
    http_response_code(503);
    die('Serviço temporariamente indisponível. Tente novamente mais tarde.');
}

/* ─────────────────────────────────────────────────────────
   TABELA AUXILIAR: auth_carousel_slides
   ─────────────────────────────────────────────────────────
   Cria automaticamente a tabela se ainda não existir.
   Remova este bloco depois da primeira execução se preferir
   gerir o schema manualmente via migrations.
   ────────────────────────────────────────────────────────── */
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `auth_carousel_slides` (
            `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `title`       VARCHAR(255)     NOT NULL DEFAULT '',
            `subtitle`    TEXT             DEFAULT NULL,
            `image_url`   VARCHAR(512)     DEFAULT NULL   COMMENT 'URL pública da imagem (pode ficar vazio para usar gradiente)',
            `cta_text`    VARCHAR(100)     DEFAULT NULL   COMMENT 'Texto do botão CTA (opcional)',
            `sort_order`  TINYINT(3)       NOT NULL DEFAULT 0,
            `is_active`   TINYINT(1)       NOT NULL DEFAULT 1,
            `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_active_order` (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Slides do carrossel nas páginas de autenticação';
    ");

    /* Insere slides de exemplo se a tabela estiver vazia */
    $count = (int) $pdo->query("SELECT COUNT(*) FROM auth_carousel_slides")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("
            INSERT INTO `auth_carousel_slides`
                (`title`, `subtitle`, `image_url`, `sort_order`, `is_active`)
            VALUES
                ('A rede social que <span>te conecta</span>',
                 'Partilhe momentos, descubra conteúdos e fique mais perto de quem importa.',
                 '', 1, 1),
                ('Monetize o seu <span>conteúdo</span>',
                 'Defina preços, venda acesso premium e receba via M-Pesa ou e-Mola.',
                 '', 2, 1),
                ('Cresça a sua <span>audiência</span>',
                 'Sistema de estrelas que impulsiona os criadores mais activos da plataforma.',
                 '', 3, 1);
        ");
    }
} catch (PDOException $e) {
    /* Não bloqueia o arranque — apenas regista */
    error_log('[Massangos DB] Falha ao criar/popular auth_carousel_slides: ' . $e->getMessage());
}
