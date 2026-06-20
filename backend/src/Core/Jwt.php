<?php

declare(strict_types=1);

namespace Snaply\Core;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Throwable;

/**
 * Wrapper sottile su firebase/php-jwt.
 * Algoritmo HS256, secret e scadenza letti dall'ambiente.
 */
final class Jwt
{
    private const ALGO = 'HS256';

    /**
     * Crea un token per l'utente indicato.
     *
     * @return string Il JWT firmato
     */
    public static function encode(int $userId, string $name): string
    {
        $now = time();
        $expiry = (int) (Env::get('JWT_EXPIRY', '86400'));

        $payload = [
            'sub'  => $userId,
            'name' => $name,
            'iat'  => $now,
            'exp'  => $now + $expiry,
        ];

        return FirebaseJwt::encode($payload, self::secret(), self::ALGO);
    }

    /**
     * Decodifica e verifica un token.
     *
     * @return array{sub:int,name:string,iat:int,exp:int}|null  Payload o null se invalido/scaduto
     */
    public static function decode(string $token): ?array
    {
        try {
            $decoded = FirebaseJwt::decode($token, new Key(self::secret(), self::ALGO));
            return (array) $decoded;
        } catch (Throwable $e) {
            // Firma errata, token scaduto o malformato.
            return null;
        }
    }

    private static function secret(): string
    {
        $secret = Env::get('JWT_SECRET');
        if ($secret === null || $secret === '') {
            // Fail-safe: senza secret non si firma nulla.
            throw new \RuntimeException('JWT_SECRET non configurato');
        }
        return $secret;
    }
}
