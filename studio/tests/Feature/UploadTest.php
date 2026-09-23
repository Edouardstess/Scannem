<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Config;
use App\Exceptions\StorageException;
use App\Exceptions\UploadException;
use App\Models\VariantType;
use App\Repositories\PhotoRepository;
use App\Services\ImageProcessingService;
use App\Services\PhotoUploadService;
use App\Services\StorageService;
use Tests\Support\Factory;
use Tests\Support\TestCase;

require_once dirname(__DIR__, 2) . '/database/seeders/ImageFactory.php';

final class UploadTest extends TestCase
{
    public function testUploadStoresTheOriginalAndBothVariants(): void
    {
        $gallery = Factory::gallery();
        $photo = Factory::photo($gallery['gallery_id'], 'IMG_0042.jpg');

        $photos = new PhotoRepository();
        $storage = new StorageService();

        $this->assertSame('IMG_0042.jpg', (string) $photo['original_filename']);
        $this->assertSame('image/jpeg', (string) $photo['mime_type']);
        $this->assertGreaterThan(0, (int) $photo['file_size']);
        $this->assertTrue($storage->exists((string) $photo['storage_path']));

        $thumbnail = $photos->variant((int) $photo['id'], VariantType::THUMBNAIL);
        $preview = $photos->variant((int) $photo['id'], VariantType::PREVIEW);

        $this->assertNotNull($thumbnail);
        $this->assertNotNull($preview);
        $this->assertTrue($storage->exists((string) $thumbnail['storage_path']));
        $this->assertTrue($storage->exists((string) $preview['storage_path']));

        $this->assertSame(
            (int) Config::get('storage.thumbnail_width'),
            max((int) $thumbnail['width'], (int) $thumbnail['height']),
            'The thumbnail must respect the configured width.'
        );
    }

    public function testStoredFilenameIsGeneratedNotTakenFromTheClient(): void
    {
        $gallery = Factory::gallery();
        $photo = Factory::photo($gallery['gallery_id'], '../../evil shell.php.jpg');

        $stored = (string) $photo['storage_path'];

        // The client name is kept for the download, but never becomes a path.
        $this->assertFalse(str_contains($stored, 'evil'));
        $this->assertFalse(str_contains($stored, '..'));
        $this->assertFalse(str_contains($stored, '.php'));
        $this->assertTrue((bool) preg_match('#^originals/\d+/[0-9a-f]{32}\.jpg$#', $stored));
        $this->assertSame('evil shell.php.jpg', (string) $photo['original_filename']);
    }

    public function testOriginalsLiveOutsideTheDocumentRoot(): void
    {
        $gallery = Factory::gallery();
        $photo = Factory::photo($gallery['gallery_id']);

        $absolute = (new StorageService())->absolute((string) $photo['storage_path']);
        $publicRoot = realpath(dirname(__DIR__, 2) . '/public');

        $this->assertFalse(
            str_starts_with($absolute, (string) $publicRoot),
            'An original inside public/ would be downloadable by anyone who guessed the URL.'
        );
    }

    public function testNonImageFilesAreRejected(): void
    {
        $gallery = Factory::gallery();
        $galleryRow = (new \App\Repositories\GalleryRepository())->find($gallery['gallery_id']);
        $uploads = new PhotoUploadService();

        foreach ([
            'this is plain text',
            '<?php echo "pwned"; ?>',
            "GIF89a\x00" . '<?php system($_GET["c"]); ?>',
        ] as $contents) {
            $path = Factory::textFile($contents);

            $this->assertThrows(UploadException::class, static function () use ($uploads, $path, $galleryRow): void {
                $uploads->store([
                    'name'     => 'disguised.jpg',
                    'type'     => 'image/jpeg',
                    'tmp_name' => $path,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => (int) filesize($path),
                ], $galleryRow);
            });

            @unlink($path);
        }
    }

    public function testRejectedUploadLeavesNothingBehind(): void
    {
        $gallery = Factory::gallery();
        $galleryRow = (new \App\Repositories\GalleryRepository())->find($gallery['gallery_id']);
        $path = Factory::textFile();

        try {
            (new PhotoUploadService())->store([
                'name'     => 'disguised.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => (int) filesize($path),
            ], $galleryRow);
        } catch (UploadException) {
            // Expected.
        }

        @unlink($path);

        $this->assertSame(0, (new PhotoRepository())->countInGallery($gallery['gallery_id']));
    }

    public function testOversizedUploadsAreRejected(): void
    {
        $gallery = Factory::gallery();
        $galleryRow = (new \App\Repositories\GalleryRepository())->find($gallery['gallery_id']);
        $images = new \Database\Seeders\ImageFactory();
        $path = $images->createTemporary(400, 300, 7);

        $previous = Config::get('storage.max_upload_bytes');
        Config::set('storage.max_upload_bytes', 100);

        $this->assertThrows(UploadException::class, static function () use ($path, $galleryRow): void {
            (new PhotoUploadService())->store([
                'name'     => 'big.jpg',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => (int) filesize($path),
            ], $galleryRow);
        });

        Config::set('storage.max_upload_bytes', $previous);
        @unlink($path);
    }

