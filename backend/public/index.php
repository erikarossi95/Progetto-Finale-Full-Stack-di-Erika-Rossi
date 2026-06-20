<?php

declare(strict_types=1);

/**
 * Snaply — front controller / entry point.
 * Carica autoload + .env, configura CORS, serve i media e dispatcha le rotte.
 */

use Snaply\Controllers\AuthController;
use Snaply\Controllers\EventController;
use Snaply\Controllers\PhotoController;
use Snaply\Controllers\ProfileController;
use Snaply\Core\Env;
use Snaply\Core\Request;
use Snaply\Core\Response;
use Snaply\Core\Router;
use Snaply\Middleware\AuthMiddleware;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

// In dev mostriamo gli errori PHP nei log, mai nel body della risposta.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// --- CORS ----------------------------------------------------------------
$allowed = array_map('trim', explode(',', Env::get('ALLOWED_ORIGINS', '') ?? ''));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight: rispondi subito.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$request = new Request();

// --- Serve i file caricati ----------------------------------------------
// I media stanno in backend/uploads/ (fuori da public/): li serve il backend.
$uploadDir = trim(Env::get('UPLOAD_DIR', 'uploads') ?? 'uploads', '/');
if (serveUploadedFile($request->path(), $uploadDir)) {
    exit;
}

// --- Gestione globale degli errori inattesi ------------------------------
set_exception_handler(function (\Throwable $e): void {
    error_log('Unhandled: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error('SERVER_ERROR', 'Errore interno del server', 500);
});

// --- Rotte ----------------------------------------------------------------
$router = new Router();

$auth = new AuthController();
$events = new EventController();
$photos = new PhotoController();
$profile = new ProfileController();

// Auth
$router->post('/api/register', fn(Request $r) => $auth->register($r));
$router->post('/api/login', fn(Request $r) => $auth->login($r));
$router->post('/api/logout', fn(Request $r) => $auth->logout($r));
$router->get('/api/me', function (Request $r) use ($auth) {
    $uid = AuthMiddleware::requireUser($r);
    $auth->me($r, $uid);
});

// Eventi (protetti)
$router->get('/api/events', function (Request $r) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->index($r, $uid);
});
$router->post('/api/events', function (Request $r) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->store($r, $uid);
});
$router->get('/api/events/{id}', function (Request $r, array $p) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->show($r, $uid, (int) $p['id']);
});
$router->get('/api/events/{id}/photos', function (Request $r, array $p) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->photos($r, $uid, (int) $p['id']);
});
$router->put('/api/events/{id}', function (Request $r, array $p) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->update($r, $uid, (int) $p['id']);
});
$router->delete('/api/events/{id}', function (Request $r, array $p) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->destroy($r, $uid, (int) $p['id']);
});
$router->post('/api/events/{id}/cover', function (Request $r, array $p) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->uploadCover($r, $uid, (int) $p['id']);
});
$router->delete('/api/events/{id}/cover', function (Request $r, array $p) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->deleteCover($r, $uid, (int) $p['id']);
});
$router->post('/api/events/{id}/avatar', function (Request $r, array $p) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->uploadAvatar($r, $uid, (int) $p['id']);
});
$router->delete('/api/events/{id}/avatar', function (Request $r, array $p) use ($events) {
    $uid = AuthMiddleware::requireUser($r);
    $events->deleteAvatar($r, $uid, (int) $p['id']);
});

// Pubblici (no auth)
$router->get('/api/public/events/{slug}', fn(Request $r, array $p) => $photos->publicShow($r, $p['slug']));
$router->get('/api/public/events/{slug}/photos', fn(Request $r, array $p) => $photos->publicPhotos($r, $p['slug']));
$router->post('/api/public/events/{slug}/photos', fn(Request $r, array $p) => $photos->publicUpload($r, $p['slug']));
$router->post('/api/public/photos/{id}/like', fn(Request $r, array $p) => $photos->like($r, (int) $p['id']));
$router->delete('/api/public/photos/{id}/like', fn(Request $r, array $p) => $photos->unlike($r, (int) $p['id']));

// Foto: delete (protetta)
$router->delete('/api/photos/{id}', function (Request $r, array $p) use ($photos) {
    $uid = AuthMiddleware::requireUser($r);
    $photos->destroy($r, $uid, (int) $p['id']);
});

// Profilo (protetto)
$router->put('/api/profile', function (Request $r) use ($profile) {
    $uid = AuthMiddleware::requireUser($r);
    $profile->update($r, $uid);
});

$router->dispatch($request);

/**
 * Se il path richiede un file dentro la cartella upload e il file esiste,
 * lo invia con il giusto content-type e ritorna true.
 */
function serveUploadedFile(string $path, string $uploadDir): bool
{
    $prefix = '/' . $uploadDir . '/';
    if (!str_starts_with($path, $prefix)) {
        return false;
    }

    $relative = substr($path, strlen($prefix));
    // Difesa anti path traversal.
    if (str_contains($relative, '..')) {
        return false;
    }

    $base = realpath(dirname(__DIR__) . '/' . $uploadDir);
    $full = realpath(dirname(__DIR__) . '/' . $uploadDir . '/' . $relative);
    if ($base === false || $full === false || !str_starts_with($full, $base) || !is_file($full)) {
        return false;
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($full) ?: 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($full));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($full);
    return true;
}
