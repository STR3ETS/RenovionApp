<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_team_page(): void
    {
        $admin = User::factory()->create(['name' => 'Imad Admin']);
        User::factory()->vakman()->create(['name' => 'Peter Stukadoor']);

        $this->actingAs($admin)->get('/team')
            ->assertOk()
            ->assertSee('Peter Stukadoor')
            ->assertSee('Vakman');
    }

    public function test_admin_can_create_a_new_vakman(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post('/team', [
            'name' => 'Ahmed Tegelzetter',
            'email' => 'ahmed@renovion.nl',
            'phone' => '06-11122233',
            'role' => 'vakman',
            'password' => 'geheim-wachtwoord',
        ]);

        $response->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Ahmed Tegelzetter',
            'email' => 'ahmed@renovion.nl',
            'role' => 'vakman',
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'user', 'action' => 'aangemaakt']);

        $this->post('/logout');
        $this->post('/login', ['email' => 'ahmed@renovion.nl', 'password' => 'geheim-wachtwoord'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_edit_a_team_member(): void
    {
        $admin = User::factory()->create();
        $vakman = User::factory()->vakman()->create();

        $this->actingAs($admin)->patch('/team/'.$vakman->id, [
            'name' => 'Nieuwe Naam',
            'email' => $vakman->email,
            'role' => 'projectleider',
        ])->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('users', [
            'id' => $vakman->id,
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
            'role' => 'vakman',
        ]);

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_non_admins_cannot_manage_the_team(): void
    {
        foreach ([UserRole::Sales, UserRole::Projectleider, UserRole::Vakman] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/team')->assertForbidden();
            $this->actingAs($user)->post('/team', [
                'name' => 'X', 'email' => 'x'.$role->value.'@test.nl', 'role' => 'admin', 'password' => 'wachtwoord123',
            ])->assertForbidden();
        }
    }
}
