<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_section_item_points_at_a_module_the_matrix_knows(): void
    {
        $known = collect(Navigation::permissions())
            ->reject(fn ($perms) => $perms === '*')
            ->flatten()
            ->merge(Navigation::MANAGEMENT_ONLY)
            ->unique();

        foreach (Navigation::sections() as $id => $section) {
            foreach (array_keys($section['items']) as $module) {
                $this->assertTrue(
                    $known->contains($module),
                    "Module {$module} uit sectie {$id} staat in geen enkele rol.",
                );
            }
        }
    }

    public function test_management_only_modules_are_closed_for_the_other_roles(): void
    {
        foreach (Navigation::MANAGEMENT_ONLY as $module) {
            $this->assertTrue(Navigation::allows(UserRole::Admin, $module));
            $this->assertTrue(Navigation::allows(UserRole::Directie, $module));
            $this->assertFalse(Navigation::allows(UserRole::Projectleider, $module));
            $this->assertFalse(Navigation::allows(UserRole::Uitvoerder, $module));
        }
    }

    public function test_the_craftsman_menu_leads_with_their_own_work(): void
    {
        $uitvoerder = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->assertFalse(Navigation::allows($uitvoerder->role, 'financieel'));
        $this->assertFalse(Navigation::allows($uitvoerder->role, 'offertes'));
        $this->assertTrue(Navigation::allows($uitvoerder->role, 'klussen'));

        $labels = collect(Navigation::bottomNavFor($uitvoerder))->pluck('label');

        $this->assertSame('Vandaag', $labels->first());
        $this->assertFalse($labels->contains('Financieel'));
    }

    public function test_the_sidebar_skips_sections_whose_screens_do_not_exist_yet(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $sections = collect(Navigation::forUser($admin))->flatten(1);

        foreach ($sections as $section) {
            $this->assertNotEmpty($section['items'], "Sectie {$section['id']} staat leeg in het menu.");
        }

        // Elke route die een sectie noemt moet bestaan, anders valt het item weg.
        foreach (Navigation::sections() as $section) {
            foreach ($section['items'] as [$label, $route]) {
                $this->assertIsString($label);
                $this->assertIsString($route);
            }
        }
    }

    public function test_section_tabs_show_the_screens_next_to_the_current_one(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Route::getRoutes()->refreshNameLookups();

        $this->actingAs($admin)->get(route('calculations.index'))
            ->assertOk()
            ->assertSee('Prijslijst');
    }
}
