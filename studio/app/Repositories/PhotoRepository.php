<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\VariantType;

final class PhotoRepository extends Repository
{
    protected string $table = 'photos';

    protected array $fillable = [
        'gallery_id', 'filename', 'original_filename', 'storage_path', 'mime_type',
        'file_size', 'width', 'height', 'orientation', 'taken_at', 'checksum',
        'downloadable', 'sort_order', 'status', 'created_at', 'updated_at',
    ];

    /**
     * Photos of a gallery with their variant paths attached.
     *
     * Variants are fetched in a single extra query and merged in PHP; joining
     * them would multiply rows, and querying per photo would be an N+1 on a
     * 250-photo gallery.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forGalleryWithVariants(int $galleryId, int $limit = 0, int $offset = 0): array
    {
        $sql = 'SELECT * FROM photos
                 WHERE gallery_id = :gallery_id AND status = :status
                 ORDER BY sort_order ASC, id ASC';
        $bindings = ['gallery_id' => $galleryId, 'status' => 'ready'];

        if ($limit > 0) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $bindings['limit'] = $limit;
            $bindings['offset'] = max(0, $offset);
        }

        $photos = $this->select($sql, $bindings);

        return $this->attachVariants($photos);
    }

    /** @param array<int, array<string, mixed>> $photos @return array<int, array<string, mixed>> */
    public function attachVariants(array $photos): array
    {
        if ($photos === []) {
            return [];
        }

        $ids = array_map(static fn (array $photo): int => (int) $photo['id'], $photos);
        [$placeholders, $bindings] = $this->inPlaceholders($ids, 'id');

        $variants = $this->select(
            'SELECT photo_id, variant_type, storage_path, width, height, file_size, mime_type
               FROM photo_variants WHERE photo_id IN (' . $placeholders . ')',
            $bindings
        );

        $byPhoto = [];

        foreach ($variants as $variant) {
            $byPhoto[(int) $variant['photo_id']][(string) $variant['variant_type']] = $variant;
        }

        foreach ($photos as $index => $photo) {
            $photoId = (int) $photo['id'];
            $photos[$index]['variants'] = $byPhoto[$photoId] ?? [];
            $photos[$index]['has_preview'] = isset($byPhoto[$photoId][VariantType::PREVIEW]);
            $photos[$index]['has_thumbnail'] = isset($byPhoto[$photoId][VariantType::THUMBNAIL]);
        }

        return $photos;
    }

    /** @return array<string, mixed>|null */
    public function findInGallery(int $photoId, int $galleryId): ?array
    {
        return $this->selectOne(
            'SELECT * FROM photos WHERE id = :id AND gallery_id = :gallery_id LIMIT 1',
            ['id' => $photoId, 'gallery_id' => $galleryId]
        );
    }

