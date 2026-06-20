<?php

declare(strict_types=1);

namespace Snaply\Tests\Core;

use PHPUnit\Framework\TestCase;
use Snaply\Core\RateLimiter;

final class RateLimiterTest extends TestCase
{
    public function testBlocksAfterMaxInWindow(): void
    {
        // Chiave unica per test isolato.
        $key = 'test:' . bin2hex(random_bytes(6));
        $max = 3;

        // Le prime $max richieste passano.
        for ($i = 0; $i < $max; $i++) {
            $this->assertFalse(RateLimiter::tooMany($key, $max, 60), "colpo $i non deve bloccare");
        }
        // La successiva supera il limite.
        $this->assertTrue(RateLimiter::tooMany($key, $max, 60));
    }

    public function testWindowResetAllowsAgain(): void
    {
        $key = 'test:' . bin2hex(random_bytes(6));
        // Finestra di 0 secondi: ogni colpo è una nuova finestra → mai bloccato.
        $this->assertFalse(RateLimiter::tooMany($key, 1, 0));
        $this->assertFalse(RateLimiter::tooMany($key, 1, 0));
    }

    public function testKeysAreIndependent(): void
    {
        $a = 'test:' . bin2hex(random_bytes(6));
        $b = 'test:' . bin2hex(random_bytes(6));
        $this->assertFalse(RateLimiter::tooMany($a, 1, 60));
        // Chiave diversa: contatore separato.
        $this->assertFalse(RateLimiter::tooMany($b, 1, 60));
        // Stessa chiave A oltre il limite.
        $this->assertTrue(RateLimiter::tooMany($a, 1, 60));
    }
}
