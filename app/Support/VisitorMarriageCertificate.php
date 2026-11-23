<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class VisitorMarriageCertificate
{
    public const DISK = 'private';

    public const DIRECTORY = 'visitor-marriage-certificates';

    public static function response(?string $path): Response
    {
        abort_unless(self::isValidPath($path), 404);

        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function isValidPath(?string $path): bool
    {
        return is_string($path)
            && preg_match('#^'.self::DIRECTORY.'/[A-Za-z0-9._-]+\.(?:jpe?g|png|webp)$#i', $path) === 1;
    }
}
