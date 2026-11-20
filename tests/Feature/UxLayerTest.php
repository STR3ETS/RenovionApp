<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * De UX-laag: het vangnet per pagina, de sneltoetsen en de snelkoppelingen.
 */
class UxLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Een scherm dat altijd faalt, om het vangnet te kunnen zien.
        Route::middleware(['web', 'auth'])->get('/kapot-scherm', fn () => throw new RuntimeException('Dit scherm is stuk.'));
    }

    public function test_a_failing_page_keeps_the_menu_and_offers_a_retry(): void
    {
        config(['app.debug' => false]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/kapot-scherm')
            ->assertStatus(500)
            ->assertSee('Deze pagina kon niet laden')
            ->assertSee('Opnieuw proberen')
            // Het menu staat er nog: je kunt gewoon verder.
            ->assertSee('Teamchat')
            ->assertSee(route('dashboard'), false)
            // De foutmelding zelf lekt niet naar het scherm.
            ->assertDontSee('Dit scherm is stuk.');
    }

    public function test_a_missing_or_forbidden_page_explains_itself_inside_the_app(): void
    {
        $admin = User::factory()->create();
        $uitvoerder = User::factory()->uitvoerder()->create();

        $this->actingAs($admin)->get('/dit-bestaat-niet')
            ->assertNotFound()
            ->assertSee('Deze pagina bestaat niet')
            ->assertSee('Naar Vandaag')
            // Opnieuw proberen heeft hier geen zin.
            ->assertDontSee('Opnieuw proberen');

        $this->actingAs($uitvoerder)->get(route('customers.index'))
            ->assertForbidden()
            ->assertSee('Deze pagina is niet voor jouw rol');
    }

    public function test_the_safety_net_stays_out_of_the_way_for_guests_json_and_debugging(): void
    {
        config(['app.debug' => false]);

        // Niet ingelogd: gewoon naar het inlogscherm.
        $this->get('/kapot-scherm')->assertRedirect(route('login'));

        $admin = User::factory()->create();

        // Een verzoek dat JSON verwacht krijgt JSON, geen pagina.
        $this->actingAs($admin)->getJson('/kapot-scherm')
            ->assertStatus(500)
            ->assertDontSee('Deze pagina kon niet laden');

        // Tijdens het ontwikkelen blijft de uitgebreide foutpagina staan. Die toont
        // ook broncode, dus we zoeken naar een tekst die alleen het vangnet
        // zelf samenstelt en die nergens letterlijk in een bestand staat.
        config(['app.debug' => true]);
        $this->actingAs($admin)->get('/kapot-scherm')->assertStatus(500)->assertDontSee('Foutcode '.'500');
    }

    public function test_the_layout_carries_the_shortcuts_and_the_help_dialog(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($sales)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="nav-schermen"', false)
            ->assertSee('id="globaal-zoeken"', false)
            ->assertSee('data-sneltoets-nieuw', false)
            ->assertSee('id="sneltoetsen"', false)
            ->assertSee('G A')
            ->assertSee('data-veelgebruikt', false);
    }

    public function test_go_to_shortcuts_only_point_at_screens_the_role_has(): void
    {
        $sales = Navigation::shortcutsFor(User::factory()->create(['role' => UserRole::Sales]));
        $uitvoerder = Navigation::shortcutsFor(User::factory()->uitvoerder()->create());

        $this->assertSame(route('agenda.index'), $sales['go']['a']);
        $this->assertSame(route('customers.index'), $sales['go']['k']);
        // Sales doet geen facturatie.
        $this->assertArrayNotHasKey('f', $sales['go']);

        $this->assertArrayHasKey('v', $uitvoerder['go']);
        $this->assertArrayNotHasKey('k', $uitvoerder['go']);
        $this->assertArrayNotHasKey('a', $uitvoerder['go']);

        // Precies één scherm is het scherm waar je nu bent, of geen als je er niet in staat.
        $this->assertLessThanOrEqual(1, collect($sales['screens'])->where('active', true)->count());
    }
}
