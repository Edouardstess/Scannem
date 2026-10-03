<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\HomeController;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Services\ImmersiveHomeService;
use App\Services\SettingsService;
use App\Validators\HomepageRequest;
use Tests\Support\TestCase;

/**
 * The immersive homepage: its photos come from the published portfolio,
 * its texts from Admin → Page d'accueil, and what the photographer types
 * can never break the page or inject markup.
 */
final class ImmersiveHomeTest extends TestCase
{
    public function setUp(): void
    {
        Database::connection()->exec('DELETE FROM portfolio_items');
        Database::connection()->exec('DELETE FROM portfolio_categories');
        SettingsService::flush();
    }

    public function testPlaceholdersAreShownUntilAPhotoIsPublished(): void
    {
        $home = new ImmersiveHomeService(['studio_name' => 'L’ENFANT VISUAL']);
        $content = $home->content();

        $this->assertTrue($home->usesPlaceholders());
        $this->assertStringContains('immersif/placeholders/hero.jpg', $content['hook']['image']);
        $this->assertCount(ImmersiveHomeService::PROOF_COUNT, $content['proof']['projects']);
        $this->assertCount(ImmersiveHomeService::FLOATER_COUNT, $content['hook']['floaters']);
        $this->assertCount(ImmersiveHomeService::TRAIL_COUNT, $content['trail']);
        $this->assertCount(3, $content['motto']['words'], 'The engine reads exactly three motto words.');
        $this->assertCount(3, $content['universes']['items']);
    }

    public function testPublishedPortfolioPhotosFillThePageFeaturedFirst(): void
    {
        $pdo = Database::connection();
        $pdo->exec("INSERT INTO portfolio_categories (name, slug, sort_order, status, created_at) VALUES ('Mariage', 'mariage', 1, 'published', '2025-06-01 10:00:00')");
        $categoryId = (int) $pdo->lastInsertId();

        $insert = $pdo->prepare(
            'INSERT INTO portfolio_items (category_id, title, image_path, thumbnail_path, featured, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$categoryId, 'Le premier regard', 'uploads/portfolio/a.jpg', 'uploads/portfolio/a-thumb.jpg', 0, 1, 'published', '2025-06-01 10:00:00']);
        $insert->execute([$categoryId, 'La sortie de l’église', 'uploads/portfolio/b.jpg', null, 1, 2, 'published', '2025-06-02 10:00:00']);
        $insert->execute([$categoryId, 'Brouillon', 'uploads/portfolio/c.jpg', null, 1, 3, 'draft', '2025-06-03 10:00:00']);

        $home = new ImmersiveHomeService([]);
        $content = $home->content();
        $titles = array_column($content['proof']['projects'], 'title');

        $this->assertFalse($home->usesPlaceholders());
        $this->assertSame('La sortie de l’église', $titles[0], 'Featured photos come first.');
        $this->assertContains('Le premier regard', $titles);
        $this->assertFalse(in_array('Brouillon', $titles, true), 'Drafts never reach visitors.');
        $this->assertSame('MARIAGE — 2025', $content['proof']['projects'][0]['meta']);
        $this->assertStringContains('uploads/portfolio/b.jpg', $content['hook']['image'], 'The opening image is the first featured photo.');
        $this->assertStringContains('uploads/portfolio/a-thumb.jpg', implode(' ', $content['trail']), 'Thumbnails feed the image trail.');

        $withHero = (new ImmersiveHomeService(['hero_image' => 'uploads/site/hero.jpg']))->content();
        $this->assertStringContains('uploads/site/hero.jpg', $withHero['hook']['image'], 'The hero image from Paramètres wins.');
    }

    public function testStoredTextsOverrideDefaultsAndBlanksFallBack(): void
    {
        $home = new ImmersiveHomeService(['immersive' => [
            'hook_line1' => 'Vos instants,',
            'pill'       => '   ',
            'accent'     => 'red;}</style><script>',
        ]]);

        $this->assertSame('Vos instants,', $home->texts()['hook_line1']);
        $this->assertSame(ImmersiveHomeService::DEFAULTS['pill'], $home->texts()['pill']);
        $this->assertSame(ImmersiveHomeService::DEFAULTS['accent'], $home->accent(), 'A malformed accent never reaches the inline style.');
        $this->assertSame('#1a1915', (new ImmersiveHomeService(['immersive' => ['accent' => '#f8cf9f']]))->accentInk());
        $this->assertSame('#ffffff', (new ImmersiveHomeService(['immersive' => ['accent' => '#1d3557']]))->accentInk());
    }

    public function testTheHomepageRendersWithTheContentAsInertJson(): void
    {
        (new SettingsService())->set('immersive', ['hook_line1' => '<script>alert(1)</script>'], 'home');

        $html = $this->home();

        $this->assertStringContains('id="site-content"', $html);
        $this->assertStringContains('immersif/app.js', $html);
        $this->assertFalse(str_contains($html, '<script>alert(1)</script>'), 'Typed text is escaped everywhere.');
        $this->assertStringContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testTheClassicHomepageCanStillBeChosen(): void
    {
        (new SettingsService())->set('home_layout', 'classic', 'home');

        $this->assertFalse(str_contains($this->home(), 'id="site-content"'));
    }

    public function testTheAdminFormRefusesTextsThatWouldBreakTheAnimation(): void
    {
        $tooLong = (new HomepageRequest())->validate($this->request([
            'positioning' => str_repeat('a', 43),
            'accent'      => 'orange',
        ]));

        foreach (['positioning', 'accent'] as $field) {
            $this->assertTrue(isset($tooLong->errors()[$field]), 'No error on ' . $field);
        }

        $misshapen = (new HomepageRequest())->validate($this->request([
            'manifesto'    => 'Sans mot entouré.',
            'pill'         => 'trois mots ici',
            'motto_1_word' => 'deux-mots ok',
            'quote_figure' => 'beaucoup',
        ]));

        foreach (['manifesto', 'pill', 'motto_1_word', 'quote_figure'] as $field) {
            $this->assertTrue(isset($misshapen->errors()[$field]), 'No error on ' . $field);
        }
    }

    public function testTheAdminFormKeepsValidTexts(): void
    {
        $form = (new HomepageRequest())->validate($this->request([
            'manifesto'    => 'Je photographie des [[instants vrais]], sans pose.',
            'pill'         => 'souvenirs.',
            'quote_figure' => '+40',
            'accent'       => '#F8CF9F',
        ]));

        $this->assertFalse($form->fails(), implode(' ', array_map('implode', $form->errors())));
        $this->assertSame('immersive', $form->data()['home_layout']);
        $this->assertSame('#f8cf9f', $form->data()['immersive']['accent']);
        $this->assertSame('+40', $form->data()['immersive']['quote_figure']);
    }

    /** GET / as the application renders it (settings are shared with every view). */
    private function home(): string
    {
        SettingsService::flush();
        View::share('settings', SettingsService::make()->all());

        return (new HomeController())->index(new Request('GET', '/', [], [], ['REMOTE_ADDR' => '203.0.113.1'], [], []))->body();
    }

    /** @param array<string, string> $overrides */
    private function request(array $overrides): Request
    {
        $body = array_merge(['home_layout' => 'immersive', 'theme' => 'light', 'accent' => '#f8cf9f'], $overrides);

        return new Request('POST', '/admin/homepage', [], $body, ['REMOTE_ADDR' => '203.0.113.1'], [], []);
    }
}
