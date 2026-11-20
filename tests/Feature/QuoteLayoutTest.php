<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use App\Support\QuoteBlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De offerte in de nieuwe opzet: genummerde hoofdstukken, vaste merkblokken
 * en de persoonlijke brief.
 */
class QuoteLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fixed_brand_block_can_move_but_cannot_be_removed(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create(['blocks' => [
            ['id' => 'b_kop', 'type' => 'hero', 'data' => ['title' => 'Offerte', 'subtitle' => 'Badkamer']],
            ['id' => 'b_merk', 'type' => 'support', 'data' => ['title' => 'Garantie en nazorg', 'intro' => '', 'items' => [], '_fixed' => true]],
            ['id' => 'b_tekst', 'type' => 'text', 'data' => ['title' => 'Aanpak', 'body' => 'Zo doen we het.']],
        ]]);

        // Verschuiven mag: het merkblok gaat naar achteren.
        $this->actingAs($user)->putJson(route('quotes.builder.update', $quote), ['blocks' => [
            $quote->blocks[0], $quote->blocks[2], $quote->blocks[1],
        ]])->assertOk()->assertJsonMissing(['notice' => 'Een vast merkblok kan niet weg en is teruggezet.']);

        $this->assertSame(['b_kop', 'b_tekst', 'b_merk'], array_column($quote->fresh()->blocks, 'id'));
        $this->assertTrue($quote->fresh()->blocks[2]['data']['_fixed']);

        // Weghalen niet: de server zet hem terug en zegt dat erbij.
        $this->actingAs($user)->putJson(route('quotes.builder.update', $quote), ['blocks' => [
            $quote->blocks[0], $quote->blocks[2],
        ]])->assertOk()->assertJsonPath('notice', 'Een vast merkblok kan niet weg en is teruggezet.');

        $this->assertContains('b_merk', array_column($quote->fresh()->blocks, 'id'));
        $this->assertCount(3, $quote->fresh()->blocks);
    }

    public function test_a_block_that_is_no_longer_fixed_can_be_removed(): void
    {
        $before = [['id' => 'b_1', 'type' => 'text', 'data' => ['title' => 'A', '_fixed' => true]]];

        // Het merkblok is in de bouwer losgemaakt en daarna pas weggehaald: de opgeslagen stand telt.
        $this->assertCount(1, QuoteBlockRegistry::keepFixed($before, []));
        $this->assertCount(0, QuoteBlockRegistry::keepFixed([['id' => 'b_1', 'type' => 'text', 'data' => ['title' => 'A']]], []));
    }

    public function test_chapters_are_numbered_and_the_cover_letter_and_images_are_not(): void
    {
        $this->assertFalse(QuoteBlockRegistry::isNumbered('hero'));
        $this->assertFalse(QuoteBlockRegistry::isNumbered('letter'));
        $this->assertFalse(QuoteBlockRegistry::isNumbered('divider'));
        $this->assertTrue(QuoteBlockRegistry::isNumbered('text'));
        $this->assertTrue(QuoteBlockRegistry::isNumbered('investment'));

        $quote = Quote::factory()->sent()->create(['blocks' => [
            ['id' => 'b_1', 'type' => 'hero', 'data' => ['title' => 'Offerte', 'subtitle' => 'Badkamer', 'tagline' => 'Velperweg 12 in Arnhem']],
            ['id' => 'b_2', 'type' => 'text', 'data' => ['title' => 'Aanpak', 'body' => 'Zo doen we het.']],
            // Zonder inhoud rendert dit blok niets, dus ook geen hoofdstuk.
            ['id' => 'b_3', 'type' => 'text', 'data' => ['title' => '', 'body' => '']],
            ['id' => 'b_4', 'type' => 'text', 'data' => ['title' => 'Planning', 'body' => 'Drie weken.']],
        ]]);

        $html = $this->get('/offerte/'.$quote->public_token)->assertOk()->getContent();

        $this->assertStringContainsString('quote-doc paper @container', $html);
        $this->assertSame(2, substr_count($html, 'class="quote-chapter"'));
        // De ondertitel van de kop staat erbij.
        $this->assertStringContainsString('Velperweg 12 in Arnhem', $html);
    }

    public function test_the_personal_letter_shows_signature_and_slogan(): void
    {
        $customer = Customer::factory()->create(['name' => 'Familie Jansen']);
        $quote = Quote::factory()->sent()->create(['customer_id' => $customer->id, 'blocks' => [
            ['id' => 'b_1', 'type' => 'hero', 'data' => ['title' => 'Offerte', 'subtitle' => 'Badkamer']],
            ['id' => 'b_2', 'type' => 'letter', 'data' => [
                'greeting' => 'Beste familie Jansen,', 'body' => 'Dank voor het prettige gesprek.', 'closing' => 'Met vriendelijke groet,',
                'signature' => 'https://voorbeeld.test/handtekening.png', 'signer' => 'Imad', 'role' => 'Directeur', 'slogan' => 'Vakwerk waar u op kunt bouwen',
            ]],
        ]]);

        $this->get('/offerte/'.$quote->public_token)
            ->assertOk()
            ->assertSee('Beste familie Jansen,')
            ->assertSee('Dank voor het prettige gesprek.')
            ->assertSee('https://voorbeeld.test/handtekening.png', false)
            ->assertSee('Handtekening van Imad', false)
            ->assertSee('Vakwerk waar u op kunt bouwen');
    }

    public function test_a_new_block_in_the_builder_starts_with_this_customers_name(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Familie Jansen']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($user)->get(route('quotes.builder', $quote))
            ->assertOk()
            ->assertSee('Persoonlijke brief')
            ->assertSee('Beste Familie Jansen,')
            ->assertSee('Eigen blok')
            ->assertSee('Blok met Nova')
            ->assertDontSee('Beste {klant},');
    }

    public function test_internal_flags_survive_saving_but_unknown_ones_do_not(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create();

        $this->actingAs($user)->putJson(route('quotes.builder.update', $quote), ['blocks' => [
            ['id' => 'b_1', 'type' => 'letter', 'data' => ['body' => 'Tekst', '_fixed' => true, '_keep' => true, '_anders' => 'weg']],
        ]])->assertOk();

        $data = $quote->fresh()->blocks[0]['data'];
        $this->assertTrue($data['_fixed']);
        $this->assertTrue($data['_keep']);
        $this->assertArrayNotHasKey('_anders', $data);
    }
}
