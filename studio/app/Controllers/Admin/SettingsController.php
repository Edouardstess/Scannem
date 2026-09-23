<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\UploadException;
use App\Models\AuditAction;
use App\Services\AuditService;
use App\Services\ImageProcessingService;
use App\Services\PublicImageService;
use App\Services\SettingsService;
use App\Services\StorageService;
use App\Services\ZipService;
use App\Validators\SettingsRequest;

/**
 * Site settings, plus a diagnostics panel.
 *
 * The diagnostics exist because the most dangerous failure in this product —
 * private storage being served directly by the web server — is completely
 * silent. Surfacing it on a screen the photographer visits is the only way it
 * gets noticed before a client's originals do.
 */
final class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settings = new SettingsService(),
        private PublicImageService $images = new PublicImageService(),
        private StorageService $storage = new StorageService(),
        private ImageProcessingService $imaging = new ImageProcessingService(),
        private ZipService $zip = new ZipService(),
        private AuditService $audit = new AuditService()
    ) {
    }

    /** GET /admin/settings */
    public function index(Request $request): Response
    {
        return $this->view('admin.settings.index', [
            'title'       => 'Paramètres',
            'values'      => $this->settings->all(),
            'diagnostics' => $this->diagnostics(),
        ]);
    }

    /** POST /admin/settings */
    public function update(Request $request): Response
    {
        $form = (new SettingsRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/settings');
        }

        $this->settings->setMany($form->data(), 'site');

        foreach (SettingsRequest::IMAGE_FIELDS as $field) {
            $this->handleImageUpload($request, $field, 'site');
        }

        $this->audit->record(AuditAction::SETTINGS_UPDATED, $request);
        $this->flashSuccess('Paramètres enregistrés.');

        return $this->redirect('admin/settings');
    }

    /** POST /admin/settings/maintenance — housekeeping actions. */
    public function maintenance(Request $request): Response
    {
        $action = $request->string('action');

        match ($action) {
            'prune_temporary' => $this->flashSuccess(sprintf(
                '%d archive(s) temporaire(s) supprimée(s).',
                $this->storage->pruneTemporary()
            )),
            'purge_rate_limits' => $this->flashSuccess(sprintf(
                '%d compteur(s) de limitation expiré(s) supprimé(s).',
                (new \App\Services\RateLimiter())->purgeExpired()
            )),
            'purge_audit' => $this->flashSuccess(sprintf(
                "%d entrée(s) d'audit de plus d'un an supprimée(s).",
                (new \App\Repositories\AuditLogRepository())->purgeOlderThan(365)
            )),
            default => $this->flashError('Action de maintenance inconnue.'),
        };

        return $this->redirect('admin/settings');
    }

    /** @param string $field The setting key that stores the resulting path. */
    private function handleImageUpload(Request $request, string $field, string $group): void
    {
        $file = $request->file($field);

        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return;
        }

        try {
            $stored = $this->images->store($file, 'site');
        } catch (UploadException $e) {
            $this->flashError(sprintf('Image "%s" : %s', $field, $e->getMessage()));

            return;
        }

        $previous = (string) $this->settings->get($field, '');
        $this->settings->set($field, $stored['path'], $group);

        if ($previous !== '') {
            $this->images->delete($previous);
        }
    }


    /**
     * Environment checks shown on the settings screen.
     *
     * @return array<int, array{label: string, ok: bool, detail: string, critical: bool}>
     */
    private function diagnostics(): array
    {
        $storageExposed = $this->storage->isInsideDocumentRoot();
        $appKey = trim((string) Config::get('app.key', ''));
        $debug = (bool) Config::get('app.debug');
        $environment = (string) Config::get('app.env', 'production');
        $usage = $this->storage->usage();

        $installer = dirname(__DIR__, 3) . '/public/install.php';
        $uploadLimit = \App\Core\Environment::uploadLimitBytes((int) Config::get('storage.max_upload_bytes'));
        $megapixels = \App\Core\Environment::maxImageMegapixels();

        return [
            [
                'label'    => "Installateur supprimé",
                'ok'       => !is_file($installer),
                'detail'   => is_file($installer)
                    ? 'CRITIQUE : public/install.php est toujours présent. Supprimez-le maintenant.'
                    : 'public/install.php a bien été supprimé.',
                'critical' => true,
            ],
            [
                'label'    => 'Stockage privé hors du dossier public',
                'ok'       => !$storageExposed,
                'detail'   => $storageExposed
                    ? 'CRITIQUE : le dossier de stockage est accessible depuis le web. Déplacez-le hors de public/.'
                    : ($this->storage->canCheckDocumentRoot()
                        ? $this->storage->files()->root()
                        : $this->storage->files()->root() . ' (racine web non déclarée par le serveur)'),
                'critical' => true,
            ],
            [
                'label'    => 'Clé applicative (APP_KEY)',
                'ok'       => $appKey !== '',
                'detail'   => $appKey === ''
                    ? "Non définie : les liens de galerie ne pourront pas être réaffichés après création."
                    : 'Définie.',
                'critical' => true,
            ],
            [
                'label'    => 'Mode debug désactivé',
                'ok'       => !$debug || $environment !== 'production',
                'detail'   => $debug
                    ? 'APP_DEBUG=true : les erreurs détaillées sont visibles par les visiteurs.'
                    : 'APP_DEBUG=false.',
                'critical' => true,
            ],
            [
                'label'    => "Traitement d'image",
                'ok'       => $this->imaging->isAvailable(),
                'detail'   => $this->imaging->driverLabel(),
                'critical' => true,
            ],
            [
                'label'    => 'Extension ZIP',
                'ok'       => $this->zip->isAvailable(),
                'detail'   => $this->zip->isAvailable()
                    ? 'Disponible : téléchargement de galerie complète possible.'
                    : 'Absente : le téléchargement groupé sera indisponible.',
                'critical' => false,
            ],
            [
                'label'    => 'Espace de stockage utilisé',
                'ok'       => true,
                'detail'   => sprintf(
                    'Originaux %s · Aperçus %s · Miniatures %s · Temporaire %s',
                    format_bytes($usage['originals']),
                    format_bytes($usage['previews']),
                    format_bytes($usage['thumbnails']),
                    format_bytes($usage['temporary'])
                ),
                'critical' => false,
            ],
            [
                'label'    => 'Taille maximale par fichier',
                // Below ~20 MB, straight-from-camera originals get refused.
                'ok'       => $uploadLimit >= 20 * 1024 * 1024,
                'detail'   => sprintf(
                    'Effective %s (application %s · PHP upload_max_filesize %s · post_max_size %s)%s',
                    format_bytes($uploadLimit),
                    format_bytes((int) Config::get('storage.max_upload_bytes')),
                    (string) ini_get('upload_max_filesize'),
                    (string) ini_get('post_max_size'),
                    $uploadLimit < 20 * 1024 * 1024 ? ' — exportez les photos en JPEG réduit avant import.' : ''
                ),
                'critical' => false,
            ],
            [
                'label'    => 'Mémoire pour le traitement des images',
                'ok'       => $megapixels >= 24,
                'detail'   => sprintf(
                    'memory_limit %s · images jusqu\'à environ %s mégapixels',
                    (string) ini_get('memory_limit'),
                    is_finite($megapixels) ? (string) (int) $megapixels : 'illimité'
                ),
                'critical' => false,
            ],
            [
                'label'    => 'Envoi d\'e-mails',
                'ok'       => \App\Core\Environment::functionAvailable('mail') || \App\Core\Environment::functionAvailable('fsockopen'),
                'detail'   => 'Pilote ' . (string) Config::get('mail.driver', 'log')
                    . ' — sur un hébergement gratuit, les e-mails peuvent être bloqués : copiez les liens depuis la page « Partager ».',
                'critical' => false,
            ],
        ];
    }
}
