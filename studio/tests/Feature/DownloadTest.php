<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Config;
use App\Exceptions\StorageException;
use App\Repositories\DownloadLogRepository;
use App\Repositories\PhotoRepository;
use App\Services\StorageService;
use App\Services\ZipService;
use Tests\Support\Factory;
use Tests\Support\TestCase;

final class DownloadTest extends TestCase
{
    public function testArchiveContainsTheOriginals(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photos = [];

        foreach (['IMG_0001.jpg', 'IMG_0002.jpg', 'IMG_0003.jpg'] as $name) {
            $photos[] = Factory::photo($gallery['gallery_id'], $name);
        }

        $archive = (new ZipService())->createArchive($photos, 'Mariage Jean & Marie');

        $this->assertSame(3, $archive['count']);
        $this->assertSame('galerie-mariage-jean-marie.zip', $archive['filename']);
        $this->assertGreaterThan(0, $archive['bytes']);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($archive['absolute']) === true);
        $this->assertSame(3, $zip->numFiles);

        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = $zip->getNameIndex($index);
        }

        $this->assertContains('IMG_0001.jpg', $names);

        // The bytes in the archive must be the original file, not a preview.
        $extracted = $zip->getFromName('IMG_0001.jpg');
        $original = (new StorageService())->absolute((string) $photos[0]['storage_path']);

        $this->assertSame(file_get_contents($original), $extracted);

        $zip->close();
    }

    public function testDuplicateFilenamesAreKeptDistinct(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photos = [
            Factory::photo($gallery['gallery_id'], 'IMG_0001.jpg'),
            Factory::photo($gallery['gallery_id'], 'IMG_0001.jpg'),
            Factory::photo($gallery['gallery_id'], 'IMG_0001.jpg'),
        ];

        $archive = (new ZipService())->createArchive($photos, 'Doublons');

        $zip = new \ZipArchive();
        $zip->open($archive['absolute']);

        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = $zip->getNameIndex($index);
        }

        $zip->close();

        // Without de-duplication one photograph would silently overwrite
        // another and the client would be a file short.
        $this->assertSame(3, count(array_unique($names)));
        $this->assertContains('IMG_0001.jpg', $names);
        $this->assertContains('IMG_0001-2.jpg', $names);
        $this->assertContains('IMG_0001-3.jpg', $names);
    }

    public function testArchiveFilenamesCannotEscapeTheArchive(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id'], '../../../etc/passwd');

        $archive = (new ZipService())->createArchive([$photo], 'Test');

        $zip = new \ZipArchive();
        $zip->open($archive['absolute']);
        $name = $zip->getNameIndex(0);
        $zip->close();

        $this->assertSame('passwd', $name, 'A zip entry must never carry a traversal path.');
    }

    public function testEmptySelectionIsRefused(): void
    {
        $zip = new ZipService();

        $this->assertThrows(StorageException::class, static function () use ($zip): void {
            $zip->createArchive([], 'Vide');
        });
    }

    public function testOversizedSelectionsAreRefused(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id']);

        $previous = Config::get('storage.zip_max_bytes');
        Config::set('storage.zip_max_bytes', 10);

        $zip = new ZipService();

        $this->assertThrows(StorageException::class, static function () use ($zip, $photo): void {
            $zip->createArchive([$photo], 'Trop gros');
        });

        Config::set('storage.zip_max_bytes', $previous);
    }

    public function testSelectiveDownloadIsScopedToItsGallery(): void
    {
        $first = Factory::gallery(['download_enabled' => true]);
        $second = Factory::gallery(['download_enabled' => true]);

        $mine = Factory::photo($first['gallery_id'], 'mine.jpg');
        $theirs = Factory::photo($second['gallery_id'], 'theirs.jpg');

        $photos = new PhotoRepository();

        $result = $photos->findManyInGallery(
            [(int) $mine['id'], (int) $theirs['id']],
            $first['gallery_id'],
            true
        );

        $this->assertCount(1, $result, "Another gallery's photo must be filtered out by the query itself.");
        $this->assertSame((int) $mine['id'], (int) $result[0]['id']);
    }

    public function testPhotosMarkedNotDownloadableAreExcluded(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $allowed = Factory::photo($gallery['gallery_id'], 'ok.jpg');
        $blocked = Factory::photo($gallery['gallery_id'], 'blocked.jpg');

        $photos = new PhotoRepository();
        $photos->setDownloadable((int) $blocked['id'], $gallery['gallery_id'], false);

        $downloadable = $photos->downloadableInGallery($gallery['gallery_id']);

        $this->assertCount(1, $downloadable);
        $this->assertSame((int) $allowed['id'], (int) $downloadable[0]['id']);

        $selected = $photos->findManyInGallery(
            [(int) $allowed['id'], (int) $blocked['id']],
            $gallery['gallery_id'],
            true
        );

        $this->assertCount(1, $selected);
    }

    public function testTemporaryArchivesArePrunedByAge(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id']);

        $archive = (new ZipService())->createArchive([$photo], 'À purger');
        $storage = new StorageService();

        $this->assertTrue($storage->exists($archive['relative']));

        // Age the file past the TTL rather than waiting for it.
        touch($archive['absolute'], time() - 7200);

        $removed = $storage->files()->pruneTemporary(3600);

        $this->assertGreaterThan(0, $removed);
        $this->assertFalse($storage->exists($archive['relative']));
    }

    public function testDownloadsAreLogged(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id']);

        $logs = new DownloadLogRepository();
        $request = new \App\Core\Request('GET', '/media', [], [], ['REMOTE_ADDR' => '203.0.113.7'], [], []);

        (new \App\Services\DownloadService())->logPhotoDownload(
            $gallery['gallery_id'],
            (int) $photo['id'],
            null,
            (int) $photo['file_size'],
            $request
        );

        $this->assertSame(1, $logs->countForGallery($gallery['gallery_id']));
        $this->assertSame(1, $logs->photosDownloadedForGallery($gallery['gallery_id']));
        $this->assertNotNull($logs->lastForGallery($gallery['gallery_id']));
    }

    public function testArchiveDownloadsCountEveryPhoto(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $logs = new DownloadLogRepository();
        $request = new \App\Core\Request('GET', '/download', [], [], ['REMOTE_ADDR' => '203.0.113.7'], [], []);

        (new \App\Services\DownloadService())->logArchiveDownload(
            $gallery['gallery_id'],
            null,
            42,
            999_999,
            $request
        );

        $this->assertSame(1, $logs->countForGallery($gallery['gallery_id']));
        $this->assertSame(42, $logs->photosDownloadedForGallery($gallery['gallery_id']));
    }
}
