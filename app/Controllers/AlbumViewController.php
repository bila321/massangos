<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Models\User;
use Massango\Models\Photo;
use Massango\Models\Album;
use Massango\Models\Comment;
use Massango\Models\FeedItem;
use Massango\Models\Like;
use Massango\Services\AlbumViewService;
use PDO;

/**
 * AlbumViewController
 *
 * Responsável apenas por:
 *   1. Validar o request (auth, ID, acesso)
 *   2. Delegar lógica ao AlbumViewService
 *   3. Passar dados à view
 *
 * Não contém SQL direto nem HTML.
 */
class AlbumViewController
{
    private PDO $pdo;
    private AlbumViewService $service;

    public function __construct(PDO $pdo)
    {
        $this->pdo     = $pdo;
        $this->service = new AlbumViewService($pdo);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ponto de entrada
    // ─────────────────────────────────────────────────────────────────────────

    public function show(): void
    {
        // 1. Auth
        if (!is_logged_in()) {
            $this->abortJson(['error' => 'Não autorizado']);
            redirect(BASE_URL . 'login.php');
        }

        // 2. Parâmetro
        $album_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$album_id) {
            $this->abortJson(['error' => 'ID inválido']);
            redirect(BASE_URL . 'index.php');
        }

        // 3. Carregar dados principais
        $current_user_id = get_current_user_id();
        $data = $this->service->loadAlbum($album_id, $current_user_id);

        if (!$data) {
            $this->abortJson(['error' => 'Álbum não encontrado']);
            redirect(BASE_URL . 'index.php');
        }

        // 4. Privacidade
        if (!$this->service->checkPrivacy($data, $current_user_id)) {
            $this->abortJson(['error' => 'Conteúdo privado']);
            set_message("Conteúdo privado.", "danger");
            redirect(BASE_URL . 'index.php');
        }

        // 5. Aprovação
        if (!$data['is_approved'] && !$data['is_owner'] && !isset($_SESSION['admin_id'])) {
            $this->abortJson(['error' => 'Aguardando aprovação']);
            set_message("Álbum aguardando aprovação.", "warning");
            redirect(BASE_URL . 'index.php');
        }

        // 6. Acesso pago
        if (!$data['has_access']) {
            $redirect = BASE_URL . 'checkout.php?type=album&id=' . $album_id;
            $this->abortJson(['error' => 'Sem acesso', 'redirect' => $redirect]);
            redirect($redirect);
        }

        // 7. Registar visualização (throttle 1h)
        $this->service->registerView($album_id, $current_user_id, $data['is_owner']);

        // 8. Enfileirar análise AI (se necessário)
        $this->service->maybeQueueAiAnalysis($album_id, $data);

        // 9. Renderizar view
        $this->render($data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Em modo AJAX envia JSON e termina; caso contrário não faz nada
     * (o redirect que se segue cuida do flow normal).
     */
    private function abortJson(array $payload): void
    {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($payload);
            exit;
        }
    }

    /**
     * Inclui a view passando todos os dados como variáveis.
     */
    private function render(array $data): void
    {
        // Expor variáveis para a view (extract é seguro aqui porque
        // $data é construído internamente pelo Service)
        extract($data, EXTR_SKIP);

        // header.php / topbar.php e outros includes legacy esperam $pdo
        // no escopo local do ficheiro que os inclui — disponibilizamos aqui.
        $pdo = $this->pdo;

        require_once __DIR__ . '/../../includes/header.php';
        require_once __DIR__ . '/../../includes/views/album/view_album.view.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }
}
