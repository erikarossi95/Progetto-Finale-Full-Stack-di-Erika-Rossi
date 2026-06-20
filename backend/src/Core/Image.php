<?php

declare(strict_types=1);

namespace Snaply\Core;

/**
 * Utility immagini basate su GD: normalizzazione (rimozione metadati EXIF con
 * applicazione dell'orientamento) e generazione di miniature.
 *
 * Nota privacy: ri-codificare l'immagine con GD elimina i metadati EXIF
 * (compresa la geolocalizzazione). Poiché GD non applica l'orientamento EXIF,
 * lo "cuociamo" nei pixel prima di salvare, così le foto da telefono non
 * risultano ruotate.
 */
final class Image
{
    /** MIME supportati → loader/saver GD. */
    private const SUPPORTED = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Ri-salva l'immagine senza metadati EXIF, applicando l'orientamento.
     * Operazione "best effort": in caso di problemi lascia il file invariato.
     */
    public static function normalize(string $absPath, string $mime): void
    {
        if (!extension_loaded('gd') || !in_array($mime, self::SUPPORTED, true)) {
            return;
        }
        $img = self::load($absPath, $mime);
        if (!$img) {
            return;
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($absPath);
            $img = self::applyOrientation($img, (int) ($exif['Orientation'] ?? 1));
        }

        self::preserveAlpha($img, $mime);
        self::save($img, $absPath, $mime, quality: 90);
        imagedestroy($img);
    }

    /**
     * Genera una miniatura (lato più lungo = $max) preservando formato e alpha.
     * Ritorna true se la miniatura è stata creata.
     */
    public static function thumbnail(string $srcAbs, string $mime, string $destAbs, int $max): bool
    {
        if (!extension_loaded('gd') || !in_array($mime, self::SUPPORTED, true)) {
            return false;
        }
        $img = self::load($srcAbs, $mime);
        if (!$img) {
            return false;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $max / max($w, $h));
        if ($scale >= 1) {
            imagedestroy($img); // già piccola: si usa l'originale
            return false;
        }
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $thumb = imagecreatetruecolor($nw, $nh);
        self::preserveAlpha($thumb, $mime);
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $ok = self::save($thumb, $destAbs, $mime, quality: 82);
        imagedestroy($img);
        imagedestroy($thumb);
        return $ok;
    }

    // --- interni ---

    private static function load(string $path, string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => false,
        };
    }

    private static function save(\GdImage $img, string $path, string $mime, int $quality): bool
    {
        return (bool) match ($mime) {
            'image/jpeg' => imagejpeg($img, $path, $quality),
            'image/png'  => imagepng($img, $path, 6),
            'image/webp' => imagewebp($img, $path, $quality),
            default      => false,
        };
    }

    private static function preserveAlpha(\GdImage $img, string $mime): void
    {
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
    }

    /** Applica l'orientamento EXIF (1–8) ai pixel. */
    private static function applyOrientation(\GdImage $img, int $orientation): \GdImage
    {
        switch ($orientation) {
            case 2:
                imageflip($img, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $img = imagerotate($img, 180, 0);
                break;
            case 4:
                imageflip($img, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $img = imagerotate($img, -90, 0);
                imageflip($img, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $img = imagerotate($img, -90, 0);
                break;
            case 7:
                $img = imagerotate($img, 90, 0);
                imageflip($img, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $img = imagerotate($img, 90, 0);
                break;
        }
        return $img;
    }
}
