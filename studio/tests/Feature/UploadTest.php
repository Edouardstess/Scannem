<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Config;
use App\Exceptions\StorageException;
use App\Exceptions\UploadException;
use App\Models\VariantType;
use App\Repositories\PhotoRepository;
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

        $this->assertCount(3, $paths, 'An original plus two variants.');

        (new PhotoUploadService())->deletePhoto((int) $photo['id']);

        $this->assertNull($photos->find((int) $photo['id']));

        foreach ($paths as $path) {
            $this->assertFalse($storage->exists($path), 'Orphan file left behind: ' . $path);
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
