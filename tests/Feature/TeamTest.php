<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\JobExecution;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_team_page(): void
    {
        $admin = User::factory()->create(['name' => 'Imad Admin']);
        User::factory()->uitvoerder()->create(['name' => 'Peter Stukadoor']);

        $this->actingAs($admin)->get('/team')
            ->assertOk()
            ->assertSee('Peter Stukadoor')
            ->assertSee('Uitvoerder');
    }

    public function test_admin_can_create_a_new_uitvoerder(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post('/team', [
            'name' => 'Ahmed Tegelzetter',
            'email' => 'ahmed@renovion.nl',
            'phone' => '06-11122233',
            'role' => 'uitvoerder',
            'password' => 'geheim-wachtwoord',
        ]);

        $response->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Ahmed Tegelzetter',
            'email' => 'ahmed@renovion.nl',
            'role' => 'uitvoerder',
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'user', 'action' => 'aangemaakt']);

        $this->post('/logout');
        $this->post('/login', ['email' => 'ahmed@renovion.nl', 'password' => 'geheim-wachtwoord'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_edit_a_team_member(): void
    {
        $admin = User::factory()->create();
        $uitvoerder = User::factory()->uitvoerder()->create();

        $this->actingAs($admin)->patch('/team/'.$uitvoerder->id, [
            'name' => 'Nieuwe Naam',
            'email' => $uitvoerder->email,
            'role' => 'projectleider',
        ])->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('users', [
            'id' => $uitvoerder->id,
            'name' => 'Nieuwe Naam',
            'role' => 'projectleider',
        ]);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->patch('/team/'.$admin->id, [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'uitvoerder',
        ]);

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_non_admins_cannot_manage_the_team(): void
    {
        foreach ([UserRole::Sales, UserRole::Projectleider, UserRole::Werkvoorbereider, UserRole::Uitvoerder] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/team')->assertForbidden();
            $this->actingAs($user)->post('/team', [
                'name' => 'X', 'email' => 'x'.$role->value.'@test.nl', 'role' => 'admin', 'password' => 'wachtwoord123',
            ])->assertForbidden();
        }
    }

    public function test_admin_can_delete_a_team_member_without_work_history(): void
    {
        $admin = User::factory()->create();
        $uitvoerder = User::factory()->uitvoerder()->create(['name' => 'Mehmet']);

        $this->actingAs($admin)->get('/team/'.$uitvoerder->id.'/bewerken')
            ->assertOk()
            ->assertSee('Verwijderen');

        $this->actingAs($admin)->delete('/team/'.$uitvoerder->id)
            ->assertRedirect(route('team.index'))
            ->assertSessionHas('success', 'Mehmet is verwijderd.');

        $this->assertDatabaseMissing('users', ['id' => $uitvoerder->id]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'user', 'action' => 'verwijderd']);
    }

    public function test_a_team_member_with_jobs_is_deactivated_so_the_work_stays(): void
    {
        $admin = User::factory()->create();
        $uitvoerder = User::factory()->uitvoerder()->create(['password' => 'geheim-wachtwoord']);
        $entry = ScheduleEntry::factory()->create(['user_id' => $uitvoerder->id]);
        $job = JobExecution::create(['schedule_entry_id' => $entry->id, 'user_id' => $uitvoerder->id]);

        $this->actingAs($admin)->delete('/team/'.$uitvoerder->id)
            ->assertRedirect(route('team.index'));

        // Het account blijft bestaan, de klus ook, maar inloggen kan niet meer.
        $this->assertFalse($uitvoerder->refresh()->active);
        $this->assertDatabaseHas('job_executions', ['id' => $job->id]);

        $this->post('/logout');
        $this->post('/login', ['email' => $uitvoerder->email, 'password' => 'geheim-wachtwoord'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // En terugzetten kan ook.
        $this->actingAs($admin)->post('/team/'.$uitvoerder->id.'/activeren')
            ->assertRedirect(route('team.index'));
        $this->assertTrue($uitvoerder->refresh()->active);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->delete('/team/'.$admin->id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->actingAs($admin)->get('/team/'.$admin->id.'/bewerken')
            ->assertOk()
            ->assertDontSee('Uit het team halen');
    }

    public function test_non_admins_cannot_delete_team_members(): void
    {
        $leider = User::factory()->create(['role' => UserRole::Projectleider]);
        $uitvoerder = User::factory()->uitvoerder()->create();

        $this->actingAs($leider)->delete('/team/'.$uitvoerder->id)->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $uitvoerder->id]);
    }
}
