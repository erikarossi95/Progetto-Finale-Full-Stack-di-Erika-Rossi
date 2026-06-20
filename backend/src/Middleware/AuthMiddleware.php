<?php

declare(strict_types=1);

namespace Snaply\Middleware;

use Snaply\Core\Jwt;
use Snaply\Core\Request;
use Snaply\Core\Response;

/**
 * Protegge le rotte autenticate: estrae il bearer token, lo verifica
 * e restituisce l'id dell'utente. Token assente/invalido/scaduto → 401.
 */
final class AuthMiddleware
{
    /**
     * Richiede un token valido. In caso di fallimento risponde 401 e termina.
     *
     * @return int L'id dell'utente autenticato (claim "sub")
     */
    public static function requireUser(Request $request): int
    {
        $token = $request->bearerToken();
        if ($token === null) {
            Response::error('UNAUTHORIZED', 'Token di autenticazione assente', 401);
        }

        $payload = Jwt::decode($token);
        if ($payload === null || !isset($payload['sub'])) {
            Response::error('UNAUTHORIZED', 'Token non valido o scaduto', 401);
        }

        return (int) $payload['sub'];
    }
}
