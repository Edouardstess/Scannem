<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\StatisticsService;

final class DashboardController extends Controller
{
    public function __construct(
        private StatisticsService $statistics = new StatisticsService(),
        private AuditService $audit = new AuditService()
    ) {
    }

    /** GET /admin */
    public function index(Request $request): Response
    {
        return $this->view('admin.dashboard', [
            'title'      => 'Tableau de bord',
            'stats'      => $this->statistics->overview(),
            'activity'   => $this->statistics->activitySeries(30),
            'recent'     => $this->statistics->recentGalleries(5),
            'popular'    => $this->statistics->popularGalleries(5),
            'upcoming'   => $this->statistics->upcomingEvents(5),
            'auditTrail' => $this->audit->recent(8),
        ]);
    }
}
