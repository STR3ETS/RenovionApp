<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Eigen mensen en het team als eigen schermen in de app. */
class AppWorkforceTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_show_their_load_planning_and_absence_for_the_chosen_week(): void
    {
        $leader = User::factory()->create(['role' => UserRole::Projectleider]);
        $worker = User::factory()->create(['role' => UserRole::Uitvoerder, 'name' => 'Zeger Tegelaar', 'weekly_capacity' => 40]);
        $project = Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Badkamer Jansen']);
        $week = today()->startOfWeek()->addWeek();

        $worker->absences()->create(['type' => 'vakantie', 'from' => $week, 'until' => $week->copy()->addDay()]);
        ScheduleEntry::factory()->create([
            'user_id' => $worker->id, 'project_id' => $project->id, 'title' => 'Tegelwerk',
            'date' => $week->copy()->addDays(2), 'end_date' => $week->copy()->addDays(4),
        ]);
        $worker->certificates()->create(['type' => 'VCA-basis', 'valid_until' => today()->addDays(10)]);

        $response = $this->withToken($leader->createToken('test')->plainTextToken)
            ->getJson(route('api.app.workforce.index', ['week' => $week->toDateString()]))->assertOk()
            ->assertJsonPath('week.start', $week->toDateString())
            ->assertJsonPath('week.current', false)
            ->assertJsonPath('week.previous', today()->startOfWeek()->toDateString())
            ->assertJsonPath('expiring', 1)
            ->assertJsonPath('can_team', false);

        $person = collect($response->json('people'))->firstWhere('name', 'Zeger Tegelaar');
        // Drie dagen ingepland van de 24 beschikbare uren die week, net als op de website.
        $this->assertSame(['planned' => 24, 'available' => 24, 'percentage' => 100, 'absent' => 2, 'tone' => 'green'], $person['load']);
        $this->assertSame('Tegelwerk', $person['entries'][0]['title']);
        $this->assertSame('VCA', $person['certificates'][0]['short']);
        $this->assertSame('bg-amber-100 text-amber-800', $person['certificates'][0]['chip']);
        $this->assertStringStartsWith('Vakantie, ', $person['absences'][0]['label']);
    }

    public function test_office_records_absence_but_only_management_edits_details_and_certificates(): void
    {
        $leaderToken = User::factory()->create(['role' => UserRole::Projectleider])->createToken('test')->plainTextToken;
        $adminToken = User::factory()->create(['role' => UserRole::Admin])->createToken('test')->plainTextToken;
        $worker = User::factory()->create(['role' => UserRole::Uitvoerder, 'weekly_capacity' => 40]);

        $this->withToken($leaderToken)->postJson(route('api.app.absences.store', $worker), [
            'type' => 'ziekte', 'from' => today()->toDateString(), 'until' => today()->toDateString(),
        ])->assertOk()->assertJsonPath('message', "Afwezigheid vastgelegd voor {$worker->name}.");
        $this->withToken($leaderToken)->deleteJson(route('api.app.absences.destroy', $worker->absences()->first()))->assertOk();
        $this->assertSame(0, $worker->absences()->count());

        $this->withToken($leaderToken)->postJson(route('api.app.certificates.store', $worker), ['type' => 'BHV'])->assertForbidden();
        $this->withToken($leaderToken)->patchJson(route('api.app.workforce.update', $worker), ['hourly_rate' => 999])->assertForbidden();

        // Sanctum onthoudt de gebruiker binnen één test: vergeten voor een ander token.
        $this->app['auth']->forgetGuards();

        $this->withToken($adminToken)->postJson(route('api.app.certificates.store', $worker), [
            'type' => 'BHV', 'issued_on' => today()->toDateString(),
        ])->assertOk();
        $this->assertSame(today()->addYear()->toDateString(), $worker->certificates()->first()->valid_until->toDateString());

        $this->withToken($adminToken)->patchJson(route('api.app.workforce.update', $worker), [
            'job_title' => 'Tegelzetter', 'hourly_rate' => 45, 'weekly_capacity' => 32, 'plannable' => false,
        ])->assertOk();
        $this->assertSame(32, $worker->fresh()->weekly_capacity);
        $this->assertFalse($worker->fresh()->plannable);

        $this->withToken($adminToken)->deleteJson(route('api.app.certificates.destroy', $worker->certificates()->first()))->assertOk();
    }

    public function test_the_team_lists_members_with_their_forms_and_the_rights_matrix(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Imad']);
        $worker = User::factory()->create(['role' => UserRole::Uitvoerder, 'name' => 'Zeger']);
        User::factory()->create(['role' => UserRole::Klant]);

        $response = $this->withToken($admin->createToken('test')->plainTextToken)->getJson(route('api.app.team.index'))->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('users.0.name', 'Imad')
            ->assertJsonPath('users.0.me', true)
            ->assertJsonPath('users.1.edit', route('team.edit', $worker, false))
            ->assertJsonPath('users.1.sub', '0 projecten, 0 open taken')
            ->assertJsonPath('create', route('team.create', [], false));

        $this->assertNotContains('klant', $response->json('matrix.roles.*.value'));
        $team = collect($response->json('matrix.sections'))->flatMap(fn (array $section) => $section['items'])->firstWhere('module', 'teambeheer');
        $this->assertTrue($team['management_only']);
        $this->assertNotContains('uitvoerder', $team['roles']);
    }

    public function test_only_management_opens_the_team_and_executors_not_the_people(): void
    {
        $leaderToken = User::factory()->create(['role' => UserRole::Projectleider])->createToken('test')->plainTextToken;
        $workerToken = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $this->withToken($leaderToken)->getJson(route('api.app.team.index'))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($workerToken)->getJson(route('api.app.workforce.index'))->assertForbidden();
    }
}
