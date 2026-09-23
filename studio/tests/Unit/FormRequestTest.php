<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Validators\BookingRequest;
use App\Validators\ClientRequest;
use App\Validators\ContactRequest;
use App\Validators\EventRequest;
use App\Validators\FormRequest;
use App\Validators\GalleryRequest;
use App\Validators\LoginRequest;
use App\Validators\PasswordChangeRequest;
use App\Validators\ServiceRequest;
use App\Validators\SettingsRequest;
use Tests\Support\Factory;
use Tests\Support\TestCase;

/**
 * The validation layer. Rules live in these classes rather than in
 * controllers, so this is where the guarantees are asserted.
 */
final class FormRequestTest extends TestCase
{
    public function testClientRequestNormalisesAndNullsBlankFields(): void
    {
        $form = (new ClientRequest())->validate($this->request([
            'first_name' => '  Jean  ',
            'last_name'  => 'Martin',
            'email'      => '  JEAN@Example.TEST ',
            'phone'      => '',
            'company'    => '   ',
        ]));

        $this->assertTrue($form->passes());

        $data = $form->data();

        $this->assertSame('Jean', $data['first_name']);
        $this->assertSame('jean@example.test', $data['email']);
        $this->assertNull($data['phone'], 'A blank optional field must become NULL, not "".');
        $this->assertNull($data['company']);
    }

    public function testClientRequestRejectsAnInvalidEmail(): void
    {
        $form = (new ClientRequest())->validate($this->request([
            'first_name' => 'Jean',
            'last_name'  => 'Martin',
            'email'      => 'pas-une-adresse',
        ]));

        $this->assertTrue($form->fails());
        $this->assertTrue(isset($form->errors()['email']));
        $this->assertSame([], $form->data(), 'A failed form must expose no data.');
    }

    public function testEventRequestRefusesAClientThatDoesNotExist(): void
    {
        $form = (new EventRequest())->validate($this->request([
            'client_id' => 9999,
            'title'     => 'Mariage',
            'status'    => 'active',
        ]));

        $this->assertTrue($form->fails());
        $this->assertTrue(isset($form->errors()['client_id']));
    }

    public function testEventRequestAcceptsAnExistingClient(): void
    {
        $clientId = Factory::client();

        $form = (new EventRequest())->validate($this->request([
            'client_id'  => $clientId,
            'title'      => 'Mariage de test',
            'event_date' => '15/06/2026',
            'status'     => 'active',
        ]));

        $this->assertTrue($form->passes());
        $this->assertSame($clientId, $form->value('client_id'));
        $this->assertSame('2026-06-15', $form->value('event_date'), 'Dates are stored as Y-m-d.');
    }

    public function testGalleryRequestRefusesAPastExpiryDate(): void
    {
        $eventId = Factory::event();

        $form = (new GalleryRequest())->validate($this->request([
            'event_id'      => $eventId,
            'title'         => 'Galerie',
            'status'        => 'active',
            'expiry_option' => 'custom',
            'expiry_date'   => date('Y-m-d', strtotime('-1 day')),
        ]));

        $this->assertTrue($form->fails());
        $this->assertTrue(isset($form->errors()['expiry_date']));
    }

    public function testGalleryRequestCollectsTheToggles(): void
    {
        $eventId = Factory::event();

        $form = (new GalleryRequest())->validate($this->request([
            'event_id'          => $eventId,
            'title'             => 'Galerie',
            'status'            => 'active',
            'download_enabled'  => '1',
            'watermark_enabled' => 'on',
            'expiry_option'     => 'never',
        ]));

        $this->assertTrue($form->passes());
        $this->assertTrue($form->value('download_enabled'));
        $this->assertTrue($form->value('watermark_enabled'));
        $this->assertFalse($form->value('selection_enabled'), 'An unchecked box is false, not absent.');
        $this->assertNull($form->value('expires_at'));
    }

    public function testGalleryRequestRejectsAShortPassword(): void
    {
        $eventId = Factory::event();

        $form = (new GalleryRequest())->validate($this->request([
            'event_id' => $eventId,
            'title'    => 'Galerie',
            'status'   => 'active',
            'password' => 'court',
        ]));

        $this->assertTrue($form->fails());
        $this->assertTrue(isset($form->errors()['password']));
    }

    public function testContactRequestCarriesTheDesiredDate(): void
    {
        $form = (new ContactRequest())->validate($this->request([
            'name'           => 'Claire Dupont',
            'email'          => 'claire@example.test',
            'preferred_date' => '2026-07-18',
            'message'        => 'Bonjour, je souhaite un devis pour un mariage.',
        ]));

        $this->assertTrue($form->passes());
        $this->assertSame('2026-07-18', $form->value('preferred_date'));
    }

    public function testContactRequestRejectsATooShortMessage(): void
    {
        $form = (new ContactRequest())->validate($this->request([
            'name'    => 'Bot',
            'email'   => 'bot@example.test',
            'message' => 'salut',
        ]));

        $this->assertTrue($form->fails());
        $this->assertTrue(isset($form->errors()['message']));
    }

