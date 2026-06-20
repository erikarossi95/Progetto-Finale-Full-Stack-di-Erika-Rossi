<?php

declare(strict_types=1);

namespace Snaply\Core;

/**
 * Helper di validazione leggero. Accumula errori per-campo e li espone
 * nel formato `fields` dell'envelope d'errore.
 *
 * Uso tipico:
 *   $v = new Validator();
 *   $v->required('email', $email)->email('email', $email);
 *   if ($v->fails()) Response::error('VALIDATION_ERROR', '...', 422, $v->errors());
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** Registra un errore solo se il campo non ne ha già uno (primo errore vince). */
    private function add(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function required(string $field, mixed $value, string $label): self
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->add($field, "$label è obbligatorio");
        }
        return $this;
    }

    public function length(string $field, ?string $value, int $min, int $max, string $label): self
    {
        if ($value === null) {
            return $this;
        }
        $len = mb_strlen(trim($value));
        if ($len < $min || $len > $max) {
            $this->add($field, "$label deve avere tra $min e $max caratteri");
        }
        return $this;
    }

    public function maxLength(string $field, ?string $value, int $max, string $label): self
    {
        if ($value !== null && mb_strlen($value) > $max) {
            $this->add($field, "$label non può superare $max caratteri");
        }
        return $this;
    }

    public function email(string $field, ?string $value): self
    {
        if ($value !== null && trim($value) !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->add($field, 'Email non valida');
        }
        return $this;
    }

    public function minPassword(string $field, ?string $value, int $min = 8): self
    {
        if ($value !== null && mb_strlen($value) < $min) {
            $this->add($field, "La password deve avere almeno $min caratteri");
        }
        return $this;
    }

    /** Verifica il formato data YYYY-MM-DD (se valorizzato). */
    public function date(string $field, ?string $value): self
    {
        if ($value === null || trim($value) === '') {
            return $this;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$d || $d->format('Y-m-d') !== $value) {
            $this->add($field, 'Data non valida (formato richiesto YYYY-MM-DD)');
        }
        return $this;
    }

    /** Verifica il formato colore hex #RRGGBB (se valorizzato). */
    public function hexColor(string $field, ?string $value): self
    {
        if ($value !== null && trim($value) !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            $this->add($field, 'Colore non valido (formato richiesto #RRGGBB)');
        }
        return $this;
    }
}
