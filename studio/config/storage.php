<?php

declare(strict_types=1);

use App\Core\Config;

$root = Config::env('STORAGE_PATH', dirname(__DIR__) . '/storage/private');

return [
    // Every one of these directories MUST live outside the document root.
    'root'       => rtrim((string) $root, '/'),
    'originals'  => rtrim((string) $root, '/') . '/originals',
    'previews'   => rtrim((string) $root, '/') . '/previews',
    'thumbnails' => rtrim((string) $root, '/') . '/thumbnails',
    'temporary'  => rtrim((string) $root, '/') . '/temporary',
    'logs'       => dirname(__DIR__) . '/storage/logs',

    'max_upload_bytes'   => Config::envInt('UPLOAD_MAX_BYTES', 100 * 1024 * 1024),
    'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp', 'image/tiff', 'image/heic', 'image/heif'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff', 'heic', 'heif'],

    'thumbnail_width' => Config::envInt('THUMBNAIL_WIDTH', 400),
    'preview_width'   => Config::envInt('PREVIEW_WIDTH', 1600),
    'preview_quality' => Config::envInt('PREVIEW_QUALITY', 82),
    'thumbnail_quality' => Config::envInt('THUMBNAIL_QUALITY', 78),

    // A WebP companion is written beside each JPEG rendition when the server
    // can produce one, and served only to browsers that accept the format.
    // Roughly a third lighter, for about a third more derivative storage.
    'webp_enabled' => Config::envBool('WEBP_ENABLED', true),
    'webp_quality' => Config::envInt('WEBP_QUALITY', 80),

    // Temporary ZIP archives are deleted after this many seconds.
    'zip_ttl_seconds' => Config::envInt('ZIP_TTL_SECONDS', 3600),
    'zip_max_bytes'   => Config::envInt('ZIP_MAX_BYTES', 4 * 1024 * 1024 * 1024),
];
