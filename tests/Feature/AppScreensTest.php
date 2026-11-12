<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\AppMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/** De schermen die van serverpagina naar de app zijn gegaan: Meldingen, Zoeken, Klanten, Aanvragen, Chat. */
class AppScreensTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function notify(User $user, string $title, ?string $readAt = null): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TaskAssigned',
            'data' => ['title' => $title, 'body' => 'Van Imad', 'url' => '/taken', 'icon' => 'clipboard-check'],
            'read_at' => $readAt,
        ]);
    }

    public function test_notifications_list_newest_first_and_are_read_after_opening(): void
    {
        $user = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $this->notify($user, 'Oude melding', now()->subDay()->toDateTimeString());
        $this->travel(1)->minutes();
        $this->notify($user, 'Nieuwe taak');

        $response = $this->withToken($this->tokenFor($user))->getJson(route('api.app.notifications'))->assertOk();

        $this->assertSame(['Nieuwe taak', 'Oude melding'], array_column($response->json('notifications'), 'title'));
        $this->assertSame([true, false], array_column($response->json('notifications'), 'new'));
        $response->assertJsonPath('notifications.0.path', '/taken');
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_someone_only_sees_their_own_notifications(): void
    {
        $user = User::factory()->create();
        $this->notify(User::factory()->create(), 'Van een ander');

        $this->withToken($this->tokenFor($user))->getJson(route('api.app.notifications'))
            ->assertOk()->assertJsonCount(0, 'notifications');
    }

    public function test_search_finds_projects_customers_leads_and_tasks(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::factory()->create(['name' => 'Familie Jansen', 'city' => 'Arnhem']);
        Project::factory()->for($customer)->create(['name' => 'Badkamer Jansen']);
        Lead::factory()->for($customer)->create(['service' => 'Stucwerk']);
        Task::factory()->create(['title' => 'Jansen terugbellen']);
        Customer::factory()->create(['name' => 'Familie Bakker']);

        $response = $this->withToken($this->tokenFor($admin))->getJson(route('api.app.search', ['q' => 'jansen']))->assertOk();

        $response->assertJsonPath('projects.0.name', 'Badkamer Jansen')
            ->assertJsonPath('customers.0.name', 'Familie Jansen')
            ->assertJsonPath('customers.0.path', route('customers.show', $customer, false))
            ->assertJsonPath('leads.0.sub', 'Stucwerk')
            ->assertJsonPath('tasks.0.name', 'Jansen terugbellen')
            ->assertJsonCount(1, 'customers');
    }

    public function test_an_empty_search_returns_nothing(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Customer::factory()->create();

        $this->withToken($this->tokenFor($admin))->getJson(route('api.app.search'))
            ->assertOk()->assertJsonCount(0, 'customers')->assertJsonCount(0, 'projects');
    }

    public function test_server_pages_only_send_apps_that_have_them_to_the_new_screens(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $app = ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) RenovionApp'];

        // Build 3 heeft Meldingen en Zoeken in de app: bel en zoekveld wijzen terug naar de app.
        $this->actingAs($admin)->withHeaders($app)->withSession(['app_bundle' => 'https://localhost', 'app_build' => 3])
            ->get(route('planning.index'))->assertOk()
            ->assertSee('https://localhost/#/meldingen', false)->assertSee('https://localhost/#/zoeken', false);

        // Build 2 kent ze niet (zou op Vandaag uitkomen): die houdt de serverpagina's.
        $this->actingAs($admin)->withHeaders($app)->withSession(['app_bundle' => 'https://localhost', 'app_build' => 2])
            ->get(route('planning.index'))->assertOk()
            ->assertDontSee('https://localhost/#/meldingen', false)->assertSee(route('notifications.index'), false);
    }

    public function test_the_app_menu_only_offers_new_screens_to_apps_that_have_them(): void
    {
        $this->assertSame('meldingen', $this->withHeader('X-App-Build', '3')->screenFor('notifications.index'));
        $this->assertNull($this->withHeader('X-App-Build', '2')->screenFor('notifications.index'));
        $this->assertSame('vandaag', $this->withHeader('X-App-Build', '2')->screenFor('dashboard'));
    }

    private function screenFor(string $route): ?string
    {
        $request = Request::create('/api/app/ik', 'GET', server: $this->transformHeadersToServerVars($this->defaultHeaders));
        $this->app->instance('request', $request);

        return AppMenu::screen($route);
    }

    public function test_executors_cannot_search(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->withToken($this->tokenFor($executor))->getJson(route('api.app.search', ['q' => 'a']))->assertForbidden();
    }

    public function test_the_customer_list_is_alphabetical_with_counts_and_searchable(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jansen = Customer::factory()->create(['name' => 'Familie Jansen', 'city' => 'Arnhem']);
        Customer::factory()->create(['name' => 'Bakker BV', 'city' => 'Ede']);
        Lead::factory()->for($jansen)->count(2)->create();
        $token = $this->tokenFor($admin);

        $this->withToken($token)->getJson(route('api.app.customers'))->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('customers.0.name', 'Bakker BV')
            ->assertJsonPath('customers.1.initial', 'J')
            ->assertJsonPath('customers.1.leads', 2);

        $this->withToken($token)->getJson(route('api.app.customers', ['q' => 'arnhem']))->assertOk()
            ->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.name', 'Familie Jansen');
    }

    public function test_the_customer_file_shows_leads_projects_and_the_timeline_and_takes_a_note(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Imad']);
        $customer = Customer::factory()->create(['name' => 'Familie Jansen', 'phone' => '0612345678']);
        Lead::factory()->for($customer)->create(['service' => 'Badkamer']);
        Project::factory()->for($customer)->create(['name' => 'Badkamer Jansen']);
        $token = $this->tokenFor($admin);

        $this->withToken($token)->getJson(route('api.app.customers.show', $customer))->assertOk()
            ->assertJsonPath('phone', '0612345678')
            ->assertJsonPath('leads.0.name', 'Badkamer')
            ->assertJsonPath('projects.0.name', 'Badkamer Jansen')
            ->assertJsonPath('portal', false);

        $this->withToken($token)->postJson(route('api.app.customers.notes', $customer), ['body' => 'Wil liever in maart beginnen'])
            ->assertCreated()
            ->assertJsonPath('timeline.0.title', 'Notitie')
            ->assertJsonPath('timeline.0.body', 'Wil liever in maart beginnen')
            ->assertJsonPath('timeline.0.who', 'Imad');

        $this->withToken($token)->postJson(route('api.app.customers.notes', $customer), ['body' => ''])->assertUnprocessable();
    }

    public function test_executors_have_no_customers_or_leads(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $customer = Customer::factory()->create();
        $token = $this->tokenFor($executor);

        $this->withToken($token)->getJson(route('api.app.customers'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.customers.show', $customer))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.leads'))->assertForbidden();
    }

    public function test_the_pipeline_has_a_column_per_stage_with_signals_on_the_cards(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::factory()->create(['name' => 'Familie Jansen', 'phone' => null]);
        Lead::factory()->for($customer)->create(['status' => LeadStatus::Contact, 'service' => 'Stucwerk']);
        Lead::factory()->for(Customer::factory())->create(['status' => LeadStatus::Verloren, 'lost_reason' => 'Te duur']);

        $response = $this->withToken($this->tokenFor($admin))->getJson(route('api.app.leads'))->assertOk();

        $this->assertSame(array_map(fn (LeadStatus $status) => $status->value, LeadStatus::pipeline()), array_column($response->json('columns'), 'value'));
        $response->assertJsonPath('open', 1)
            ->assertJsonPath('columns.1.leads.0.name', 'Familie Jansen')
            ->assertJsonPath('columns.1.leads.0.signals.0.text', 'Geen telefoonnummer')
            ->assertJsonPath('inactive.0.lost_reason', 'Te duur');
    }

    public function test_a_lead_shows_its_details_and_takes_a_status_and_a_contact_moment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $lead = Lead::factory()->for(Customer::factory()->create(['name' => 'Familie Jansen', 'city' => 'Arnhem']))
            ->create(['service' => 'Badkamer', 'status' => LeadStatus::Nieuw]);
        $token = $this->tokenFor($admin);

        $this->withToken($token)->getJson(route('api.app.leads.show', $lead))->assertOk()
            ->assertJsonPath('name', 'Familie Jansen')
            ->assertJsonPath('subtitle', 'Badkamer, Arnhem')
            ->assertJsonPath('status.value', 'nieuw')
            ->assertJsonCount(count(LeadStatus::cases()), 'statuses');

        $this->withToken($token)->patchJson(route('api.app.leads.status', $lead), ['status' => 'contact'])
            ->assertOk()->assertJsonPath('label', 'Contact');
        $this->assertSame(LeadStatus::Contact, $lead->fresh()->status);

        $this->withToken($token)->postJson(route('api.app.leads.contacts', $lead), [
            'type' => 'telefoon', 'summary' => 'Gebeld, stuurt foto’s', 'next_action' => 'Terugbellen', 'next_action_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertCreated();
        $this->assertSame('Terugbellen', $lead->fresh()->next_action);

        $this->withToken($token)->getJson(route('api.app.leads.show', $lead))->assertOk()
            ->assertJsonPath('details.next_action', 'Terugbellen')
            ->assertJsonPath('timeline.0.title', 'Telefoongesprek: Gebeld, stuurt foto’s');

        $this->withToken($token)->postJson(route('api.app.leads.contacts', $lead), ['type' => 'fax', 'summary' => 'x'])->assertUnprocessable();
    }
}
