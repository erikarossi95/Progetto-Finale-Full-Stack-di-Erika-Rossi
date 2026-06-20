<?php

declare(strict_types=1);

namespace Snaply\Core;

/**
 * Caricatore minimale per file .env (niente dipendenze esterne).
 * Popola getenv()/$_ENV con le coppie chiave=valore trovate.
 */
final class Env
{
    /** Carica il file .env indicato, se esiste. */
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Salta commenti e righe senza '='.
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Rimuove eventuali apici di contorno.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    /** Legge una variabile d'ambiente con default opzionale. */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}
