<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademyProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** De Academy en een rondleiding als eigen schermen in de app. */
class AppAcademyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_academy_shows_the_own_path_tours_rules_and_questions(): void
    {
        $token = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.app.academy'))->assertOk()
            ->assertJsonPath('done', 0)
            ->assertJsonPath('own.title', 'Op de bouwplaats')
            ->assertJsonPath('own.percentage', 0)
            ->assertJsonPath('own.tours.0.id', 'klus')
            ->assertJsonPath('own.tours.0.path', route('academy.show', 'klus', false))
            ->assertJsonPath('rules.0.text', 'Elke klantvraag binnen één werkdag beantwoord');

        $this->assertNotEmpty($response->json('faq'));
        // Een uitvoerder krijgt geen rondleiding over geld.
        $ids = collect($response->json('categories'))->flatMap(fn (array $category) => array_column($category['tours'], 'id'));
        $this->assertNotContains('geld', $ids);
        $this->assertContains('klus', $ids);
    }

    public function test_opening_steps_records_progress_and_finishing_completes_the_tour(): void
    {
        $user = User::factory()->create(['role' => UserRole::Uitvoerder]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.academy.show', 'klus'))->assertOk()
            ->assertJsonPath('id', 'klus')
            ->assertJsonPath('step', 0)
            ->assertJsonPath('last', false)
            ->assertJsonPath('completed_on', null);

        $this->withToken($token)->getJson(route('api.app.academy.show', ['klus', 'stap' => 2]))->assertOk()
            ->assertJsonPath('step', 2);
        $this->assertSame(2, AcademyProgress::firstWhere(['user_id' => $user->id, 'tour' => 'klus'])->step);

        // Een stap voorbij het einde wordt de laatste.
        $last = $this->withToken($token)->getJson(route('api.app.academy.show', ['klus', 'stap' => 99]))->assertOk()
            ->assertJsonPath('last', true);
        $this->assertSame($last->json('count') - 1, $last->json('step'));

        $this->withToken($token)->postJson(route('api.app.academy.complete', 'klus'))->assertOk()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'afgerond'));
        $this->assertTrue(AcademyProgress::firstWhere(['user_id' => $user->id, 'tour' => 'klus'])->isComplete());

        $this->withToken($token)->getJson(route('api.app.academy'))->assertOk()->assertJsonPath('done', 1);
        $this->withToken($token)->getJson(route('api.app.academy.show', 'klus'))->assertOk()
            ->assertJsonPath('completed_on', today()->translatedFormat('j M Y'));
    }

    public function test_a_step_that_points_to_a_screen_gives_its_path(): void
    {
        $token = User::factory()->create(['role' => UserRole::Admin])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.academy.show', 'basis'))->assertOk()
            ->assertJsonPath('current.path', '/');
    }

    public function test_a_tour_the_role_cannot_see_is_not_found(): void
    {
        $token = User::factory()->create(['role' => UserRole::Uitvoerder])->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.app.academy.show', 'geld'))->assertNotFound();
        $this->withToken($token)->postJson(route('api.app.academy.complete', 'geld'))->assertNotFound();
    }
}
