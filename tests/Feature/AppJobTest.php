<?php

namespace Tests\Feature;

use App\Enums\JobReportType;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\JobExecution;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mijn klussen als eigen scherm in de app: dezelfde regels als de website, antwoord als JSON. */
class AppJobTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Project, 2: ScheduleEntry} */
    private function setupJob(): array
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder, 'hourly_rate' => 48]);
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'type' => 'Badkamerrenovatie', 'address' => 'Dorpsstraat 1', 'city' => 'Arnhem']);
        $project->craftsmen()->attach($uitvoerder);
        $entry = ScheduleEntry::factory()->create(['user_id' => $uitvoerder->id, 'project_id' => $project->id, 'title' => 'Tegelwerk badkamer', 'date' => today()]);

        return [$uitvoerder, $project, $entry];
    }

    /** Een token voor deze gebruiker; de guard vergeet de vorige, zodat wisselen in één test werkt. */
    private function token(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('test')->plainTextToken;
    }

    public function test_my_jobs_lists_today_without_creating_a_job_and_the_leadership_can_look_along(): void
    {
        [$uitvoerder, , $entry] = $this->setupJob();

        $this->withToken($this->token($uitvoerder))->getJson(route('api.app.jobs.index'))->assertOk()
            ->assertJsonPath('subject.me', true)
            ->assertJsonPath('colleagues', [])
            ->assertJsonPath('vandaag.0.id', $entry->id)
            ->assertJsonPath('vandaag.0.title', 'Tegelwerk badkamer')
            ->assertJsonPath('vandaag.0.status.value', 'gepland')
            ->assertJsonPath('vandaag.0.address', 'Dorpsstraat 1, Arnhem')
            ->assertJsonPath('vandaag.0.path', route('jobs.show', $entry, false));
        $this->assertDatabaseCount('job_executions', 0);

        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $this->withToken($this->token($leider))->getJson(route('api.app.jobs.index', ['medewerker' => $uitvoerder->id]))->assertOk()
            ->assertJsonPath('subject.id', $uitvoerder->id)
            ->assertJsonPath('subject.me', false)
            ->assertJsonPath('vandaag.0.id', $entry->id);

        // Een uitvoerder kan niet bij een ander meekijken.
        $ander = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $this->withToken($this->token($ander))->getJson(route('api.app.jobs.index', ['medewerker' => $uitvoerder->id]))->assertOk()
            ->assertJsonPath('subject.id', $ander->id)
            ->assertJsonPath('vandaag', []);
    }

    public function test_the_whole_job_runs_from_the_app_up_to_the_work_order(): void
    {
        Storage::fake('local');
        [$uitvoerder, , $entry] = $this->setupJob();
        $token = $this->token($uitvoerder);

        $data = $this->withToken($token)->getJson(route('api.app.jobs.show', $entry))->assertOk()
            ->assertJsonPath('can_execute', true)
            ->assertJsonPath('status.value', 'gepland')
            ->assertJsonPath('action.next', 'onderweg')
            ->assertJsonPath('address', 'Dorpsstraat 1, Arnhem')
            ->json();
        $job = JobExecution::firstWhere('schedule_entry_id', $entry->id);
        $this->assertSame($job->id, $data['job_id']);
        $this->assertCount($job->steps()->count(), $data['steps']);
        $this->assertCount(5, $data['lmra']);

        $this->withToken($token)->patchJson(route('api.app.jobs.status', $job), ['status' => 'onderweg'])
            ->assertOk()->assertJsonPath('message', 'Onderweg gemeld. Fijne rit.');
        $this->withToken($token)->getJson(route('api.app.jobs.show', $entry))->assertJsonPath('action.lmra', true);

        // Starten alleen als alle vijf vragen ja zijn.
        $this->withToken($token)->patchJson(route('api.app.jobs.status', $job), ['status' => 'bezig', 'lmra' => [true, true, true, true, false]])
            ->assertUnprocessable()->assertJsonPath('message', 'Alle vijf vragen van de veiligheidscheck moeten ja zijn voordat je begint.');
        $this->withToken($token)->patchJson(route('api.app.jobs.status', $job), ['status' => 'bezig', 'lmra' => [true, true, true, true, true]])
            ->assertOk();
        $this->assertSame(JobStatus::Bezig, $job->fresh()->status);

        // Een stap met verplichte foto gaat niet af zonder foto.
        $step = $job->steps()->where('photo_required', true)->first();
        $this->withToken($token)->patchJson(route('api.app.job-steps.toggle', [$job, $step]))
            ->assertUnprocessable()->assertJsonPath('message', 'Bij deze stap hoort een foto. Maak die eerst.');
        $this->withToken($token)->post(route('api.app.job-steps.photos', [$job, $step]), ['photos' => [UploadedFile::fake()->image('bewijs.jpg')]], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('message', 'Foto opgeslagen.');
        $this->withToken($token)->patchJson(route('api.app.job-steps.toggle', [$job, $step]))->assertOk()->assertJsonPath('message', 'Stap afgevinkt.');
        $this->withToken($token)->patchJson(route('api.app.job-steps.note', [$job, $step]), ['note' => 'Kitband extra breed'])->assertOk();

        $shown = $this->withToken($token)->getJson(route('api.app.jobs.show', $entry))->assertOk()->json();
        $shownStep = collect($shown['steps'])->firstWhere('id', $step->id);
        $this->assertTrue($shownStep['done']);
        $this->assertSame('Kitband extra breed', $shownStep['note']);
        $this->assertStringStartsWith('/api/app/fotos/', $shownStep['photos'][0]['url']);
        $this->get($shownStep['photos'][0]['url'])->assertOk();

        // Afronden blokkeert zolang er verplichte foto's ontbreken.
        if (count($shown['finish']['missing_evidence'])) {
            $this->withToken($token)->postJson(route('api.app.jobs.complete', $job), ['hours' => 6])->assertUnprocessable();
            foreach ($job->steps()->where('photo_required', true)->get() as $required) {
                $required->photos()->create(['project_id' => $job->project_id, 'uploaded_by' => $uitvoerder->id, 'path' => 'project-photos/bewijs.jpg', 'client_visible' => false]);
            }
        }

        $done = $this->withToken($token)->postJson(route('api.app.jobs.complete', $job), ['hours' => 6.5, 'signed_name' => 'Mevrouw De Groot'])->assertOk();
        $order = WorkOrder::firstWhere('job_execution_id', $job->id);
        $this->assertSame(route('work-orders.show', $order, false), $done->json('work_order'));
        $this->assertSame(WorkOrderStatus::Controle, $order->status);

        $this->withToken($token)->getJson(route('api.app.jobs.show', $entry))->assertOk()
            ->assertJsonPath('done', true)
            ->assertJsonPath('action', null)
            ->assertJsonPath('work_order.number', $order->number);
    }

    public function test_a_report_from_the_site_goes_to_the_leader_who_handles_it_in_the_app(): void
    {
        Storage::fake('local');
        [$uitvoerder, , $entry] = $this->setupJob();
        $job = JobExecution::forEntry($entry);
        $job->update(['status' => JobStatus::Bezig]);

        $this->withToken($this->token($uitvoerder))->post(route('api.app.job-reports.store', $job), [
            'type' => JobReportType::Materiaal->value,
            'body' => 'Twee gipsplaten beschadigd geleverd.',
            'urgent' => '1',
            'photos' => [UploadedFile::fake()->image('schade.jpg')],
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('message', 'Spoedmelding verstuurd. De projectleider krijgt hem direct te zien.');

        $report = $job->reports()->first();
        $this->assertTrue($report->urgent);
        $this->assertCount(1, $report->photos);

        // De uitvoerder handelt zijn eigen melding niet af.
        $this->withToken($this->token($uitvoerder))->postJson(route('api.app.job-reports.resolve', $report))->assertForbidden();

        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $leiderToken = $this->token($leider);
        $this->withToken($leiderToken)->getJson(route('api.app.jobs.show', $entry))->assertOk()
            ->assertJsonPath('can_execute', false)
            ->assertJsonPath('can_resolve', true)
            ->assertJsonPath('counts.meldingen', 1)
            ->assertJsonPath('reports.0.urgent', true)
            ->assertJsonPath('reports.0.open', true);

        $this->withToken($leiderToken)->postJson(route('api.app.job-reports.resolve', $report), ['resolution' => 'Nabestelling staat morgen op de bouw.'])
            ->assertOk()->assertJsonPath('message', 'Melding afgehandeld.');
        $this->assertFalse($report->fresh()->isOpen());

        // Meekijken is geen uitvoeren.
        $this->withToken($leiderToken)->patchJson(route('api.app.jobs.status', $job), ['status' => 'pauze'])->assertForbidden();
    }

    public function test_a_craftsman_cannot_open_someone_elses_job(): void
    {
        [, , $entry] = $this->setupJob();
        $ander = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->withToken($this->token($ander))->getJson(route('api.app.jobs.show', $entry))->assertForbidden();
    }

    public function test_the_website_keeps_redirecting_with_a_message(): void
    {
        [$uitvoerder, , $entry] = $this->setupJob();
        $job = JobExecution::forEntry($entry);

        $this->actingAs($uitvoerder)->from(route('jobs.show', $entry))
            ->patch(route('jobs.status', $job), ['status' => 'onderweg'])
            ->assertRedirect(route('jobs.show', $entry))
            ->assertSessionHas('success', 'Onderweg gemeld. Fijne rit.');
    }
}
