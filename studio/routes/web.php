<?php

declare(strict_types=1);

/**
 * Route table.
 *
 * $router is provided by Application::loadRoutes().
 *
 * Three families, with different guards:
 *   - Public pages: security headers + CSRF on writes.
 *   - /admin: additionally AuthMiddleware, then a per-permission guard.
 *   - /gallery, /download, /media: no session identity at all. Authorisation
 *     comes from the token in the URL and is enforced in the controllers via
 *     GalleryAccessService. They are deliberately NOT behind AuthMiddleware:
 *     clients have no accounts.
 */

use App\Controllers\Admin\ClientController as AdminClientController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EventController as AdminEventController;
use App\Controllers\Admin\GalleryController as AdminGalleryController;
use App\Controllers\Admin\MessageController;
use App\Controllers\Admin\PhotoController;
use App\Controllers\Admin\PortfolioAdminController;
use App\Controllers\Admin\ServiceAdminController;
use App\Controllers\Admin\ShareController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\StatisticsController;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\ClientAreaController;
use App\Controllers\ContactController;
use App\Controllers\DownloadController;
use App\Controllers\GalleryController;
use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\PortfolioController;
use App\Controllers\ServiceController;
use App\Controllers\SitemapController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RequiresClientManageMiddleware;
use App\Middleware\RequiresEventManageMiddleware;
use App\Middleware\RequiresGalleryCreateMiddleware;
use App\Middleware\RequiresGalleryDeleteMiddleware;
use App\Middleware\RequiresGalleryUpdateMiddleware;
use App\Middleware\RequiresMessageManageMiddleware;
use App\Middleware\RequiresPhotoDeleteMiddleware;
use App\Middleware\RequiresPhotoUploadMiddleware;
use App\Middleware\RequiresPortfolioManageMiddleware;
use App\Middleware\RequiresSettingsManageMiddleware;
use App\Middleware\RequiresStatisticsViewMiddleware;
use App\Middleware\SecurityHeadersMiddleware;

/** @var Router $router */

// Applied to every route, including 404s.
$router->middleware(SecurityHeadersMiddleware::class, CsrfMiddleware::class);

$admin = [AuthMiddleware::class];

// --- Public site ------------------------------------------------------------

$router->get('/', [HomeController::class, 'index'], [], 'home');
$router->get('/a-propos', [HomeController::class, 'about'], [], 'about');

$router->get('/portfolio', [PortfolioController::class, 'index'], [], 'portfolio');
$router->get('/portfolio/{slug}', [PortfolioController::class, 'index'], [], 'portfolio.category');

$router->get('/services', [ServiceController::class, 'index'], [], 'services');
$router->get('/services/{slug}', [ServiceController::class, 'show'], [], 'services.show');

$router->get('/contact', [ContactController::class, 'show'], [], 'contact');
$router->post('/contact', [ContactController::class, 'submit']);

$router->get('/reservation', [BookingController::class, 'show'], [], 'booking');
$router->post('/reservation', [BookingController::class, 'submit']);

$router->get('/espace-client', [ClientAreaController::class, 'show'], [], 'client-area');
$router->post('/espace-client', [ClientAreaController::class, 'open']);

$router->get('/robots.txt', [SitemapController::class, 'robots']);
$router->get('/sitemap.xml', [SitemapController::class, 'sitemap']);

// --- Client galleries (token-authorised, no account) -------------------------

$router->get('/gallery/{token}', [GalleryController::class, 'show'], [], 'gallery.show');
$router->post('/gallery/{token}/unlock', [GalleryController::class, 'unlock']);
$router->get('/gallery/{token}/photos', [GalleryController::class, 'photos']);
$router->post('/gallery/{token}/select', [GalleryController::class, 'toggleSelection']);

$router->get('/download/{token}', [DownloadController::class, 'show'], [], 'download.show');
$router->post('/download/{token}/unlock', [DownloadController::class, 'unlock']);
$router->get('/download/{token}/photos', [DownloadController::class, 'photos']);
$router->post('/download/{token}/archive', [DownloadController::class, 'createArchive']);
$router->get('/download/{token}/archive/{handle}', [DownloadController::class, 'fetchArchive']);

// Image delivery. Every one of these re-authorises from scratch.
$router->get('/media/thumb/{token}', [MediaController::class, 'thumbnail']);
$router->get('/media/preview/{token}', [MediaController::class, 'preview']);
$router->get('/media/download/{token}', [MediaController::class, 'download']);

// --- Authentication ---------------------------------------------------------