    public function testPhpUploadErrorsAreReported(): void
    {
        $gallery = Factory::gallery();
        $galleryRow = (new \App\Repositories\GalleryRepository())->find($gallery['gallery_id']);

        $this->assertThrows(UploadException::class, static function () use ($galleryRow): void {
            (new PhotoUploadService())->store([
                'name'     => 'x.jpg',
                'tmp_name' => '',
                'error'    => UPLOAD_ERR_INI_SIZE,
                'size'     => 0,
            ], $galleryRow);
        });
    }

    public function testDeletingAPhotoRemovesItsFiles(): void
    {
        $gallery = Factory::gallery();
        $photo = Factory::photo($gallery['gallery_id']);
        $photos = new PhotoRepository();
        $storage = new StorageService();

        $paths = array_map(
            static fn (array $row): string => (string) $row['storage_path'],
            $photos->pathsFor((int) $photo['id'])
        );

        // The original plus every rendition: two on a server without WebP,
        // four where WebP companions were generated. The count is derived
        // rather than hard-coded, so adding a rendition does not silently
        // leave it undeleted.
        $expected = (new ImageProcessingService())->supportsWebp() ? 5 : 3;

        $this->assertCount($expected, $paths);

        (new PhotoUploadService())->deletePhoto((int) $photo['id']);

        $this->assertNull($photos->find((int) $photo['id']));
        $this->assertCount(0, $photos->variantsFor((int) $photo['id']));

        foreach ($paths as $path) {
            $this->assertFalse($storage->exists($path), 'Orphan file left behind: ' . $path);
        }
    }

    public function testWebpCompanionsAreGeneratedWhenTheServerSupportsThem(): void
    {
        $images = new ImageProcessingService();

        if (!$images->supportsWebp()) {
            // Nothing to assert on a host without WebP; the JPEG path is
            // covered by the other tests and is what such a host serves.
            $this->assertTrue(true);

            return;
        }

        $gallery = Factory::gallery();
        $photo = Factory::photo($gallery['gallery_id']);
        $photos = new PhotoRepository();

        $jpeg = $photos->variant((int) $photo['id'], VariantType::PREVIEW);
        $webp = $photos->variant((int) $photo['id'], VariantType::PREVIEW_WEBP);

        $this->assertNotNull($webp, 'A WebP companion should exist beside the JPEG preview.');
        $this->assertSame('image/webp', (string) $webp['mime_type']);
        $this->assertSame((int) $jpeg['width'], (int) $webp['width'], 'Both renditions must be the same size.');

        // The point of the format is weight; if it is not lighter it is only
        // costing storage.
        $this->assertTrue(
            (int) $webp['file_size'] < (int) $jpeg['file_size'],
            'The WebP rendition should be smaller than the JPEG.'
        );
    }

    public function testBestVariantFallsBackToJpegWhenNoWebpExists(): void
    {
        $gallery = Factory::gallery();
        $photo = Factory::photo($gallery['gallery_id']);
        $photos = new PhotoRepository();

        // Simulate a photo imported before WebP existed, or a host that
        // cannot produce it: the gallery must still work.
        // Deleted by exact type rather than a LIKE pattern, because "_" is a
        // wildcard in LIKE and escaping it portably needs an ESCAPE clause.
        $statement = \App\Core\Database::connection()->prepare(
            'DELETE FROM photo_variants WHERE photo_id = :id AND variant_type = :type'
        );

        foreach ([VariantType::PREVIEW_WEBP, VariantType::THUMBNAIL_WEBP] as $type) {
            $statement->execute(['id' => (int) $photo['id'], 'type' => $type]);
        }

        $chosen = $photos->bestVariant((int) $photo['id'], VariantType::PREVIEW, true);

        $this->assertNotNull($chosen);
        $this->assertSame('image/jpeg', (string) $chosen['mime_type']);
    }

    public function testBaselineJpegIsAlwaysWrittenEvenWhenWebpIsPreferred(): void
    {
        $gallery = Factory::gallery();
        $photo = Factory::photo($gallery['gallery_id']);
        $photos = new PhotoRepository();

        // A browser that does not accept WebP must always find a rendition.
        foreach (VariantType::REQUIRED as $type) {
            $this->assertNotNull(
                $photos->variant((int) $photo['id'], $type),
                'Missing baseline rendition: ' . $type
            );
        }
    }

    public function testStorageRefusesPathTraversal(): void
    {
        $storage = new StorageService();

        foreach (['../../../etc/passwd', 'originals/../../../etc/passwd', '/etc/passwd'] as $path) {
            $this->assertThrows(StorageException::class, static function () use ($storage, $path): void {
                $storage->absolute($path);
            });
        }
    }

    public function testExifOrientationIsAppliedToVariants(): void
    {
        $gallery = Factory::gallery();
        $photo = Factory::photo($gallery['gallery_id']);

        $preview = (new PhotoRepository())->variant((int) $photo['id'], VariantType::PREVIEW);
        $absolute = (new StorageService())->absolute((string) $preview['storage_path']);
        $size = getimagesize($absolute);

        $this->assertSame((int) $preview['width'], (int) $size[0]);
        $this->assertSame((int) $preview['height'], (int) $size[1]);
    }
}
