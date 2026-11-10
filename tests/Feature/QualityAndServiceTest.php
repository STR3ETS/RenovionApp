<?php

namespace Tests\Feature;

use App\Enums\DocumentCategory;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Project;
use App\Models\QualityReview;
use App\Models\ServiceTicket;
use App\Models\SnagItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AttentionService;
use App\Support\QualityTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityAndServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_review_gets_the_check_list_for_the_kind_of_work(): void
    {
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'type' => 'Badkamerrenovatie',
        ]);

        $review = QualityReview::forProject($project);

        $this->assertSame('badkamer', $review->template);
        $this->assertFalse($review->wkb);
        $this->assertSame(count(QualityTemplates::CHECKS['badkamer']), $review->checks()->count());
        $this->assertTrue($review->checks->contains('title', 'Persproef leidingen uitgevoerd'));
    }

    public function test_structural_work_adds_the_four_wkb_items(): void
    {
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'type' => 'Aanbouw met plat dak',
        ]);

        $review = QualityReview::forProject($project);

        $this->assertTrue($review->wkb);
        $this->assertSame(
            count(QualityTemplates::CHECKS['aanbouw']) + count(QualityTemplates::WKB_CHECKS),
            $review->checks()->count(),
        );
        $this->assertTrue($review->checks->contains('title', 'Gereedmelding bij de gemeente gedaan'));
    }

    public function test_step_one_blocks_while_work_orders_wait_for_approval(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'type' => 'Badkamerrenovatie',
            'progress' => 98,
        ]);

        WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'title' => 'Laatste dag',
            'date' => today(),
            'status' => WorkOrderStatus::Controle,
            'hours' => 8,
        ]);

        $review = QualityReview::forProject($project);

        $this->assertStringContainsString('werkbon wacht', $review->blockerFor(0));

        $this->actingAs($leider)
            ->post(route('quality.advance', $review))
            ->assertSessionHas('error');

        $this->assertSame(0, $review->fresh()->step);
    }

    public function test_step_one_also_blocks_on_progress_below_ninety_five(): void
    {
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'progress' => 80,
        ]);

        $review = QualityReview::forProject($project);

        $this->assertStringContainsString('80 procent', $review->blockerFor(0));

        $project->update(['progress' => 96]);

        $this->assertSame('', $review->fresh()->blockerFor(0));
    }

    public function test_the_second_step_needs_every_check_ticked(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'type' => 'Stucwerk woonkamer',
            'progress' => 100,
        ]);

        $review = QualityReview::forProject($project);
        $review->update(['step' => 1]);

        $this->assertStringContainsString('controlepunten', $review->blockerFor(1));

        foreach ($review->checks as $check) {
            $this->actingAs($leider)
                ->patch(route('quality-checks.toggle', $check))
                ->assertSessionHas('success');
        }

        $review = $review->fresh(['checks', 'project.snags']);

        $this->assertTrue($review->checksComplete());
        $this->assertSame('', $review->blockerFor(1));

        $this->actingAs($leider)->post(route('quality.advance', $review))->assertSessionHas('success');
        $this->assertSame(2, $review->fresh()->step);
    }

    public function test_open_snags_block_the_customer_inspection(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'progress' => 100]);
        $review = QualityReview::forProject($project);
        $review->update(['step' => 3]);

        $project->snags()->create([
            'title' => 'Kras in het tegelwerk',
            'location' => 'Badkamer, achterwand',
            'status' => SnagItem::STATUS_OPEN,
        ]);

        $review = $review->fresh(['checks', 'project.snags']);

        $this->assertStringContainsString('restpunt staat', $review->blockerFor(3));

        $this->actingAs($leider)->post(route('quality.advance', $review))->assertSessionHas('error');

        $project->snags()->update(['status' => SnagItem::STATUS_RESOLVED, 'resolved_at' => now()]);

        $review = $review->fresh(['checks', 'project.snags']);

        $this->assertSame('', $review->blockerFor(3));
    }

    public function test_signing_is_required_before_the_documents_step(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'progress' => 100]);
        $review = QualityReview::forProject($project);
        $review->update(['step' => 5]);

        $this->assertStringContainsString('niet getekend', $review->blockerFor(5));

        $this->actingAs($leider)->post(route('quality.sign', $review), [
            'signed_name' => 'Mevrouw De Groot',
        ])->assertSessionHas('success');

        $review->refresh();

        $this->assertNotNull($review->signed_at);
        $this->assertSame('', $review->blockerFor(5));
    }

    public function test_the_last_step_marks_the_project_delivered_with_its_warranty(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'type' => 'Aanbouw',
            'progress' => 100,
            'end_date_actual' => null,
        ]);

        $review = QualityReview::forProject($project);
        $review->update(['step' => count(QualityTemplates::FLOW) - 2]);

        $this->actingAs($leider)->post(route('quality.advance', $review))->assertSessionHas('success');

        $project->refresh();

        $this->assertNotNull($project->end_date_actual);
        // Constructief werk krijgt tien jaar garantie.
        $this->assertSame(120, $project->warranty_months);
        $this->assertTrue($review->fresh()->isDelivered());
    }

    public function test_a_service_report_within_the_warranty_period_is_on_our_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'end_date_actual' => today()->subMonths(6),
            'warranty_months' => 24,
        ]);

        $this->actingAs($admin)->post(route('service.store'), [
            'project_id' => $project->id,
            'title' => 'Kit laat los bij de douchehoek',
            'description' => 'Klant meldt dat de kit loslaat.',
            'priority' => 'normaal',
            'reported_via' => 'telefoon',
        ])->assertSessionHas('success');

        $ticket = ServiceTicket::first();

        $this->assertTrue($ticket->warranty);
        $this->assertStringContainsString('Binnen de garantietermijn', $ticket->warranty_note);
    }

    public function test_a_service_report_outside_the_warranty_period_needs_a_talk_first(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $project = Project::factory()->create([
            'customer_id' => Customer::factory(),
            'end_date_actual' => today()->subMonths(30),
            'warranty_months' => 24,
        ]);

        $this->actingAs($admin)->post(route('service.store'), [
            'project_id' => $project->id,
            'title' => 'Tegel los',
            'description' => 'Een tegel komt los.',
            'priority' => 'normaal',
            'reported_via' => 'mail',
        ]);

        $ticket = ServiceTicket::first();

        $this->assertFalse($ticket->warranty);
        $this->assertStringContainsString('Buiten de garantietermijn', $ticket->warranty_note);
    }

    public function test_service_numbers_run_per_year(): void
    {
        $this->assertSame('SRV-'.now()->year.'-0001', ServiceTicket::nextNumber());

        ServiceTicket::create([
            'number' => ServiceTicket::nextNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'title' => 'Eerste',
            'description' => 'Test',
        ]);

        $this->assertSame('SRV-'.now()->year.'-0002', ServiceTicket::nextNumber());
    }

    public function test_new_service_reports_and_overdue_snags_reach_the_inbox(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Badkamer Jansen']);

        ServiceTicket::create([
            'number' => ServiceTicket::nextNumber(),
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'title' => 'Lekkage onder de wastafel',
            'description' => 'Water op de vloer.',
            'priority' => 'spoed',
            'status' => ServiceTicket::STATUS_NEW,
        ]);

        $project->snags()->create([
            'title' => 'Plint ontbreekt',
            'location' => 'Hal',
            'status' => SnagItem::STATUS_OPEN,
            'due_on' => today()->subDays(4),
        ]);

        $items = (new AttentionService)->items();

        $melding = $items->firstWhere('label', 'Spoedmelding service');
        $this->assertNotNull($melding);
        $this->assertSame('service', $melding['kind']);
        $this->assertSame('rood', $melding['severity']);

        $restpunt = $items->firstWhere('label', 'Restpunt over de datum');
        $this->assertNotNull($restpunt);
        $this->assertStringContainsString('Hal', $restpunt['subtitle']);
    }

    public function test_a_craftsman_can_look_at_quality_but_not_at_service(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get(route('quality.index'))->assertOk();
        $this->actingAs($uitvoerder)->get(route('service.index'))->assertForbidden();
    }

    public function test_the_document_library_groups_categories(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::factory()->create();

        foreach ([
            ['V en G-plan.pdf', DocumentCategory::Veiligheid],
            ['Garantiebewijs.pdf', DocumentCategory::Garantie],
        ] as [$name, $category]) {
            Document::create([
                'documentable_type' => 'customer',
                'documentable_id' => $customer->id,
                'customer_id' => $customer->id,
                'name' => $name,
                'path' => 'documents/'.$name,
                'category' => $category,
            ]);
        }

        $this->actingAs($admin)->get(route('documents.index'))
            ->assertOk()
            // Twee groepen, elk met één document erin.
            ->assertSee('Veiligheid en keuring')
            ->assertSee('Oplevering en garantie')
            ->assertSee('Garantiebewijs.pdf');

        // Filteren op groep laat alleen die groep zien.
        $this->actingAs($admin)->get(route('documents.index', ['groep' => 'Veiligheid en keuring']))
            ->assertOk()
            ->assertSee('V en G-plan.pdf')
            ->assertDontSee('Garantiebewijs.pdf');
    }
}
