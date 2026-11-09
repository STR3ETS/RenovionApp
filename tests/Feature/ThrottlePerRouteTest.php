<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ThrottlePerRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_route_has_its_own_counter(): void
    {
        $peter = User::factory()->create(['role' => UserRole::Uitvoerder]);

        // Veel verzoeken naar de ongelezen-teller (60 per minuut)...
        foreach (range(1, 40) as $_) {
            $this->actingAs($peter)->getJson(route('chat.unread'))->assertOk();
        }

        // ...tellen niet mee voor de testmelding (5 per minuut): die heeft een eigen teller.
        foreach (range(1, 5) as $_) {
            $this->actingAs($peter)->post(route('notification-settings.test'))->assertRedirect();
        }
        $this->actingAs($peter)->post(route('notification-settings.test'))->assertTooManyRequests();
    }

    public function test_app_traffic_does_not_block_opening_a_server_page(): void
    {
        $imad = User::factory()->create(['role' => UserRole::Admin]);
        $token = $imad->createToken('telefoon')->plainTextToken;

        // Een drukke minuut: schermen in de app laden en de ongelezen-teller op de serverpagina's.
        foreach (range(1, 40) as $_) {
            $this->withToken($token)->getJson(route('api.app.today'))->assertOk();
            $this->actingAs($imad)->getJson(route('chat.unread'))->assertOk();
        }

        // Daarna lukt een serverpagina openen nog steeds (webcode en app/sessie, ingelogd in de webview).
        $url = $this->withToken($token)->postJson(route('api.app.web-code'), ['naar' => '/chat', 'terug' => 'https://localhost'])
            ->assertOk()->json('url');
        $this->actingAs($imad)->get($url)->assertRedirect('/chat');
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
