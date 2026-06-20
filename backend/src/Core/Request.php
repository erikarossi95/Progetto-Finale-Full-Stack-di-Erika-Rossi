<?php

declare(strict_types=1);

namespace Snaply\Core;

/**
 * Incapsula l'accesso ai dati della richiesta HTTP:
 * metodo, path, body JSON, file caricati, header.
 */
final class Request
{
    private ?array $jsonBody = null;
    private bool $jsonParsed = false;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Path normalizzato senza query string (es. /api/events/3). */
    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        // Rimuove trailing slash (tranne la root).
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    /**
     * Corpo JSON decodificato come array associativo.
     * Ritorna null se il body non è JSON valido.
     */
    public function json(): ?array
    {
        if ($this->jsonParsed) {
            return $this->jsonBody;
        }
        $this->jsonParsed = true;

        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($raw, true);
        $this->jsonBody = is_array($decoded) ? $decoded : null;
        return $this->jsonBody;
    }

    /** Singolo valore dal body JSON. */
    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->json() ?? [];
        return $body[$key] ?? $default;
    }

    /** Valore dalla query string ($_GET). */
    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /** Valore da $_POST (per multipart/form-data). */
    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /** File caricato (struttura di $_FILES) o null. */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /** Header Authorization grezzo, gestendo varianti di server. */
    public function authorizationHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ($header === null && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = $value;
                    break;
                }
            }
        }

        return $header;
    }

    /** Estrae il token bearer dall'header Authorization. */
    public function bearerToken(): ?string
    {
        $header = $this->authorizationHeader();
        if ($header !== null && preg_match('/Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
