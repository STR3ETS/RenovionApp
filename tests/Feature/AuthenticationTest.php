<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('favicon.svg', false)
            ->assertSee('apple-touch-icon.png', false);
    }

    public function test_remember_me_is_checked_by_default_in_the_native_app(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 RenovionApp')
            ->get('/login')
            ->assertSee('name="remember" value="1" checked', false);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Safari/605.1.15')
            ->get('/login')
            ->assertDontSee('name="remember" value="1" checked', false);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'verkeerd-wachtwoord',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
