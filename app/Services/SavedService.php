<?php
declare(strict_types=1);

namespace Massango\Services;

use PDO;

/**
 * SavedService
 *
 * Encapsula todas as queries e a lógica de negócio da página de guardados.
 * Não emite HTML nem headers.
 *
 * ============================================================
 * FIX v3 (definitivo): SELECT dinâmico baseado em colunas reais.
 *
 * O bug v2 assumia nomes de coluna fixos (video_path, duration, views,
 * width, height) que podiam não existir — causando PDOException
 * "Unknown column 'p.duration' in 'field list'".
 *
 * Agora, a query é construída DINAMICAMENTE:
 *   1. Antes de montar o SELECT, fazemos SHOW COLUMNS das tabelas
 *      videos, posts, albums, album_photos (uma única vez, com cache).
 *   2. Só incluímos no SELECT as colunas que realmente existem.
 *   3. Os aliases canónicos (video_url, video_duration, etc.) são
 *      aplicados quando a coluna existe; quando não existe, o alias
 *      fica como NULL via `NULL AS alias` para que os helpers PHP
 *      (itemVideoUrl, itemDuration, etc.) não gerem warnings.
 *
 * Isto torna o código ROBUSTO a qualquer schema, mesmo se o DB
 * usar nomes diferentes (file_url em vez de video_path, etc.).
 *
 * Para renomear uma coluna "descoberta" para o alias canónico,
 * basta adicionar um mapeamento em $column_aliases abaixo.
 * ============================================================
 */
class SavedService
{
    // Tipos permitidos como filtro
    public const ALLOWED_FILTERS = ['all', 'post', 'video', 'album', 'photo', 'reel'];

    // Itens por página
    public const PER_PAGE = 24;

    public function __construct(private PDO $pdo) {}

    /**
     * Cache de colunas por tabela (evita SHOW COLUMNS repetido na mesma request).
     * @var array<string, array<string,bool>>
     */
    private array $_columnsCache = [];

    /**
     * Mapeamento de colunas "descobertas" para aliases canónicos.
     *
     * Chave = nome real da coluna no DB (qualquer tabela).
     * Valor = lista de aliases canónicos que devem receber esta coluna.
     *
     * Se uma coluna do DB casar com um dos padrões, ela é usada.
     * Isto permite que, por exemplo, a tabela `videos` use `video_path`
     * OU `file_url` OU `video_file` — todos mapeados para `video_url`.
     */
    private const VIDEO_URL_COLS = ['video_path', 'video_url', 'file_url', 'file_path', 'video_file', 'video_src'];
    private const DURATION_COLS = ['duration', 'video_duration', 'length', 'length_seconds'];
    private const VIEWS_COLS    = ['views', 'views_count', 'view_count'];
    private const WIDTH_COLS    = ['width', 'video_width'];
    private const HEIGHT_COLS   = ['height', 'video_height'];

