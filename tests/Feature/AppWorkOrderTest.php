<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Werkbonnen als eigen scherm in de app: eigen bonnen voor uitvoerders, goedkeuren voor de projectleiding. */
class AppWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('test')->plainTextToken;
    }

    private function order(User $user, Project $project, array $attributes = []): WorkOrder
    {
        return WorkOrder::create([
            'number' => WorkOrder::nextNumber(),
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Tegelwerk badkamer',
            'date' => today(),
            'status' => WorkOrderStatus::Controle,
            'hours' => 8,
            'budget_hours' => 6,
            'materials' => [['description' => 'gipsplaat', 'quantity' => 6, 'unit' => 'st']],
            'checklist' => [['title' => 'Werkplek afdekken', 'done' => true, 'note' => null, 'photos' => 1]],
            ...$attributes,
        ]);
    }

    public function test_a_craftsman_sees_only_their_own_work_orders(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder, 'hourly_rate' => 48]);
        $ander = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $mine = $this->order($uitvoerder, $project);
        $theirs = $this->order($ander, $project, ['title' => 'Werk van iemand anders']);
        $token = $this->token($uitvoerder);

        $this->withToken($token)->getJson(route('api.app.work-orders.index'))->assertOk()
            ->assertJsonPath('crm', false)
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $mine->id)
            ->assertJsonPath('orders.0.hours', '8 uur')
            ->assertJsonPath('orders.0.variance.text', '+2 tegenover begroot')
            ->assertJsonPath('filters.0.count', 1);

        $this->withToken($token)->getJson(route('api.app.work-orders.show', $mine))->assertOk()
            ->assertJsonPath('number', $mine->number)
            ->assertJsonPath('variance.over', true)
            ->assertJsonPath('materials.0.quantity', '6 st')
            ->assertJsonPath('checklist_done', 1)
            ->assertJsonPath('can_approve', false);

        $this->withToken($token)->getJson(route('api.app.work-orders.show', $theirs))->assertForbidden();
        $this->withToken($token)->postJson(route('api.app.work-orders.approve', $mine))->assertForbidden();
    }

    public function test_the_leadership_approves_and_rejects_from_the_app(): void
    {
        $project = Project::factory()->create(['customer_id' => Customer::factory()]);
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder, 'hourly_rate' => 48]);
        $order = $this->order($uitvoerder, $project);
        $other = $this->order($uitvoerder, $project, ['title' => 'Stucwerk']);
        $token = $this->token(User::factory()->create(['role' => UserRole::Projectleider]));

        $this->withToken($token)->getJson(route('api.app.work-orders.index', ['status' => 'controle', 'project' => $project->id]))->assertOk()
            ->assertJsonPath('crm', true)
            ->assertJsonPath('to_review', 2)
            ->assertJsonPath('filter', 'controle')
            ->assertJsonPath('project', $project->name)
            ->assertJsonCount(2, 'orders');
        $this->withToken($token)->getJson(route('api.app.work-orders.show', $order))->assertJsonPath('can_approve', true);

        $this->withToken($token)->postJson(route('api.app.work-orders.approve', $order))->assertOk()
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, '8 uur geboekt'));
        $this->assertSame(WorkOrderStatus::Goedgekeurd, $order->fresh()->status);
        $this->withToken($token)->postJson(route('api.app.work-orders.approve', $order))
            ->assertUnprocessable()->assertJsonPath('message', 'Deze werkbon staat niet ter controle.');

        $this->withToken($token)->postJson(route('api.app.work-orders.reject', $other))->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->withToken($token)->postJson(route('api.app.work-orders.reject', $other), ['reason' => 'Uren kloppen niet'])
            ->assertOk()->assertJsonPath('message', 'Werkbon teruggestuurd naar de uitvoerder.');
        $this->withToken($token)->getJson(route('api.app.work-orders.show', $other))->assertJsonPath('rejection_reason', 'Uren kloppen niet');
    }
}
