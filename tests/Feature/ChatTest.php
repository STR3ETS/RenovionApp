<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ChatChannel;
use App\Models\Project;
use App\Models\User;
use App\Services\NovaAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_project_gets_its_own_chat_channel(): void
    {
        $project = Project::factory()->create(['name' => 'Verbouwing Huissen']);

        $this->assertNotNull($project->chatChannel);
        $this->assertSame('project', $project->chatChannel->type);
    }

    public function test_team_members_can_chat_and_unread_counters_work(): void
    {
        $imad = User::factory()->create(['name' => 'Imad']);
        $raphael = User::factory()->create(['name' => 'Raphael']);
        $kanaal = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        $this->actingAs($imad)->postJson('/chat/'.$kanaal->id.'/berichten', ['body' => 'Weekstart om 09:00'])
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Weekstart om 09:00')
            ->assertJsonPath('messages.0.user.name', 'Imad');

        $this->assertSame(1, $kanaal->fresh()->load('readers')->unreadCountFor($raphael));

        $this->actingAs($raphael)->get('/chat/'.$kanaal->id)->assertOk()->assertSee('Algemeen');
        $this->assertSame(0, $kanaal->fresh()->load('readers')->unreadCountFor($raphael));
    }

    public function test_uitvoerders_see_team_channels_and_only_their_own_project_channels(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        $eigen = Project::factory()->create(['name' => 'Eigen Badkamer']);
        $eigen->craftsmen()->attach($uitvoerder);
        $ander = Project::factory()->create(['name' => 'Andermans Keuken']);

        $kanalen = ChatChannel::forUser($uitvoerder);
        $this->assertTrue($kanalen->contains('name', 'Algemeen'));
        $this->assertTrue($kanalen->contains('project_id', $eigen->id));
        $this->assertFalse($kanalen->contains('project_id', $ander->id));

        $this->actingAs($uitvoerder)->get('/chat/'.$ander->chatChannel->id)->assertForbidden();
        $this->actingAs($uitvoerder)->get('/chat/'.$eigen->chatChannel->id)->assertOk();
    }

    public function test_klanten_cannot_reach_the_chat(): void
    {
        $klant = User::factory()->create(['role' => UserRole::Klant]);
        $kanaal = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        $this->actingAs($klant)->get('/chat')->assertForbidden();
        $this->actingAs($klant)->postJson('/chat/'.$kanaal->id.'/berichten', ['body' => 'x'])->assertForbidden();
    }

    public function test_a_photo_can_be_attached_and_streamed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $kanaal = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        $response = $this->actingAs($user)->post('/chat/'.$kanaal->id.'/berichten', [
            'attachment' => UploadedFile::fake()->image('levering-kozijnen.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $bericht = $kanaal->messages()->first();
        $this->assertNotNull($bericht->attachment_path);
        Storage::assertExists($bericht->attachment_path);

        $this->actingAs($user)->get('/chat/bijlagen/'.$bericht->id)->assertOk();
    }

    public function test_a_task_can_be_created_from_a_project_chat_message(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $kanaal = $project->chatChannel;

        $this->actingAs($user)->postJson('/chat/'.$kanaal->id.'/berichten', ['body' => 'Kozijnen levering opvolgen']);
        $bericht = $kanaal->messages()->first();

        $this->actingAs($user)->postJson('/chat/berichten/'.$bericht->id.'/taak')->assertOk();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Kozijnen levering opvolgen',
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'owner_id' => $user->id,
        ]);
    }

    public function test_mentioning_nova_returns_a_proposal_that_can_be_confirmed(): void
    {
        config(['renovion.ai.api_key' => 'test-key']);

        $this->mock(NovaAssistant::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('propose')->once()->andReturn([
                'type' => 'proposal',
                'action' => ['type' => 'create_task', 'params' => ['title' => 'Klant terugbellen']],
                'preview' => 'Taak: "Klant terugbellen"',
            ]);
            $mock->shouldReceive('execute')->once()->andReturn([
                'message' => 'Taak aangemaakt: "Klant terugbellen".',
                'url' => '/taken',
            ]);
        });

        $user = User::factory()->create();
        $kanaal = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        $response = $this->actingAs($user)->postJson('/chat/'.$kanaal->id.'/berichten', [
            'body' => '@Nova zet een taak om de klant terug te bellen',
        ]);

        $response->assertOk()->assertJsonCount(2, 'messages');
        $this->assertSame('Taak: "Klant terugbellen"', $response->json('messages.1.nova.preview'));

        $novaBericht = $kanaal->messages()->whereNull('user_id')->first();

        $this->actingAs($user)->postJson('/chat/berichten/'.$novaBericht->id.'/nova-bevestigen')
            ->assertOk()
            ->assertJsonPath('messages.0.nova.executed', true)
            ->assertJsonPath('messages.1.body', 'Taak aangemaakt: "Klant terugbellen".');

        // Nogmaals bevestigen kan niet.
        $this->actingAs($user)->postJson('/chat/berichten/'.$novaBericht->id.'/nova-bevestigen')->assertUnprocessable();
    }

    public function test_uitvoerders_do_not_trigger_nova(): void
    {
        config(['renovion.ai.api_key' => 'test-key']);

        $this->mock(NovaAssistant::class, function ($mock) {
            $mock->shouldReceive('propose')->never();
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $uitvoerder = User::factory()->uitvoerder()->create();
        $kanaal = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        $this->actingAs($uitvoerder)->postJson('/chat/'.$kanaal->id.'/berichten', ['body' => '@Nova doe iets'])
            ->assertOk()
            ->assertJsonCount(1, 'messages');
    }
}
