<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class SupabaseStorage
{
    public static function musicUrl(?string $path): ?string
    {
        return self::temporaryUrl('supabase_music', 'music', $path)
            ?? self::publicUrl('music', $path);
    }

    public static function publicMusicUrl(?string $path): ?string
    {
        return self::publicUrl('music', $path);
    }

    public static function imageUrl(?string $path): ?string
    {
        return self::publicUrl('images', $path);
    }

    public static function legacyStorageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $normalizedPath = ltrim($path, '/');

        if (str_starts_with($normalizedPath, 'storage/')) {
            $normalizedPath = substr($normalizedPath, strlen('storage/'));
        }

        $musicPrefixes = ['tracks/', 'audio/', 'music/'];

        foreach ($musicPrefixes as $prefix) {
            if (str_starts_with($normalizedPath, $prefix)) {
                return self::musicUrl($normalizedPath);
            }
        }

        return self::imageUrl($normalizedPath);
    }

    private static function publicUrl(string $bucket, ?string $path): ?string
    {
        $normalized = self::normalizeObjectPath($bucket, $path);

        if ($normalized === null) {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        $baseUrl = rtrim((string) config('filesystems.supabase_public_url'), '/');

        if ($baseUrl === '') {
            return null;
        }

        return $baseUrl.'/storage/v1/object/public/'.$bucket.'/'.ltrim($normalized, '/');
    }

    private static function temporaryUrl(string $disk, string $bucket, ?string $path): ?string
    {
        $normalized = self::normalizeObjectPath($bucket, $path);

        if ($normalized === null) {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        try {
            $minutes = (int) config('filesystems.supabase_signed_url_minutes', 10080);

            return Storage::disk($disk)->temporaryUrl(
                ltrim($normalized, '/'),
                now()->addMinutes(max($minutes, 1))
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalizeObjectPath(string $bucket, ?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $normalized = trim($path);

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            $urlPath = parse_url($normalized, PHP_URL_PATH);

            if (!is_string($urlPath) || !str_starts_with($urlPath, '/storage/')) {
                return $normalized;
            }

            $signedPrefix = '/storage/v1/object/sign/';
            if (str_starts_with($urlPath, $signedPrefix)) {
                return $normalized;
            }

            $publicPrefix = '/storage/v1/object/public/'.$bucket.'/';
            if (str_starts_with($urlPath, $publicPrefix)) {
                return substr($urlPath, strlen($publicPrefix));
            }

            $normalized = substr($urlPath, strlen('/storage/'));
        }

        if (str_starts_with($normalized, '/storage/')) {
            $normalized = substr($normalized, strlen('/storage/'));
        } elseif (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        $publicPrefix = 'v1/object/public/'.$bucket.'/';
        if (str_starts_with($normalized, $publicPrefix)) {
            $normalized = substr($normalized, strlen($publicPrefix));
        }

        return ltrim($normalized, '/');
    }
}
