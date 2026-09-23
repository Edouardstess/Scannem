<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Exceptions\UploadException;
use App\Repositories\ServiceRepository;
use App\Services\PublicImageService;

final class ServiceAdminController extends Controller
{
    public function __construct(
        private ServiceRepository $services = new ServiceRepository(),
        private PublicImageService $images = new PublicImageService()
    ) {
    }

    /** GET /admin/services */
    public function index(Request $request): Response
    {
        return $this->view('admin.services.index', [
            'title'    => 'Prestations',
            'services' => $this->services->allOrdered(),
        ]);
    }

    /** GET /admin/services/create */
    public function create(Request $request): Response
    {
        return $this->view('admin.services.form', ['title' => 'Nouvelle prestation', 'service' => null]);
    }

    /** POST /admin/services */
    public function store(Request $request): Response
    {
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'admin/services/create');
        }

        $data = $validator->validated();
        $attributes = $this->attributes($request, $data);
        $attributes['slug'] = $this->uniqueSlug(str_slug((string) $data['title']));
        $attributes['sort_order'] = $this->services->nextSortOrder();
        $attributes['created_at'] = date('Y-m-d H:i:s');
        $attributes['updated_at'] = date('Y-m-d H:i:s');

        $image = $this->storeImage($request);

        if (is_array($image) && isset($image['error'])) {
            return $this->redirectWithErrors($request, ['image' => $image['error']], 'admin/services/create');
        }

        if (is_string($image)) {
            $attributes['image_path'] = $image;
        }

        $this->services->insert($attributes);
        $this->flashSuccess('Prestation créée.');

        return $this->redirect('admin/services');
    }

    /** GET /admin/services/{id}/edit */
    public function edit(Request $request, array $parameters): Response
    {
        $service = $this->orFail($this->services->find((int) $parameters['id']), 'Prestation introuvable.');

        return $this->view('admin.services.form', [
            'title'   => 'Modifier la prestation',
            'service' => $service,
        ]);
    }

    /** PUT /admin/services/{id} */
    public function update(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $service = $this->orFail($this->services->find($id), 'Prestation introuvable.');

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'admin/services/' . $id . '/edit');
        }

        $data = $validator->validated();
        $attributes = $this->attributes($request, $data);
        $attributes['sort_order'] = $request->int('sort_order', (int) $service['sort_order']);
        $attributes['updated_at'] = date('Y-m-d H:i:s');

        if ((string) $service['title'] !== (string) $data['title']) {
            $attributes['slug'] = $this->uniqueSlug(str_slug((string) $data['title']), $id);
        }

        $image = $this->storeImage($request);

        if (is_array($image) && isset($image['error'])) {
            return $this->redirectWithErrors($request, ['image' => $image['error']], 'admin/services/' . $id . '/edit');
        }

        if (is_string($image)) {
            $this->images->delete($service['image_path'] ?? null);
            $attributes['image_path'] = $image;
        }

        $this->services->update($id, $attributes);
        $this->flashSuccess('Prestation mise à jour.');

        return $this->redirect('admin/services');
    }

    /** DELETE /admin/services/{id} */
    public function destroy(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $service = $this->orFail($this->services->find($id), 'Prestation introuvable.');

        $this->services->delete($id);
        $this->images->delete($service['image_path'] ?? null);
        $this->flashSuccess('Prestation supprimée.');

        return $this->redirect('admin/services');
    }

    /** @return string|array{error: string}|null */
    private function storeImage(Request $request): string|array|null
    {
        $file = $request->file('image');

        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        try {
            return $this->images->store($file, 'services')['path'];
        } catch (UploadException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(Request $request, array $data): array
    {
        $price = trim((string) ($data['price_from'] ?? ''));

        return [
            'title'        => (string) $data['title'],
            'summary'      => $data['summary'] ?? null,
            'description'  => $data['description'] ?? null,
            'price_from'   => $price === '' ? null : (float) str_replace(',', '.', $price),
            'currency'     => $request->string('currency', 'EUR'),
            'duration'     => $data['duration'] ?? null,
            'deliverables' => $data['deliverables'] ?? null,
            'status'       => $request->string('status', 'published'),
        ];
    }

    private function uniqueSlug(string $slug, ?int $exceptId = null): string
    {
        $slug = $slug === '' ? 'prestation' : $slug;
        $candidate = $slug;
        $suffix = 2;

        while ($this->services->slugExists($candidate, $exceptId)) {
            $candidate = $slug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function validator(Request $request): Validator
    {
        return Validator::make($request->all(), [
            'title'        => 'required|string|min:2|max:190',
            'summary'      => 'nullable|string|max:255',
            'description'  => 'nullable|string|max:5000',
            'price_from'   => 'nullable|numeric|min:0',
            'duration'     => 'nullable|string|max:80',
            'deliverables' => 'nullable|string|max:2000',
            'status'       => 'required|in:published,draft',
        ], [], [
            'title'      => 'titre',
            'summary'    => 'résumé',
            'price_from' => 'prix',
            'duration'   => 'durée',
            'status'     => 'statut',
        ]);
    }
}
