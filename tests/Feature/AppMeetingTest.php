<?php

namespace Tests\Feature;

use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Models\ChatChannel;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\MeetingReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Gespreksverslagen als eigen schermen in de app: lijst, opnemen via api/app en het verslag met zijn acties. */
class AppMeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['renovion.transcription.driver' => 'demo', 'renovion.ai.api_key' => 'test']);
    }

    private function tokenFor(User $user): string
    {
        // Elke gebruiker een eigen verzoek: de guard onthoudt anders de vorige gebruiker.
        $this->app['auth']->forgetGuards();

        return $user->createToken('test')->plainTextToken;
    }

    /** Opnemen zoals de app het doet: starten, een stukje geluid, stoppen. */
    private function record(string $token, array $data = []): Meeting
    {
        $id = $this->withToken($token)->postJson(route('api.app.meetings.store'), [
            'title' => 'Bouwvergadering badkamer', 'kind' => 'bouwvergadering', 'consent' => 1, 'mime' => 'audio/mp4', ...$data,
        ])->assertCreated()->json('id');

        $this->withToken($token)->post(route('api.app.meetings.chunk', $id), [
            'seq' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('stuk-0', 'geluid-0'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('received', 1);

        // Te ver vooruit: de server zegt welk stuk hij verwacht (de recorder nummert dan opnieuw).
        $this->withToken($token)->post(route('api.app.meetings.chunk', $id), [
            'seq' => 3,
            'chunk' => UploadedFile::fake()->createWithContent('stuk-3', 'geluid-3'),
        ], ['Accept' => 'application/json'])->assertStatus(409)->assertJsonPath('received', 1);

        $this->withToken($token)->postJson(route('api.app.meetings.stop', $id), ['duration' => 65])->assertOk();

        return Meeting::findOrFail($id);
    }

    public function test_recording_from_the_app_ends_in_a_report_whose_actions_become_tasks(): void
    {
        $user = User::factory()->create(['role' => UserRole::Projectleider, 'name' => 'Raphael Test']);
        $project = Project::factory()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson(route('api.app.meetings.create'))->assertOk()
            ->assertJsonPath('kinds.0.value', 'intern')
            ->assertJsonPath('projects.0.id', $project->id)
            ->assertJsonPath('privacy_path', '/privacy#gesprekken');

        $this->withToken($token)->postJson(route('api.app.meetings.store'), ['title' => 'Overleg', 'kind' => 'intern'])
            ->assertUnprocessable()->assertJsonValidationErrors('consent');

        $meeting = $this->record($token, ['project_id' => $project->id]);
        $this->assertSame(MeetingStatus::Verwerken, $meeting->status);
        $this->assertStringEndsWith('.m4a', $meeting->audio_path);

        $this->withToken($token)->getJson(route('api.app.meetings.show', $meeting))->assertOk()
            ->assertJsonPath('status.value', 'verwerken')
            ->assertJsonPath('summary', null)
            ->assertJsonPath('project.path', route('projects.show', $project, false));

        $this->mock(MeetingReporter::class, function ($mock) use ($user) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('report')->once()->andReturn([
                'summary' => 'Klant kiest voor een inloopdouche.',
                'decisions' => ['Inloopdouche 120 cm'],
                'actions' => [
                    ['title' => 'Begroting aanpassen', 'owner_id' => $user->id, 'owner' => $user->name, 'due' => today()->addDays(2)->toDateString(), 'task_id' => null],
                    ['title' => 'Tegels bestellen', 'owner_id' => null, 'owner' => null, 'due' => null, 'task_id' => null],
                ],
                'questions' => ['Kan de spiegelkast 140 breed?'],
                'speakers' => ['A' => 'Raphael'],
            ]);
        });

        // Het scherm kijkt elke 5 s naar de status; daarmee gaat de verwerking verder (na het antwoord).
        $this->withToken($token)->getJson(route('api.app.meetings.status', $meeting))->assertOk()->assertJsonPath('status', 'verwerken');
        $this->withToken($token)->getJson(route('api.app.meetings.status', $meeting))->assertOk()->assertJsonPath('status', 'klaar');

        $channel = $project->fresh()->chatChannel;
        $report = $this->withToken($token)->getJson(route('api.app.meetings.show', $meeting))->assertOk()
            ->assertJsonPath('status.value', 'klaar')
            ->assertJsonPath('summary', 'Klant kiest voor een inloopdouche.')
            ->assertJsonPath('decisions.0', 'Inloopdouche 120 cm')
            ->assertJsonPath('questions.0', 'Kan de spiegelkast 140 breed?')
            ->assertJsonPath('actions.0.owner_id', $user->id)
            ->assertJsonPath('actions.1.task_id', null)
            ->assertJsonPath('transcript.0.speaker', 'Raphael')
            ->assertJsonPath('can_delete', true)
            ->assertJsonPath('team.0.name', 'Raphael Test');
        $this->assertTrue(collect($report->json('channels'))->firstWhere('id', $channel->id)['selected']);

        // De opname voor <audio>: ondertekend adres zonder token, en zonder handtekening niets.
        $audio = $report->json('audio.url');
        $this->assertStringStartsWith('/api/app/verslagen/'.$meeting->id.'/opname?', $audio);
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->get($audio)->assertOk()->assertHeader('Content-Type', 'audio/mp4');
        $this->get('/api/app/verslagen/'.$meeting->id.'/opname')->assertForbidden();

        // Alleen de aangevinkte actie wordt een taak, met de titel zoals de gebruiker hem zette.
        $this->withToken($token)->postJson(route('api.app.meetings.tasks', $meeting), ['actions' => [
            ['selected' => true, 'title' => 'Begroting aanpassen en opsturen', 'owner_id' => $user->id, 'deadline' => today()->addDays(2)->toDateString()],
            ['selected' => false, 'title' => 'Tegels bestellen', 'owner_id' => null, 'deadline' => null],
        ]])->assertOk()->assertJsonPath('message', '1 taak aangemaakt.');
        $this->assertDatabaseHas('tasks', ['title' => 'Begroting aanpassen en opsturen', 'project_id' => $project->id, 'owner_id' => $user->id]);
        $this->assertDatabaseMissing('tasks', ['title' => 'Tegels bestellen']);

        $this->withToken($token)->postJson(route('api.app.meetings.tasks', $meeting), ['actions' => [
            ['title' => 'Begroting aanpassen en opsturen'],
            ['selected' => true, 'title' => 'Tegels bestellen'],
        ]])->assertOk()->assertJsonPath('message', '1 taak aangemaakt.');
        $this->assertSame(1, Task::where('title', 'Begroting aanpassen en opsturen')->count());
        $this->withToken($token)->getJson(route('api.app.meetings.show', $meeting))->assertOk()
            ->assertJsonPath('team', []);

        $this->withToken($token)->postJson(route('api.app.meetings.share', $meeting), ['channel_id' => $channel->id])
            ->assertOk()->assertJsonPath('message', 'Gedeeld in '.$channel->displayName().'.');
        $this->assertSame('/verslagen/'.$meeting->id, $channel->messages()->latest('id')->first()->nova['url']);

        $this->withToken($token)->getJson(route('api.app.meetings.index'))->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('meetings.0.status.label', 'Klaar')
            ->assertJsonPath('meetings.0.sub', 'Bouwvergadering, '.$project->name.', '.$meeting->started_at->translatedFormat('j M Y H:i').', 01:05');
    }

    public function test_a_craftsman_only_sees_his_own_recordings_and_those_of_his_projects(): void
    {
        $craftsman = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $own = Project::factory()->create();
        $own->craftsmen()->attach($craftsman);
        $other = Project::factory()->create();
        $mine = Meeting::create(['consent_at' => now(), 'title' => 'Eigen', 'kind' => 'intern', 'user_id' => $craftsman->id, 'status' => MeetingStatus::Verwerken, 'started_at' => now()->subHour()]);
        $onProject = Meeting::create(['consent_at' => now(), 'title' => 'Op project', 'kind' => 'intern', 'project_id' => $own->id, 'user_id' => User::factory()->create()->id, 'status' => MeetingStatus::Klaar, 'started_at' => now()]);
        $hidden = Meeting::create(['consent_at' => now(), 'title' => 'Elders', 'kind' => 'intern', 'project_id' => $other->id, 'user_id' => User::factory()->create()->id, 'status' => MeetingStatus::Klaar, 'started_at' => now()]);
        $token = $this->tokenFor($craftsman);

        $this->withToken($token)->getJson(route('api.app.meetings.index'))->assertOk()
            ->assertJsonPath('meetings.*.title', ['Op project', 'Eigen'])
            ->assertJsonPath('meetings.1.status.label', 'Bezig');
        $this->withToken($token)->getJson(route('api.app.meetings.show', $hidden))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.meetings.show', $onProject))->assertOk()->assertJsonPath('can_delete', false);
        $this->withToken($token)->deleteJson(route('api.app.meetings.destroy', $onProject))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.meetings.create'))->assertOk()->assertJsonPath('projects.*.id', [$own->id]);
        $this->withToken($token)->postJson(route('api.app.meetings.store'), ['title' => 'X', 'kind' => 'intern', 'consent' => 1, 'project_id' => $other->id])->assertForbidden();

        $this->withToken($token)->deleteJson(route('api.app.meetings.destroy', $mine))->assertOk()->assertJsonPath('message', 'Verslag en opname verwijderd.');
        $this->assertModelMissing($mine);
    }

    public function test_an_unfinished_recording_can_still_be_processed_and_a_failed_one_retried(): void
    {
        $user = User::factory()->create(['role' => UserRole::Projectleider]);
        $meeting = Meeting::create(['consent_at' => now(), 'title' => 'Gesloten', 'kind' => 'intern', 'user_id' => $user->id, 'status' => MeetingStatus::Opname, 'started_at' => now()->subMinutes(3), 'duration_seconds' => 125]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson(route('api.app.meetings.show', $meeting))->assertOk()
            ->assertJsonPath('can_stop', true)
            ->assertJsonPath('duration', '02:05')
            ->assertJsonPath('audio', null);
        $this->withToken($token)->postJson(route('api.app.meetings.stop', $meeting), [])->assertOk();
        $this->assertSame(MeetingStatus::Verwerken, $meeting->fresh()->status);

        $meeting->update(['status' => MeetingStatus::Mislukt, 'error' => 'Transcriptie mislukt.']);
        $this->withToken($token)->getJson(route('api.app.meetings.show', $meeting))->assertOk()->assertJsonPath('error', 'Transcriptie mislukt.');
        $this->withToken($token)->postJson(route('api.app.meetings.retry', $meeting))->assertOk()->assertJsonPath('status', 'verwerken');
        $this->assertSame(MeetingStatus::Verwerken, $meeting->fresh()->status);
    }

    public function test_sharing_needs_a_finished_report_and_a_channel_the_user_may_see(): void
    {
        $craftsman = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $meeting = Meeting::create(['consent_at' => now(), 'title' => 'Eigen', 'kind' => 'intern', 'user_id' => $craftsman->id, 'status' => MeetingStatus::Klaar, 'started_at' => now(), 'summary' => 'Kort.']);
        $foreign = Project::factory()->create()->chatChannel;
        $team = ChatChannel::create(['name' => 'Algemeen', 'type' => 'team']);
        $token = $this->tokenFor($craftsman);

        $this->withToken($token)->postJson(route('api.app.meetings.share', $meeting), ['channel_id' => $foreign->id])->assertForbidden();
        $this->withToken($token)->postJson(route('api.app.meetings.share', $meeting), ['channel_id' => $team->id])->assertOk();
    }
}
