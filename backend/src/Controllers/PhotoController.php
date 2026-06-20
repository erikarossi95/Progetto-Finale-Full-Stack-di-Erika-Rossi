<?php

declare(strict_types=1);

namespace Snaply\Controllers;

use Snaply\Core\Env;
use Snaply\Core\Image;
use Snaply\Core\RateLimiter;
use Snaply\Core\Request;
use Snaply\Core\Response;
use Snaply\Models\Event;
use Snaply\Models\Photo;

/**
 * Vista pubblica evento, upload pubblico (invitati) e delete foto (organizzatore).
 */
final class PhotoController
{
    /** Foto per pagina nelle viste galleria. */
    private const PER_PAGE = 24;

    /** MIME ammessi → estensione + tipo logico. */
    private const ALLOWED_MIME = [
        'image/jpeg'      => ['ext' => 'jpg',  'type' => 'image'],
        'image/png'       => ['ext' => 'png',  'type' => 'image'],
        'image/webp'      => ['ext' => 'webp', 'type' => 'image'],
        'video/mp4'       => ['ext' => 'mp4',  'type' => 'video'],
        'video/quicktime' => ['ext' => 'mov',  'type' => 'video'],
    ];

    public function __construct(
        private Event $events = new Event(),
        private Photo $photos = new Photo(),
    ) {
    }

    /** GET /api/public/events/{slug} — info evento + foto, senza dati organizzatore. */
    public function publicShow(Request $request, string $slug): void
    {
        $event = $this->events->findBySlug($slug);
        if ($event === null) {
            Response::error('NOT_FOUND', 'Evento non trovato', 404);
        }

        $eventId = (int) $event['id'];
        // Solo la prima pagina; le successive via /photos?page=N.
        $photos = array_map(
            [Photo::class, 'publicData'],
            $this->photos->allForEvent($eventId, self::PER_PAGE, 0)
        );

        Response::success([
            'event' => [
                'title'            => $event['title'],
                'description'      => $event['description'],
                'event_date'       => $event['event_date'],
                'cover_color'      => $event['cover_color'],
                'cover_image_url'  => Event::coverUrl($event['cover_image'] ?? null),
                'avatar_image_url' => Event::avatarUrl($event['avatar_image'] ?? null),
                'avatar_emoji'     => $event['avatar_emoji'] ?? null,
                'slug'             => $event['slug'],
                'photos'           => $photos,
                'photos_total'     => $this->photos->countForEvent($eventId),
                'per_page'         => self::PER_PAGE,
            ],
        ], 200);
    }

    /** GET /api/public/events/{slug}/photos?page=N — pagina successiva di media. */
    public function publicPhotos(Request $request, string $slug): void
    {
        $event = $this->events->findBySlug($slug);
        if ($event === null) {
            Response::error('NOT_FOUND', 'Evento non trovato', 404);
        }
        $eventId = (int) $event['id'];
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;
        $total = $this->photos->countForEvent($eventId);
        $photos = array_map(
            [Photo::class, 'publicData'],
            $this->photos->allForEvent($eventId, self::PER_PAGE, $offset)
        );
        Response::success([
            'photos'   => $photos,
            'page'     => $page,
            'per_page' => self::PER_PAGE,
            'total'    => $total,
            'has_more' => ($offset + count($photos)) < $total,
        ], 200);
    }

    /** POST /api/public/events/{slug}/photos — upload da invitato (multipart). */
    public function publicUpload(Request $request, string $slug): void
    {
        // Anti-abuso: max 60 upload ogni 10 minuti per IP.
        if (RateLimiter::tooMany('upload:' . RateLimiter::clientIp(), 60, 600)) {
            Response::error('RATE_LIMITED', 'Troppi caricamenti, riprova tra qualche minuto', 429);
        }

        $event = $this->events->findBySlug($slug);
        if ($event === null) {
            Response::error('NOT_FOUND', 'Evento non trovato', 404);
        }

        $file = $request->file('file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Response::error('VALIDATION_ERROR', 'Nessun file caricato', 422, ['file' => 'File obbligatorio']);
        }

        // Errori di upload PHP (oltre a NO_FILE già gestito).
        if ($file['error'] !== UPLOAD_ERR_OK) {
            if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                Response::error('VALIDATION_ERROR', 'File troppo grande', 422, ['file' => 'File troppo grande']);
            }
            Response::error('VALIDATION_ERROR', 'Caricamento fallito', 422, ['file' => 'Caricamento fallito']);
        }

        // Limite dimensione dalla config.
        $maxSize = (int) Env::get('MAX_UPLOAD_SIZE', '26214400');
        if (($file['size'] ?? 0) > $maxSize) {
            $mb = round($maxSize / (1024 * 1024));
            Response::error('VALIDATION_ERROR', "File troppo grande (max {$mb} MB)", 422, ['file' => "Max {$mb} MB"]);
        }

