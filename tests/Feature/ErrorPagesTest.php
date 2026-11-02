<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_page_is_dutch_and_has_a_way_back(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/bestaat-niet');

        $response->assertNotFound();
        $response->assertSee('Pagina niet gevonden');
        $response->assertSee('Terug naar Vandaag');
        $response->assertDontSee('Not Found');
    }

    public function test_a_forbidden_page_is_dutch_and_has_a_way_back(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $response = $this->actingAs($uitvoerder)->get('/klanten');

        $response->assertForbidden();
        $response->assertSee('Geen toegang');
        $response->assertSee('Terug naar Vandaag');
    }

    public function test_an_expired_session_sends_people_to_the_login(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('Opnieuw inloggen', $html);
        $this->assertStringContainsString(route('login'), $html);
    }
}
