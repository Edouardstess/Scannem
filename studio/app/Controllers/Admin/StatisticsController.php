<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Services\StatisticsService;
use App\Services\StorageService;

final class StatisticsController extends Controller
{
    private const PER_PAGE = 40;

    public function __construct(
        private StatisticsService $statistics = new StatisticsService(),
        private AuditLogRepository $audit = new AuditLogRepository(),
        private StorageService $storage = new StorageService()
    ) {
    }

    /** GET /admin/statistics */
    public function index(Request $request): Response
    {
        $days = max(7, min(365, $request->int('days', 30)));

        return $this->view('admin.statistics.index', [
            'title'      => 'Statistiques',
            'stats'      => $this->statistics->overview(),
            'activity'   => $this->statistics->activitySeries($days),
            'monthly'    => $this->statistics->galleriesPerMonth(12),
            'popular'    => $this->statistics->popularGalleries(10),
            'days'       => $days,
            'usage'      => $this->storage->usage(),
        ]);
    }

    /** GET /admin/statistics/audit */
    public function audit(Request $request): Response
    {
        $page = $this->page($request);
        $action = $request->string('action');
        $result = $this->audit->paginate($page, self::PER_PAGE, $action);

        return $this->view('admin.statistics.audit', [
            'title'      => "Journal d'audit",
            'logs'       => $result['rows'],
            'pagination' => $this->paginationMeta($result['total'], $page, self::PER_PAGE),
            'actions'    => $this->audit->distinctActions(),
            'action'     => $action,
        ]);
    }
}
