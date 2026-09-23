<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Repositories\DownloadLogRepository;

/**
 * File delivery.
 *
 * Every byte a client receives goes through here, after GalleryAccessService
 * has granted access. The filesystem path is resolved from the database row
 * and never from anything the client sent, and the response is streamed in
 * chunks so that a 60 MB original does not have to fit in PHP's memory limit.
 */
final class DownloadService
{
    /** Chunk size for streamed reads. */
    private const CHUNK_BYTES = 262144;

    public function __construct(
        private ?StorageService $storage = null,
        private ?DownloadLogRepository $downloads = null
    ) {
        $this->storage = $storage ?? new StorageService();
        $this->downloads = $downloads ?? new DownloadLogRepository();
    }

    /**
     * Stream a stored file.
     *
     * @param string $disposition 'inline' for gallery display, 'attachment' to download.
     */
    public function serve(
        string $relativePath,
        string $mimeType,
        string $downloadFilename,
        string $disposition = 'inline',
        bool $cacheable = true,
        ?Request $request = null
    ): Response {
        if (!$this->storage->exists($relativePath)) {
            throw new HttpException(404, 'Fichier introuvable.');
        }

        $absolute = $this->storage->absolute($relativePath);
        $size = (int) (@filesize($absolute) ?: 0);
        $lastModified = (int) (@filemtime($absolute) ?: time());
        $etag = '"' . md5($relativePath . '|' . $size . '|' . $lastModified) . '"';

        $headers = [
            'Content-Type'        => $mimeType,
            'Content-Length'      => (string) $size,
            'Content-Disposition' => $this->contentDisposition($disposition, $downloadFilename),
            // Without nosniff a browser could decide an "image" is HTML and
            // execute it in the site's origin.
            'X-Content-Type-Options' => 'nosniff',
            'Last-Modified'       => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
            'ETag'                => $etag,
            'Accept-Ranges'       => 'none',
        ];

        // Private, not public: a shared cache must never hold a client's
        // photographs where another visitor's request could be served from it.
        $headers['Cache-Control'] = $cacheable
            ? 'private, max-age=3600, no-transform'
            : 'private, no-store, no-cache, must-revalidate';

        if ($cacheable && $request !== null && $this->isNotModified($request, $etag, $lastModified)) {
            return Response::make('', 304, $headers);
        }

        return Response::stream(function () use ($absolute): void {
            $this->streamFile($absolute);
        }, 200, $headers);
    }

    private function isNotModified(Request $request, string $etag, int $lastModified): bool
    {
        $ifNoneMatch = $request->header('If-None-Match');

        if (is_string($ifNoneMatch) && trim($ifNoneMatch) !== '') {
            foreach (array_map('trim', explode(',', $ifNoneMatch)) as $candidate) {
                if ($candidate === $etag || $candidate === 'W/' . $etag || $candidate === '*') {
                    return true;
                }
            }
        }

        $ifModifiedSince = $request->header('If-Modified-Since');

        if (is_string($ifModifiedSince) && trim($ifModifiedSince) !== '') {
            $timestamp = strtotime($ifModifiedSince);

            return $timestamp !== false && $timestamp >= $lastModified;
        }

        return false;
    }

    private function streamFile(string $absolute): void
    {
        // Any buffer still open would hold the whole file in memory before
        // sending a byte, which defeats the point of streaming.
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $handle = @fopen($absolute, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, self::CHUNK_BYTES);

                if ($chunk === false) {
                    break;
                }

                echo $chunk;
                flush();

                // Stop burning CPU and bandwidth when the client walks away
                // mid-download, which is common on mobile.
                if (connection_aborted() !== 0) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * RFC 6266 Content-Disposition with a UTF-8 filename.
     *
     * Non-ASCII filenames need the filename* form; the plain filename is kept
     * as an ASCII fallback for older clients.
     */
    private function contentDisposition(string $disposition, string $filename): string
    {
        $disposition = $disposition === 'attachment' ? 'attachment' : 'inline';

        $ascii = (string) preg_replace('/[^\x20-\x7E]/', '_', $filename);
        $ascii = str_replace(['"', '\\', ';'], '_', $ascii);

        if ($ascii === '') {
            $ascii = 'photo';
        }

        return sprintf(
            '%s; filename="%s"; filename*=UTF-8\'\'%s',
            $disposition,
            $ascii,
            rawurlencode($filename)
        );
    }

    /** Record a single-photo download. */
    public function logPhotoDownload(int $galleryId, int $photoId, ?int $tokenId, int $bytes, Request $request): void
    {
        $this->log($galleryId, $photoId, $tokenId, 'single', 1, $bytes, $request);
    }

    /** Record a ZIP download covering several photos. */
    public function logArchiveDownload(
        int $galleryId,
        ?int $tokenId,
        int $photoCount,
        int $bytes,
        Request $request,
        string $kind = 'zip'
    ): void {
        $this->log($galleryId, null, $tokenId, $kind, $photoCount, $bytes, $request);
    }

    private function log(
        int $galleryId,
        ?int $photoId,
        ?int $tokenId,
        string $kind,
        int $photoCount,
        int $bytes,
        Request $request
    ): void {
        $this->downloads->insert([
            'gallery_id'  => $galleryId,
            'photo_id'    => $photoId,
            'token_id'    => $tokenId,
            'kind'        => $kind,
            'photo_count' => $photoCount,
            'bytes'       => $bytes,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
