<?php

namespace Tests\Feature;

use App\Actions\ProcessMeeting;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\MeetingReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['renovion.transcription.driver' => 'demo', 'renovion.ai.api_key' => 'test']);
    }

    private function start(User $user, array $data = []): Meeting
    {
        $id = $this->actingAs($user)->postJson(route('meetings.store'), [
            'title' => 'Bouwvergadering badkamer',
            'kind' => 'bouwvergadering',
            'consent' => 1,
            'mime' => 'audio/webm;codecs=opus',
            ...$data,
        ])->assertCreated()->json('id');

        return Meeting::findOrFail($id);
    }

    private function chunk(Meeting $meeting, int $seq): TestResponse
    {
        return $this->post(route('meetings.chunk', $meeting), [
            'seq' => $seq,
            'chunk' => UploadedFile::fake()->createWithContent('stuk', 'geluid-'.$seq),
        ], ['Accept' => 'application/json']);
    }

    public function test_recording_needs_consent(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('meetings.store'), [
            'title' => 'Overleg', 'kind' => 'intern',
        ])->assertUnprocessable()->assertJsonPath('errors.consent.0', 'Bevestig dat iedereen weet dat het gesprek wordt opgenomen.');
    }

    public function test_audio_arrives_in_order_and_a_resend_is_harmless(): void
    {
        $meeting = $this->start(User::factory()->create());

        $this->chunk($meeting, 0)->assertOk()->assertJsonPath('received', 1);
        $this->chunk($meeting, 0)->assertOk()->assertJsonPath('received', 1); // opnieuw na een time-out
        $this->chunk($meeting, 2)->assertStatus(409)->assertJsonPath('received', 1); // stuk 1 ontbreekt
        $this->chunk($meeting, 1)->assertOk()->assertJsonPath('received', 2);

        $meeting->refresh();
        $this->assertSame('geluid-0geluid-1', Storage::get($meeting->audio_path));
        $this->assertStringEndsWith('.webm', $meeting->audio_path);
    }

    public function test_only_the_recorder_can_send_audio(): void
    {
        $meeting = $this->start(User::factory()->create());

        $this->actingAs(User::factory()->create());
        $this->chunk($meeting, 0)->assertForbidden();
    }

    public function test_after_stop_nova_makes_a_report_and_actions_become_tasks(): void
    {
        $user = User::factory()->create(['name' => 'Raphael Test']);
        $project = Project::factory()->create();
        $meeting = $this->start($user, ['project_id' => $project->id]);
        $this->chunk($meeting, 0)->assertOk();

        $this->postJson(route('meetings.stop', $meeting), ['duration' => 34])->assertOk();
        $this->assertSame(MeetingStatus::Verwerken, $meeting->fresh()->status);

        $this->mock(MeetingReporter::class, function ($mock) use ($user) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('report')->once()->andReturn([
                'summary' => 'Klant kiest voor een inloopdouche.',
                'decisions' => ['Inloopdouche 120 cm in plaats van bad'],
                'actions' => [['title' => 'Begroting aanpassen', 'owner_id' => $user->id, 'owner' => $user->name, 'due' => today()->addDays(2)->toDateString(), 'task_id' => null]],
                'questions' => ['Kan de spiegelkast 140 breed?'],
                'speakers' => ['A' => 'Raphael'],
            ]);
        });

        // Het verslag kijkt zelf of het klaar is; daarmee gaat de verwerking verder (ook zonder wachtrij).
        $this->getJson(route('meetings.status', $meeting))->assertOk()->assertJsonPath('status', 'klaar');

        $meeting->refresh();
        $this->assertCount(6, $meeting->transcript);
        $this->assertSame('Raphael', $meeting->speakerName('A'));
        $this->get(route('meetings.show', $meeting))->assertOk()->assertSee('Klant kiest voor een inloopdouche.')->assertSee('Kan de spiegelkast 140 breed?');
        $this->assertDatabaseHas('timeline_events', ['customer_id' => $project->customer_id, 'title' => 'Gespreksverslag: Bouwvergadering badkamer']);

        $this->post(route('meetings.tasks', $meeting), [
            'actions' => [['selected' => 1, 'title' => 'Begroting aanpassen en opsturen', 'owner_id' => $user->id, 'deadline' => today()->addDays(2)->toDateString()]],
        ])->assertRedirect();

        $this->assertDatabaseHas('tasks', ['title' => 'Begroting aanpassen en opsturen', 'project_id' => $project->id, 'owner_id' => $user->id]);
        $this->assertNotNull($meeting->fresh()->actions[0]['task_id']);

        // Nog een keer versturen maakt geen dubbele taak.
        $this->post(route('meetings.tasks', $meeting), ['actions' => [['selected' => 1, 'title' => 'Begroting aanpassen en opsturen']]]);
        $this->assertSame(1, Task::where('title', 'Begroting aanpassen en opsturen')->count());
    }

    public function test_without_a_transcription_key_the_recording_waits(): void
    {
        config(['renovion.transcription.driver' => 'assemblyai', 'renovion.transcription.api_key' => null]);
        $meeting = $this->start(User::factory()->create());
        $this->chunk($meeting, 0);
        $this->postJson(route('meetings.stop', $meeting));

        app(ProcessMeeting::class)->handle($meeting->fresh());

        $this->assertSame(MeetingStatus::Verwerken, $meeting->fresh()->status);
        $this->get(route('meetings.show', $meeting))->assertSee('De transcriptiedienst staat nog niet aan');
    }

    public function test_whisper_transcribes_in_dutch_and_drops_made_up_subtitle_lines(): void
    {
        config(['renovion.transcription.driver' => 'whisper', 'renovion.transcription.openai_key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['segments' => [
            ['start' => 0.0, 'end' => 4.2, 'text' => ' We kiezen voor de inloopdouche.'],
            ['start' => 4.5, 'end' => 9.0, 'text' => ' Ik stuur voor vrijdag de nieuwe begroting.'],
            ['start' => 9.5, 'end' => 12.0, 'text' => ' Ondertiteling ingediend door de Amara.org gemeenschap'],
        ]])]);
        $this->mock(MeetingReporter::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('report')->once()->andReturn(['summary' => 'Inloopdouche gekozen.', 'decisions' => [], 'actions' => [], 'questions' => [], 'speakers' => []]);
        });

        $meeting = $this->start(User::factory()->create());
        $this->chunk($meeting, 0);
        $this->postJson(route('meetings.stop', $meeting));
        $this->getJson(route('meetings.status', $meeting))->assertJsonPath('status', 'klaar');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/audio/transcriptions'
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && collect($request->data())->contains(fn ($part) => ($part['name'] ?? null) === 'language' && $part['contents'] === 'nl'));

        $transcript = $meeting->fresh()->transcript;
        $this->assertSame(['We kiezen voor de inloopdouche.', 'Ik stuur voor vrijdag de nieuwe begroting.'], array_column($transcript, 'text'));
        $this->assertSame([0, 4500], array_column($transcript, 'start'));
        $this->assertSame('', $transcript[0]['speaker']);
        $this->get(route('meetings.show', $meeting))->assertOk()->assertSee('We kiezen voor de inloopdouche.')->assertDontSee('Spreker');
    }

    public function test_a_craftsman_only_sees_meetings_of_his_projects(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $meeting = $this->start(User::factory()->create(), ['project_id' => Project::factory()->create()->id]);

        $this->actingAs($uitvoerder)->get(route('meetings.show', $meeting))->assertForbidden();

        $meeting->project->craftsmen()->attach($uitvoerder);
        $this->actingAs($uitvoerder)->get(route('meetings.show', $meeting))->assertOk();
    }

    public function test_old_audio_is_removed_but_the_report_stays(): void
    {
        $meeting = $this->start(User::factory()->create());
        $this->chunk($meeting, 0);
        $meeting->update(['status' => MeetingStatus::Klaar, 'ended_at' => now()->subDays(91), 'summary' => 'Kort overleg.']);

        $this->artisan('meetings:process')->assertSuccessful();

        $meeting->refresh();
        $this->assertNotNull($meeting->audio_deleted_at);
        $this->assertFalse(Storage::exists($meeting->audio_path));
        $this->assertSame('Kort overleg.', $meeting->summary);
    }
}
