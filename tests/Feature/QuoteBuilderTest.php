<?php

namespace Tests\Feature;

use App\Models\Calculation;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use App\Services\QuoteBlockAi;
use Database\Seeders\QuoteTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuoteBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_builder_opens_and_converts_legacy_blocks_to_typed_blocks(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create([
            'blocks' => [
                ['key' => 'samenvatting', 'title' => 'Samenvatting', 'body' => 'Beste klant, bedankt.', 'enabled' => true],
                ['key' => 'werkzaamheden', 'title' => 'Werkzaamheden', 'body' => "- Stucwerk\n- Tegelwerk", 'enabled' => true],
                ['key' => 'meerwerk', 'title' => 'Meerwerk', 'body' => 'Uitgeschakeld blok', 'enabled' => false],
            ],
        ]);

        $this->actingAs($user)->get('/offertes/'.$quote->id.'/builder')
            ->assertOk()
            ->assertSee('Offerte bouwen');

        $blocks = collect($quote->refresh()->blocks);
        $this->assertSame('hero', $blocks->first()['type']);
        $this->assertTrue($blocks->contains(fn ($block) => $block['type'] === 'text' && $block['data']['body'] === 'Beste klant, bedankt.'));
        $this->assertTrue($blocks->contains(fn ($block) => $block['type'] === 'list' && $block['data']['items'] === ['Stucwerk', 'Tegelwerk']));
        $this->assertFalse($blocks->contains(fn ($block) => str_contains($block['data']['body'] ?? '', 'Uitgeschakeld')));
    }

    public function test_the_builder_is_only_for_concept_quotes(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->sent()->create();

        $this->actingAs($user)->get('/offertes/'.$quote->id.'/builder')
            ->assertRedirect(route('quotes.show', $quote));

        $this->actingAs($user)->putJson('/offertes/'.$quote->id.'/builder', [
            'blocks' => [['id' => 'x', 'type' => 'text', 'data' => []]],
        ])->assertUnprocessable();
    }

    public function test_saving_normalizes_unknown_types_and_keys(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create();

        $this->actingAs($user)->putJson('/offertes/'.$quote->id.'/builder', [
            'blocks' => [
                ['id' => 'b_1', 'type' => 'text', 'data' => ['title' => 'Ok', 'body' => 'Tekst', '_keep' => true]],
                ['id' => 'b_2', 'type' => 'script-injectie', 'data' => []],
            ],
        ])->assertOk();

        $blocks = $quote->refresh()->blocks;
        $this->assertCount(1, $blocks);
        $this->assertArrayNotHasKey('_keep', $blocks[0]['data']);
    }

    public function test_a_db_template_can_be_applied_with_customer_substitution(): void
    {
        $this->seed(QuoteTemplateSeeder::class);

        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Familie Jansen', 'city' => 'Huissen']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        $template = QuoteTemplate::firstWhere('slug', 'badkamer');

        $response = $this->actingAs($user)->postJson('/offertes/'.$quote->id.'/builder/template', [
            'template_id' => $template->id,
        ]);

        $response->assertOk();
        $samenvatting = collect($response->json('blocks'))->first(fn ($block) => ($block['data']['title'] ?? '') === 'Samenvatting');
        $this->assertStringContainsString('Familie Jansen', $samenvatting['data']['body']);
        $this->assertStringContainsString('Huissen', $samenvatting['data']['body']);
    }

    public function test_a_quote_can_be_saved_as_template_and_custom_templates_deleted(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create(['blocks' => [
            ['id' => 'b_1', 'type' => 'text', 'data' => ['title' => 'Eigen blok', 'body' => 'Inhoud']],
        ]]);

        $response = $this->actingAs($user)->postJson('/offertes/'.$quote->id.'/builder/template-opslaan', [
            'name' => 'Badkamer luxe',
        ]);

        $response->assertOk();
        $template = QuoteTemplate::firstWhere('slug', 'badkamer-luxe');
        $this->assertNotNull($template);
        $this->assertFalse($template->is_default);

        $this->actingAs($user)->deleteJson('/offerte-templates/'.$template->id)->assertOk();
        $this->assertDatabaseMissing('quote_templates', ['id' => $template->id]);

        $default = QuoteTemplate::create(['name' => 'X', 'slug' => 'x', 'blocks' => [], 'is_default' => true, 'sort_order' => 1]);
        $this->actingAs($user)->deleteJson('/offerte-templates/'.$default->id)->assertUnprocessable();
    }

    public function test_images_upload_and_are_served_via_the_client_link(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $quote = Quote::factory()->create();

        $response = $this->actingAs($user)->post('/offertes/'.$quote->id.'/builder/upload', [
            'file' => UploadedFile::fake()->image('badkamer.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $url = $response->json('url');
        $this->assertStringContainsString('/offerte/'.$quote->public_token.'/media/', $url);

        // Klant kan de foto zonder inlog zien (zoals de offerte zelf).
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk();

        // Padtrucs worden geweigerd.
        $this->get('/offerte/'.$quote->public_token.'/media/..%2Fgeheim.jpg')->assertNotFound();
    }

    public function test_block_ai_rewrites_a_block(): void
    {
        config(['renovion.ai.api_key' => 'test-key']);

        $this->mock(QuoteBlockAi::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('rewrite')->once()->andReturn([
                'ok' => true,
                'data' => ['title' => 'Samenvatting', 'body' => 'Door Nova geschreven tekst.'],
            ]);
        });

        $user = User::factory()->create();
        $quote = Quote::factory()->create();

        $this->actingAs($user)->postJson('/offertes/'.$quote->id.'/builder/blok-ai', [
            'type' => 'text',
            'data' => ['title' => 'Samenvatting', 'body' => 'Oud'],
            'instruction' => 'Maak het persoonlijker',
        ])->assertOk()->assertJsonPath('data.body', 'Door Nova geschreven tekst.');
    }

    public function test_the_public_page_renders_typed_blocks(): void
    {
        $quote = Quote::factory()->sent()->create([
            'blocks' => [
                ['id' => 'b_1', 'type' => 'hero', 'data' => ['title' => 'Offerte', 'subtitle' => 'Badkamerrenovatie']],
                ['id' => 'b_2', 'type' => 'list', 'data' => ['title' => 'Werkzaamheden', 'intro' => '', 'items' => ['Tegelwerk vloer en wanden']]],
                ['id' => 'b_3', 'type' => 'investment', 'data' => ['title' => 'Investering', 'intro' => 'De investering.', 'note' => '']],
            ],
        ]);

        $this->get('/offerte/'.$quote->public_token)
            ->assertOk()
            ->assertSee('Badkamerrenovatie')
            ->assertSee('Tegelwerk vloer en wanden')
            ->assertSee('Totaal incl. btw');
    }

    public function test_new_quotes_from_calculation_get_typed_builder_blocks(): void
    {
        $this->seed(QuoteTemplateSeeder::class);

        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $calculation = Calculation::factory()->definitief()->create(['customer_id' => $customer->id]);
        $calculation->addLine(['type' => 'arbeid', 'description' => 'Stucwerk', 'quantity' => 10, 'unit_price' => 30]);

        $this->actingAs($user)->post('/calculaties/'.$calculation->id.'/offerte');

        $quote = Quote::firstWhere('calculation_id', $calculation->id);
        $this->assertSame('hero', $quote->blocks[0]['type']);
        $this->assertTrue(collect($quote->blocks)->contains(fn ($block) => $block['type'] === 'investment'));
    }
}