    // ─────────────────────────────────────────────────────────────────────────
    // Carregar página de guardados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{items: array, total: int, total_pages: int, filter: string, page: int, ai_analysis_map: array}
     */
    public function load(int $user_id, string $filter, int $page): array
    {
        $use_filter = ($filter !== 'all');
        $offset     = ($page - 1) * self::PER_PAGE;

        $items       = $this->fetchItems($user_id, $use_filter, $filter, self::PER_PAGE, $offset);
        $total       = $this->countItems($user_id, $use_filter, $filter);
        $total_pages = (int)ceil($total / self::PER_PAGE);
        $ai_map      = $this->fetchAiAnalysisMap($items);

        return compact('items', 'total', 'total_pages', 'filter', 'page', 'ai_map');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Detetor de schema (FIX v3)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna a lista de colunas de uma tabela, com cache.
     * @return array<string,bool> colunas existentes (chave=nome, valor=true)
     */
    private function tableColumns(string $table): array
    {
        if (isset($this->_columnsCache[$table])) {
            return $this->_columnsCache[$table];
        }

        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `$table`");
            $stmt->execute();
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[strtolower($row['Field'])] = true;
            }
            $this->_columnsCache[$table] = $cols;
            return $cols;
        } catch (\Throwable $e) {
            // Tabela não existe — retornar vazio
            $this->_columnsCache[$table] = [];
            return [];
        }
    }

    /**
     * Encontra a primeira coluna existente numa tabela, dado um conjunto de candidatos.
     * @param string[] $candidates nomes possíveis (lowercase)
     * @return string|null nome real da coluna, ou null se nenhuma existir
     */
    private function findColumn(string $table, array $candidates): ?string
    {
        $cols = $this->tableColumns($table);
        foreach ($candidates as $c) {
            if (isset($cols[strtolower($c)])) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Constrói uma expressão SQL `coluna AS alias` ou `NULL AS alias`
     * consoante a coluna exista ou não.
     */
    private function colOrNull(string $table, array $candidates, string $alias): string
    {
        $col = $this->findColumn($table, $candidates);
        return $col !== null
            ? "`$table`.`$col` AS `$alias`"
            : "NULL AS `$alias`";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Queries privadas
    // ─────────────────────────────────────────────────────────────────────────

    private function fetchItems(
        int $user_id,
        bool $use_filter,
        string $filter,
        int $limit,
        int $offset
    ): array {
        $type_clause = $use_filter ? 'AND sp.item_type = :item_type' : '';

        // ============================================================
        // FIX v3: Construir SELECT dinamicamente, usando apenas colunas
        // que realmente existem nas tabelas. Isto elimina o erro
        // "Unknown column 'p.duration'".
        // ============================================================

        // ── Colunas da tabela posts (reels vivem aqui) ──
        $post_image_col       = $this->findColumn('posts', ['image_path', 'image', 'image_url']);
        $post_video_thumb_col = $this->findColumn('posts', ['video_thumbnail_path', 'video_thumb', 'thumbnail_path']);
        $post_content_col     = $this->findColumn('posts', ['content', 'caption', 'description', 'text']);
        $post_price_col       = $this->findColumn('posts', ['price']);
        $post_for_sale_col    = $this->findColumn('posts', ['is_for_sale', 'for_sale', 'paid']);
        $post_type_col        = $this->findColumn('posts', ['post_type', 'type']);
        $post_video_url_col   = $this->findColumn('posts', self::VIDEO_URL_COLS);
        $post_duration_col    = $this->findColumn('posts', self::DURATION_COLS);
        $post_views_col       = $this->findColumn('posts', self::VIEWS_COLS);

        // ── Colunas da tabela videos ──
        $video_thumb_col    = $this->findColumn('videos', ['thumbnail_path', 'thumb', 'thumbnail', 'poster']);
        $video_caption_col  = $this->findColumn('videos', ['caption', 'title', 'description']);
        $video_price_col    = $this->findColumn('videos', ['price']);
        $video_for_sale_col = $this->findColumn('videos', ['is_for_sale', 'for_sale', 'paid']);
        $video_url_col      = $this->findColumn('videos', self::VIDEO_URL_COLS);
        $video_duration_col = $this->findColumn('videos', self::DURATION_COLS);
        $video_views_col    = $this->findColumn('videos', self::VIEWS_COLS);
        $video_width_col    = $this->findColumn('videos', self::WIDTH_COLS);
        $video_height_col   = $this->findColumn('videos', self::HEIGHT_COLS);

        // ── Colunas da tabela albums ──
        $album_thumb_col    = $this->findColumn('albums', ['thumbnail_path', 'thumb', 'thumbnail']);
        $album_cover_col    = $this->findColumn('albums', ['cover_photo_url', 'cover_photo', 'cover']);
        $album_name_col     = $this->findColumn('albums', ['name', 'title']);
        $album_price_col    = $this->findColumn('albums', ['price']);
        $album_for_sale_col = $this->findColumn('albums', ['is_for_sale', 'for_sale', 'paid']);

        // ── Colunas da tabela album_photos ──
        $photo_thumb_col   = $this->findColumn('album_photos', ['thumbnail_path', 'thumb', 'thumbnail']);
        $photo_path_col    = $this->findColumn('album_photos', ['photo_path', 'image_path', 'image', 'image_url', 'file_url', 'file_path']);
        $photo_caption_col = $this->findColumn('album_photos', ['caption', 'description', 'title']);
        $photo_album_id_col = $this->findColumn('album_photos', ['album_id', 'album']);

        // ============================================================
        // Construir lista de colunas do SELECT
        // ============================================================
        $selectParts = [
            'sp.id           AS save_id',
            'sp.item_type',
            'sp.item_id',
            'sp.created_at   AS saved_at',
        ];

        // Posts / Reels
        $selectParts[] = $post_image_col       !== null ? "p.`$post_image_col`       AS post_thumb"       : "NULL AS post_thumb";
        $selectParts[] = $post_video_thumb_col !== null ? "p.`$post_video_thumb_col` AS post_video_thumb" : "NULL AS post_video_thumb";
        $selectParts[] = $post_content_col     !== null ? "p.`$post_content_col`     AS post_content"     : "NULL AS post_content";
        $selectParts[] = $post_price_col       !== null ? "p.`$post_price_col`       AS post_price"       : "NULL AS post_price";
        $selectParts[] = $post_for_sale_col    !== null ? "p.`$post_for_sale_col`    AS post_for_sale"    : "NULL AS post_for_sale";
        $selectParts[] = $post_type_col        !== null ? "p.`$post_type_col`        AS post_type"        : "NULL AS post_type";
        $selectParts[] = $post_video_url_col   !== null ? "p.`$post_video_url_col`   AS post_video_url"   : "NULL AS post_video_url";
        $selectParts[] = $post_duration_col    !== null ? "p.`$post_duration_col`    AS post_duration"    : "NULL AS post_duration";
        $selectParts[] = $post_views_col       !== null ? "p.`$post_views_col`       AS post_views"       : "NULL AS post_views";

        // Videos
        $selectParts[] = $video_thumb_col    !== null ? "v.`$video_thumb_col`    AS video_thumb"    : "NULL AS video_thumb";
        $selectParts[] = $video_caption_col  !== null ? "v.`$video_caption_col`  AS video_caption"  : "NULL AS video_caption";
        $selectParts[] = $video_price_col    !== null ? "v.`$video_price_col`    AS video_price"    : "NULL AS video_price";
        $selectParts[] = $video_for_sale_col !== null ? "v.`$video_for_sale_col` AS video_for_sale" : "NULL AS video_for_sale";
        $selectParts[] = $video_url_col      !== null ? "v.`$video_url_col`      AS video_url"      : "NULL AS video_url";
        $selectParts[] = $video_duration_col !== null ? "v.`$video_duration_col` AS video_duration" : "NULL AS video_duration";
        $selectParts[] = $video_views_col    !== null ? "v.`$video_views_col`    AS video_views"    : "NULL AS video_views";
        $selectParts[] = $video_width_col    !== null ? "v.`$video_width_col`    AS video_width"    : "NULL AS video_width";
        $selectParts[] = $video_height_col   !== null ? "v.`$video_height_col`   AS video_height"   : "NULL AS video_height";

        // Albums
        $selectParts[] = $album_thumb_col    !== null ? "a.`$album_thumb_col`    AS album_thumb"    : "NULL AS album_thumb";
        $selectParts[] = $album_cover_col    !== null ? "a.`$album_cover_col`    AS album_cover"    : "NULL AS album_cover";
        $selectParts[] = $album_name_col     !== null ? "a.`$album_name_col`     AS album_name"     : "NULL AS album_name";
        $selectParts[] = $album_price_col    !== null ? "a.`$album_price_col`    AS album_price"    : "NULL AS album_price";
        $selectParts[] = $album_for_sale_col !== null ? "a.`$album_for_sale_col` AS album_for_sale" : "NULL AS album_for_sale";

        // Photos
        $selectParts[] = $photo_thumb_col    !== null ? "ap.`$photo_thumb_col`    AS photo_thumb"    : "NULL AS photo_thumb";
        $selectParts[] = $photo_path_col     !== null ? "ap.`$photo_path_col`     AS photo_path"     : "NULL AS photo_path";
        $selectParts[] = $photo_caption_col  !== null ? "ap.`$photo_caption_col`  AS photo_caption"  : "NULL AS photo_caption";
        $selectParts[] = $photo_album_id_col !== null ? "ap.`$photo_album_id_col` AS photo_album_id" : "NULL AS photo_album_id";

        // Autor
        $selectParts[] = 'u.username';
        $selectParts[] = 'u.profile_picture';
        $selectParts[] = 'u.id AS author_id';

        $selectList = implode(",\n                ", $selectParts);

        $sql = "
            SELECT
                $selectList

            FROM saved_posts sp
            LEFT JOIN posts       p  ON sp.item_type IN ('post','reel') AND sp.item_id = p.id
            LEFT JOIN videos      v  ON sp.item_type = 'video'          AND sp.item_id = v.id
            LEFT JOIN albums      a  ON sp.item_type = 'album'          AND sp.item_id = a.id
            LEFT JOIN album_photos ap ON sp.item_type = 'photo'          AND sp.item_id = ap.id
            LEFT JOIN users       u  ON (
                CASE sp.item_type
                    WHEN 'post'  THEN p.user_id
                    WHEN 'reel'  THEN p.user_id
                    WHEN 'video' THEN v.user_id
                    WHEN 'album' THEN a.user_id
                    WHEN 'photo' THEN ap.user_id
                END
            ) = u.id
            WHERE sp.user_id = :uid
            $type_clause
            ORDER BY sp.created_at DESC
            LIMIT :lim OFFSET :off
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,   PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        if ($use_filter) {
            $stmt->bindValue(':item_type', $filter, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function countItems(int $user_id, bool $use_filter, string $filter): int
    {
        $type_clause = $use_filter ? 'AND item_type = :item_type' : '';

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM saved_posts WHERE user_id = :uid $type_clause"
        );
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        if ($use_filter) {
            $stmt->bindValue(':item_type', $filter, PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Pré-carrega análise AI para todos os itens em batch (evita N+1).
     */
    private function fetchAiAnalysisMap(array $items): array
    {
        if (empty($items)) return [];

        $ids_by_type = [];
        foreach ($items as $it) {
            $type = $this->analysisType($it);
            if ($type !== '') {
                $ids_by_type[$type][] = (int)$it['item_id'];
            }
        }

        $map = [];
        foreach ($ids_by_type as $type => $ids) {
            $ids  = array_values(array_unique($ids));
            $ph   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT post_id, risk_level, status, explicit_percentage
                 FROM media_analysis
                 WHERE post_id IN ($ph) AND type = ?
                 ORDER BY id DESC"
            );
            $stmt->execute([...$ids, $type]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = $type . '_' . $row['post_id'];
                if (!isset($map[$key])) {
                    $map[$key] = $row;
                }
            }
        }

        return $map;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers públicos (usados pela view)
    // ─────────────────────────────────────────────────────────────────────────

    public function analysisType(array $item): string
    {
        return match ($item['item_type']) {
            'album' => 'album',
            'video' => 'video',
            'photo' => 'image',
            'reel'  => 'video',
            'post'  => (!empty($item['post_video_thumb']) || (($item['post_type'] ?? '') === 'reel'))
                ? 'video'
                : 'image',
            default => '',
        };
    }

    public static function itemThumb(array $item): string
    {
        return match ($item['item_type']) {
            'post', 'reel' => $item['post_video_thumb'] ?: $item['post_thumb'] ?: '',
            'video'        => $item['video_thumb'] ?: '',
            'album'        => $item['album_thumb'] ?: $item['album_cover'] ?: '',
            'photo'        => $item['photo_thumb'] ?: $item['photo_path'] ?: '',
            default        => '',
        };
    }

    /**
     * FIX v3: Retorna a URL do vídeo do item, com fallbacks defensivos.
     * Lida com colunas que podem ser NULL (não existiam no schema).
     */
    public static function itemVideoUrl(array $item): string
    {
        // Ordem canónica: alias do SELECT dinâmico → fallbacks por nome
        $candidates = [
            // Alias canónicos (sempre presentes, podem ser NULL)
            'video_url',       // v.video_path (ou equivalente)
            'post_video_url',  // p.video_path (ou equivalente)
            // Fallbacks: nomes reais que podem ter vindo diretamente
            'video_path', 'video_file', 'video_src', 'file_url', 'file_path',
            'media_url', 'media_path', 'source', 'url',
        ];
        foreach ($candidates as $key) {
            if (!empty($item[$key]) && is_string($item[$key])) {
                return $item[$key];
            }
        }
        return '';
    }

    /**
     * FIX v3: Retorna a duração em segundos (ou 0). Lida com NULL.
     */
    public static function itemDuration(array $item): int
    {
        $candidates = ['video_duration', 'post_duration', 'duration', 'length'];
        foreach ($candidates as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                return (int)$item[$key];
            }
        }
        return 0;
    }

    /**
     * FIX v3: Retorna o número de visualizações (ou 0). Lida com NULL.
     */
    public static function itemViews(array $item): int
    {
        $candidates = ['video_views', 'post_views', 'views', 'views_count'];
        foreach ($candidates as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                return (int)$item[$key];
            }
        }
        return 0;
    }

    /**
     * FIX v3: Retorna [width, height] do vídeo (ou [0, 0]). Lida com NULL.
     * @return array{0:int,1:int}
     */
    public static function itemVideoDimensions(array $item): array
    {
        $w = 0;
        $h = 0;
        foreach (['video_width', 'width'] as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                $w = (int)$item[$key];
                break;
            }
        }
        foreach (['video_height', 'height'] as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                $h = (int)$item[$key];
                break;
            }
        }
        return [$w, $h];
    }

    public static function itemUrl(array $item): string
    {
        return match ($item['item_type']) {
            'post'  => BASE_URL . 'post.php?id='       . $item['item_id'],
            'reel'  => BASE_URL . 'reels.php?id='      . $item['item_id'],
            'video' => BASE_URL . 'post.php?id='       . $item['item_id'],
            'album' => BASE_URL . 'view_album.php?id=' . $item['item_id'],
            'photo' => BASE_URL . 'view_album.php?id=' . ($item['photo_album_id'] ?? '')
                . '#photo-' . $item['item_id'],
            default => '#',
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'post'  => 'fa-image',
            'reel'  => 'fa-clapperboard',
            'video' => 'fa-play',
            'album' => 'fa-images',
            'photo' => 'fa-image',
            default => 'fa-bookmark',
        };
    }

    public static function itemPrice(array $item): float
    {
        return (float)match ($item['item_type']) {
            'post', 'reel' => $item['post_price']  ?? 0,
            'video'        => $item['video_price'] ?? 0,
            'album'        => $item['album_price'] ?? 0,
            default        => 0,
        };
    }

    public static function itemIsPaid(array $item): bool
    {
        return (bool)match ($item['item_type']) {
            'post', 'reel' => $item['post_for_sale']  ?? false,
            'video'        => $item['video_for_sale'] ?? false,
            'album'        => $item['album_for_sale'] ?? false,
            default        => false,
        };
    }
}