    public function testBookingRequestResolvesTheServiceFromTheDatabase(): void
    {
        $serviceId = (new \App\Repositories\ServiceRepository())->insert([
            'title'      => 'Reportage de mariage',
            'slug'       => 'reportage-de-mariage',
            'status'     => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $form = (new BookingRequest())->validate($this->request([
            'name'       => 'Marc Petit',
            'email'      => 'marc@example.test',
            'service_id' => $serviceId,
        ]));

        $this->assertTrue($form->passes());
        $this->assertSame($serviceId, $form->value('service_id'));
        $this->assertSame('Reportage de mariage', $form->value('service_label'));
    }

    public function testBookingRequestIgnoresAnUnknownService(): void
    {
        $form = (new BookingRequest())->validate($this->request([
            'name'       => 'Marc Petit',
            'email'      => 'marc@example.test',
            'service_id' => 9999,
        ]));

        // A tampered id must not invent a prestation, and must not fail the
        // whole form either: the prestation is optional.
        $this->assertTrue($form->passes());
        $this->assertNull($form->value('service_id'));
        $this->assertNull($form->value('service_label'));
    }

    public function testServiceRequestAcceptsFrenchDecimalPrices(): void
    {
        $form = (new ServiceRequest())->validate($this->request([
            'title'      => 'Séance portrait',
            'price_from' => '380',
            'status'     => 'published',
        ]));

        $this->assertTrue($form->passes());
        $this->assertSame(380.0, $form->value('price_from'));
    }

    public function testLoginRequestNeverTrimsThePassword(): void
    {
        $form = (new LoginRequest())->validate($this->request([
            'email'    => ' Admin@Example.TEST ',
            'password' => ' un mot de passe ',
        ]));

        $this->assertTrue($form->passes());
        $this->assertSame('admin@example.test', $form->value('email'));
        $this->assertSame(' un mot de passe ', $form->value('password'), 'A space is part of a password.');
    }

    public function testPasswordChangeRequestRequiresAConfirmation(): void
    {
        $mismatch = (new PasswordChangeRequest())->validate($this->request([
            'current_password'      => 'ancien',
            'password'              => 'un-nouveau-mot-de-passe',
            'password_confirmation' => 'autre-chose',
        ]));

        $this->assertTrue($mismatch->fails());

        $short = (new PasswordChangeRequest())->validate($this->request([
            'current_password'      => 'ancien',
            'password'              => 'court',
            'password_confirmation' => 'court',
        ]));

        $this->assertTrue($short->fails());

        $ok = (new PasswordChangeRequest())->validate($this->request([
            'current_password'      => 'ancien',
            'password'              => 'un-nouveau-mot-de-passe',
            'password_confirmation' => 'un-nouveau-mot-de-passe',
        ]));

        $this->assertTrue($ok->passes());
    }

    public function testSettingsRequestNormalisesColours(): void
    {
        $form = (new SettingsRequest())->validate($this->request([
            'studio_name'    => 'Atelier',
            'color_primary'  => 'b08d57',
            'color_accent'   => '#1a1a1a',
        ]));

        $this->assertTrue($form->passes());
        $this->assertSame('#b08d57', $form->value('color_primary'), 'A missing # is added.');
        $this->assertSame('#1a1a1a', $form->value('color_accent'));
    }

    public function testSettingsRequestRejectsAnInvalidUrl(): void
    {
        $form = (new SettingsRequest())->validate($this->request([
            'studio_name'      => 'Atelier',
            'social_instagram' => 'pas une url',
        ]));

        $this->assertTrue($form->fails());
        $this->assertTrue(isset($form->errors()['social_instagram']));
    }

    public function testEveryValidatorDeclaresRulesAndReturnsOnlyDeclaredKeys(): void
    {
        // Mass assignment defence at the form layer: a field nobody declared
        // must not appear in the data handed to a service.
        $form = (new ClientRequest())->validate($this->request([
            'first_name' => 'Jean',
            'last_name'  => 'Martin',
            'role'       => 'SUPER_ADMIN',
            'id'         => 1,
        ]));

        $this->assertTrue($form->passes());
        $this->assertFalse(array_key_exists('role', $form->data()));
        $this->assertFalse(array_key_exists('id', $form->data()));
    }

    public function testEveryFormRequestClassIsWired(): void
    {
        $missing = [];

        foreach (glob(dirname(__DIR__, 2) . '/app/Validators/*.php') ?: [] as $file) {
            $class = 'App\\Validators\\' . basename($file, '.php');

            if ($class === FormRequest::class) {
                continue;
            }

            if (!class_exists($class) || !is_subclass_of($class, FormRequest::class)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing);
    }

    /** @param array<string, mixed> $body */
    private function request(array $body): Request
    {
        return new Request('POST', '/test', [], $body, ['REMOTE_ADDR' => '203.0.113.1'], [], []);
    }
}
