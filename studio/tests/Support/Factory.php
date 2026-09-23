<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\EventStatus;
use App\Models\GalleryStatus;
use App\Models\Role;
use App\Repositories\ClientRepository;
use App\Repositories\EventRepository;
use App\Repositories\UserRepository;
use App\Services\GalleryService;
use App\Services\PhotoUploadService;
use App\Services\StorageService;

require_once dirname(__DIR__, 2) . '/database/seeders/ImageFactory.php';

/**
 * Builders for the objects the tests need.
 *
 * Photos are created through the real upload pipeline rather than by inserting
 * rows: a test that fakes the pipeline would not prove that uploads work.
 */
final class Factory
{
    public static function user(string $email = 'admin@example.test', string $password = 'correct-horse-battery', string $role = Role::SUPER_ADMIN): array
    {
        $users = new UserRepository();
        $id = $users->create('Test Admin', $email, $password, $role);

        return $users->find($id) ?? [];
    }

    public static function client(string $lastName = 'Martin'): int
    {
        return (new ClientRepository())->insert([
            'first_name' => 'Jean',
            'last_name'  => $lastName,
            'email'      => strtolower($lastName) . '@example.test',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function event(?int $clientId = null): int
    {
        $clientId ??= self::client();

        return (new EventRepository())->insert([
            'client_id'  => $clientId,
            'title'      => 'Mariage de test',
            'event_type' => 'wedding',
            'event_date' => date('Y-m-d'),
            'status'     => EventStatus::ACTIVE,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{gallery_id: int, view_token: string, download_token: string}
     */
    public static function gallery(array $overrides = []): array
    {
        $attributes = array_merge([
            'event_id'          => self::event(),
            'title'             => 'Galerie de test',
            'description'       => null,
            'password'          => null,
            'watermark_enabled' => false,
            'download_enabled'  => true,
            'selection_enabled' => false,
            'status'            => GalleryStatus::ACTIVE,
            'expires_at'        => null,
        ], $overrides);

        return (new GalleryService())->create($attributes);
    }

    /** Store a real generated image through PhotoUploadService. */
    public static function photo(int $galleryId, string $filename = 'IMG_0001.jpg'): array
    {
        (new StorageService())->ensureReady();

        $gallery = (new \App\Repositories\GalleryRepository())->find($galleryId);
        $images = new \Database\Seeders\ImageFactory();
        $temporary = $images->createTemporary(1200, 800, random_int(1, 999), 'TEST');

        return (new PhotoUploadService())->store([
            'name'     => $filename,
            'type'     => 'image/jpeg',
            'tmp_name' => $temporary,
            'error'    => UPLOAD_ERR_OK,
            'size'     => (int) filesize($temporary),
        ], $gallery ?? []);
    }

    /** A non-image file, to exercise upload validation. */
    public static function textFile(string $contents = 'this is definitely not an image'): string
    {
        $path = sys_get_temp_dir() . '/studio-test-' . bin2hex(random_bytes(6)) . '.jpg';
        file_put_contents($path, $contents);

        return $path;
    }
}
