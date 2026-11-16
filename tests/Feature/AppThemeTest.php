<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Weergave van de iOS/Android-app: licht, donker of volgens de telefoon (users.app_theme). */
class AppThemeTest extends TestCase
{
    use RefreshDatabase;

    private const APP = 'Mozilla/5.0 (Linux; Android 14) RenovionApp';

    public function test_the_app_follows_the_phone_until_someone_chooses(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('telefoon')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.me'))->assertOk()->assertJsonPath('theme', 'telefoon');
        $this->assertSame('telefoon', $user->fresh()->app_theme); // ook de standaard in de database

        // De oude route (zijmenu van build 4 en eerder) blijft werken.

        $this->withToken($token)->patchJson(route('api.app.theme'), ['theme' => 'donker'])
            ->assertOk()->assertJsonPath('theme', 'donker');
        $this->assertSame('donker', $user->fresh()->app_theme);

        $this->withToken($token)->patchJson(route('api.app.theme'), ['theme' => 'paars'])->assertUnprocessable();
        $this->assertSame('donker', $user->fresh()->app_theme);
    }

    public function test_server_pages_in_the_app_follow_the_choice_without_a_switch_in_the_menu(): void
    {
        // Kiezen gebeurt in Instellingen; het zijmenu van een serverpagina heeft geen schakelaar meer.
        $this->actingAs(User::factory()->create(['app_theme' => 'donker']))->withHeader('User-Agent', self::APP)
            ->get(route('planning.index'))->assertOk()
            ->assertSee("const theme = 'donker'", false)
            ->assertDontSee('app-theme-switch', false);

        $this->actingAs(User::factory()->create())->withHeader('User-Agent', self::APP)
            ->get(route('planning.index'))->assertOk()
            ->assertSee("const theme = 'telefoon'", false);
    }

    public function test_the_website_in_a_browser_has_no_theme_switch(): void
    {
        $user = User::factory()->create(['app_theme' => 'donker']);

        $this->actingAs($user)->get(route('planning.index'))->assertOk()
            ->assertDontSee("const theme = 'donker'", false)
            ->assertDontSee('app-theme-switch', false);
    }
}
