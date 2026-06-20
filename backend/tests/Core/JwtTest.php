<?php

declare(strict_types=1);

namespace Snaply\Tests\Core;

use PHPUnit\Framework\TestCase;
use Snaply\Core\Jwt;

final class JwtTest extends TestCase
{
    public function testEncodeDecodeRoundtrip(): void
    {
        $token = Jwt::encode(42, 'Erika');
        $payload = Jwt::decode($token);

        $this->assertIsArray($payload);
        $this->assertSame(42, (int) $payload['sub']);
        $this->assertSame('Erika', $payload['name']);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function testDecodeRejectsTamperedToken(): void
    {
        $token = Jwt::encode(1, 'Tizio');
        // Altera l'ultimo carattere della firma.
        $tampered = substr($token, 0, -1) . ($token[strlen($token) - 1] === 'a' ? 'b' : 'a');
        $this->assertNull(Jwt::decode($tampered));
    }

    public function testDecodeRejectsGarbage(): void
    {
        $this->assertNull(Jwt::decode('not-a-jwt'));
        $this->assertNull(Jwt::decode(''));
    }
}
