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

    public function test_the_app_starts_light_and_saves_the_choice_on_the_server(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('telefoon')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.me'))->assertOk()->assertJsonPath('theme', 'licht');

        $this->withToken($token)->patchJson(route('api.app.theme'), ['theme' => 'donker'])
            ->assertOk()->assertJsonPath('theme', 'donker');
        $this->assertSame('donker', $user->fresh()->app_theme);

        $this->withToken($token)->patchJson(route('api.app.theme'), ['theme' => 'paars'])->assertUnprocessable();
        $this->assertSame('donker', $user->fresh()->app_theme);
    }

    public function test_server_pages_in_the_app_follow_the_choice_and_can_change_it(): void
    {
        $user = User::factory()->create(['app_theme' => 'telefoon']);

        $this->actingAs($user)->withHeader('User-Agent', self::APP)->get(route('planning.index'))->assertOk()
            ->assertSee("let theme = 'telefoon'", false)
            ->assertSee('app-theme-switch', false);

        $this->actingAs($user)->patchJson(route('app-theme.update'), ['theme' => 'licht'])->assertOk();
        $this->assertSame('licht', $user->fresh()->app_theme);
    }

    public function test_the_website_in_a_browser_has_no_theme_switch(): void
    {
        $user = User::factory()->create(['app_theme' => 'donker']);

        $this->actingAs($user)->get(route('planning.index'))->assertOk()
            ->assertDontSee('renovionTheme', false)
            ->assertDontSee('app-theme-switch', false);
    }
}
