<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AppUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const APP = 'Mozilla/5.0 (Linux; Android 14) RenovionApp';

    protected function tearDown(): void
    {
        AppRelease::flush();
        parent::tearDown();
    }

    private function require(int $minimum): void
    {
        AppRelease::flush(['build' => 3, 'minimum_build' => $minimum, 'links' => ['android' => 'https://play.google.com/store/apps/details?id=nl.renovion.dash', 'ios' => null]]);
    }

    public function test_the_release_file_never_locks_out_the_newest_app(): void
    {
        $release = json_decode(file_get_contents(base_path('mobile/release.json')), true);

        $this->assertIsInt($release['build']);
        $this->assertIsInt($release['minimum_build']);
        // Een minimum boven de huidige build zou iedereen buitensluiten, ook wie net heeft geüpdatet.
        $this->assertLessThanOrEqual($release['build'], $release['minimum_build']);
    }

    public function test_without_a_minimum_every_app_keeps_working(): void
    {
        $this->require(0);

        $this->getJson('/api/app/versie')->assertOk()->assertJsonPath('ok', true);
        $this->withHeader('X-App-Build', '1')->getJson('/api/app/versie')->assertOk();
    }

    public function test_an_older_app_gets_the_update_message_from_the_api(): void
    {
        $this->require(2);

        $this->withHeaders(['X-App-Build' => '1', 'User-Agent' => self::APP])->getJson('/api/app/versie')
            ->assertStatus(426)
            ->assertJsonPath('update.url', 'https://play.google.com/store/apps/details?id=nl.renovion.dash');

        $this->withHeader('X-App-Build', '2')->getJson('/api/app/versie')->assertOk();
        $this->withHeader('X-App-Build', '3')->getJson('/api/app/versie')->assertOk();
    }

    public function test_an_app_from_before_this_check_cannot_log_in_and_reads_why(): void
    {
        $this->require(2);
        $user = User::factory()->create(['password' => 'geheim-wachtwoord']);

        // Oude apps sturen geen buildnummer en tonen de "message" van de server op het inlogscherm.
        $this->postJson('/api/app/inloggen', ['email' => $user->email, 'password' => 'geheim-wachtwoord', 'device' => 'Telefoon'])
            ->assertStatus(426)
            ->assertJsonPath('message', 'Er is een nieuwe versie van Renovion OS. Download de nieuwste versie om verder te werken.');
    }

    public function test_server_pages_in_an_old_app_show_the_update_screen_but_the_website_does_not(): void
    {
        $this->require(2);
        $user = User::factory()->create();

        $this->actingAs($user)->withHeader('User-Agent', self::APP)->withSession(['app_build' => 1])->get('/')
            ->assertStatus(426)->assertSee('Update nodig')->assertSee('Nieuwste versie downloaden');

        $this->actingAs($user)->withHeader('User-Agent', self::APP)->withSession(['app_build' => 2])->get('/')->assertOk();

        // Dezelfde gebruiker in een gewone browser merkt er niets van.
        $this->actingAs($user)->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Safari')->get('/')->assertOk();

        // Lezen wat er met je gegevens gebeurt mag altijd.
        $this->withHeader('User-Agent', self::APP)->withSession(['app_build' => 1])->get('/privacy')->assertOk();
    }

    public function test_the_build_travels_from_the_app_to_its_server_pages(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('telefoon')->plainTextToken;

        $url = $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-App-Build' => '3'])
            ->postJson('/api/app/webcode', ['naar' => '/planning', 'terug' => 'https://localhost'])
            ->assertOk()->json('url');

        $code = str($url)->after('code=')->toString();
        $this->assertSame(3, Cache::get("app-web-code:{$code}")['build']);

        $this->get($url)->assertRedirect('/planning')->assertSessionHas('app_build', 3);
    }
}
