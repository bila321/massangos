<?php
// app/Controllers/VideoController.php
namespace Massango\Controllers;

use Massango\Services\VideoService;

class VideoController
{
    private VideoService $videoService;

    public function __construct(\PDO $pdo, int $userId)
    {
        $this->videoService = new VideoService($pdo, $userId);
    }

    public function handle(array $post, array $files): array
    {
        if (($post['action'] ?? '') === 'delete_video') {
            $videoId = (int)($post['video_id'] ?? 0);
            return $this->videoService->deleteVideo($videoId);
        }

        return $this->videoService->createVideo(
            $post,
            $files['video']     ?? [],
            $files['thumbnail'] ?? null
        );
    }
}
