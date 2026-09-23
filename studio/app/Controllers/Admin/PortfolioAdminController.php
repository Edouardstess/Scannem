<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Exceptions\UploadException;
use App\Models\AuditAction;
use App\Repositories\PortfolioRepository;
use App\Services\AuditService;
use App\Services\PublicImageService;

final class PortfolioAdminController extends Controller
{
    public function __construct(
        private PortfolioRepository $portfolio = new PortfolioRepository(),
        private PublicImageService $images = new PublicImageService(),
        private AuditService $audit = new AuditService()
    ) {
    }

    /** GET /admin/portfolio */
    public function index(Request $request): Response
    {
        return $this->view('admin.portfolio.index', [
            'title'      => 'Portfolio',
            'items'      => $this->portfolio->allItems(),
            'categories' => $this->portfolio->categories(),
        ]);
    }

    /** GET /admin/portfolio/create */
    public function create(Request $request): Response
    {
        return $this->view('admin.portfolio.form', [
            'title'      => 'Ajouter une photo au portfolio',
            'item'       => null,
            'categories' => $this->portfolio->categories(),
        ]);
    }

    /** POST /admin/portfolio */
    public function store(Request $request): Response
    {
        $validator = $this->itemValidator($request);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'admin/portfolio/create');
        }

        $file = $request->file('image');

        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->redirectWithErrors($request, ['image' => 'Sélectionnez une image.'], 'admin/portfolio/create');
        }

        try {
            $stored = $this->images->store($file, 'portfolio');
        } catch (UploadException $e) {
            return $this->redirectWithErrors($request, ['image' => $e->getMessage()], 'admin/portfolio/create');
        }

        $data = $validator->validated();

        $id = $this->portfolio->insert([
            'category_id'    => $this->categoryId($request),
            'title'          => (string) $data['title'],
            'description'    => $data['description'] ?? null,
            'image_path'     => $stored['path'],
            'thumbnail_path' => $stored['thumbnail'],
            'width'          => $stored['width'],
            'height'         => $stored['height'],
            'featured'       => $request->bool('featured') ? 1 : 0,
            'sort_order'     => $this->portfolio->nextSortOrder(),
            'status'         => $request->string('status', 'published'),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->audit->record(AuditAction::PORTFOLIO_UPDATED, $request, null, null, ['item_id' => $id]);
        $this->flashSuccess('Photo ajoutée au portfolio.');

        return $this->redirect('admin/portfolio');
    }

    /** GET /admin/portfolio/{id}/edit */
    public function edit(Request $request, array $parameters): Response
    {
        $item = $this->orFail($this->portfolio->findItem((int) $parameters['id']), 'Élément introuvable.');

        return $this->view('admin.portfolio.form', [
            'title'      => 'Modifier la photo',
            'item'       => $item,
            'categories' => $this->portfolio->categories(),
        ]);
    }

    /** PUT /admin/portfolio/{id} */
    public function update(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $item = $this->orFail($this->portfolio->findItem($id), 'Élément introuvable.');

        $validator = $this->itemValidator($request);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'admin/portfolio/' . $id . '/edit');
        }

        $data = $validator->validated();

        $attributes = [
            'category_id' => $this->categoryId($request),
            'title'       => (string) $data['title'],
            'description' => $data['description'] ?? null,
            'featured'    => $request->bool('featured') ? 1 : 0,
            'status'      => $request->string('status', 'published'),
            'sort_order'  => $request->int('sort_order', (int) $item['sort_order']),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $file = $request->file('image');

        if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $stored = $this->images->store($file, 'portfolio');
            } catch (UploadException $e) {
                return $this->redirectWithErrors($request, ['image' => $e->getMessage()], 'admin/portfolio/' . $id . '/edit');
            }

            // The replacement is stored before the old file is removed, so a
            // failed upload never leaves the item without an image.
            $this->images->delete($item['image_path'] ?? null);

            $attributes['image_path'] = $stored['path'];
            $attributes['thumbnail_path'] = $stored['thumbnail'];
            $attributes['width'] = $stored['width'];
            $attributes['height'] = $stored['height'];
        }

        $this->portfolio->update($id, $attributes);
        $this->audit->record(AuditAction::PORTFOLIO_UPDATED, $request, null, null, ['item_id' => $id]);
        $this->flashSuccess('Photo mise à jour.');

        return $this->redirect('admin/portfolio');
    }

    /** DELETE /admin/portfolio/{id} */
    public function destroy(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $item = $this->orFail($this->portfolio->findItem($id), 'Élément introuvable.');

        $this->portfolio->delete($id);
        $this->images->delete($item['image_path'] ?? null);

        $this->audit->record(AuditAction::PORTFOLIO_UPDATED, $request, null, null, ['deleted_item_id' => $id]);
        $this->flashSuccess('Photo retirée du portfolio.');

        return $this->redirect('admin/portfolio');
    }

    // --- Categories --------------------------------------------------------

    /** GET /admin/portfolio/categories */
    public function categories(Request $request): Response
    {
        return $this->view('admin.portfolio.categories', [
            'title'      => 'Catégories du portfolio',
            'categories' => $this->portfolio->categories(),
        ]);
    }

    /** POST /admin/portfolio/categories */
    public function storeCategory(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:2|max:120',
            'description' => 'nullable|string|max:1000',
            'status'      => 'required|in:published,draft',
        ], [], ['name' => 'nom', 'status' => 'statut']);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'admin/portfolio/categories');
        }

        $name = (string) $validator->validated()['name'];
        $slug = $this->uniqueSlug(str_slug($name));

        $this->portfolio->createCategory([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $validator->validated()['description'] ?? null,
            'sort_order'  => $this->portfolio->nextCategorySortOrder(),
            'status'      => $request->string('status', 'published'),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->flashSuccess('Catégorie créée.');

        return $this->redirect('admin/portfolio/categories');
    }

    /** PUT /admin/portfolio/categories/{id} */
    public function updateCategory(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $category = $this->orFail($this->portfolio->findCategory($id), 'Catégorie introuvable.');

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:2|max:120',
            'description' => 'nullable|string|max:1000',
            'status'      => 'required|in:published,draft',
        ], [], ['name' => 'nom', 'status' => 'statut']);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'admin/portfolio/categories');
        }

        $name = (string) $validator->validated()['name'];

        // The slug only changes when the name does, so public URLs that were
        // already shared keep working through a description edit.
        $slug = (string) $category['name'] === $name
            ? (string) $category['slug']
            : $this->uniqueSlug(str_slug($name), $id);

        $this->portfolio->updateCategory($id, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $validator->validated()['description'] ?? null,
            'status'      => $request->string('status', 'published'),
            'sort_order'  => $request->int('sort_order', (int) $category['sort_order']),
        ]);

        $this->flashSuccess('Catégorie mise à jour.');

        return $this->redirect('admin/portfolio/categories');
    }

    /** DELETE /admin/portfolio/categories/{id} */
    public function destroyCategory(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $this->orFail($this->portfolio->findCategory($id), 'Catégorie introuvable.');

        // portfolio_items.category_id is ON DELETE SET NULL: the photographs
        // survive, uncategorised, rather than disappearing with the category.
        $this->portfolio->deleteCategory($id);
        $this->flashSuccess('Catégorie supprimée. Les photos associées restent dans le portfolio.');

        return $this->redirect('admin/portfolio/categories');
    }

    private function uniqueSlug(string $slug, ?int $exceptId = null): string
    {
        $slug = $slug === '' ? 'categorie' : $slug;
        $candidate = $slug;
        $suffix = 2;

        while ($this->portfolio->categorySlugExists($candidate, $exceptId)) {
            $candidate = $slug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function categoryId(Request $request): ?int
    {
        $id = $request->int('category_id');

        return $id > 0 && $this->portfolio->findCategory($id) !== null ? $id : null;
    }

    private function itemValidator(Request $request): Validator
    {
        return Validator::make($request->all(), [
            'title'       => 'required|string|min:2|max:190',
            'description' => 'nullable|string|max:2000',
            'category_id' => 'nullable|integer',
            'status'      => 'required|in:published,draft',
        ], [], ['title' => 'titre', 'status' => 'statut', 'category_id' => 'catégorie']);
    }
}
