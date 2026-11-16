<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Photo;
use App\Models\Project;
use App\Models\QualityReview;
use App\Models\SnagItem;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kwaliteit, oplevering en het opleverrapport als eigen schermen in de app. */
class AppQualityTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('test')->plainTextToken;
    }

    public function test_a_craftsman_sees_quality_of_own_projects_but_cannot_change_it(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $mine = Project::factory()->create(['customer_id' => Customer::factory(), 'type' => 'Badkamerrenovatie', 'name' => 'Badkamer Velp']);
        $mine->craftsmen()->attach($uitvoerder);
        $other = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Ander project']);
        $token = $this->token($uitvoerder);

        $this->withToken($token)->getJson(route('api.app.quality.index'))->assertOk()
            ->assertJsonCount(1, 'projects')
            ->assertJsonPath('projects.0.name', 'Badkamer Velp')
            ->assertJsonPath('projects.0.path', route('quality.show', $mine, false))
            ->assertJsonPath('steps', 8);

        $data = $this->withToken($token)->getJson(route('api.app.quality.show', $mine))->assertOk()
            ->assertJsonPath('can_manage', false)
            ->assertJsonPath('people', [])
            ->assertJsonPath('flow.0.state', 'current')
            ->json();
        $this->assertNotEmpty($data['checks']);

        $this->withToken($token)->getJson(route('api.app.quality.show', $other))->assertForbidden();
        $check = QualityReview::forProject($mine)->checks()->first();
        $this->withToken($token)->patchJson(route('api.app.quality-checks.toggle', $check))->assertForbidden();
        $this->withToken($token)->postJson(route('api.app.snags.store', $mine), ['title' => 'Kras'])->assertForbidden();
    }

    public function test_the_leadership_ticks_checks_records_snags_signs_and_advances_from_the_app(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'type' => 'Stucwerk woonkamer', 'progress' => 100]);
        $review = QualityReview::forProject($project);
        $review->update(['step' => 1]);
        $token = $this->token(User::factory()->create(['role' => UserRole::Projectleider]));

        $this->withToken($token)->getJson(route('api.app.quality.show', $project))->assertOk()
            ->assertJsonPath('can_manage', true)
            ->assertJsonPath('step', 1)
            ->assertJsonPath('blocker', 'Niet alle controlepunten zijn afgevinkt.')
            ->assertJsonPath('advance', 'Interne kwaliteitscontrole afronden');

        $this->withToken($token)->postJson(route('api.app.quality.advance', $review))
            ->assertUnprocessable()->assertJsonPath('message', 'Niet alle controlepunten zijn afgevinkt.');

        foreach ($review->checks as $check) {
            $this->withToken($token)->patchJson(route('api.app.quality-checks.toggle', $check))->assertOk()->assertJsonPath('message', 'Controlepunt afgevinkt.');
        }
        $this->withToken($token)->postJson(route('api.app.quality.advance', $review))
            ->assertOk()->assertJsonPath('message', 'Stap afgerond: Interne kwaliteitscontrole.');
        $this->assertSame(2, $review->fresh()->step);

        // Restpunten: vastleggen, status wijzigen, verwijderen.
        $this->withToken($token)->postJson(route('api.app.snags.store', $project), ['title' => 'Kras in het stucwerk', 'location' => 'Woonkamer', 'source' => 'klant'])
            ->assertOk()->assertJsonPath('message', 'Restpunt vastgelegd.');
        $snag = SnagItem::firstWhere('title', 'Kras in het stucwerk');
        $this->withToken($token)->getJson(route('api.app.quality.show', $project))->assertOk()
            ->assertJsonPath('snags.0.customer', true)
            ->assertJsonPath('snags.0.status.value', 'open')
            ->assertJsonPath('snags_open', 1);
        $this->withToken($token)->patchJson(route('api.app.snags.update', $snag), ['status' => 'hersteld'])->assertOk();
        $this->assertSame(SnagItem::STATUS_RESOLVED, $snag->fresh()->status);
        $this->withToken($token)->deleteJson(route('api.app.snags.destroy', $snag))->assertOk()->assertJsonPath('message', 'Restpunt verwijderd.');

        $this->withToken($token)->postJson(route('api.app.quality.sign', $review), ['signed_name' => 'Mevrouw De Groot'])
            ->assertOk()->assertJsonPath('message', 'Opleverformulier getekend.');
        $this->withToken($token)->getJson(route('api.app.quality.show', $project))->assertJsonPath('signed.name', 'Mevrouw De Groot');
    }

    public function test_the_delivery_report_opens_in_the_app_and_renovion_signs_it(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Verbouwing Huissen']);
        $package = WorkPackage::factory()->gereed()->create(['project_id' => $project->id, 'project_phase_id' => $project->phases()->firstWhere('name', 'Uitvoering')->id, 'name' => 'Elektra begane grond']);
        $package->items()->create(['label' => 'Eindcontrole', 'done_at' => now(), 'position' => 1]);
        Photo::factory()->create(['project_id' => $project->id, 'work_package_id' => $package->id]);
        $leider = User::factory()->create(['role' => UserRole::Projectleider, 'name' => 'Imad']);
        $token = $this->token($leider);

        $made = $this->withToken($token)->postJson(route('api.app.delivery-reports.store', $project))->assertOk();
        $report = $project->refresh()->deliveryReport;
        $this->assertSame(route('delivery-reports.show', $report, false), $made->json('path'));

        $this->withToken($token)->getJson(route('api.app.delivery-reports.show', $report))->assertOk()
            ->assertJsonPath('header.name', 'Verbouwing Huissen')
            ->assertJsonPath('work.0.name', 'Elektra begane grond')
            ->assertJsonPath('work.0.items.0.label', 'Eindcontrole')
            ->assertJsonCount(1, 'work.0.photos')
            ->assertJsonCount(9, 'phases')
            ->assertJsonPath('can_sign', true)
            ->assertJsonPath('can_regenerate', true)
            ->assertJsonPath('signatures.company', null)
            ->assertJsonPath('path', route('delivery-reports.show', $report, false));

        $this->withToken($token)->postJson(route('api.app.delivery-reports.sign', $report))->assertOk()->assertJsonPath('message', 'Rapport ondertekend namens Renovion.');
        $this->withToken($token)->postJson(route('api.app.delivery-reports.sign', $report))->assertUnprocessable()->assertJsonPath('message', 'Dit rapport is al namens Renovion ondertekend.');
        $this->withToken($token)->getJson(route('api.app.delivery-reports.show', $report))->assertJsonPath('signatures.company.name', 'Imad')->assertJsonPath('can_sign', false);

        // Na de klanthandtekening is hergenereren voorbij.
        $report->update(['client_signed_name' => 'R. Jansen', 'client_signed_at' => now(), 'client_signed_ip' => '127.0.0.1']);
        $this->withToken($token)->postJson(route('api.app.delivery-reports.store', $project))->assertUnprocessable();

        // Een uitvoerder op het project kijkt mee, maar tekent niet.
        $uitvoerder = User::factory()->uitvoerder()->create();
        $project->craftsmen()->attach($uitvoerder);
        $craftsman = $this->token($uitvoerder);
        $this->withToken($craftsman)->getJson(route('api.app.delivery-reports.show', $report))->assertOk()->assertJsonPath('can_sign', false);
        $this->withToken($craftsman)->postJson(route('api.app.delivery-reports.sign', $report))->assertForbidden();
    }
}
