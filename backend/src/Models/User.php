<?php

declare(strict_types=1);

namespace Snaply\Models;

use PDO;
use Snaply\Config\Database;

/**
 * Accesso alla tabella `users`. Query sempre parametrizzate.
 */
final class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Crea un utente e ritorna l'id. La password arriva già hashata. */
    public function create(string $name, string $email, string $passwordHash): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)'
        );
        $stmt->execute([
            ':name'  => $name,
            ':email' => $email,
            ':hash'  => $passwordHash,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Esiste già un utente con questa email (escludendo facoltativamente un id)? */
    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = :email';
        $params = [':email' => $email];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $exceptId;
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    /**
     * Aggiorna i campi indicati (name/email/password_hash). Solo i presenti.
     *
     * @param array<string,string> $fields
     */
    public function update(int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $allowed = ['name', 'email', 'password_hash'];
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
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }

    /**
     * Rappresentazione pubblica di un utente (mai l'hash password).
     *
     * @param array<string,mixed> $row
     * @return array{id:int,name:string,email:string}
     */
    public static function publicData(array $row): array
    {
        return [
            'id'    => (int) $row['id'],
            'name'  => $row['name'],
            'email' => $row['email'],
        ];
    }
}
