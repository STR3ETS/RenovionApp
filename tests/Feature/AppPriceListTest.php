<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PriceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** De prijslijst als eigen scherm in de app: alle artikelen, toevoegen, bewerken en aan/uit. */
class AppPriceListTest extends TestCase
{
    use RefreshDatabase;

    private function token(UserRole $role = UserRole::Admin): string
    {
        return User::factory()->create(['role' => $role])->createToken('test')->plainTextToken;
    }

    public function test_the_list_shows_every_article_with_its_type_and_price(): void
    {
        PriceItem::query()->delete();
        $item = PriceItem::factory()->create(['name' => 'Wanden glad stucwerk', 'type' => 'arbeid', 'code' => null, 'unit' => 'm2', 'unit_price' => 27.5, 'surcharge_pct' => 10, 'index_factor' => 1]);
        PriceItem::factory()->create(['name' => 'Tegellijm', 'type' => 'materiaal', 'code' => null, 'active' => false]);

        $this->withToken($this->token())->getJson(route('api.app.price-items.index'))->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('types.0.value', 'arbeid')
            ->assertJsonPath('items.0.id', $item->id)
            ->assertJsonPath('items.0.code', 'ARB-001')
            ->assertJsonPath('items.0.price', 27.5)
            ->assertJsonPath('items.0.type.label', 'Arbeid')
            ->assertJsonPath('items.0.sub', fn (string $sub) => str_ends_with($sub, ', opslag 10%'))
            ->assertJsonPath('items.1.active', false);
    }

    public function test_adding_editing_and_switching_an_article_from_the_app(): void
    {
        $token = $this->token();

        $id = $this->withToken($token)->postJson(route('api.app.price-items.store'), ['name' => 'Plafond sauzen', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 9.5])
            ->assertOk()->json('id');
        $item = PriceItem::findOrFail($id);
        $this->assertSame('Renovion praktijkprijzen', $item->source);

        $this->withToken($token)->patchJson(route('api.app.price-items.update', $item), ['name' => 'Plafond sauzen (2 lagen)', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 11, 'surcharge_pct' => 5])
            ->assertOk()->assertJsonPath('message', "Artikel {$item->code} bijgewerkt.");
        $this->assertSame('11.00', $item->fresh()->unit_price);

        $this->withToken($token)->patchJson(route('api.app.price-items.update', $item), ['toggle' => 1])
            ->assertOk()->assertJsonPath('active', false);
        $this->assertFalse($item->fresh()->active);

        $this->withToken($token)->postJson(route('api.app.price-items.store'), ['name' => '', 'type' => 'arbeid', 'unit' => 'm2'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'unit_price']);
    }

    public function test_executors_cannot_open_the_price_list(): void
    {
        $this->withToken($this->token(UserRole::Uitvoerder))->getJson(route('api.app.price-items.index'))->assertForbidden();
    }
}
