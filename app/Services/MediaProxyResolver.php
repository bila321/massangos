<?php

namespace Massango\Services;

/**
 * MediaProxyResolver
 *
 * Resolve QUAL ficheiro servir e SE é permitido servi-lo.
 * Não faz streaming de bytes — isso fica no entry point (media-proxy.php),
 * porque misturar resolução de path com headers/readfile() é onde
 * costumam aparecer bugs sutis de output buffering.
 *
 * Mantém EXATAMENTE a mesma lógica de segurança do ficheiro original:
 *   - path traversal bloqueado por segmento (".." rejeitado, não substring)
 *   - validação HMAC do token via hash_equals (resistente a timing attack)
 *   - verifications/ exige ser dono ou admin
 */
class MediaProxyResolver
{
    private string $storageBase;
    private string $storageBaseLegacy;

    public function __construct(string $storageBase, string $storageBaseLegacy)
    {
        $this->storageBase       = rtrim($storageBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->storageBaseLegacy = rtrim($storageBaseLegacy, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Decodifica o parâmetro ?id= (base64, com padding reposto).
     */
    public function decodeMediaId(string $mediaIdClean): string
    {
        $padded = $mediaIdClean . str_repeat('=', (4 - strlen($mediaIdClean) % 4) % 4);
        return base64_decode($padded);
    }

    /**
     * Valida o token HMAC de acesso. Devolve true se válido OU se não
     * havia token/salt a validar (mesmo comportamento permissivo do original).
     */
    public function isTokenValid(string $token, string $mediaId): bool
    {
        if (!defined('SECURITY_SALT') || empty($token)) {
            return true;
        }

        $tokenDecoded = base64_decode($token);
        $parts = explode(':', $tokenDecoded);

        if (count($parts) < 4) {
            return true; // mesmo comportamento original: só valida se tiver 4 partes
        }

        [$tId, $tTime, $tRandom, $tHash] = $parts;
        $expectedHash = hash_hmac('sha256', "$tId:$tTime:$tRandom", SECURITY_SALT);

        return hash_equals($expectedHash, $tHash);
    }

    /**
     * Sanitiza o path pedido, rejeitando path traversal.
     * Lança RuntimeException com código 403 se for inválido.
     *
     * @throws \RuntimeException
     */
    public function sanitizePath(string $file): string
    {
        $file = str_replace('\\', '', $file);
        $file = ltrim($file, '/\\');

        if (empty($file)) {
            return '';
        }

        $segments   = explode('/', $file);
        $normalized = [];

        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                throw new \RuntimeException('Forbidden: path traversal', 403);
            }
            $normalized[] = $seg;
        }

        $file = implode('/', $normalized);

        // Confirmação extra: se o ficheiro existir, valida com realpath
        $resolvedStorageBase = realpath($this->storageBase);
        $candidate = $this->storageBase . $file;

        if ($resolvedStorageBase !== false && file_exists($candidate)) {
            $resolvedPath = realpath($candidate);
            if ($resolvedPath === false || !str_starts_with($resolvedPath, $resolvedStorageBase)) {
                throw new \RuntimeException('Forbidden: outside storage base', 403);
            }
        }

        return $file;
    }

    /**
     * Verifica se o utilizador atual pode acessar ficheiros em verifications/.
     * Pressupõe que a sessão já foi iniciada pelo chamador.
     */
    public function canAccessVerification(string $file): bool
    {
        if (!str_starts_with($file, 'verifications/')) {
            return true; // não é um ficheiro de verificação, regra não se aplica
        }

        $parts       = explode('/', $file);
        $userFolder  = $parts[1] ?? '';
        $isAdmin     = isset($_SESSION['admin_id']);
        $isOwner     = isset($_SESSION['user_id']) && $userFolder == $_SESSION['user_id'];

        return $isAdmin || $isOwner;
    }

    /**
     * Normaliza segmentos duplicados de thumbnail (ex: thumbnails/thumbnails/).
     */
    public function normalizeDuplicateSegments(string $file): string
    {
        $file = preg_replace('#(videos/thumbnails)/thumbnails/#', '$1/', $file);
        $file = preg_replace('#(albums/thumbnails)/thumbnails/#', '$1/', $file);
        return $file;
    }

    /**
     * Resolve o caminho físico final a servir, aplicando todos os
     * fallbacks (legado, thumbnail ausente, imagem por omissão).
     *
     * Devolve null se nenhum ficheiro válido foi encontrado.
     */
    public function resolvePhysicalPath(string $file): ?string
    {
        $path = $this->storageBase . $file;
        if (!file_exists($path)) {
            $path = $this->storageBaseLegacy . $file;
        }

        // Fallback: thumbnail de álbum ausente → tenta o original
        if (!file_exists($path) && str_contains($file, 'albums/thumbnails/')) {
            $original = str_replace('albums/thumbnails/', 'albums/', $file);
            $tryPath  = $this->storageBase . $original;
            if (!file_exists($tryPath)) {
                $tryPath = $this->storageBaseLegacy . $original;
            }
            if (file_exists($tryPath)) {
                $path = $tryPath;
            }
        }

        // Fallback: thumbnail de vídeo ausente → tenta o vídeo original
        if (!file_exists($path) && str_contains($file, 'videos/thumbnails/')) {
            $original = str_replace('videos/thumbnails/', 'videos/', $file);
            $original = preg_replace('/_thumb\.jpg$/', '.mp4', $original);
            $tryPath  = $this->storageBase . $original;
            if (!file_exists($tryPath)) {
                $tryPath = $this->storageBaseLegacy . $original;
            }
            if (file_exists($tryPath)) {
                $path = $tryPath;
            }
        }

        // Fallbacks para imagem por omissão
        if (!file_exists($path)) {
            if (str_contains($file, 'profile')) {
                $path = $this->storageBase . 'default_profile.png';
                if (!file_exists($path)) {
                    $path = $this->storageBaseLegacy . 'default_profile.png';
                }
            } elseif (str_contains($file, 'post')) {
                $path = $this->storageBase . 'default_post.png';
                if (!file_exists($path)) {
                    $path = $this->storageBaseLegacy . 'default_post.png';
                }
            } else {
                return null;
            }

            if (!file_exists($path)) {
                return null;
            }
        }

        return $path;
    }

    /**
     * Devolve o caminho da imagem de perfil por omissão, ou null se não existir.
     */
    public function resolveDefaultProfilePath(): ?string
    {
        $default = $this->storageBase . 'default_profile.png';
        return file_exists($default) ? $default : null;
    }

    /**
     * Devolve o MIME type para a extensão do ficheiro.
     */
    public function resolveMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeMap = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
        ];

        return $mimeMap[$ext] ?? 'application/octet-stream';
    }
}