    /**
     * Fetch several photos of one gallery, filtered to ids the caller supplied.
     *
     * Used by selective download: passing the gallery id into the query is
     * what stops a download token from reaching another gallery's photos.
     *
     * @param array<int, int> $photoIds
     * @return array<int, array<string, mixed>>
     */
    public function findManyInGallery(array $photoIds, int $galleryId, bool $downloadableOnly = false): array
    {
        $photoIds = array_values(array_unique(array_filter(
            array_map('intval', $photoIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($photoIds === []) {
            return [];
        }

        [$placeholders, $bindings] = $this->inPlaceholders($photoIds, 'id');
        $bindings['gallery_id'] = $galleryId;
        $bindings['status'] = 'ready';

        $sql = 'SELECT * FROM photos
                 WHERE id IN (' . $placeholders . ')
                   AND gallery_id = :gallery_id
                   AND status = :status';

        if ($downloadableOnly) {
            $sql .= ' AND downloadable = 1';
        }

        return $this->select($sql . ' ORDER BY sort_order ASC, id ASC', $bindings);
    }

    /** @return array<int, array<string, mixed>> */
    public function downloadableInGallery(int $galleryId): array
    {
        return $this->select(
            "SELECT * FROM photos
              WHERE gallery_id = :gallery_id AND status = 'ready' AND downloadable = 1
              ORDER BY sort_order ASC, id ASC",
            ['gallery_id' => $galleryId]
        );
    }

    public function countInGallery(int $galleryId): int
    {
        return $this->count(
            "gallery_id = :gallery_id AND status = 'ready'",
            ['gallery_id' => $galleryId]
        );
    }

    public function totalBytesInGallery(int $galleryId): int
    {
        return (int) $this->scalar(
            "SELECT COALESCE(SUM(file_size), 0) FROM photos WHERE gallery_id = :gallery_id AND status = 'ready'",
            ['gallery_id' => $galleryId]
        );
    }

    public function nextSortOrder(int $galleryId): int
    {
        return (int) $this->scalar(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM photos WHERE gallery_id = :gallery_id',
            ['gallery_id' => $galleryId]
        );
    }

    /** @param array<int, int> $orderedIds */
    public function reorder(array $orderedIds, int $galleryId): void
    {
        Database::transaction(function () use ($orderedIds, $galleryId): void {
            foreach (array_values($orderedIds) as $position => $photoId) {
                $this->run(
                    'UPDATE photos SET sort_order = :sort_order WHERE id = :id AND gallery_id = :gallery_id',
                    ['sort_order' => $position + 1, 'id' => (int) $photoId, 'gallery_id' => $galleryId]
                );
            }
        });
    }

    public function setDownloadable(int $photoId, int $galleryId, bool $downloadable): void
    {
        $this->run(
            'UPDATE photos SET downloadable = :downloadable, updated_at = :now
              WHERE id = :id AND gallery_id = :gallery_id',
            [
                'downloadable' => $downloadable ? 1 : 0,
                'now'          => $this->now(),
                'id'           => $photoId,
                'gallery_id'   => $galleryId,
            ]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function variantsFor(int $photoId): array
    {
        return $this->select(
            'SELECT * FROM photo_variants WHERE photo_id = :photo_id',
            ['photo_id' => $photoId]
        );
    }

    /** @return array<string, mixed>|null */
    public function variant(int $photoId, string $type): ?array
    {
        return $this->selectOne(
            'SELECT * FROM photo_variants WHERE photo_id = :photo_id AND variant_type = :type LIMIT 1',
            ['photo_id' => $photoId, 'type' => $type]
        );
    }

    /**
     * The lightest rendition of a type that the caller can display.
     *
     * Falls back to the baseline JPEG whenever the WebP companion was not
     * generated — which is the case on a host without WebP support, and on
     * every photo imported before the feature existed.
     *
     * @return array<string, mixed>|null
     */
    public function bestVariant(int $photoId, string $type, bool $preferWebp): ?array
    {
        if ($preferWebp) {
            $webpType = VariantType::webpOf($type);

            if ($webpType !== null) {
                $webp = $this->variant($photoId, $webpType);

                if ($webp !== null) {
                    return $webp;
                }
            }
        }

        return $this->variant($photoId, $type);
    }

    /** @param array<string, mixed> $attributes */
    public function insertVariant(array $attributes): int
    {
        $columns = ['photo_id', 'variant_type', 'storage_path', 'mime_type', 'width', 'height', 'file_size', 'created_at'];
        $attributes = array_intersect_key($attributes, array_flip($columns));
        $attributes['created_at'] ??= $this->now();

        $names = array_keys($attributes);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $names);

        $this->run(
            sprintf(
                'INSERT INTO photo_variants (%s) VALUES (%s)',
                implode(', ', $names),
                implode(', ', $placeholders)
            ),
            $attributes
        );

        return (int) $this->pdo()->lastInsertId();
    }

    public function deleteVariants(int $photoId): void
    {
        $this->run('DELETE FROM photo_variants WHERE photo_id = :photo_id', ['photo_id' => $photoId]);
    }

    /** @return array<int, array<string, mixed>> Storage paths to erase with the row. */
    public function pathsFor(int $photoId): array
    {
        $photo = $this->find($photoId);

        if ($photo === null) {
            return [];
        }

        $paths = [['storage_path' => $photo['storage_path']]];

        foreach ($this->variantsFor($photoId) as $variant) {
            $paths[] = ['storage_path' => $variant['storage_path']];
        }

        return $paths;
    }

    /** @return array<int, array<string, mixed>> */
    public function allPathsForGallery(int $galleryId): array
    {
        return $this->select(
            'SELECT storage_path FROM photos WHERE gallery_id = :gallery_id
             UNION ALL
             SELECT v.storage_path FROM photo_variants v
               JOIN photos p ON p.id = v.photo_id
              WHERE p.gallery_id = :gallery_id2',
            ['gallery_id' => $galleryId, 'gallery_id2' => $galleryId]
        );
    }
}
