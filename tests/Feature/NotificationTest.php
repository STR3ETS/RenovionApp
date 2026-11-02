<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\ChatChannel;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ChatMention;
use App\Notifications\QuoteSigned;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentioned_colleague_gets_a_notification_but_sender_and_strangers_do_not(): void
    {
        $imad = User::factory()->create(['name' => 'Imad', 'role' => UserRole::Admin]);
        $peter = User::factory()->create(['name' => 'Peter de Vries', 'role' => UserRole::Uitvoerder]);
        $klant = User::factory()->create(['name' => 'Peter Klant', 'role' => UserRole::Klant]);
        $kanaal = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        $this->actingAs($imad)->postJson(route('chat.messages.store', $kanaal), ['body' => '@peter kun je even kijken? @Imad @onbekend'])->assertOk();

        $this->assertCount(1, $peter->notifications);
        $this->assertSame(ChatMention::class, $peter->notifications->first()->type);
        $this->assertSame('/chat/'.$kanaal->id, $peter->notifications->first()->data['url']);
        $this->assertCount(0, $imad->notifications);
        $this->assertCount(0, $klant->notifications);
    }

    public function test_no_mention_notification_for_a_project_channel_you_cannot_see(): void
    {
        $imad = User::factory()->create(['name' => 'Imad', 'role' => UserRole::Admin]);
        $peter = User::factory()->create(['name' => 'Peter', 'role' => UserRole::Uitvoerder]);
        $project = Project::factory()->create();

        $this->actingAs($imad)->postJson(route('chat.messages.store', $project->chatChannel), ['body' => '@Peter hier?'])->assertOk();

        $this->assertCount(0, $peter->notifications);
    }

    public function test_assigning_a_task_to_someone_else_notifies_them(): void
    {
        $imad = User::factory()->create(['name' => 'Imad', 'role' => UserRole::Admin]);
        $peter = User::factory()->create(['name' => 'Peter', 'role' => UserRole::Uitvoerder]);

        $this->actingAs($imad)->post(route('tasks.store'), ['title' => 'Kitwerk badkamer', 'owner_id' => $peter->id])->assertRedirect();
        $this->actingAs($imad)->post(route('tasks.store'), ['title' => 'Eigen taak', 'owner_id' => $imad->id])->assertRedirect();

        $this->assertCount(1, $peter->notifications);
        $this->assertSame(TaskAssigned::class, $peter->notifications->first()->type);
        $this->assertStringContainsString('Kitwerk badkamer', $peter->notifications->first()->data['body']);
        $this->assertCount(0, $imad->notifications);
    }

    public function test_tasks_from_automations_do_not_notify(): void
    {
        $peter = User::factory()->create(['role' => UserRole::Uitvoerder]);

        Task::factory()->create(['owner_id' => $peter->id]);

        $this->assertCount(0, $peter->notifications);
    }

    public function test_client_signing_a_quote_notifies_the_salesperson(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $lead = Lead::factory()->status(LeadStatus::Offerte)->create(['assigned_to' => $sales->id]);
        $quote = Quote::factory()->sent()->create(['customer_id' => $lead->customer_id, 'lead_id' => $lead->id]);

        $this->post(route('quotes.public.sign', $quote->public_token), ['signed_name' => 'Jan Jansen', 'agree' => '1'])->assertRedirect();

        $this->assertCount(1, $sales->notifications);
        $this->assertSame(QuoteSigned::class, $sales->notifications->first()->type);
        $this->assertStringContainsString('Jan Jansen', $sales->notifications->first()->data['body']);
        $this->assertCount(0, $admin->notifications);
    }
}
