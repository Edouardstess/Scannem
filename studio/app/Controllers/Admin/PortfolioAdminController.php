<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\UploadException;
use App\Models\AuditAction;
use App\Repositories\PortfolioRepository;
use App\Services\AuditService;
use App\Services\PublicImageService;
use App\Validators\PortfolioCategoryRequest;
use App\Validators\PortfolioItemRequest;

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
        $form = (new PortfolioItemRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/portfolio/create');
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

        $id = $this->portfolio->insert($form->data() + [
            'image_path'     => $stored['path'],
            'thumbnail_path' => $stored['thumbnail'],
            'width'          => $stored['width'],
            'height'         => $stored['height'],
            'sort_order'     => $this->portfolio->nextSortOrder(),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->audit->record(AuditAction::PORTFOLIO_UPDATED, $request, null, null, ['item_id' => $id]);
        $this->flashSuccess('Photo ajoutée au portfolio.');

        return $this->redirect('admin/portfolio');
    }

    /**
     * POST /admin/portfolio/bulk — one photo per request, from the uploader.
     *
     * Every photo gets the category, status and "featured" chosen above the
     * drop zone; its title comes from the file name and can be edited later.
     */
    public function bulkStore(Request $request): Response
    {
        $file = $request->file('photo');

        if ($file === null) {
            return $this->json(['error' => 'Aucun fichier reçu.'], 422);
        }

        $categoryId = $request->int('category_id');
        $category = $categoryId > 0 ? $this->portfolio->findCategory($categoryId) : null;
        $status = $request->string('status') === 'draft' ? 'draft' : 'published';
        $name = (string) ($file['name'] ?? 'photo');

        try {
            $stored = $this->images->store($file, 'portfolio');
        } catch (UploadException $e) {
            return $this->json(['uploaded' => [], 'failed' => [['name' => $name, 'error' => $e->getMessage()]]], 422);
        } catch (\Throwable $e) {
            // Image processing refusals (too many pixels for the memory
            // available, unreadable file) carry a message worth showing.
            return $this->json(['uploaded' => [], 'failed' => [['name' => $name, 'error' => $e->getMessage()]]], 422);
        }

        $now = date('Y-m-d H:i:s');
        $id = $this->portfolio->insert([
            'title'          => self::titleFromFilename($name, $category !== null ? (string) $category['name'] : null),
            'description'    => null,
            'category_id'    => $category !== null ? (int) $category['id'] : null,
            'featured'       => $request->bool('featured') ? 1 : 0,
            'status'         => $status,
            'image_path'     => $stored['path'],
            'thumbnail_path' => $stored['thumbnail'],
            'width'          => $stored['width'],
            'height'         => $stored['height'],
            'sort_order'     => $this->portfolio->nextSortOrder(),
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $this->audit->record(AuditAction::PORTFOLIO_UPDATED, $request, null, null, ['item_id' => $id, 'bulk' => true]);

        return $this->json([
            'uploaded' => [[
                'id'        => $id,
                'name'      => $name,
                'thumb_url' => url($stored['thumbnail']),
            ]],
            'failed'   => [],
            'total'    => count($this->portfolio->allItems()),
        ], 201);
    }

    /**
     * A readable title from a file name: "coucher-de-soleil_jacmel.jpg" gives
     * "Coucher de soleil jacmel". Camera names (IMG_1234, DSC01234…) say
     * nothing to a visitor, so they fall back to the category name.
     */
    public static function titleFromFilename(string $filename, ?string $fallback = null): string
    {
        $base = trim((string) preg_replace('/[\s_\-.]+/u', ' ', pathinfo($filename, PATHINFO_FILENAME)));
        $cameraName = preg_match('/^(img|dsc[nf]?|dji|pxl|mvimg|photo|image|_?mg|p|wp|screenshot)?[\s\d]*$/i', $base) === 1;

        if ($base === '' || $cameraName) {
            return $fallback !== null && trim($fallback) !== '' ? trim($fallback) : 'Photographie';
        }

        return mb_strtoupper(mb_substr($base, 0, 1)) . mb_substr(mb_substr($base, 1), 0, 189);
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

        $form = (new PortfolioItemRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/portfolio/' . $id . '/edit');
        }

        $attributes = $form->data() + [
            'sort_order' => $request->int('sort_order', (int) $item['sort_order']),
            'updated_at' => date('Y-m-d H:i:s'),
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
        $form = (new PortfolioCategoryRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/portfolio/categories');
        }

        $this->portfolio->createCategory($form->data() + [
            'slug'       => $this->uniqueSlug(str_slug((string) $form->value('name'))),
            'sort_order' => $this->portfolio->nextCategorySortOrder(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flashSuccess('Catégorie créée.');

        return $this->redirect('admin/portfolio/categories');
    }

    /** PUT /admin/portfolio/categories/{id} */
    public function updateCategory(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $category = $this->orFail($this->portfolio->findCategory($id), 'Catégorie introuvable.');

        $form = (new PortfolioCategoryRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/portfolio/categories');
        }

        $name = (string) $form->value('name');

        // The slug only changes when the name does, so public URLs that were
        // already shared keep working through a description edit.
        $slug = (string) $category['name'] === $name
            ? (string) $category['slug']
            : $this->uniqueSlug(str_slug($name), $id);

        $this->portfolio->updateCategory($id, $form->data() + [
            'slug'       => $slug,
            'sort_order' => $request->int('sort_order', (int) $category['sort_order']),
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


}
