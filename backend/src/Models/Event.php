<?php

declare(strict_types=1);

namespace Snaply\Models;

use PDO;
use Snaply\Config\Database;

/**
 * Accesso alla tabella `events`. Tutte le letture "private" filtrano per user_id;
 * l'accesso pubblico avviene solo per slug.
 */
final class Event
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Lista eventi di un utente con conteggio foto.
     *
     * @return array<int,array<string,mixed>>
     */
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT e.*, COUNT(p.id) AS photo_count
               FROM events e
          LEFT JOIN photos p ON p.event_id = e.id
              WHERE e.user_id = :uid
           GROUP BY e.id
           ORDER BY e.created_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM events WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM events WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        return (bool) $stmt->fetch();
    }

    public function photoCount(int $eventId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM photos WHERE event_id = :eid');
        $stmt->execute([':eid' => $eventId]);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /**
     * Crea un evento e ritorna l'id.
     *
     * @param array{title:string,description:?string,event_date:?string,cover_color:string,slug:string} $data
     */
    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO events (user_id, title, description, event_date, slug, cover_color, avatar_emoji)
             VALUES (:uid, :title, :description, :event_date, :slug, :cover_color, :avatar_emoji)'
        );
        $stmt->execute([
            ':uid'          => $userId,
            ':title'        => $data['title'],
            ':description'  => $data['description'],
            ':event_date'   => $data['event_date'],
            ':slug'         => $data['slug'],
            ':cover_color'  => $data['cover_color'],
            ':avatar_emoji' => $data['avatar_emoji'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Aggiorna i campi presenti (whitelist title/description/event_date/cover_color).
     *
     * @param array<string,mixed> $fields
     */
    public function update(int $id, array $fields): void
    {
        $allowed = ['title', 'description', 'event_date', 'cover_color', 'avatar_emoji'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($fields as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $sets[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE events SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM events WHERE id = :id')->execute([':id' => $id]);
    }

    /** Imposta (o azzera con null) il path della copertina. */
    public function setCover(int $id, ?string $path): void
    {
        $this->db->prepare('UPDATE events SET cover_image = :path WHERE id = :id')
            ->execute([':path' => $path, ':id' => $id]);
    }

    /** Imposta (o azzera con null) il path dell'avatar immagine. */
    public function setAvatarImage(int $id, ?string $path): void
    {
        $this->db->prepare('UPDATE events SET avatar_image = :path WHERE id = :id')
            ->execute([':path' => $path, ':id' => $id]);
    }

    /** URL pubblico di un media dell'evento (copertina/avatar) dal path salvato, o null. */
    public static function coverUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $dir = \Snaply\Core\Env::get('UPLOAD_DIR', 'uploads');
        return '/' . trim($dir, '/') . '/' . ltrim($path, '/');
    }

    /** Alias semantico per l'avatar (stessa trasformazione path → URL). */
    public static function avatarUrl(?string $path): ?string
    {
        return self::coverUrl($path);
    }

    /**
     * Rappresentazione "lista" (senza foto).
     *
     * @param array<string,mixed> $row
     */
    public static function listData(array $row): array
    {
        return [
            'id'               => (int) $row['id'],
            'title'            => $row['title'],
            'description'      => $row['description'],
            'slug'             => $row['slug'],
            'event_date'       => $row['event_date'],
            'cover_color'      => $row['cover_color'],
            'cover_image_url'  => self::coverUrl($row['cover_image'] ?? null),
            'avatar_image_url' => self::avatarUrl($row['avatar_image'] ?? null),
            'avatar_emoji'     => $row['avatar_emoji'] ?? null,
            'photo_count'      => (int) ($row['photo_count'] ?? 0),
            'created_at'       => $row['created_at'],
        ];
    }
}
