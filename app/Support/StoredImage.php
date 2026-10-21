<?php

namespace App\Support;

class StoredImage
{
    public static function uri(?string $stored): ?string
    {
        $payload = self::payload($stored);
        if ($payload === null) {
            return null;
        }

        $mime = self::mimeOf($stored);
        if ($mime === null) {
            return null;
        }

        return 'data:'.$mime.';base64,'.$payload;
    }

    public static function mimeOf(?string $stored): ?string
    {
        $payload = self::payload($stored);
        if ($payload === null) {
            return null;
        }

        $bytes = base64_decode($payload, true);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return self::mime($bytes);
    }

    public static function bytes(?string $stored): ?string
    {
        $payload = self::payload($stored);
        if ($payload === null || self::mimeOf($stored) === null) {
            return null;
        }

        $bytes = base64_decode($payload, true);

        return $bytes === false || $bytes === '' ? null : $bytes;
    }

    public static function normalize(?string $stored): ?string
    {
        $payload = self::payload($stored);
        if ($payload === null) {
            return null;
        }

        return base64_decode($payload, true) === false ? null : $payload;
    }

    private static function payload(?string $stored): ?string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }

        if (str_starts_with($stored, 'data:')) {
            $stored = (string) preg_replace('/^data:[^,]*,/', '', $stored);
        }

        return preg_replace('/\s+/', '', $stored) ?: null;
    }

    private static function mime(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, "\x89PNG") => 'image/png',
            str_starts_with($bytes, 'GIF8') => 'image/gif',
            str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 16), 'WEBP') => 'image/webp',
            default => null,
        };
    }
}
