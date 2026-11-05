<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppApiTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_the_profile_carries_the_menu_with_screens_in_the_app(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $menu = $this->withToken($this->tokenFor($admin))->getJson(route('api.app.me'))->assertOk()->json('menu');

        $this->assertSame(['vandaag', 'inbox', 'projecten', 'planning'], array_column($menu['tabs'], 'screen'));
        $sales = collect($menu['groups'])->flatMap(fn ($group) => $group['items'])->firstWhere('label', 'Verkoop');
        $this->assertNull($sales['screen']);
        $this->assertSame('/aanvragen', $sales['path']);
    }

    public function test_a_phone_registers_for_push_and_logging_out_removes_it(): void
    {
        $user = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson(route('api.app.devices'), ['token' => 'firebase-token-123', 'platform' => 'android'])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'token' => 'firebase-token-123']);

        $this->withToken($token)->postJson(route('api.app.logout'), ['push_token' => 'firebase-token-123'])->assertNoContent();
        $this->assertDatabaseMissing('device_tokens', ['token' => 'firebase-token-123']);
    }

    public function test_a_token_unused_for_sixty_days_no_longer_works(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $token = $user->createToken('test');
        $token->accessToken->forceFill(['last_used_at' => now()->subDays(61)])->save();

        $this->withToken($token->plainTextToken)->getJson(route('api.app.me'))->assertUnauthorized();
    }

    public function test_the_project_file_shows_phases_and_open_work_to_people_on_the_project(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $project = Project::factory()->create(['customer_id' => Customer::factory()->create()->id, 'name' => 'Badkamer Visser']);
        $project->craftsmen()->attach($executor);
        $other = Project::factory()->create(['customer_id' => Customer::factory()->create()->id]);
        $token = $this->tokenFor($executor);

        $this->withToken($token)->getJson(route('api.app.projects.show', $project))
            ->assertOk()
            ->assertJsonPath('name', 'Badkamer Visser')
            ->assertJsonCount(9, 'phases')
            ->assertJsonPath('value', null)
            ->assertJsonPath('links.edit', null)
            ->assertJsonStructure(['current' => ['step', 'name'], 'links' => ['execution', 'photos'], 'tasks', 'planning']);

        $this->withToken($token)->getJson(route('api.app.projects.show', $other))->assertForbidden();
    }

    public function test_tasks_can_be_listed_and_added_from_the_app(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $boss = User::factory()->create(['role' => UserRole::Admin]);
        Task::factory()->create(['owner_id' => $boss->id, 'title' => 'Niet van mij']);
        $token = $this->tokenFor($executor);

        // Een uitvoerder maakt alleen taken voor zichzelf, ook als hij een ander opgeeft.
        $this->withToken($token)->postJson(route('api.app.tasks.store'), ['title' => 'Kitwerk badkamer', 'owner_id' => $boss->id])->assertCreated();
        $this->assertDatabaseHas('tasks', ['title' => 'Kitwerk badkamer', 'owner_id' => $executor->id]);

        $titles = collect($this->withToken($token)->getJson(route('api.app.tasks'))->assertOk()->json('tasks'))->pluck('title');
        $this->assertTrue($titles->contains('Kitwerk badkamer'));
        $this->assertFalse($titles->contains('Niet van mij'));
    }

    public function test_nova_is_only_for_the_office(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->withToken($this->tokenFor($executor))->postJson(route('api.app.nova'), ['message' => 'Plan iets'])->assertForbidden();
    }

    public function test_logging_in_returns_a_token_and_profile(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'password' => 'geheim-wachtwoord']);

        $this->postJson(route('api.app.login'), ['email' => $user->email, 'password' => 'geheim-wachtwoord'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['name', 'role'], 'can' => ['crm'], 'counts' => ['attention', 'chat']])
            ->assertJsonPath('can.crm', true);
    }

    public function test_a_wrong_password_gets_a_dutch_error(): void
    {
        $user = User::factory()->create(['password' => 'geheim-wachtwoord']);

        $this->postJson(route('api.app.login'), ['email' => $user->email, 'password' => 'fout'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'De combinatie van e-mailadres en wachtwoord klopt niet.');
    }

    public function test_data_needs_a_token(): void
    {
        $this->getJson(route('api.app.today'))->assertUnauthorized();
        $this->getJson(route('api.app.projects'))->assertUnauthorized();
    }

    public function test_the_office_gets_its_today_screen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->withToken($this->tokenFor($admin))
            ->getJson(route('api.app.today'))
            ->assertOk()
            ->assertJsonPath('type', 'kantoor')
            ->assertJsonStructure(['stats' => ['omzet_lopend', 'actieve_projecten'], 'first', 'planning', 'tasks', 'projects']);
    }

    public function test_an_executor_only_sees_own_projects_and_no_values(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $customer = Customer::factory()->create();
        $own = Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Eigen badkamer', 'status' => 'uitvoering']);
        $own->craftsmen()->attach($executor);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Ander project', 'status' => 'uitvoering']);

        $response = $this->withToken($this->tokenFor($executor))->getJson(route('api.app.projects'))->assertOk();

        $this->assertSame(['Eigen badkamer'], collect($response->json('projects'))->pluck('name')->all());
        $this->assertNull($response->json('projects.0.value'));

        $this->withToken($this->tokenFor($executor))
            ->getJson(route('api.app.today'))
            ->assertOk()
            ->assertJsonPath('type', 'uitvoerder');
    }

    public function test_the_inbox_lists_exceptions_with_paths_into_the_web_app(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Project::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'name' => 'Te late badkamer',
            'status' => 'uitvoering',
            'end_date_expected' => today()->subDays(3),
        ]);

        $response = $this->withToken($this->tokenFor($admin))->getJson(route('api.app.inbox'))->assertOk();

        $item = collect($response->json('items'))->firstWhere('title', 'Te late badkamer');
        $this->assertNotNull($item);
        $this->assertStringStartsWith('/projecten/', $item['path']);
    }

    public function test_an_executor_has_no_inbox(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->withToken($this->tokenFor($executor))->getJson(route('api.app.inbox'))->assertForbidden();
    }

    public function test_the_planner_gets_the_week_with_rows_entries_and_the_form(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder, 'name' => 'Peter Mulder']);
        ScheduleEntry::factory()->create(['user_id' => $executor->id, 'date' => today()->startOfWeek(), 'title' => 'Tegels zetten']);

        $this->withToken($this->tokenFor($admin))
            ->getJson(route('api.app.planning'))
            ->assertOk()
            ->assertJsonCount(5, 'days')
            ->assertJsonPath('rows.0.name', 'Peter')
            ->assertJsonPath('entries.0.title', 'Tegels zetten')
            ->assertJsonPath('entries.0.delete', true)
            ->assertJsonStructure(['form' => ['users', 'projects', 'types'], 'previous', 'next']);
    }

    public function test_an_executor_only_sees_own_planning_and_cannot_plan(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $other = User::factory()->create(['role' => UserRole::Uitvoerder]);
        ScheduleEntry::factory()->create(['user_id' => $executor->id, 'date' => today()->startOfWeek(), 'title' => 'Eigen werk']);
        ScheduleEntry::factory()->create(['user_id' => $other->id, 'date' => today()->startOfWeek(), 'title' => 'Werk van een ander']);
        $token = $this->tokenFor($executor);

        $response = $this->withToken($token)->getJson(route('api.app.planning'))->assertOk()->assertJsonPath('form', null);
        $this->assertSame(['Eigen werk'], collect($response->json('entries'))->pluck('title')->all());

        $this->withToken($token)->postJson(route('api.app.planning.store'), ['user_id' => $executor->id, 'type' => 'project', 'date' => today()->toDateString()])->assertForbidden();
    }

    public function test_the_planner_can_plan_and_remove_from_the_app(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $token = $this->tokenFor($admin);

        $id = $this->withToken($token)
            ->postJson(route('api.app.planning.store'), ['user_id' => $executor->id, 'type' => 'project', 'date' => today()->toDateString(), 'title' => 'Badkamer slopen', 'start_time' => '08:00', 'end_time' => '12:00'])
            ->assertCreated()
            ->json('id');

        $this->assertDatabaseHas('schedule_entries', ['id' => $id, 'title' => 'Badkamer slopen']);

        $this->withToken($token)->deleteJson(route('api.app.planning.destroy', $id))->assertNoContent();
        $this->assertDatabaseMissing('schedule_entries', ['id' => $id]);
    }

    public function test_a_customer_account_gets_no_internal_data(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Klant, 'customer_id' => Customer::factory()->create()->id]);

        $this->withToken($this->tokenFor($customer))->getJson(route('api.app.today'))->assertForbidden();
        $this->withToken($this->tokenFor($customer))->getJson(route('api.app.me'))->assertOk()->assertJsonPath('can.internal', false);
    }

    public function test_a_task_can_be_finished_from_the_app(): void
    {
        $user = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $task = Task::factory()->create(['owner_id' => $user->id, 'status' => TaskStatus::TeDoen]);

        $this->withToken($this->tokenFor($user))
            ->patchJson(route('api.app.tasks.status', $task), ['status' => 'afgerond'])
            ->assertOk();

        $this->assertSame(TaskStatus::Afgerond, $task->fresh()->status);
    }

    public function test_a_web_code_signs_in_once_and_points_back_to_the_app(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $url = $this->withToken($this->tokenFor($user))
            ->postJson(route('api.app.web-code'), ['naar' => '/aandacht', 'terug' => 'https://localhost'])
            ->assertOk()
            ->json('url');

        $this->get($url)->assertRedirect('/aandacht');
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('https://localhost', session('app_bundle'));

        auth('web')->logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_a_web_code_only_goes_to_own_pages_and_back_to_the_app(): void
    {
        $token = $this->tokenFor(User::factory()->create(['role' => UserRole::Admin]));

        $this->withToken($token)->postJson(route('api.app.web-code'), ['naar' => '//evil.example/x', 'terug' => 'https://localhost'])->assertUnprocessable();
        $this->withToken($token)->postJson(route('api.app.web-code'), ['naar' => '/aandacht', 'terug' => 'https://evil.example'])->assertUnprocessable();
    }

    public function test_logging_out_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson(route('api.app.logout'))->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_only_the_app_may_call_the_api_from_a_browser(): void
    {
        $this->withHeaders(['Origin' => 'https://localhost'])->optionsJson(route('api.app.login'), [], ['Access-Control-Request-Method' => 'POST'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://localhost');

        $response = $this->withHeaders(['Origin' => 'https://evil.example'])->optionsJson(route('api.app.login'), [], ['Access-Control-Request-Method' => 'POST']);
        $this->assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