        // MIME reale dal contenuto, non dal client (no spoofing fidato).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED_MIME[$mime])) {
            Response::error('VALIDATION_ERROR', 'Formato file non ammesso', 422, ['file' => 'Formato non supportato']);
        }

        $meta = self::ALLOWED_MIME[$mime];
        $uploaderName = $request->post('uploader_name');
        $uploaderName = is_string($uploaderName) && trim($uploaderName) !== ''
            ? mb_substr(trim($uploaderName), 0, 100)
            : null;

        // Cartella per slug, nome file random (no path traversal, no overwrite).
        $relDir = $event['slug'];
        $absDir = $this->uploadsBasePath() . '/' . $relDir;
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            error_log("Impossibile creare la cartella upload: $absDir");
            Response::error('SERVER_ERROR', 'Errore durante il salvataggio', 500);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $meta['ext'];
        $relPath = $relDir . '/' . $filename;
        $absPath = $absDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            error_log("move_uploaded_file fallita verso $absPath");
            Response::error('SERVER_ERROR', 'Errore durante il salvataggio', 500);
        }

        $sizeBytes = (int) ($file['size'] ?? 0);
        $thumbRel = null;
        if ($meta['type'] === 'image') {
            // Privacy: rimuove i metadati EXIF (anche GPS) applicando l'orientamento.
            Image::normalize($absPath, $mime);
            $sizeBytes = (int) (@filesize($absPath) ?: $sizeBytes);
            // Miniatura: riduce banda e velocizza la galleria (fallback all'originale).
            $thumbName = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.' . $meta['ext'];
            if (Image::thumbnail($absPath, $mime, $absDir . '/' . $thumbName, 600)) {
                $thumbRel = $relDir . '/' . $thumbName;
            }
        }

        $id = $this->photos->create([
            'event_id'      => (int) $event['id'],
            'file_path'     => $relPath,
            'thumb_path'    => $thumbRel,
            'file_type'     => $meta['type'],
            'original_name' => is_string($file['name'] ?? null) ? mb_substr($file['name'], 0, 255) : null,
            'uploader_name' => $uploaderName,
            'size_bytes'    => $sizeBytes,
        ]);

        $row = $this->photos->findById($id);
        Response::success(['photo' => Photo::publicData($row)], 201);
    }

    /** DELETE /api/photos/{id} — l'organizzatore elimina una foto di un suo evento. */
    public function destroy(Request $request, int $userId, int $id): void
    {
        $photo = $this->photos->findWithOwner($id);
        if ($photo === null) {
            Response::error('NOT_FOUND', 'Foto non trovata', 404);
        }
        if ((int) $photo['owner_id'] !== $userId) {
            Response::error('FORBIDDEN', 'Non hai accesso a questa foto', 403);
        }

        // Cancella prima i file fisici (originale + miniatura), poi il record.
        foreach ([$photo['file_path'] ?? null, $photo['thumb_path'] ?? null] as $rel) {
            if (!empty($rel)) {
                $full = $this->uploadsBasePath() . '/' . ltrim($rel, '/');
                if (is_file($full)) {
                    @unlink($full);
                }
            }
        }
        $this->photos->delete($id);

        Response::success(['message' => 'Foto eliminata'], 200);
    }

    /** POST /api/public/photos/{id}/like — aggiunge un cuore (pubblico). */
    public function like(Request $request, int $id): void
    {
        if (RateLimiter::tooMany('like:' . RateLimiter::clientIp(), 100, 60)) {
            Response::error('RATE_LIMITED', 'Troppe richieste, rallenta un attimo', 429);
        }
        if ($this->photos->findById($id) === null) {
            Response::error('NOT_FOUND', 'Contenuto non trovato', 404);
        }
        $this->photos->like($id);
        $row = $this->photos->findById($id);
        Response::success(['id' => $id, 'likes' => (int) $row['likes']], 200);
    }

    /** DELETE /api/public/photos/{id}/like — toglie un cuore (pubblico). */
    public function unlike(Request $request, int $id): void
    {
        if (RateLimiter::tooMany('like:' . RateLimiter::clientIp(), 100, 60)) {
            Response::error('RATE_LIMITED', 'Troppe richieste, rallenta un attimo', 429);
        }
        if ($this->photos->findById($id) === null) {
            Response::error('NOT_FOUND', 'Contenuto non trovato', 404);
        }
        $this->photos->unlike($id);
        $row = $this->photos->findById($id);
        Response::success(['id' => $id, 'likes' => (int) $row['likes']], 200);
    }

    private function uploadsBasePath(): string
    {
        $dir = Env::get('UPLOAD_DIR', 'uploads');
        return dirname(__DIR__, 2) . '/' . trim($dir, '/');
    }
}