$router->get('/admin/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class], 'admin.login');
$router->post('/admin/login', [AuthController::class, 'login'], [GuestMiddleware::class]);
$router->post('/admin/logout', [AuthController::class, 'logout'], $admin);

// --- Admin ------------------------------------------------------------------

$router->get('/admin', [DashboardController::class, 'index'], $admin, 'admin.dashboard');

$router->get('/admin/profile', [AuthController::class, 'profile'], $admin, 'admin.profile');
$router->post('/admin/profile/password', [AuthController::class, 'updatePassword'], $admin);

// Clients
$clientGuard = array_merge($admin, [RequiresClientManageMiddleware::class]);
$router->get('/admin/clients', [AdminClientController::class, 'index'], $clientGuard, 'admin.clients');
$router->get('/admin/clients/create', [AdminClientController::class, 'create'], $clientGuard);
$router->post('/admin/clients', [AdminClientController::class, 'store'], $clientGuard);
$router->get('/admin/clients/{id:\d+}', [AdminClientController::class, 'show'], $clientGuard, 'admin.clients.show');
$router->get('/admin/clients/{id:\d+}/edit', [AdminClientController::class, 'edit'], $clientGuard);
$router->put('/admin/clients/{id:\d+}', [AdminClientController::class, 'update'], $clientGuard);
$router->delete('/admin/clients/{id:\d+}', [AdminClientController::class, 'destroy'], $clientGuard);

// Events
$eventGuard = array_merge($admin, [RequiresEventManageMiddleware::class]);
$router->get('/admin/events', [AdminEventController::class, 'index'], $eventGuard, 'admin.events');
$router->get('/admin/events/create', [AdminEventController::class, 'create'], $eventGuard);
$router->post('/admin/events', [AdminEventController::class, 'store'], $eventGuard);
$router->get('/admin/events/{id:\d+}', [AdminEventController::class, 'show'], $eventGuard, 'admin.events.show');
$router->get('/admin/events/{id:\d+}/edit', [AdminEventController::class, 'edit'], $eventGuard);
$router->put('/admin/events/{id:\d+}', [AdminEventController::class, 'update'], $eventGuard);
$router->delete('/admin/events/{id:\d+}', [AdminEventController::class, 'destroy'], $eventGuard);

// Galleries
$galleryRead   = array_merge($admin, [RequiresGalleryUpdateMiddleware::class]);
$galleryCreate = array_merge($admin, [RequiresGalleryCreateMiddleware::class]);
$galleryDelete = array_merge($admin, [RequiresGalleryDeleteMiddleware::class]);

$router->get('/admin/galleries', [AdminGalleryController::class, 'index'], $galleryRead, 'admin.galleries');
$router->get('/admin/galleries/create', [AdminGalleryController::class, 'create'], $galleryCreate);
$router->post('/admin/galleries', [AdminGalleryController::class, 'store'], $galleryCreate);
$router->get('/admin/galleries/{id:\d+}', [AdminGalleryController::class, 'show'], $galleryRead, 'admin.galleries.show');
$router->get('/admin/galleries/{id:\d+}/edit', [AdminGalleryController::class, 'edit'], $galleryRead);
$router->put('/admin/galleries/{id:\d+}', [AdminGalleryController::class, 'update'], $galleryRead);
$router->delete('/admin/galleries/{id:\d+}', [AdminGalleryController::class, 'destroy'], $galleryDelete);

$router->post('/admin/galleries/{id:\d+}/status', [AdminGalleryController::class, 'changeStatus'], $galleryRead);

// Sharing and link lifecycle: who can reach a gallery, as opposed to what it
// contains.
$router->get('/admin/galleries/{id:\d+}/share', [ShareController::class, 'show'], $galleryRead, 'admin.galleries.share');
$router->post('/admin/galleries/{id:\d+}/notify', [ShareController::class, 'notifyClient'], $galleryRead);
$router->post('/admin/galleries/{id:\d+}/tokens/{type}/regenerate', [ShareController::class, 'regenerate'], $galleryRead);
$router->post('/admin/galleries/{id:\d+}/tokens/{type}/revoke', [ShareController::class, 'revoke'], $galleryRead);

// Photos
$photoUpload = array_merge($admin, [RequiresPhotoUploadMiddleware::class]);
$photoDelete = array_merge($admin, [RequiresPhotoDeleteMiddleware::class]);

$router->post('/admin/galleries/{id:\d+}/photos', [PhotoController::class, 'store'], $photoUpload);
$router->post('/admin/galleries/{id:\d+}/photos/reorder', [PhotoController::class, 'reorder'], $photoUpload);
$router->post('/admin/galleries/{id:\d+}/cover', [PhotoController::class, 'setCover'], $photoUpload);
$router->get('/admin/photos/{id:\d+}/thumb', [PhotoController::class, 'thumbnail'], $admin);
$router->get('/admin/photos/{id:\d+}/preview', [PhotoController::class, 'preview'], $admin);
$router->get('/admin/photos/{id:\d+}/original', [PhotoController::class, 'original'], $admin);
$router->post('/admin/photos/{id:\d+}/downloadable', [PhotoController::class, 'toggleDownloadable'], $photoUpload);
$router->delete('/admin/photos/{id:\d+}', [PhotoController::class, 'destroy'], $photoDelete);

// Portfolio
$portfolioGuard = array_merge($admin, [RequiresPortfolioManageMiddleware::class]);
$router->get('/admin/portfolio', [PortfolioAdminController::class, 'index'], $portfolioGuard, 'admin.portfolio');
$router->get('/admin/portfolio/create', [PortfolioAdminController::class, 'create'], $portfolioGuard);
$router->post('/admin/portfolio', [PortfolioAdminController::class, 'store'], $portfolioGuard);
$router->get('/admin/portfolio/categories', [PortfolioAdminController::class, 'categories'], $portfolioGuard, 'admin.portfolio.categories');
$router->post('/admin/portfolio/categories', [PortfolioAdminController::class, 'storeCategory'], $portfolioGuard);
$router->put('/admin/portfolio/categories/{id:\d+}', [PortfolioAdminController::class, 'updateCategory'], $portfolioGuard);
$router->delete('/admin/portfolio/categories/{id:\d+}', [PortfolioAdminController::class, 'destroyCategory'], $portfolioGuard);
$router->get('/admin/portfolio/{id:\d+}/edit', [PortfolioAdminController::class, 'edit'], $portfolioGuard);
$router->put('/admin/portfolio/{id:\d+}', [PortfolioAdminController::class, 'update'], $portfolioGuard);
$router->delete('/admin/portfolio/{id:\d+}', [PortfolioAdminController::class, 'destroy'], $portfolioGuard);

// Services
$router->get('/admin/services', [ServiceAdminController::class, 'index'], $portfolioGuard, 'admin.services');
$router->get('/admin/services/create', [ServiceAdminController::class, 'create'], $portfolioGuard);
$router->post('/admin/services', [ServiceAdminController::class, 'store'], $portfolioGuard);
$router->get('/admin/services/{id:\d+}/edit', [ServiceAdminController::class, 'edit'], $portfolioGuard);
$router->put('/admin/services/{id:\d+}', [ServiceAdminController::class, 'update'], $portfolioGuard);
$router->delete('/admin/services/{id:\d+}', [ServiceAdminController::class, 'destroy'], $portfolioGuard);

// Messages and bookings
$messageGuard = array_merge($admin, [RequiresMessageManageMiddleware::class]);
$router->get('/admin/messages', [MessageController::class, 'index'], $messageGuard, 'admin.messages');
$router->get('/admin/messages/{id:\d+}', [MessageController::class, 'show'], $messageGuard);
$router->post('/admin/messages/{id:\d+}/status', [MessageController::class, 'setStatus'], $messageGuard);
$router->delete('/admin/messages/{id:\d+}', [MessageController::class, 'destroy'], $messageGuard);
$router->get('/admin/bookings', [MessageController::class, 'bookings'], $messageGuard, 'admin.bookings');
$router->post('/admin/bookings/{id:\d+}/status', [MessageController::class, 'setBookingStatus'], $messageGuard);

// Statistics
$statsGuard = array_merge($admin, [RequiresStatisticsViewMiddleware::class]);
$router->get('/admin/statistics', [StatisticsController::class, 'index'], $statsGuard, 'admin.statistics');
$router->get('/admin/statistics/audit', [StatisticsController::class, 'audit'], $statsGuard, 'admin.audit');

// Settings
$settingsGuard = array_merge($admin, [RequiresSettingsManageMiddleware::class]);
$router->get('/admin/settings', [SettingsController::class, 'index'], $settingsGuard, 'admin.settings');
$router->post('/admin/settings', [SettingsController::class, 'update'], $settingsGuard);
$router->post('/admin/settings/maintenance', [SettingsController::class, 'maintenance'], $settingsGuard);
