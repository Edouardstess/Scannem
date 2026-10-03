<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditAction;
use App\Services\AuditService;
use App\Services\ImmersiveHomeService;
use App\Services\SettingsService;
use App\Validators\HomepageRequest;

/**
 * Admin → Page d'accueil: texts, theme and accent of the immersive homepage.
 * Its photos come from the portfolio (featured first) and the hero image
 * set in Paramètres, so nothing here uploads files.
 */
final class HomepageController extends Controller
{
    public function __construct(
        private SettingsService $settings = new SettingsService(),
        private AuditService $audit = new AuditService()
    ) {
    }

    /** GET /admin/homepage */
    public function index(Request $request): Response
    {
        $all = $this->settings->all();
        $home = new ImmersiveHomeService($all);

        return $this->view('admin.homepage.index', [
            'title'        => 'Page d’accueil',
            'layout'       => (string) ($all['home_layout'] ?? 'immersive'),
            'texts'        => $home->texts(),
            'placeholders' => $home->usesPlaceholders(),
        ]);
    }

    /** POST /admin/homepage */
    public function update(Request $request): Response
    {
        $form = (new HomepageRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/homepage');
        }

        $data = $form->data();
        $this->settings->set('home_layout', $data['home_layout'], 'home');
        $this->settings->set('immersive', $data['immersive'], 'home');

        $this->audit->record(AuditAction::SETTINGS_UPDATED, $request, null, null, ['section' => 'homepage']);
        $this->flashSuccess('Page d’accueil enregistrée.');

        return $this->redirect('admin/homepage');
    }
}
