<?php

namespace Tests\Feature;

use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use App\Support\QuoteHeroStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_renders_colour_photo_and_overlay_for_the_customer(): void
    {
        $quote = Quote::factory()->sent()->create(['blocks' => [
            ['id' => 'b_hero', 'type' => 'hero', 'data' => [
                'title' => 'Offerte', 'subtitle' => 'Badkamer',
                'bg' => '#14342B', 'image' => '/offerte/abc/media/foto.jpg', 'overlay' => 40,
            ]],
        ]]);

        $html = $this->get('/offerte/'.$quote->public_token)->assertOk()->getContent();

        $this->assertStringContainsString('background-color: #14342b', $html);
        $this->assertStringContainsString('rgba(20,52,43,0.40)', $html);
        $this->assertStringContainsString('url(&quot;/offerte/abc/media/foto.jpg&quot;)', $html);
        $this->assertStringContainsString('--hero-fg: #ffffff', $html);
    }

    public function test_light_background_switches_to_dark_text_and_unsafe_values_are_ignored(): void
    {
        $light = QuoteHeroStyle::resolve(['bg' => '#f3eee6']);
        $this->assertSame('dark', $light['theme']);
        $this->assertStringContainsString('--hero-fg: #070724', $light['style']);

        $forced = QuoteHeroStyle::resolve(['bg' => '#f3eee6', 'text' => 'light']);
        $this->assertSame('light', $forced['theme']);

        $unsafe = QuoteHeroStyle::resolve(['bg' => 'red; background: url(x)', 'image' => 'javascript:alert(1)', 'overlay' => 400]);
        $this->assertSame(QuoteHeroStyle::DEFAULT_BG, $unsafe['bg']);
        $this->assertNull($unsafe['image']);
        $this->assertSame(95, $unsafe['overlay']);
        $this->assertStringNotContainsString('background-image', $unsafe['style']);

        // Op een oranje achtergrond zou het oranje accent wegvallen: dan de tekstkleur.
        $orange = QuoteHeroStyle::resolve(['bg' => '#f85b0b']);
        $this->assertStringContainsString('--hero-accent: #ffffff', $orange['style']);
    }

    public function test_default_hero_keeps_the_navy_look(): void
    {
        $quote = Quote::factory()->sent()->create(['blocks' => null]);

        $html = $this->get('/offerte/'.$quote->public_token)->assertOk()->getContent();

        $this->assertStringContainsString('background-color: #070724', $html);
        $this->assertStringNotContainsString('background-image', $html);
    }

    public function test_builder_saves_background_settings_and_templates_drop_the_photo(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create();

        $this->actingAs($user)->putJson('/offertes/'.$quote->id.'/builder', ['blocks' => [
            ['id' => 'b_hero', 'type' => 'hero', 'data' => ['title' => 'Offerte', 'subtitle' => 'Keuken', 'bg' => '#111111', 'image' => '/offerte/x/media/a.jpg', 'overlay' => 60, 'text' => 'light']],
        ]])->assertOk();

        $hero = $quote->refresh()->blocks[0]['data'];
        $this->assertSame('#111111', $hero['bg']);
        $this->assertSame(60, $hero['overlay']);
        $this->assertSame('/offerte/x/media/a.jpg', $hero['image']);

        $this->actingAs($user)->postJson('/offertes/'.$quote->id.'/builder/template-opslaan', ['name' => 'Donkere kop'])->assertOk();

        $template = QuoteTemplate::firstWhere('name', 'Donkere kop');
        $this->assertSame('#111111', $template->blocks[0]['data']['bg']);
        $this->assertArrayNotHasKey('image', $template->blocks[0]['data'], 'een foto hoort bij één offerte, niet bij de template');
    }
}
