<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_attention_page_shows_only_exceptions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $overdueProject = Project::factory()->create([
            'name' => 'Badkamer Uitloop',
            'end_date_expected' => today()->subDays(2)->toDateString(),
        ]);
        $quote = Quote::factory()->create([
            'status' => QuoteStatus::Verstuurd,
            'sent_at' => now()->subDays(6),
        ]);
        Task::factory()->create([
            'title' => 'Verlopen taak',
            'deadline' => today()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get('/aandacht');

        $response->assertOk();
        $response->assertSee('Badkamer Uitloop');
        $response->assertSee('Offerte niet opgevolgd');
        $response->assertSee('Verlopen taak');
    }

    public function test_attention_page_is_empty_when_everything_is_on_track(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->get('/aandacht')
            ->assertOk()
            ->assertSee('Niets vraagt op dit moment aandacht');
    }

    public function test_vakman_cannot_open_attention_or_automations(): void
    {
        $vakman = User::factory()->create(['role' => UserRole::Vakman]);

        $this->actingAs($vakman)->get('/aandacht')->assertForbidden();
        $this->actingAs($vakman)->get('/automations')->assertForbidden();
    }

    public function test_automations_page_lists_automations(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->get('/automations')
            ->assertOk()
            ->assertSee('Offerte-opvolging')
            ->assertSee('Ontbrekend telefoonnummer opvragen');
    }
}
