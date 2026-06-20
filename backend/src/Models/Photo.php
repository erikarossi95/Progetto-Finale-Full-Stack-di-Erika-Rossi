<?php

declare(strict_types=1);

namespace Snaply\Models;

use PDO;
use Snaply\Config\Database;
use Snaply\Core\Env;

/**
 * Accesso alla tabella `photos`.
 */
final class Photo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Foto di un evento, dalla più recente. Con $limit applica la paginazione.
     *
     * @return array<int,array<string,mixed>>
     */
    public function allForEvent(int $eventId, ?int $limit = null, int $offset = 0): array
    {
        $sql = 'SELECT * FROM photos WHERE event_id = :eid ORDER BY created_at DESC, id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT :lim OFFSET :off';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':eid', $eventId, PDO::PARAM_INT);
        if ($limit !== null) {
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Numero totale di foto/video di un evento. */
    public function countForEvent(int $eventId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM photos WHERE event_id = :eid');
        $stmt->execute([':eid' => $eventId]);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM photos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Foto con info di proprietà (join su events) — usato per autorizzare la delete.
     *
     * @return array<string,mixed>|null
     */
    public function findWithOwner(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, e.user_id AS owner_id
               FROM photos p
               JOIN events e ON e.id = p.event_id
              WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array{event_id:int,file_path:string,thumb_path:?string,file_type:string,original_name:?string,uploader_name:?string,size_bytes:?int} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO photos (event_id, file_path, thumb_path, file_type, original_name, uploader_name, size_bytes)
             VALUES (:eid, :path, :thumb, :type, :orig, :uploader, :size)'
        );
        $stmt->execute([
            ':eid'      => $data['event_id'],
            ':path'     => $data['file_path'],
            ':thumb'    => $data['thumb_path'] ?? null,
            ':type'     => $data['file_type'],
            ':orig'     => $data['original_name'],
            ':uploader' => $data['uploader_name'],
            ':size'     => $data['size_bytes'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM photos WHERE id = :id')->execute([':id' => $id]);
    }

    /** Incrementa di 1 i "cuori" della foto. */
    public function like(int $id): void
    {
        $this->db->prepare('UPDATE photos SET likes = likes + 1 WHERE id = :id')->execute([':id' => $id]);
    }

    /** Decrementa i "cuori" (mai sotto zero). */
    public function unlike(int $id): void
    {
        // IF evita l'underflow su colonna UNSIGNED.
        $this->db->prepare('UPDATE photos SET likes = IF(likes > 0, likes - 1, 0) WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * Rappresentazione pubblica di una foto. Costruisce file_url da file_path.
     *
     * @param array<string,mixed> $row
     */
    public static function publicData(array $row): array
    {
        // thumb_url: miniatura se disponibile, altrimenti l'originale (retrocompat).
        $thumb = !empty($row['thumb_path']) ? self::fileUrl($row['thumb_path']) : self::fileUrl($row['file_path']);
        return [
            'id'            => (int) $row['id'],
            'file_url'      => self::fileUrl($row['file_path']),
            'thumb_url'     => $thumb,
            'file_type'     => $row['file_type'],
            'uploader_name' => $row['uploader_name'],
            'likes'         => (int) ($row['likes'] ?? 0),
            'created_at'    => $row['created_at'] ?? null,
        ];
    }

    /** Costruisce l'URL pubblico del file a partire dal path relativo salvato. */
    public static function fileUrl(string $filePath): string
    {
        $dir = Env::get('UPLOAD_DIR', 'uploads');
        return '/' . trim($dir, '/') . '/' . ltrim($filePath, '/');
    }
}
