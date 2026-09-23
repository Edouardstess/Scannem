<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditAction;
use App\Models\TokenType;
use App\Repositories\GalleryRepository;
use App\Services\AuditService;
use App\Services\GalleryService;
use App\Services\MailService;
use App\Services\TokenService;

/**
 * Everything the photographer does with a gallery's two links.
 *
 * Kept apart from GalleryController because it is a different job: that one
 * edits a gallery's content, this one governs who can reach it. Revocation
 * and regeneration are the actions taken under pressure — a link sent to the
 * wrong person — and they deserve to be readable on their own.
 */
final class ShareController extends Controller
{
    public function __construct(
        private GalleryRepository $galleries = new GalleryRepository(),
        private GalleryService $service = new GalleryService(),
        private TokenService $tokens = new TokenService(),
        private AuditService $audit = new AuditService(),
        private MailService $mail = new MailService()
    ) {
    }

    /** GET /admin/galleries/{id}/share */
    public function show(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $gallery = $this->orFail($this->galleries->findDetailed($id), 'Galerie introuvable.');

        return $this->view('admin.galleries.share', [
            'title'   => 'Partager — ' . (string) $gallery['title'],
            'gallery' => $gallery,
            'links'   => $this->links($id),
        ]);
    }

    /** POST /admin/galleries/{id}/tokens/{type}/regenerate */
    public function regenerate(Request $request, array $parameters): Response
    {
        [$id, $type] = $this->resolve($parameters);

        $this->service->regenerateToken($id, $type);
        $this->audit->record(AuditAction::TOKEN_REGENERATED, $request, $id, null, ['type' => $type]);

        $this->flashSuccess(sprintf(
            "Nouveau lien de %s généré. L'ancien lien ne fonctionne plus.",
            mb_strtolower(TokenType::label($type))
        ));

        return $this->redirect('admin/galleries/' . $id . '/share');
    }

    /** POST /admin/galleries/{id}/tokens/{type}/revoke */
    public function revoke(Request $request, array $parameters): Response
    {
        [$id, $type] = $this->resolve($parameters);

        $this->service->revokeToken($id, $type);
        $this->audit->record(AuditAction::TOKEN_REVOKED, $request, $id, null, ['type' => $type]);

        $this->flashSuccess(sprintf('Lien de %s désactivé.', mb_strtolower(TokenType::label($type))));

        return $this->redirect('admin/galleries/' . $id . '/share');
    }

    /** POST /admin/galleries/{id}/notify */
    public function notifyClient(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $gallery = $this->orFail($this->galleries->findDetailed($id), 'Galerie introuvable.');
        $back = 'admin/galleries/' . $id . '/share';

        $recipient = trim((string) ($gallery['client_email'] ?? ''));

        if ($recipient === '') {
            $this->flashError("Ce client n'a pas d'adresse e-mail enregistrée.");

            return $this->back($request, $back);
        }

        $kind = $request->string('kind', 'view') === 'download' ? 'download' : 'view';
        $links = $this->links($id);

        if ($links[$kind]['url'] === null) {
            $this->flashError(sprintf(
                'Aucun lien de %s actif à envoyer.',
                $kind === 'download' ? 'téléchargement' : 'consultation'
            ));

            return $this->back($request, $back);
        }

        $sent = $kind === 'download'
            ? $this->mail->notifyDownloadAvailable($recipient, $gallery, $links['download']['url'])
            : $this->mail->notifyGalleryReady($recipient, $gallery, $links['view']['url']);

        $sent
            ? $this->flashSuccess('E-mail envoyé à ' . $recipient . '.')
            : $this->flashError("L'e-mail n'a pas pu être envoyé (l'hébergeur bloque peut-être l'envoi). Copiez les liens ci-dessous et transmettez-les vous-même : e-mail, SMS ou WhatsApp.");

        return $this->back($request, $back);
    }

    /**
     * The shareable URLs for a gallery.
     *
     * The raw token is recovered from its encrypted copy. When APP_KEY is
     * missing or has changed, the URL is null and the view offers to
     * regenerate — a link that cannot be displayed is never faked.
     *
     * @return array{view: array<string, mixed>, download: array<string, mixed>}
     */
    public function links(int $galleryId): array
    {
        $tokens = $this->service->activeTokens($galleryId);
        $links = [];

        foreach (['view' => TokenType::VIEW, 'download' => TokenType::DOWNLOAD] as $key => $type) {
            $token = $tokens[$key];
            $raw = $token === null ? null : $this->tokens->revealRawToken($token);

            $links[$key] = [
                'token'        => $token,
                'url'          => $raw === null ? null : $this->tokens->urlFor($type, $raw),
                'expires_at'   => $token === null ? null : ($token['expires_at'] ?? null),
                'last_used_at' => $token === null ? null : ($token['last_used_at'] ?? null),
                'use_count'    => $token === null ? 0 : (int) $token['use_count'],
            ];
        }

        return $links;
    }

    /**
     * @param array<string, string> $parameters
     * @return array{0: int, 1: string}
     */
    private function resolve(array $parameters): array
    {
        $id = (int) $parameters['id'];
        $type = strtoupper((string) $parameters['type']);

        $this->orFail($this->galleries->find($id), 'Galerie introuvable.');

        if (!in_array($type, TokenType::ALL, true)) {
            $this->abort(404, 'Type de lien inconnu.');
        }

        return [$id, $type];
    }
}
