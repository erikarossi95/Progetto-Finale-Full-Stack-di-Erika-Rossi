<?php

declare(strict_types=1);

namespace Snaply\Core;

/**
 * Rate limiter a finestra fissa, basato su file (zero dipendenze).
 * Pensato per gli endpoint pubblici (upload, like) per limitare gli abusi.
 *
 * Strategia "fail-open": se lo storage non è disponibile non blocca le
 * richieste legittime (preferiamo la disponibilità a un blocco erroneo).
 */
final class RateLimiter
{
    /**
     * Registra un colpo per la chiave e dice se il limite è stato superato.
     *
     * @param string $key    Identificatore (es. "upload:1.2.3.4")
     * @param int    $max    Numero massimo di richieste nella finestra
     * @param int    $window Ampiezza finestra in secondi
     * @return bool true se la richiesta SUPERA il limite (va bloccata)
     */
    public static function tooMany(string $key, int $max, int $window): bool
    {
        $dir = dirname(__DIR__, 2) . '/storage/ratelimit';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false; // fail-open
        }

        // Pulizia probabilistica (1%) dei file scaduti, per non accumulare.
        if (random_int(1, 100) === 1) {
            self::gc($dir);
        }

        $file = $dir . '/' . hash('sha256', $key) . '.json';
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return false; // fail-open
        }

        try {
            flock($fp, LOCK_EX);
            $now = time();
            $raw = stream_get_contents($fp) ?: '';
            $data = json_decode($raw, true);
            if (!is_array($data) || (int) ($data['reset'] ?? 0) <= $now) {
                $data = ['count' => 0, 'reset' => $now + $window];
            }
            $data['count'] = (int) $data['count'] + 1;
            $blocked = $data['count'] > $max;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            return $blocked;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** IP del client (REMOTE_ADDR). */
    public static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /** Rimuove i file di finestre già scadute. */
    private static function gc(string $dir): void
    {
        $now = time();
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            $data = json_decode(@file_get_contents($f) ?: '', true);
            if (!is_array($data) || (int) ($data['reset'] ?? 0) < $now) {
                @unlink($f);
            }
        }
    }
}
