<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** De organisatie-instellingen (bedrijfsgegevens en standaardwaarden) als eigen scherm in de app. */
class AppCompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_sees_the_fields_per_group_and_the_environment(): void
    {
        $token = User::factory()->create(['role' => UserRole::Directie])->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.app.company-settings'))->assertOk()
            ->assertJsonPath('groups.0.name', 'Bedrijf')
            ->assertJsonPath('groups.0.fields.0.key', 'bedrijf.naam')
            ->assertJsonPath('groups.0.fields.0.value', 'Renovion')
            ->assertJsonPath('environment.0.label', 'AI-sleutel voor Nova')
            ->assertJsonPath('modules.0.name', 'Offertes')
            ->assertJsonPath('links.0.path', route('onboarding', [], false));

        // Geheimen gaan nooit mee: alleen of ze er zijn.
        $this->assertSame(['label', 'ready', 'about'], array_keys($response->json('environment.0')));
    }

    public function test_saving_from_the_app_stores_and_audits_only_what_changed(): void
    {
        $token = User::factory()->create(['role' => UserRole::Admin])->createToken('test')->plainTextToken;

        $this->withToken($token)->patchJson(route('api.app.company-settings.update'), ['settings' => [
            'bedrijf.naam' => 'Renovion',
            'calculatie.uurtarief' => '62',
            'bestaat.niet' => 'x',
        ]])->assertOk()
            ->assertJsonPath('message', '1 instelling opgeslagen.')
            ->assertJsonPath('changed', 1);

        $this->assertSame('62', Setting::get('calculatie.uurtarief'));
        $this->assertSame(1, AuditLog::where('action', 'instelling gewijzigd')->count());

        $this->withToken($token)->getJson(route('api.app.company-settings'))->assertOk()
            ->assertJsonPath('groups.1.fields.0.value', '62');

        $this->withToken($token)->patchJson(route('api.app.company-settings.update'), ['settings' => ['calculatie.uurtarief' => '62']])
            ->assertOk()->assertJsonPath('message', 'Niets gewijzigd.');
        $this->withToken($token)->patchJson(route('api.app.company-settings.update'), [])->assertUnprocessable();
    }

    public function test_other_office_staff_cannot_open_or_save_the_settings(): void
    {
        $token = User::factory()->create(['role' => UserRole::Projectleider])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.company-settings'))->assertForbidden();
        $this->withToken($token)->patchJson(route('api.app.company-settings.update'), ['settings' => ['bedrijf.naam' => 'X']])->assertForbidden();
        $this->assertSame('Renovion', Setting::get('bedrijf.naam'));
    }
}
