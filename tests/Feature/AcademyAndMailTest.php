<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademyProgress;
use App\Models\Customer;
use App\Models\MailTemplate;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\Support\Academy;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AcademyAndMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_tour_step_points_at_an_existing_route_and_module(): void
    {
        foreach (Academy::TOURS as $id => $tour) {
            $this->assertArrayHasKey($tour['category'], Academy::CATEGORIES, "Rondleiding {$id} heeft een onbekende categorie.");

            foreach ($tour['steps'] as $step) {
                if (isset($step['route'])) {
                    $this->assertTrue(
                        Route::has($step['route']),
                        "Rondleiding {$id} verwijst naar onbekende route {$step['route']}.",
                    );
                }

                if (isset($step['module'])) {
                    $this->assertTrue(
                        Navigation::allows(UserRole::Admin, $step['module']),
                        "Rondleiding {$id} verwijst naar onbekende module {$step['module']}.",
                    );
                }
            }
        }

        // Elk leerpad verwijst naar bestaande rondleidingen.
        foreach (Academy::PATHS as $pathId => $path) {
            foreach ($path['tours'] as $tourId) {
                $this->assertArrayHasKey($tourId, Academy::TOURS, "Leerpad {$pathId} verwijst naar onbekende rondleiding {$tourId}.");
            }
        }
    }

    public function test_a_craftsman_does_not_get_told_about_screens_they_cannot_open(): void
    {
        $tours = Academy::toursFor(UserRole::Uitvoerder);

        // Geld hoort niet bij een uitvoerder, dus die rondleiding valt weg.
        $this->assertArrayNotHasKey('geld', $tours);
        $this->assertArrayNotHasKey('beheer', $tours);
        // En zijn eigen werk wel.
        $this->assertArrayHasKey('klus', $tours);

        $steps = collect($tours['basis']['steps'])->pluck('module')->filter();

        foreach ($steps as $module) {
            $this->assertTrue(Navigation::allows(UserRole::Uitvoerder, $module));
        }
    }

    public function test_the_learning_path_follows_the_role(): void
    {
        $this->assertSame('bouwplaats', Academy::pathFor(UserRole::Uitvoerder));
        $this->assertSame('verkoop', Academy::pathFor(UserRole::Sales));
        $this->assertSame('geld', Academy::pathFor(UserRole::Administratie));
        $this->assertSame('project', Academy::pathFor(UserRole::Projectleider));
    }

    public function test_opening_a_tour_records_progress_by_itself(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get(route('academy.show', 'klus'))->assertOk();

        $progress = AcademyProgress::firstWhere(['user_id' => $uitvoerder->id, 'tour' => 'klus']);

        $this->assertNotNull($progress);
        $this->assertNotNull($progress->started_at);
        $this->assertFalse($progress->isComplete());

        // Doorklikken schuift de voortgang op, terugklikken niet terug.
        $this->actingAs($uitvoerder)->get(route('academy.show', ['klus', 'stap' => 3]))->assertOk();
        $this->assertSame(3, $progress->fresh()->step);

        $this->actingAs($uitvoerder)->get(route('academy.show', ['klus', 'stap' => 1]))->assertOk();
        $this->assertSame(3, $progress->fresh()->step);
    }

    public function test_finishing_a_tour_counts_towards_the_learning_path(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->post(route('academy.complete', 'klus'))->assertRedirect();

        $this->assertTrue(AcademyProgress::firstWhere('tour', 'klus')->isComplete());

        $this->actingAs($uitvoerder)->get(route('academy.index'))
            ->assertOk()
            ->assertSee('Op de bouwplaats')
            ->assertSee('Afgerond');
    }

    public function test_a_tour_a_role_cannot_see_gives_a_not_found(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get(route('academy.show', 'geld'))->assertNotFound();
        $this->actingAs($uitvoerder)->post(route('academy.complete', 'geld'))->assertNotFound();
    }

    public function test_the_mail_templates_seed_themselves_on_first_visit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertSame(0, MailTemplate::count());

        $this->actingAs($admin)->get(route('mail-templates.index'))->assertOk();

        $this->assertSame(count(MailTemplate::defaults()), MailTemplate::count());

        // Een tweede bezoek maakt ze niet nog eens aan.
        $this->actingAs($admin)->get(route('mail-templates.index'))->assertOk();
        $this->assertSame(count(MailTemplate::defaults()), MailTemplate::count());
    }

    public function test_a_template_fills_in_real_values_and_marks_what_is_missing(): void
    {
        $template = MailTemplate::create([
            'key' => 'test',
            'name' => 'Test',
            'subject' => 'Uw offerte {{offertenummer}}',
            'blocks' => [
                ['type' => 'tekst', 'body' => 'Beste {{contactpersoon}}, hierbij {{project_naam}}.'],
                ['type' => 'gegevens', 'body' => 'Bedrag: {{offertebedrag}}'],
            ],
        ]);

        $rendered = $template->render([
            'contactpersoon' => 'Mevrouw De Groot',
            'project_naam' => 'Badkamer',
            // offertebedrag ontbreekt bewust.
        ]);

        $this->assertStringContainsString('Beste Mevrouw De Groot, hierbij Badkamer.', $rendered);
        // Wat niet gevuld kan worden blijft zichtbaar, in plaats van stil leeg.
        $this->assertStringContainsString('[ontbreekt: offertebedrag]', $rendered);
        $this->assertStringContainsString('[ontbreekt: offertenummer]', $template->renderSubject([]));
    }

    public function test_a_typo_in_a_variable_is_reported(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $template = MailTemplate::create([
            'key' => 'test',
            'name' => 'Test',
            'subject' => 'Test',
            'blocks' => [],
        ]);

        $this->actingAs($admin)->patch(route('mail-templates.update', $template), [
            'subject' => 'Uw offerte {{offerte_nummer}}',
            'blocks' => [['type' => 'tekst', 'body' => 'Beste {{contactpersoon}}']],
        ])->assertSessionHas('error');

        $template->refresh();

        // De tekst is wel opgeslagen, maar het systeem zegt wat het niet kent.
        $this->assertSame(['offerte_nummer'], $template->unknownVariables());
    }

    public function test_empty_blocks_are_dropped_on_save(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $template = MailTemplate::create(['key' => 't', 'name' => 'T', 'subject' => 'T', 'blocks' => []]);

        $this->actingAs($admin)->patch(route('mail-templates.update', $template), [
            'subject' => 'T',
            'blocks' => [
                ['type' => 'tekst', 'body' => 'Wel tekst'],
                ['type' => 'tekst', 'body' => ''],
                ['type' => 'kop', 'body' => '   '],
            ],
        ])->assertSessionHas('success');

        $this->assertCount(1, $template->fresh()->blocks);
    }

    public function test_the_month_view_puts_a_block_on_a_bar(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder, 'name' => 'Klaas Bakker']);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        $month = today()->startOfMonth();

        ScheduleEntry::factory()->create([
            'user_id' => $uitvoerder->id,
            'project_id' => $project->id,
            'title' => 'Voorzetwanden',
            'date' => $month->copy()->addDays(4),
            'end_date' => $month->copy()->addDays(8),
        ]);

        $this->actingAs($leider)
            ->get(route('planning.index', ['weergave' => 'maand', 'maand' => $month->format('Y-m')]))
            ->assertOk()
            ->assertSee('Klaas Bakker')
            ->assertSee('Voorzetwanden');
    }

    public function test_the_capacity_view_marks_an_overbooked_week(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $uitvoerder = User::factory()->create([
            'role' => UserRole::Uitvoerder,
            'name' => 'Peter Vol',
            'weekly_capacity' => 40,
        ]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);

        $week = today()->startOfWeek();

        // Vijf werkdagen is veertig uur, plus een tweede blok erbovenop.
        ScheduleEntry::factory()->create([
            'user_id' => $uitvoerder->id,
            'project_id' => $project->id,
            'date' => $week,
            'end_date' => $week->copy()->addDays(4),
        ]);

        ScheduleEntry::factory()->create([
            'user_id' => $uitvoerder->id,
            'project_id' => $project->id,
            'date' => $week,
            'end_date' => $week->copy()->addDays(2),
        ]);

        $this->actingAs($leider)
            ->get(route('planning.index', ['weergave' => 'capaciteit', 'week' => $week->toDateString()]))
            ->assertOk()
            ->assertSee('Peter Vol')
            // 64 van 40 uur is 160 procent: overvol.
            ->assertSee('160%')
            ->assertSee('Overvol');
    }

    public function test_the_permission_matrix_shows_why_a_colleague_misses_a_screen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('team.index'))
            ->assertOk()
            ->assertSee('Rechten')
            ->assertSee('alleen directie')
            ->assertSee('Mijn klussen');
    }
}
