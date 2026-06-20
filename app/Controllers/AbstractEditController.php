<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Models\FeedItem;
use PDO;

/**
 * AbstractEditController
 *
 * Base partilhada pelos controllers de edição (post, vídeo, álbum):
 * auth, detecção de AJAX, carregar item, verificar dono, carregar feed_item_id.
 *
 * Cada subclasse só precisa de implementar:
 *   - itemType(): string                 ex: 'post'
 *   - fetchItem(int $id): ?array         busca o registo na BD
 *   - notLoggedInMessage(): string
 *   - notFoundMessage(): string
 *   - noPermissionMessage(): string
 *   - viewPath(): string                 caminho do partial específico
 */
abstract class AbstractEditController
{
    protected bool  $is_ajax;
    protected int   $current_user_id;
    protected array $item;
    protected ?int  $feed_item_id;
    protected string $logged_in_user_profile_pic;
    protected string $redirect_to;

    public function __construct(protected PDO $pdo)
    {
        $this->is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos abstratos — cada subclasse implementa
    // ─────────────────────────────────────────────────────────────────────────

    abstract protected function itemType(): string;
    abstract protected function fetchItem(int $id): ?array;
    abstract protected function notLoggedInMessage(): string;
    abstract protected function notFoundMessage(): string;
    abstract protected function noPermissionMessage(): string;
    abstract protected function viewPath(): string;

    /**
     * Hook opcional: subclasses que precisem de dados extra na view
     * (ex: EditAlbumController precisa de $photos) sobrescrevem este
     * método. Chamado depois de $this->item já estar definido.
     *
     * @return array<string, mixed>  nome da variável => valor
     */
    protected function extraViewData(): array
    {
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fluxo principal
    // ─────────────────────────────────────────────────────────────────────────

    public function show(): void
    {
        // 1. Header (só se não for AJAX)
        if (!$this->is_ajax) {
            require_once __DIR__ . '/../../includes/header.php';
        }

        // 2. Auth
        if (!is_logged_in()) {
            set_message($this->notLoggedInMessage(), "danger");
            redirect(BASE_URL . 'login.php');
        }
        $this->current_user_id = get_current_user_id();

        // 3. ID do item
        $item_id = (int)($_GET['id'] ?? 0);
        if (!$item_id) {
            set_message("ID não especificado.", "danger");
            redirect(BASE_URL);
        }

        // 4. Buscar item
        $item = $this->fetchItem($item_id);
        if (!$item) {
            set_message($this->notFoundMessage(), "danger");
            redirect(BASE_URL);
        }
        $this->item = $item;

        // 5. Verificar dono
        if ((int)$item['user_id'] !== $this->current_user_id) {
            set_message($this->noPermissionMessage(), "danger");
            redirect(BASE_URL);
        }

        // 6. feed_item_id
        $feed_item = FeedItem::getFeedItemById($this->pdo, $item_id, $this->itemType());
        $this->feed_item_id = $feed_item['feed_item_id'] ?? null;

        // 7. Dados auxiliares para a view
        $this->logged_in_user_profile_pic = $_SESSION['user_profile_picture']
            ?? UPLOAD_URL . 'profiles/default_profile.png';
        $this->redirect_to = $_GET['redirect_to'] ?? 'index.php';

        // 8. Render
        $this->render();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────────────────

    protected function render(): void
    {
        $item                       = $this->item;
        $feed_item_id               = $this->feed_item_id;
        $is_ajax                    = $this->is_ajax;
        $redirect_to                = $this->redirect_to;
        $logged_in_user_profile_pic = $this->logged_in_user_profile_pic;
        $pdo                        = $this->pdo; // legacy includes esperam $pdo no escopo local

        extract($this->extraViewData(), EXTR_SKIP); // ex: $photos no caso do álbum

        require $this->viewPath();

        if (!$is_ajax) {
            require_once __DIR__ . '/../../includes/footer.php';
        }
    }
}
