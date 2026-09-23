<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BookingRepository;
use App\Repositories\ClientRepository;
use App\Repositories\DownloadLogRepository;
use App\Repositories\EventRepository;
use App\Repositories\GalleryRepository;
use App\Repositories\GalleryViewRepository;
use App\Repositories\MessageRepository;
use App\Repositories\PhotoRepository;

/**
 * Aggregates for the dashboard and the statistics screen.
 *
 * Counts come from COUNT queries against indexed columns rather than from
 * loading rows, so the dashboard stays fast as galleries accumulate.
 */
final class StatisticsService
{
    public function __construct(
        private ?ClientRepository $clients = null,
        private ?EventRepository $events = null,
        private ?GalleryRepository $galleries = null,
        private ?PhotoRepository $photos = null,
        private ?GalleryViewRepository $views = null,
        private ?DownloadLogRepository $downloads = null,
        private ?MessageRepository $messages = null,
        private ?BookingRepository $bookings = null
    ) {
        $this->clients = $clients ?? new ClientRepository();
        $this->events = $events ?? new EventRepository();
        $this->galleries = $galleries ?? new GalleryRepository();
        $this->photos = $photos ?? new PhotoRepository();
        $this->views = $views ?? new GalleryViewRepository();
        $this->downloads = $downloads ?? new DownloadLogRepository();
        $this->messages = $messages ?? new MessageRepository();
        $this->bookings = $bookings ?? new BookingRepository();
    }

    /** @return array<string, int> */
    public function overview(): array
    {
        return [
            'clients'          => $this->clients->count(),
            'events'           => $this->events->count(),
            'galleries'        => $this->galleries->count(),
            'galleries_active' => $this->galleries->countByStatus('active'),
            'photos'           => $this->photos->count(),
            'views'            => $this->views->count(),
            'downloads'        => $this->downloads->count(),
            'unread_messages'  => $this->messages->countUnread(),
            'pending_bookings' => $this->bookings->countPending(),
            'downloaded_bytes' => $this->downloads->totalBytes(),
        ];
    }

    /**
     * Daily views and downloads over a window, with empty days filled in.
     *
     * The chart must show a flat line on quiet days rather than skipping them,
     * or the x-axis silently compresses and misrepresents activity.
     *
     * @return array{labels: array<int, string>, views: array<int, int>, downloads: array<int, int>}
     */
    public function activitySeries(int $days = 30): array
    {
        $days = max(7, min(365, $days));

        $views = $this->indexByDay($this->views->dailyTotals($days));
        $downloads = $this->indexByDay($this->downloads->dailyTotals($days));

        $labels = [];
        $viewSeries = [];
        $downloadSeries = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = date('Y-m-d', strtotime('-' . $offset . ' days'));
            $labels[] = $day;
            $viewSeries[] = $views[$day] ?? 0;
            $downloadSeries[] = $downloads[$day] ?? 0;
        }

        return ['labels' => $labels, 'views' => $viewSeries, 'downloads' => $downloadSeries];
    }

    /** @param array<int, array{day: string, total: int}> $rows @return array<string, int> */
    private function indexByDay(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[$row['day']] = $row['total'];
        }

        return $indexed;
    }

    /** @return array<int, array<string, mixed>> */
    public function popularGalleries(int $limit = 5): array
    {
        return $this->galleries->mostViewed($limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function recentGalleries(int $limit = 5): array
    {
        return $this->galleries->recent($limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function upcomingEvents(int $limit = 5): array
    {
        return $this->events->upcoming($limit);
    }

    /**
     * Per-gallery figures for the gallery detail screen.
     *
     * @return array<string, mixed>
     */
    public function forGallery(int $galleryId): array
    {
        return [
            'views'              => $this->views->countForGallery($galleryId),
            'downloads'          => $this->downloads->countForGallery($galleryId),
            'photos_downloaded'  => $this->downloads->photosDownloadedForGallery($galleryId),
            'last_viewed_at'     => $this->views->lastForGallery($galleryId),
            'last_downloaded_at' => $this->downloads->lastForGallery($galleryId),
            'photos'             => $this->photos->countInGallery($galleryId),
            'total_bytes'        => $this->photos->totalBytesInGallery($galleryId),
        ];
    }

    /**
     * Monthly gallery creation counts.
     *
     * @return array{labels: array<int, string>, totals: array<int, int>}
     */
    public function galleriesPerMonth(int $months = 12): array
    {
        $labels = [];
        $totals = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $month = date('Y-m', strtotime('-' . $offset . ' months'));
            $labels[] = $month;
            $totals[] = $this->galleries->count(
                "created_at >= :from AND created_at < :to",
                [
                    'from' => $month . '-01 00:00:00',
                    'to'   => date('Y-m-01 00:00:00', strtotime($month . '-01 +1 month')),
                ]
            );
        }

        return ['labels' => $labels, 'totals' => $totals];
    }
}
