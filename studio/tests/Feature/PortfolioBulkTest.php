<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Admin\PortfolioAdminController;
use App\Core\Request;
use App\Repositories\PortfolioRepository;
use App\Services\PublicImageService;
use Tests\Support\TestCase;

/**
 * Showing work to every visitor: several portfolio photos in one go, with
 * the category, visibility and "featured" chosen once for the batch.
 */
final class PortfolioBulkTest extends TestCase
{
    /** @var array<int, string> */
    private array $created = [];

    public function tearDown(): void
    {
        $images = new PublicImageService();

        foreach ($this->created as $path) {
            $images->delete($path);
        }
    }

    public function testEachPhotoBecomesAPublishedPortfolioItem(): void
    {
        $portfolio = new PortfolioRepository();
        $categoryId = $portfolio->createCategory([
            'name' => 'Mariages', 'slug' => 'mariages-bulk-' . bin2hex(random_bytes(3)),
            'description' => null, 'sort_order' => 1, 'status' => 'published',
        ]);

        $response = (new PortfolioAdminController())->bulkStore($this->upload('coucher-de-soleil_jacmel.jpg', [
            'category_id' => (string) $categoryId,
            'status'      => 'published',
            'featured'    => '1',
        ]));

        $this->assertSame(201, $response->status());

        $data = json_decode($response->body(), true);
        $item = $portfolio->findItem((int) $data['uploaded'][0]['id']);
        $this->created[] = (string) $item['image_path'];

        $this->assertSame('Coucher de soleil jacmel', $item['title']);
        $this->assertSame($categoryId, (int) $item['category_id']);
        $this->assertSame('published', $item['status']);
        $this->assertSame(1, (int) $item['featured']);
        $this->assertTrue(is_file(dirname(__DIR__, 2) . '/public/' . $item['image_path']), 'The image is stored in the public folder.');

        $visible = array_map('intval', array_column($portfolio->publishedItems(), 'id'));
        $this->assertContains((int) $item['id'], $visible, 'Visitors see the new photo.');
        $this->assertContains((int) $item['id'], array_map('intval', array_column($portfolio->featured(50), 'id')));
    }

    public function testDraftsStayHiddenFromVisitors(): void
    {
        $portfolio = new PortfolioRepository();
        $response = (new PortfolioAdminController())->bulkStore($this->upload('IMG_4821.jpg', ['status' => 'draft']));
        $item = $portfolio->findItem((int) json_decode($response->body(), true)['uploaded'][0]['id']);
        $this->created[] = (string) $item['image_path'];

        $this->assertSame('draft', $item['status']);
        $this->assertSame('Photographie', $item['title'], 'Camera file names are not used as titles.');
        $this->assertFalse(in_array((int) $item['id'], array_map('intval', array_column($portfolio->publishedItems(), 'id')), true));
    }

    public function testANonImageIsRefusedWithAReadableMessage(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pf');
        file_put_contents($path, '<?php echo "hello";');

        $request = new Request('POST', '/admin/portfolio/bulk', [], [], [], [
            'photo' => ['name' => 'pas-une-image.jpg', 'type' => 'image/jpeg', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)],
        ], []);

        $response = (new PortfolioAdminController())->bulkStore($request);
        @unlink($path);

        $this->assertSame(422, $response->status());
        $this->assertStringContains("n'est pas une image valide", $response->body());
    }

    public function testTitlesComeFromFileNames(): void
    {
        $this->assertSame('Portrait de famille', PortfolioAdminController::titleFromFilename('portrait-de-famille.JPG'));
        $this->assertSame('Mariage 2026', PortfolioAdminController::titleFromFilename('Mariage_2026.jpeg'));
        $this->assertSame('Portraits', PortfolioAdminController::titleFromFilename('DSC01234.jpg', 'Portraits'));
        $this->assertSame('Photographie', PortfolioAdminController::titleFromFilename('20260914_183022.jpg'));
        $this->assertSame('Été à jacmel', PortfolioAdminController::titleFromFilename('été à jacmel.webp'));
    }

    /** @param array<string, string> $fields */
    private function upload(string $name, array $fields): Request
    {
        $path = tempnam(sys_get_temp_dir(), 'pf');
        $image = imagecreatetruecolor(1200, 800);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 90, 60));
        imagejpeg($image, $path, 85);
        imagedestroy($image);

        return new Request('POST', '/admin/portfolio/bulk', [], $fields, [], [
            'photo' => ['name' => $name, 'type' => 'image/jpeg', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)],
        ], []);
    }
}
