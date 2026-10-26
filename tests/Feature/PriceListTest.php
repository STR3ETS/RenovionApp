<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PriceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_items_get_an_article_number_automatically(): void
    {
        $first = PriceItem::factory()->create(['type' => 'arbeid', 'code' => null]);
        $second = PriceItem::factory()->create(['type' => 'arbeid', 'code' => null]);
        $material = PriceItem::factory()->create(['type' => 'materiaal', 'code' => null]);

        $this->assertSame('ARB-001', $first->code);
        $this->assertSame('ARB-002', $second->code);
        $this->assertSame('MAT-001', $material->code);
    }

    public function test_the_price_list_page_shows_and_searches_articles(): void
    {
        $user = User::factory()->create();
        PriceItem::factory()->create(['name' => 'Stukadoor (uurtarief)', 'type' => 'arbeid', 'code' => null]);
        PriceItem::factory()->create(['name' => 'Vloertegels (materiaal)', 'type' => 'materiaal', 'code' => null]);

        $this->actingAs($user)->get('/prijslijst')
            ->assertOk()
            ->assertSee('ARB-001')
            ->assertSee('Stukadoor (uurtarief)')
            ->assertSee('MAT-001');

        $this->actingAs($user)->get('/prijslijst?q=MAT-001')
            ->assertOk()
            ->assertSee('Vloertegels (materiaal)')
            ->assertDontSee('Stukadoor (uurtarief)');
    }

    public function test_articles_can_be_added_updated_and_toggled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/prijslijst', [
            'name' => 'Sanitair (stelpost)',
            'type' => 'stelpost',
            'unit' => 'post',
            'unit_price' => 3500,
        ])->assertRedirect();

        $item = PriceItem::firstWhere('name', 'Sanitair (stelpost)');
        $this->assertSame('STP-001', $item->code);
        $this->assertTrue($item->active);

        $this->actingAs($user)->patch('/prijslijst/'.$item->id, [
            'name' => 'Sanitair (stelpost middenklasse)',
            'type' => 'stelpost',
            'unit' => 'post',
            'unit_price' => 4000,
        ])->assertRedirect();

        $this->assertSame('Sanitair (stelpost middenklasse)', $item->refresh()->name);
        $this->assertSame(4000.0, (float) $item->unit_price);

        $this->actingAs($user)->patch('/prijslijst/'.$item->id, ['toggle' => 1])->assertRedirect();
        $this->assertFalse($item->refresh()->active);
    }

    public function test_the_picker_finds_items_by_article_number(): void
    {
        $user = User::factory()->create();
        $item = PriceItem::factory()->create(['name' => 'Tegelzetter (uurtarief)', 'type' => 'arbeid', 'code' => null]);

        $this->actingAs($user)->getJson('/prijsitems?q='.$item->code)
            ->assertOk()
            ->assertJsonPath('items.0.code', $item->code)
            ->assertJsonPath('items.0.name', 'Tegelzetter (uurtarief)');
    }

    public function test_uitvoerders_cannot_manage_the_price_list(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($uitvoerder)->get('/prijslijst')->assertForbidden();
    }
}
