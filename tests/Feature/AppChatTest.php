<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ChatChannel;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ChatMention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Teamchat in de iOS/Android-app (api/app/chat). */
class AppChatTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_the_channel_list_shows_unread_counts_and_the_latest_message(): void
    {
        $me = User::factory()->create(['name' => 'Imad']);
        $peter = User::factory()->create(['name' => 'Peter de Vries']);
        $channel = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);
        $channel->messages()->create(['user_id' => $peter->id, 'body' => 'Tegels zijn binnen']);

        $this->withToken($this->tokenFor($me))->getJson(route('api.app.chat'))->assertOk()
            ->assertJsonPath('channels.0.name', 'Algemeen')
            ->assertJsonPath('channels.0.kind', 'team')
            ->assertJsonPath('channels.0.unread', 1)
            ->assertJsonPath('channels.0.preview', 'Peter: Tegels zijn binnen');
    }

    public function test_opening_a_conversation_marks_it_read_and_sending_reaches_mentions(): void
    {
        Notification::fake();
        $me = User::factory()->create(['name' => 'Imad']);
        $peter = User::factory()->create(['name' => 'Peter']);
        $channel = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);
        $first = $channel->messages()->create(['user_id' => $peter->id, 'body' => 'Goedemorgen']);
        $token = $this->tokenFor($me);

        $this->withToken($token)->getJson(route('api.app.chat.show', $channel))->assertOk()
            ->assertJsonPath('name', 'Algemeen')
            ->assertJsonPath('messages.0.body', 'Goedemorgen')
            ->assertJsonPath('messages.0.mine', false);
        $this->assertSame(0, $channel->fresh()->unreadCountFor($me));

        $this->withToken($token)->postJson(route('api.app.chat.messages.store', $channel), ['body' => '@Peter kun jij bellen?'])
            ->assertOk()->assertJsonPath('messages.0.mine', true);
        Notification::assertSentTo($peter, ChatMention::class);

        $this->withToken($token)->getJson(route('api.app.chat.messages', ['channel' => $channel, 'after' => $first->id]))
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', '@Peter kun jij bellen?');
    }

    public function test_a_photo_from_the_app_gets_a_signed_address_that_works_without_a_token(): void
    {
        Storage::fake('local');
        $me = User::factory()->create();
        $channel = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        $url = $this->withToken($this->tokenFor($me))
            ->post(route('api.app.chat.messages.store', $channel), ['attachment' => UploadedFile::fake()->image('kozijn.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->json('messages.0.attachment_url');

        $this->assertStringStartsWith('/api/app/chat/bijlagen/', $url);
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk();
        $this->get(strtok($url, '?'))->assertForbidden();

        // De website houdt zijn eigen adres (sessie).
        $this->assertStringContainsString('/chat/bijlagen/', $channel->messages()->first()->toChatArray($me)['attachment_url']);
        $this->assertStringNotContainsString('signature', $channel->messages()->first()->toChatArray($me)['attachment_url']);
    }

    public function test_executors_only_reach_team_channels_and_their_own_projects(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);
        ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);
        $own = Project::factory()->create(['name' => 'Eigen Badkamer']);
        $own->craftsmen()->attach($executor);
        $other = Project::factory()->create(['name' => 'Andermans Keuken']);
        $token = $this->tokenFor($executor);

        $names = array_column($this->withToken($token)->getJson(route('api.app.chat'))->assertOk()->json('channels'), 'name');
        $this->assertContains('Algemeen', $names);
        $this->assertContains('Eigen Badkamer', $names);
        $this->assertNotContains('Andermans Keuken', $names);

        $this->withToken($token)->getJson(route('api.app.chat.show', $other->chatChannel))->assertForbidden();
        $this->withToken($token)->postJson(route('api.app.chat.messages.store', $other->chatChannel), ['body' => 'x'])->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.chat.show', $own->chatChannel))->assertOk()
            ->assertJsonPath('kind', 'project')->assertJsonPath('nova', false);
    }

    public function test_only_the_office_creates_channels(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $id = $this->withToken($this->tokenFor($admin))->postJson(route('api.app.chat.channels.store'), ['name' => 'Werkvoorbereiding'])
            ->assertCreated()->json('id');
        $this->assertSame('Werkvoorbereiding', ChatChannel::find($id)->name);
    }

    public function test_executors_cannot_create_channels(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->withToken($this->tokenFor($executor))->postJson(route('api.app.chat.channels.store'), ['name' => 'Eigen kanaal'])->assertForbidden();
    }

    public function test_a_message_becomes_a_task(): void
    {
        $me = User::factory()->create();
        $channel = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);
        $message = $channel->messages()->create(['user_id' => $me->id, 'body' => 'Silicone kit bestellen']);

        $this->withToken($this->tokenFor($me))->postJson(route('api.app.chat.task', $message))->assertOk();
        $this->assertDatabaseHas('tasks', ['title' => 'Silicone kit bestellen', 'owner_id' => $me->id]);
    }
}
