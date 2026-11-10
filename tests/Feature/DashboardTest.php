<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_stats_and_action_list(): void
    {
        $user = User::factory()->create(['name' => 'Imad Test']);

        Lead::factory()->count(3)->create();
        Lead::factory()->status(LeadStatus::Verloren)->create();
        Task::factory()->vandaag()->create(['title' => 'Aanbetaling Bakker controleren', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Imad');
        $response->assertSee('Nieuwe aanvragen');
        $response->assertSee('Aanbetaling Bakker controleren');
        // De kop staat opgesplitst in de opmaak: "Nu <span>doen</span>".
        $response->assertSeeInOrder(['Nu ', 'doen'], escape: false);
    }

    public function test_the_attention_sentence_and_badge_show_the_same_total(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(2)->create(['deadline' => today()->subDays(2), 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->get('/');

        $response->assertViewHas('attentionCount', 2);
        $response->assertSee('Vandaag vragen 2 zaken je aandacht.');
    }

    public function test_main_pages_are_accessible_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        foreach (['/aanvragen', '/offertes', '/projecten', '/taken', '/planning', '/klanten'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}
