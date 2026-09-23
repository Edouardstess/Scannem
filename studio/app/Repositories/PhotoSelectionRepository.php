<?php

declare(strict_types=1);

namespace App\Repositories;

final class PhotoSelectionRepository extends Repository
{
    protected string $table = 'photo_selections';

    protected array $fillable = ['gallery_id', 'photo_id', 'token_id', 'created_at'];

    /** @return array<int, int> Photo ids selected under this token. */
    public function photoIdsFor(int $galleryId, ?int $tokenId): array
    {
        $rows = $this->select(
            'SELECT photo_id FROM photo_selections
              WHERE gallery_id = :gallery_id AND (token_id = :token_id OR (:token_id2 IS NULL AND token_id IS NULL))',
            ['gallery_id' => $galleryId, 'token_id' => $tokenId, 'token_id2' => $tokenId]
        );

        return array_map(static fn (array $row): int => (int) $row['photo_id'], $rows);
    }

    public function toggle(int $galleryId, int $photoId, ?int $tokenId): bool
    {
        $existing = $this->selectOne(
            'SELECT id FROM photo_selections WHERE photo_id = :photo_id AND token_id = :token_id',
            ['photo_id' => $photoId, 'token_id' => $tokenId]
        );

        if ($existing !== null) {
            $this->run('DELETE FROM photo_selections WHERE id = :id', ['id' => (int) $existing['id']]);

            return false;
        }

        $this->insert([
            'gallery_id' => $galleryId,
            'photo_id'   => $photoId,
            'token_id'   => $tokenId,
            'created_at' => $this->now(),
        ]);

        return true;
    }

    public function countForGallery(int $galleryId): int
    {
        return $this->count('gallery_id = :gallery_id', ['gallery_id' => $galleryId]);
    }

    /** @return array<int, array<string, mixed>> Selected photos, for the photographer. */
    public function selectedPhotos(int $galleryId): array
    {
        return $this->select(
            'SELECT p.*, COUNT(s.id) AS selection_count
               FROM photo_selections s
               JOIN photos p ON p.id = s.photo_id
              WHERE s.gallery_id = :gallery_id
              GROUP BY p.id
              ORDER BY p.sort_order ASC, p.id ASC',
            ['gallery_id' => $galleryId]
        );
    }
}
