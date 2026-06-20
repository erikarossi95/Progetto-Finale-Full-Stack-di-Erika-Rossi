<?php

declare(strict_types=1);

namespace Snaply\Controllers;

use Snaply\Core\Request;
use Snaply\Core\Response;
use Snaply\Core\Validator;
use Snaply\Models\Event;
use Snaply\Models\Photo;

/**
 * CRUD eventi. Tutte le rotte sono protette: l'id utente arriva dal middleware.
 * Ogni operazione su un evento verifica la proprietà (altrimenti 403).
 */
final class EventController
{
    /** Foto per pagina nella galleria di dettaglio. */
    private const PER_PAGE = 24;

    /** MIME immagine ammessi per la copertina (no video). */
    private const COVER_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private Event $events = new Event(),
        private Photo $photos = new Photo(),
    ) {
    }

    /** GET /api/events — lista degli eventi dell'utente con conteggio foto. */
    public function index(Request $request, int $userId): void
    {
        $rows = $this->events->allForUser($userId);
        $events = array_map([Event::class, 'listData'], $rows);
        Response::success(['events' => $events], 200);
    }

    /** POST /api/events — crea un evento con slug univoco. */
    public function store(Request $request, int $userId): void
    {
        $body = $request->json();
        if ($body === null) {
            Response::error('BAD_REQUEST', 'Corpo JSON non valido', 400);
        }

        $data = $this->validatePayload($body, required: true);

        $data['slug'] = $this->generateUniqueSlug();
        $id = $this->events->create($userId, $data);

        $row = $this->events->findById($id);
        Response::success(['event' => $this->detailData($row)], 201);
    }

    /** GET /api/events/{id} — dettaglio + foto. */
    public function show(Request $request, int $userId, int $id): void
    {
        $event = $this->ownedOrFail($id, $userId);
        Response::success(['event' => $this->detailData($event, withPhotos: true)], 200);
    }

    /** PUT /api/events/{id} — aggiorna i campi presenti. */
    public function update(Request $request, int $userId, int $id): void
    {
        $event = $this->ownedOrFail($id, $userId);

        $body = $request->json();
        if ($body === null) {
            Response::error('BAD_REQUEST', 'Corpo JSON non valido', 400);
        }

        // In update i campi sono opzionali: si validano solo quelli presenti.
        $data = $this->validatePayload($body, required: false);

        $fields = [];
        foreach (['title', 'description', 'event_date', 'cover_color', 'avatar_emoji'] as $key) {
            if (array_key_exists($key, $body)) {
                $fields[$key] = $data[$key];
            }
        }

        $this->events->update($id, $fields);
        $row = $this->events->findById($id);
        Response::success(['event' => $this->detailData($row)], 200);
    }

    /** DELETE /api/events/{id} — elimina evento, foto (cascade) e file fisici. */
    public function destroy(Request $request, int $userId, int $id): void
    {
        $event = $this->ownedOrFail($id, $userId);

        // Cancella i file fisici prima del record (il cascade rimuove le righe).
        $photos = $this->photos->allForEvent($id);
        foreach ($photos as $photo) {
            $this->deletePhysicalFile($photo['file_path']);
            if (!empty($photo['thumb_path'])) {
                $this->deletePhysicalFile($photo['thumb_path']);
            }
        }
        // Cancella anche copertina e avatar (non sono nella tabella photos).
        if (!empty($event['cover_image'])) {
            $this->deletePhysicalFile($event['cover_image']);
        }
        if (!empty($event['avatar_image'])) {
            $this->deletePhysicalFile($event['avatar_image']);
        }
        // Rimuove anche la cartella dello slug se vuota.
        $this->removeEventDir($event['slug']);

        $this->events->delete($id);
        Response::success(['message' => 'Evento eliminato'], 200);
    }

    /** POST /api/events/{id}/cover — carica/sostituisce la copertina (immagine). */
    public function uploadCover(Request $request, int $userId, int $id): void
    {
        $event = $this->ownedOrFail($id, $userId);
        $relPath = $this->storeEventImage($event, $request->file('file'), 'cover');

        if (!empty($event['cover_image'])) {
            $this->deletePhysicalFile($event['cover_image']);
        }
        $this->events->setCover($id, $relPath);

        $row = $this->events->findById($id);
        Response::success(['event' => $this->detailData($row, withPhotos: true)], 200);
    }

    /** DELETE /api/events/{id}/cover — rimuove la copertina personalizzata. */
    public function deleteCover(Request $request, int $userId, int $id): void
    {
        $event = $this->ownedOrFail($id, $userId);
        if (!empty($event['cover_image'])) {
            $this->deletePhysicalFile($event['cover_image']);
            $this->events->setCover($id, null);
        }
        $row = $this->events->findById($id);
        Response::success(['event' => $this->detailData($row, withPhotos: true)], 200);
    }

    /** POST /api/events/{id}/avatar — carica/sostituisce l'avatar dell'evento (immagine). */
    public function uploadAvatar(Request $request, int $userId, int $id): void
    {
        $event = $this->ownedOrFail($id, $userId);
        $relPath = $this->storeEventImage($event, $request->file('file'), 'avatar');

        if (!empty($event['avatar_image'])) {
            $this->deletePhysicalFile($event['avatar_image']);
        }
        $this->events->setAvatarImage($id, $relPath);

        $row = $this->events->findById($id);
        Response::success(['event' => $this->detailData($row, withPhotos: true)], 200);
    }

    /** DELETE /api/events/{id}/avatar — rimuove l'avatar immagine. */
    public function deleteAvatar(Request $request, int $userId, int $id): void
    {
        $event = $this->ownedOrFail($id, $userId);
        if (!empty($event['avatar_image'])) {
            $this->deletePhysicalFile($event['avatar_image']);
            $this->events->setAvatarImage($id, null);
        }
        $row = $this->events->findById($id);
        Response::success(['event' => $this->detailData($row, withPhotos: true)], 200);
    }

    /**
     * Valida un'immagine caricata (MIME, dimensione) e la salva in uploads/{slug}/
     * con nome random. Ritorna il path relativo. In caso d'errore risponde e termina.
     *
     * @param array<string,mixed>|null $file  Struttura di $_FILES['file']
     */
    private function storeEventImage(array $event, ?array $file, string $prefix): string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Response::error('VALIDATION_ERROR', 'Nessun file caricato', 422, ['file' => 'File obbligatorio']);
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                Response::error('VALIDATION_ERROR', 'Immagine troppo grande', 422, ['file' => 'Immagine troppo grande']);
            }
            Response::error('VALIDATION_ERROR', 'Caricamento fallito', 422, ['file' => 'Caricamento fallito']);
        }

        $maxSize = (int) \Snaply\Core\Env::get('MAX_UPLOAD_SIZE', '26214400');
        if (($file['size'] ?? 0) > $maxSize) {
            $mb = round($maxSize / (1024 * 1024));
            Response::error('VALIDATION_ERROR', "Immagine troppo grande (max {$mb} MB)", 422, ['file' => "Max {$mb} MB"]);
        }

        // MIME reale dal contenuto: solo immagini.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::COVER_MIME[$mime])) {
            Response::error('VALIDATION_ERROR', 'Formato non valido: usa JPG, PNG o WebP', 422, ['file' => 'Solo immagini JPG, PNG o WebP']);
        }

        $absDir = $this->uploadsBasePath() . '/' . $event['slug'];
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            error_log("Impossibile creare la cartella upload: $absDir");
            Response::error('SERVER_ERROR', 'Errore durante il salvataggio', 500);
        }

        $filename = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . self::COVER_MIME[$mime];
        $relPath = $event['slug'] . '/' . $filename;
        $absFile = $absDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $absFile)) {
            error_log("move_uploaded_file ($prefix) fallita");
            Response::error('SERVER_ERROR', 'Errore durante il salvataggio', 500);
        }

        // Privacy: rimuove i metadati EXIF (anche GPS) e normalizza l'orientamento.
        \Snaply\Core\Image::normalize($absFile, $mime);

        return $relPath;
    }

    // --- Helpers -------------------------------------------------------------

    /**
     * Recupera un evento verificando la proprietà.
     * 404 se non esiste, 403 se non appartiene all'utente.
     *
     * @return array<string,mixed>
     */
    private function ownedOrFail(int $id, int $userId): array
    {
        $event = $this->events->findById($id);
        if ($event === null) {
            Response::error('NOT_FOUND', 'Evento non trovato', 404);
        }
        if ((int) $event['user_id'] !== $userId) {
            Response::error('FORBIDDEN', 'Non hai accesso a questo evento', 403);
        }
        return $event;
    }

    /**
     * Valida e normalizza il payload evento.
     *
     * @param array<string,mixed> $body
     * @return array{title:?string,description:?string,event_date:?string,cover_color:string}
     */
    private function validatePayload(array $body, bool $required): array
    {
        $title = isset($body['title']) && is_string($body['title']) ? trim($body['title']) : null;
        $description = isset($body['description']) && is_string($body['description']) ? trim($body['description']) : null;
        $eventDate = isset($body['event_date']) && is_string($body['event_date']) && trim($body['event_date']) !== ''
            ? trim($body['event_date']) : null;
        $coverColor = isset($body['cover_color']) && is_string($body['cover_color']) && trim($body['cover_color']) !== ''
            ? trim($body['cover_color']) : null;
        // Emoji avatar: stringa breve, opzionale (vuota → null).
        $avatarEmoji = isset($body['avatar_emoji']) && is_string($body['avatar_emoji']) && trim($body['avatar_emoji']) !== ''
            ? mb_substr(trim($body['avatar_emoji']), 0, 8) : null;

        $v = new Validator();
        if ($required || array_key_exists('title', $body)) {
            $v->required('title', $title, 'Il titolo')->length('title', $title, 2, 150, 'Il titolo');
        }
        $v->maxLength('description', $description, 2000, 'La descrizione');
        $v->date('event_date', $eventDate);
        $v->hexColor('cover_color', $coverColor);

        if ($v->fails()) {
            Response::error('VALIDATION_ERROR', 'Dati non validi', 422, $v->errors());
        }

        return [
            'title'        => $title,
            'description'  => $description,
            'event_date'   => $eventDate,
            // Default brand se non fornito alla creazione.
            'cover_color'  => $coverColor ?? '#6C5CE7',
            'avatar_emoji' => $avatarEmoji,
        ];
    }

    /**
     * Costruisce la rappresentazione di dettaglio dell'evento.
     *
     * @param array<string,mixed> $row
     */
    private function detailData(array $row, bool $withPhotos = false): array
    {
        $id = (int) $row['id'];
        $data = [
            'id'              => $id,
            'title'           => $row['title'],
            'description'     => $row['description'],
            'slug'            => $row['slug'],
            'event_date'      => $row['event_date'],
            'cover_color'      => $row['cover_color'],
            'cover_image_url'  => Event::coverUrl($row['cover_image'] ?? null),
            'avatar_image_url' => Event::avatarUrl($row['avatar_image'] ?? null),
            'avatar_emoji'     => $row['avatar_emoji'] ?? null,
            'created_at'       => $row['created_at'],
            'photo_count'      => $this->events->photoCount($id),
        ];

        if ($withPhotos) {
            // Solo la prima pagina; le successive via GET /events/{id}/photos?page=N.
            $data['photos'] = array_map(
                [Photo::class, 'publicData'],
                $this->photos->allForEvent($id, self::PER_PAGE, 0)
            );
            $data['per_page'] = self::PER_PAGE;
        }

        return $data;
    }

    /** GET /api/events/{id}/photos?page=N — pagina di media (protetto, owner). */
    public function photos(Request $request, int $userId, int $id): void
    {
        $this->ownedOrFail($id, $userId);
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;
        $total = $this->photos->countForEvent($id);
        $photos = array_map(
            [Photo::class, 'publicData'],
            $this->photos->allForEvent($id, self::PER_PAGE, $offset)
        );
        Response::success([
            'photos'   => $photos,
            'page'     => $page,
            'per_page' => self::PER_PAGE,
            'total'    => $total,
            'has_more' => ($offset + count($photos)) < $total,
        ], 200);
    }

    /** Genera uno slug alfanumerico di 12 caratteri, garantito univoco. */
    private function generateUniqueSlug(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        do {
            $slug = '';
            for ($i = 0; $i < 12; $i++) {
                $slug .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ($this->events->slugExists($slug));
        return $slug;
    }

    private function uploadsBasePath(): string
    {
        $dir = \Snaply\Core\Env::get('UPLOAD_DIR', 'uploads');
        return dirname(__DIR__, 2) . '/' . trim($dir, '/');
    }

    private function deletePhysicalFile(string $filePath): void
    {
        $full = $this->uploadsBasePath() . '/' . ltrim($filePath, '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private function removeEventDir(string $slug): void
    {
        $dir = $this->uploadsBasePath() . '/' . $slug;
        if (is_dir($dir)) {
            @rmdir($dir); // rimossa solo se vuota
        }
    }
}
