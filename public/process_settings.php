<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;
use Massango\Services\MediaProcessor;

if (!is_logged_in()) {
    set_message("Você precisa estar logado.", "danger");
    redirect(BASE_URL . 'login.php');
}

$current_user_id = get_current_user_id();

function analyze_profile_picture(string $file_path): array
{
    $default = ['is_sensitive' => false, 'score' => 0.0, 'triggered_by' => null, 'error' => null];
    if (!function_exists('curl_init') || !file_exists($file_path)) return $default;

    $relative_path = 'uploads/profiles/' . basename($file_path);
    $post_data = http_build_query(['file_path' => $relative_path, 'post_id' => 0]);

    $ch = curl_init('http://127.0.0.1:8000/analyze-sync');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post_data,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err || $http_code !== 200) {
        error_log('[profile_nudenet] FastAPI indisponível: ' . ($curl_err ?: 'HTTP ' . $http_code));
        return $default;
    }
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) return $default;

    return [
        'is_sensitive' => (bool)($result['is_sensitive'] ?? false),
        'score'        => (float)($result['score']        ?? 0.0),
        'triggered_by' => $result['triggered_by']          ?? null,
        'error'        => null
    ];
}

// FIX: proteger default_profile.png independentemente do caminho na BD
function is_default_profile(string $path): bool
{
    return basename($path) === 'default_profile.png';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action']) && $_POST['action'] === 'update_privacy') {
        $privacy = $_POST['profile_privacy'] ?? 'public';
        if (User::updatePrivacy($pdo, $current_user_id, $privacy)) {
            set_message("Configurações de privacidade atualizadas!", "success");
        } else {
            set_message("Erro ao atualizar privacidade.", "danger");
        }
        redirect(BASE_URL . 'settings.php');
    }

    if (isset($_POST['update_profile'])) {
        $username = sanitize_input($_POST['username']);
        $email    = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $bio      = sanitize_input($_POST['bio']);

        // ── Widget 1: campos de informação de perfil ─────────────────
        $location           = mb_substr(trim(strip_tags($_POST['location']  ?? '')), 0, 100);

        // BUG FIX: sanitizar e validar website como URL
        $website_raw = mb_substr(trim(strip_tags($_POST['website'] ?? '')), 0, 255);
        if ($website_raw !== '' && !preg_match('#^https?://#i', $website_raw)) {
            $website_raw = 'https://' . $website_raw;
        }
        $website = filter_var($website_raw, FILTER_VALIDATE_URL) ? $website_raw : '';

        // BUG FIX: validar formato Y-m-d para evitar dados inválidos na BD
        $birth_raw          = trim($_POST['profile_birth_date'] ?? '');
        $profile_birth_date = null;
        if ($birth_raw !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $birth_raw);
            if ($d && $d->format('Y-m-d') === $birth_raw) {
                $profile_birth_date = $birth_raw;
            }
        }

        $allowed_genders    = ['male', 'female', 'other', 'prefer_not_to_say'];
        $gender             = in_array($_POST['gender'] ?? '', $allowed_genders, true) ? $_POST['gender'] : null;
        $show_location      = isset($_POST['show_location'])    ? 1 : 0;
        $show_website       = isset($_POST['show_website'])     ? 1 : 0;
        $show_birth_date    = isset($_POST['show_birth_date'])  ? 1 : 0;
        $show_gender        = isset($_POST['show_gender'])      ? 1 : 0;
        // ──────────────────────────────────────────────────────────────

        $user_data            = User::getUserById($pdo, $current_user_id);
        $profile_picture_path = $user_data['profile_picture'];

        if (!empty($_POST['cropped_image'])) {
            $destination = null;
            try {
                $data          = explode(',', $_POST['cropped_image']);
                $image_content = base64_decode($data[1] ?? '');
                if (empty($image_content)) throw new Exception("Imagem inválida.");

                $file_name    = uniqid('profile_') . '.jpg';
                $profiles_dir = UPLOAD_DIR . 'profiles/';
                $destination  = $profiles_dir . $file_name;
                if (!is_dir($profiles_dir)) mkdir($profiles_dir, 0775, true);
                if (!file_put_contents($destination, $image_content)) throw new Exception("Não foi possível guardar a imagem.");

                $nudenet = analyze_profile_picture($destination);
                if ($nudenet['is_sensitive'] && $nudenet['score'] > 40) {
                    @unlink($destination);
                    $reason = $nudenet['triggered_by'] ? ' (' . $nudenet['triggered_by'] . ')' : '';
                    throw new Exception("Foto rejeitada: conteúdo explícito detectado{$reason}. Escolha uma imagem apropriada.");
                }

                $profile_picture_path = 'profiles/' . $file_name;

                // FIX: nunca apagar default_profile.png
                if ($user_data['profile_picture'] && !is_default_profile($user_data['profile_picture']) && file_exists(UPLOAD_DIR . $user_data['profile_picture'])) {
                    @unlink(UPLOAD_DIR . $user_data['profile_picture']);
                }
            } catch (Exception $e) {
                if ($destination && file_exists($destination)) @unlink($destination);
                set_message($e->getMessage(), "danger");
                redirect(BASE_URL . 'settings.php');
                exit;
            }
        } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $destination = null;
            try {
                MediaProcessor::validateUpload($_FILES['profile_picture'], ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE);
                $profiles_dir = UPLOAD_DIR . 'profiles/';
                if (!is_dir($profiles_dir)) mkdir($profiles_dir, 0775, true);
                $ext         = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $file_name   = uniqid('profile_') . '.' . $ext;
                $destination = $profiles_dir . $file_name;
                if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) throw new Exception("Não foi possível guardar a imagem.");

                $nudenet = analyze_profile_picture($destination);
                if ($nudenet['is_sensitive'] && $nudenet['score'] > 40) {
                    @unlink($destination);
                    $reason = $nudenet['triggered_by'] ? ' (' . $nudenet['triggered_by'] . ')' : '';
                    throw new Exception("Foto rejeitada: conteúdo explícito detectado{$reason}. Escolha uma imagem apropriada.");
                }

                $profile_picture_path = 'profiles/' . $file_name;

                // FIX: nunca apagar default_profile.png
                if ($user_data['profile_picture'] && !is_default_profile($user_data['profile_picture']) && file_exists(UPLOAD_DIR . $user_data['profile_picture'])) {
                    @unlink(UPLOAD_DIR . $user_data['profile_picture']);
                }
            } catch (Exception $e) {
                if ($destination && file_exists($destination)) @unlink($destination);
                set_message($e->getMessage(), "danger");
                redirect(BASE_URL . 'settings.php');
                exit;
            }
        }

        if (User::updateProfile($pdo, $current_user_id, $username, $email, $bio, $profile_picture_path, $location, $website, $profile_birth_date, $gender, $show_location, $show_website, $show_birth_date, $show_gender)) {
            $_SESSION['username'] = $username;
            if ($profile_picture_path !== $user_data['profile_picture']) {
                $_SESSION['user_profile_picture'] = UPLOAD_URL . $profile_picture_path;
            }
            set_message("Perfil atualizado com sucesso!", "success");
        } else {
            set_message("Erro ao atualizar perfil.", "danger");
        }

        redirect(BASE_URL . 'settings.php');
    }
}
