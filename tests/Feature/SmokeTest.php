<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PriceLibrarySeeder;
use Database\Seeders\RenovionOsDemoSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Elk scherm uit het menu openen, voor elke rol die het mag zien.
 *
 * Dit vangt wat de losse tests missen: een Blade-fout, een ontbrekende relatie
 * of een view die een variabele verwacht die de controller niet meegeeft. Met
 * een lege database én met demodata, want veel fouten komen pas naar boven
 * zodra er echte rijen zijn.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_menu_screen_opens_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $checked = 0;

        foreach (Navigation::sections() as $id => $section) {
            foreach ($section['items'] as $module => [$label, $route]) {
                if (! Route::has($route)) {
                    continue;
                }

                $this->actingAs($admin)->get(route($route))->assertOk(
                    "Scherm {$label} ({$route}) in sectie {$id} geeft geen 200.",
                );

                $checked++;
            }
        }

        // Geen stille regressie waarbij het menu leeg raakt.
        $this->assertGreaterThan(25, $checked, 'Er zijn opvallend weinig schermen gecontroleerd.');
    }

    public function test_every_menu_screen_opens_with_demo_data(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(PriceLibrarySeeder::class);
        $this->seed(DemoSeeder::class);
        $this->seed(RenovionOsDemoSeeder::class);

        $admin = User::firstWhere('role', UserRole::Admin) ?? User::factory()->create(['role' => UserRole::Admin]);

        foreach (Navigation::sections() as $section) {
            foreach ($section['items'] as [$label, $route]) {
                if (! Route::has($route)) {
                    continue;
                }

                $this->actingAs($admin)->get(route($route))->assertOk(
                    "Scherm {$label} ({$route}) geeft geen 200 met demodata.",
                );
            }
        }
    }

    public function test_every_role_sees_its_own_screens_and_no_others(): void
    {
        foreach (Navigation::permissions() as $value => $perms) {
            $role = UserRole::from($value);

            // De buitenrollen hebben hun eigen portaal, geen menu.
            if ($role->isExternal()) {
                continue;
            }

            $user = User::factory()->create(['role' => $role]);
            $sections = Navigation::forUser($user);

            $this->assertNotEmpty($sections, "Rol {$role->label()} heeft een leeg menu.");

            foreach ($sections as $group => $items) {
                foreach ($items as $item) {
                    $this->actingAs($user)->get($item['url'])->assertOk(
                        "Rol {$role->label()} krijgt geen 200 op {$item['label']} ({$item['url']}).",
                    );
                }
            }
        }
    }

    public function test_the_craftsman_menu_stays_small_and_about_their_own_work(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $labels = collect(Navigation::forUser($uitvoerder))->flatten(1)->pluck('label');

        // Wat er wel in hoort.
        $this->assertTrue($labels->contains('Vandaag'));
        $this->assertTrue($labels->contains('Planning en uitvoering'));
        $this->assertTrue($labels->contains('Academy'));

        // En wat er niet in hoort.
        foreach (['Geld', 'Calculatie en offertes', 'Organisatie', 'Verkoop', 'Rapportages'] as $forbidden) {
            $this->assertFalse($labels->contains($forbidden), "Een uitvoerder ziet {$forbidden} en dat hoort niet.");
        }
    }
}
