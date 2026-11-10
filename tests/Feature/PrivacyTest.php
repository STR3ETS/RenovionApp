<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_statement_is_public(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacyverklaring')
            ->assertSee('KVK 95384782')
            ->assertSee('Autoriteit Persoonsgegevens')
            ->assertSee('id="gesprekken"', false);
    }

    public function test_it_states_the_real_retention_for_recordings(): void
    {
        config(['renovion.transcription.audio_retention_days' => 30]);

        $this->get(route('privacy'))->assertSee('na 30 dagen automatisch verwijderd');
    }

    public function test_login_and_recording_screen_link_to_it(): void
    {
        $this->get(route('login'))->assertSee(route('privacy'));

        $this->actingAs(User::factory()->create())
            ->get(route('meetings.create'))
            ->assertSee(route('privacy').'#gesprekken');
    }
}
