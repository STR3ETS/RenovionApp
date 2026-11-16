<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Onderaannemers en het partnerdossier als eigen schermen in de app. */
class AppSubcontractorTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Werkvoorbereider): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    public function test_a_partner_is_registered_and_listed_with_an_open_dossier(): void
    {
        $token = $this->token();

        $response = $this->withToken($token)->postJson(route('api.app.subcontractors.store'), [
            'name' => 'Stukadoors Oost', 'contact_name' => 'Kees', 'email' => 'kees@oost.test', 'city' => 'Arnhem',
        ])->assertOk();
        $partner = Subcontractor::firstWhere('name', 'Stukadoors Oost');
        $this->assertSame(route('subcontractors.show', $partner, false), $response->json('path'));

        $this->withToken($token)->getJson(route('api.app.subcontractors.index'))->assertOk()
            ->assertJsonPath('statuses.*.value', ['goedgekeurd', 'dossier_incompleet', 'document_verlopen', 'geblokkeerd'])
            ->assertJsonPath('subcontractors.0.contact', 'Kees, Arnhem')
            ->assertJsonPath('subcontractors.0.status.value', 'dossier_incompleet')
            ->assertJsonPath('subcontractors.0.status.classes', 'bg-amber-100 text-amber-800')
            ->assertJsonPath('subcontractors.0.dossier', '9 punten open')
            ->assertJsonPath('subcontractors.0.meta', '0 opdrachten');
    }

    public function test_the_dossier_is_filled_with_a_file_and_the_details_are_saved(): void
    {
        Storage::fake();
        $partner = Subcontractor::create(['name' => 'Tegelzetters Arnhem', 'email' => 'rob@tegel.test']);
        $token = $this->token();

        $this->withToken($token)->getJson(route('api.app.subcontractors.show', $partner))->assertOk()
            ->assertJsonPath('dossier.open', 9)
            ->assertJsonCount(9, 'dossier.items')
            ->assertJsonPath('dossier.items.0.sub', 'Ontbreekt')
            ->assertJsonPath('document_types.0', 'KvK-uittreksel')
            ->assertJsonPath('portal', null);

        $this->withToken($token)->post(route('api.app.subcontractor-documents.store', $partner), [
            'type' => 'KvK-uittreksel',
            'valid_until' => today()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('kvk.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('message', '8 punten nog open in het dossier.');
        Storage::assertExists($partner->documents()->first()->path);

        $this->withToken($token)->patchJson(route('api.app.subcontractors.update', $partner), [
            'phone' => '0612345678', 'trades' => ['tegelwerk', 'badkamers'], 'id_checked' => true, 'rating' => 4,
        ])->assertOk()->assertJsonPath('message', 'Gegevens bijgewerkt.');

        $this->withToken($token)->getJson(route('api.app.subcontractors.show', $partner))->assertOk()
            ->assertJsonPath('dossier.open', 7)
            ->assertJsonPath('form.trades', ['tegelwerk', 'badkamers'])
            ->assertJsonPath('form.id_checked', true)
            ->assertJsonPath('form.rating', 4);
    }

    public function test_the_portal_login_returns_the_one_time_password(): void
    {
        $token = $this->token();
        $withoutEmail = Subcontractor::create(['name' => 'Zonder mail']);
        $partner = Subcontractor::create(['name' => 'Schilders West', 'contact_name' => 'Anna', 'email' => 'anna@west.test']);

        $this->withToken($token)->postJson(route('api.app.subcontractors.portal', $withoutEmail))
            ->assertUnprocessable()->assertJsonPath('message', 'Vul eerst een e-mailadres in bij deze partner.');

        $response = $this->withToken($token)->postJson(route('api.app.subcontractors.portal', $partner))->assertOk()
            ->assertJsonPath('email', 'anna@west.test');
        $this->assertSame(14, strlen($response->json('password')));
        $this->assertSame(UserRole::Onderaannemer, $partner->fresh()->user->role);

        $this->withToken($token)->getJson(route('api.app.subcontractors.show', $partner))->assertOk()
            ->assertJsonPath('portal', 'anna@west.test');
    }

    public function test_executors_cannot_open_partners(): void
    {
        $partner = Subcontractor::create(['name' => 'Tegelzetters Arnhem']);
        $token = $this->token(UserRole::Uitvoerder);

        $this->withToken($token)->getJson(route('api.app.subcontractors.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.subcontractors.show', $partner))->assertForbidden();
    }
}
