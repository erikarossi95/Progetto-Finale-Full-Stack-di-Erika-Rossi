<?php

declare(strict_types=1);

namespace Snaply\Core;

/**
 * Costruisce le risposte JSON con l'envelope standard dell'API.
 * Successo: { success: true, data: {...} }
 * Errore:   { success: false, error: { code, message, fields? } }
 */
final class Response
{
    /** Invia una risposta di successo e termina lo script. */
    public static function success(mixed $data = null, int $status = 200): never
    {
        self::send($status, [
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Invia una risposta d'errore e termina lo script.
     *
     * @param array<string,string>|null $fields Errori per-campo (solo validazione)
     */
    public static function error(
        string $code,
        string $message,
        int $status,
        ?array $fields = null
    ): never {
        $error = [
            'code'    => $code,
            'message' => $message,
        ];
        if ($fields !== null) {
            $error['fields'] = $fields;
        }

        self::send($status, [
            'success' => false,
            'error'   => $error,
        ]);
    }

    /** Serializza e invia il payload con lo status indicato. */
    private static function send(int $status, array $payload): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
