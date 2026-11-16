<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Inkoop als eigen scherm in de app: bestellingen, regels, levering en inkoopfacturen. */
class AppPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Inkoper): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    private function project(): Project
    {
        return Project::factory()->create(['customer_id' => Customer::factory(), 'name' => 'Badkamer Jansen']);
    }

    public function test_an_order_is_created_filled_delivered_and_its_lines_removed(): void
    {
        $project = $this->project();
        $token = $this->token();

        $response = $this->withToken($token)->postJson(route('api.app.purchase-orders.store'), [
            'project_id' => $project->id, 'supplier' => 'Mosa', 'category' => 'materiaal', 'expected_on' => today()->subDay()->toDateString(),
        ])->assertOk();
        $order = PurchaseOrder::firstWhere('supplier', 'Mosa');
        $this->assertSame(route('purchase-orders.show', $order, false), $response->json('path'));

        $this->withToken($token)->postJson(route('api.app.purchase-order-lines.store', $order), [
            'description' => 'Wandtegels 30x60', 'quantity' => '12,5', 'unit' => 'm2', 'unit_price' => '40',
        ])->assertOk()->assertJsonPath('message', 'Regel toegevoegd.');

        $this->withToken($token)->getJson(route('api.app.purchase-orders.show', $order))->assertOk()
            ->assertJsonPath('supplier', 'Mosa')
            ->assertJsonPath('subtitle', 'Badkamer Jansen, Materiaal')
            ->assertJsonPath('status.value', 'concept')
            ->assertJsonPath('statuses.*.value', ['verzonden', 'bevestigd', 'geleverd'])
            ->assertJsonPath('lines.0.quantity', '12,5 m2')
            ->assertJsonPath('total', 500)
            ->assertJsonPath('delivery.1.late', true)
            ->assertJsonPath('invoices', []);

        $this->withToken($token)->patchJson(route('api.app.purchase-orders.update', $order), ['status' => 'geleverd'])
            ->assertOk()->assertJsonPath('label', 'Geleverd');
        $this->assertSame(today()->toDateString(), $order->fresh()->delivered_on->toDateString());

        $line = $order->lines()->first();
        $this->withToken($token)->deleteJson(route('api.app.purchase-order-lines.destroy', $line))->assertOk();
        $this->assertModelMissing($line);
    }

    public function test_purchase_invoices_are_booked_approved_and_paid_from_the_list(): void
    {
        $project = $this->project();
        $token = $this->token();
        $invoice = ['supplier' => 'Gamma', 'number' => 'F-100', 'project_id' => $project->id, 'category' => 'materiaal', 'date' => today()->toDateString(), 'amount' => 100];

        $this->withToken($token)->postJson(route('api.app.purchase-invoices.store'), $invoice)->assertOk();
        $this->withToken($token)->postJson(route('api.app.purchase-invoices.store'), $invoice)
            ->assertUnprocessable()->assertJsonPath('message', 'Deze leverancier heeft dat factuurnummer al ingediend.');

        $booked = PurchaseInvoice::firstWhere('number', 'F-100');
        $this->withToken($token)->getJson(route('api.app.purchase-orders.index'))->assertOk()
            ->assertJsonPath('kpis.to_approve', 1)
            ->assertJsonPath('invoices.0.supplier', 'Gamma')
            ->assertJsonPath('invoices.0.total', 121)
            ->assertJsonPath('invoices.0.actions.*.value', ['goedgekeurd', 'afgewezen'])
            ->assertJsonPath('form.projects.0.name', 'Badkamer Jansen');

        $this->withToken($token)->patchJson(route('api.app.purchase-invoices.update', $booked), ['status' => 'goedgekeurd'])
            ->assertOk()->assertJsonPath('label', 'Goedgekeurd');
        $this->assertNotNull($booked->fresh()->approved_at);

        $this->withToken($token)->getJson(route('api.app.purchase-orders.index'))->assertOk()
            ->assertJsonPath('kpis.to_approve', 0)
            ->assertJsonPath('invoices.0.actions.*.value', ['betaald']);
    }

    public function test_executors_cannot_open_purchasing(): void
    {
        $token = $this->token(UserRole::Uitvoerder);

        $this->withToken($token)->getJson(route('api.app.purchase-orders.index'))->assertForbidden();
        $this->withToken($token)->postJson(route('api.app.purchase-orders.store'), [])->assertForbidden();
    }
}
