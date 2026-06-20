<?php
declare(strict_types=1);

namespace Massango\Controllers;

use Massango\Services\AlbumPartnersPageService;
use PDO;

/**
 * AlbumPartnersPageController
 *
 * Valida o request, delega ao Service e passa dados à view.
 * Não contém SQL nem HTML.
 */
class AlbumPartnersPageController
{
    private AlbumPartnersPageService $service;

    public function __construct(private PDO $pdo)
    {
        $this->service = new AlbumPartnersPageService($pdo);
    }

    public function show(): void
    {
        // 1. Auth
        if (!is_logged_in()) {
            set_message("Você precisa estar logado.", "danger");
            redirect(BASE_URL . 'login.php');
        }

        // 2. Input
        $user_id  = get_current_user_id();
        $album_id = (int)($_GET['album_id'] ?? 0);

        if ($album_id <= 0) {
            set_message("Álbum não especificado.", "danger");
            redirect(BASE_URL . 'index.php');
        }

        // 3. Dados
        $data = $this->service->load($album_id, $user_id);
        if (!$data) {
            set_message("Álbum não encontrado.", "danger");
            redirect(BASE_URL . 'index.php');
        }

        // 4. Permissão
        if (!$this->service->canView($data)) {
            set_message("Você não tem permissão para visualizar esta página.", "danger");
            redirect(BASE_URL . 'index.php');
        }

        // 5. Render
        $album_id_var = $album_id; // disponibilizar para a view com nome estável
        extract($data, EXTR_SKIP);

        $extra_css = ['premium_lightbox.css'];
        require_once __DIR__ . '/../../includes/header.php';
        require       __DIR__ . '/../../includes/views/album_partners/album_partners.view.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }
}
