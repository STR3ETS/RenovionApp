<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutchValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('nl');
    }

    public function test_general_rules_use_dutch_and_the_field_name_from_the_screen(): void
    {
        $this->assertSame('E-mailadres is verplicht.', __('validation.required', ['attribute' => __('validation.attributes.email')]));
    }

    public function test_a_lead_without_a_customer_gets_a_readable_message(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('leads.store'), []);

        $response->assertSessionHasErrors(['name' => 'Vul een naam in of kies een bestaande klant.']);
    }
}
