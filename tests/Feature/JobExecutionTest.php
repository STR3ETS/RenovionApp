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

class JobExecutionTest extends TestCase
{
    use RefreshDatabase;

    private function setupJob(array $entry = []): array
    {
        $uitvoerder = User::factory()->create([
            'role' => UserRole::Uitvoerder,
            'hourly_rate' => 48,
        ]);

        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'type' => 'Badkamerrenovatie',
            'progress' => 10,
        ]);
        $project->craftsmen()->attach($uitvoerder);

        $scheduleEntry = ScheduleEntry::factory()->create([
            'user_id' => $uitvoerder->id,
            'project_id' => $project->id,
            'title' => 'Tegelwerk badkamer',
            'date' => today(),
            ...$entry,
        ]);

        return [$uitvoerder, $project, $scheduleEntry];
    }

    public function test_my_jobs_shows_todays_planning_as_jobs(): void
    {
        [$uitvoerder, , $entry] = $this->setupJob();

        $this->actingAs($uitvoerder)->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Tegelwerk badkamer')
            ->assertSee('Vandaag');

        // Nog geen klus aangemaakt: de lijst leest alleen.
        $this->assertDatabaseCount('job_executions', 0);
    }

    public function test_opening_a_job_creates_the_checklist_for_the_trade(): void
    {
        [$uitvoerder, , $entry] = $this->setupJob();

        $this->actingAs($uitvoerder)->get(route('jobs.show', $entry))
            ->assertOk()
            ->assertSee('Aanmelden bij klant of bouwplaats')
            // Tegelwerk in de titel levert de tegelzetterschecklist op.
            ->assertSee('Waterdichting en kitband aanbrengen')
            ->assertSee('Afschot vloer meten');

        $job = JobExecution::firstWhere('schedule_entry_id', $entry->id);

        $this->assertNotNull($job);
        $this->assertSame(JobStatus::Gepland, $job->status);
        $this->assertGreaterThan(5, $job->steps()->count());
    }

    public function test_work_only_starts_after_all_five_safety_questions(): void
    {
        [$uitvoerder, , $entry] = $this->setupJob();
        $job = JobExecution::forEntry($entry);
        $job->update(['status' => JobStatus::Onderweg]);

        // Vier van de vijf: nog niet starten.
        $this->actingAs($uitvoerder)
            ->patch(route('jobs.status', $job), ['status' => 'bezig', 'lmra' => [1, 1, 1, 1]])
            ->assertSessionHas('error');

        $this->assertSame(JobStatus::Onderweg, $job->fresh()->status);

        $this->actingAs($uitvoerder)
            ->patch(route('jobs.status', $job), ['status' => 'bezig', 'lmra' => [1, 1, 1, 1, 1]])
            ->assertSessionHas('success');

        $job->refresh();

        $this->assertSame(JobStatus::Bezig, $job->status);
        $this->assertNotNull($job->lmra_confirmed_at);
        $this->assertNotNull($job->started_at);
    }

    public function test_a_step_that_requires_a_photo_cannot_be_ticked_without_one(): void
    {
        Storage::fake('local');

        [$uitvoerder, , $entry] = $this->setupJob();
        $job = JobExecution::forEntry($entry);
        $job->update(['status' => JobStatus::Bezig, 'lmra_confirmed_at' => now(), 'started_at' => now()]);

        $step = $job->steps()->where('photo_required', true)->first();

        $this->actingAs($uitvoerder)
            ->patch(route('job-steps.toggle', [$job, $step]))
            ->assertSessionHas('error');

        $this->assertNull($step->fresh()->completed_at);

        $this->actingAs($uitvoerder)->post(route('job-steps.photos', [$job, $step]), [
            'photos' => [UploadedFile::fake()->image('waterdichting.jpg')],
        ])->assertSessionHas('success');

        $this->actingAs($uitvoerder)
            ->patch(route('job-steps.toggle', [$job, $step]))
            ->assertSessionHas('success');

        $this->assertNotNull($step->fresh()->completed_at);
    }

    public function test_finishing_a_job_produces_a_work_order_awaiting_approval(): void
    {
        Storage::fake('local');

        [$uitvoerder, $project, $entry] = $this->setupJob();
        $job = JobExecution::forEntry($entry);
        $job->update(['status' => JobStatus::Bezig, 'started_at' => now()->subHours(6)]);

        // Alle verplichte foto's leveren, anders blokkeert het afronden.
        foreach ($job->steps()->where('photo_required', true)->get() as $step) {
            $step->photos()->create([
                'project_id' => $project->id,
                'uploaded_by' => $uitvoerder->id,
                'path' => 'project-photos/bewijs.jpg',
                'client_visible' => false,
            ]);
        }

        $this->actingAs($uitvoerder)->post(route('jobs.complete', $job), [
            'hours' => 7.5,
            'materials' => "6 st gipsplaat\n2,5 m2 tegels\nemmer tegellijm",
            'notes' => 'Afvoer zat dieper dan op tekening.',
            'signed_name' => 'Mevrouw De Groot',
        ])->assertRedirect(route('jobs.index'));

        $order = WorkOrder::firstWhere('job_execution_id', $job->id);

        $this->assertNotNull($order);
        $this->assertSame(WorkOrderStatus::Controle, $order->status);
        $this->assertSame('7.50', $order->hours);
        $this->assertSame(JobStatus::Klaar, $job->fresh()->status);
        $this->assertTrue($order->isSigned());

        // Materiaal is per regel ontleed, ook de regel zonder hoeveelheid.
        // JSON kent geen verschil tussen 6 en 6.0, dus vergelijken we als float.
        $this->assertCount(3, $order->materials);
        $this->assertSame(6.0, (float) $order->materials[0]['quantity']);
        $this->assertSame('gipsplaat', $order->materials[0]['description']);
        $this->assertSame(2.5, (float) $order->materials[1]['quantity']);
        $this->assertSame('emmer tegellijm', $order->materials[2]['description']);

        // De checklist is bevroren op de bon.
        $this->assertCount($job->steps()->count(), $order->checklist);
    }

    public function test_finishing_is_blocked_while_required_photo_evidence_is_missing(): void
    {
        [$uitvoerder, , $entry] = $this->setupJob();
        $job = JobExecution::forEntry($entry);
        $job->update(['status' => JobStatus::Bezig, 'started_at' => now()->subHours(3)]);

        $this->actingAs($uitvoerder)
            ->post(route('jobs.complete', $job), ['hours' => 4])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('work_orders', 0);
        $this->assertSame(JobStatus::Bezig, $job->fresh()->status);
    }

    public function test_approving_a_work_order_books_hours_and_moves_progress(): void
    {
        [$uitvoerder, $project, $entry] = $this->setupJob();
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);

        $order = WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'user_id' => $uitvoerder->id,
            'title' => 'Tegelwerk badkamer',
            'date' => today(),
            'status' => WorkOrderStatus::Controle,
            'hours' => 8,
            'budget_hours' => 16,
        ]);

        $this->actingAs($leider)
            ->post(route('work-orders.approve', $order))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(WorkOrderStatus::Goedgekeurd, $order->status);
        $this->assertSame($leider->id, $order->approved_by);
        // Het tarief van de uitvoerder is vastgelegd op de bon.
        $this->assertSame('48.00', $order->hourly_rate);
        $this->assertSame(384.0, $order->laborCost());
        // 16 begrote uren van minimaal 80 is 20 procent, bovenop de 10 die er stond.
        $this->assertSame(30, $project->fresh()->progress);
    }

    public function test_only_the_project_leadership_approves_work_orders(): void
    {
        [$uitvoerder, $project] = $this->setupJob();

        $order = WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'user_id' => $uitvoerder->id,
            'title' => 'Tegelwerk',
            'date' => today(),
            'status' => WorkOrderStatus::Controle,
            'hours' => 8,
            'budget_hours' => 8,
        ]);

        $this->actingAs($uitvoerder)
            ->post(route('work-orders.approve', $order))
            ->assertForbidden();

        $this->assertSame(WorkOrderStatus::Controle, $order->fresh()->status);
    }

    public function test_a_report_from_the_site_stays_open_until_the_leader_handles_it(): void
    {
        [$uitvoerder, $project, $entry] = $this->setupJob();
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $job = JobExecution::forEntry($entry);
        $job->update(['status' => JobStatus::Bezig]);

        $this->actingAs($uitvoerder)->post(route('job-reports.store', $job), [
            'type' => JobReportType::Materiaal->value,
            'body' => 'Twee gipsplaten beschadigd geleverd, twee extra nodig.',
            'urgent' => 1,
        ])->assertSessionHas('success');

        $report = $job->reports()->first();

        $this->assertTrue($report->isOpen());
        $this->assertTrue($report->urgent);
        $this->assertSame($project->id, $report->project_id);

        $this->actingAs($leider)->post(route('job-reports.resolve', $report), [
            'resolution' => 'Nabestelling staat morgen op de bouw.',
        ])->assertSessionHas('success');

        $this->assertFalse($report->fresh()->isOpen());
    }

    public function test_a_craftsman_cannot_open_someone_elses_job(): void
    {
        [, , $entry] = $this->setupJob();
        $ander = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($ander)->get(route('jobs.show', $entry))->assertForbidden();
    }

    public function test_a_craftsman_only_sees_their_own_work_orders(): void
    {
        [$uitvoerder, $project] = $this->setupJob();
        $ander = User::factory()->create(['role' => UserRole::Uitvoerder]);

        WorkOrder::create([
            'number' => 'WB-2026-0900',
            'project_id' => $project->id,
            'user_id' => $ander->id,
            'title' => 'Werk van iemand anders',
            'date' => today(),
            'status' => WorkOrderStatus::Controle,
        ]);

        $this->actingAs($uitvoerder)->get(route('work-orders.index'))
            ->assertOk()
            ->assertDontSee('Werk van iemand anders');
    }

    public function test_work_order_numbers_run_per_year(): void
    {
        $this->assertSame('WB-'.now()->year.'-0001', WorkOrder::nextNumber());

        [$uitvoerder, $project] = $this->setupJob();

        WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'user_id' => $uitvoerder->id,
            'title' => 'Eerste',
            'date' => today(),
        ]);

        $this->assertSame('WB-'.now()->year.'-0002', WorkOrder::nextNumber());
    }
}
