<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TestPush;
use App\Support\AppMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Instellingen in de app (api/app/instellingen): de app stuurt wijzigingen in één keer bij het verlaten. */
class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_gets_every_setting_in_one_answer(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('telefoon')->plainTextToken)->getJson(route('api.app.settings'))
            ->assertOk()
            ->assertJsonPath('theme', 'licht')
            ->assertJsonPath('motion', true)
            ->assertJsonPath('devices', 0)
            ->assertJsonPath('notifications.0.type', 'chat_mention')
            ->assertJsonPath('notifications.0.enabled', true)
            ->assertJsonCount(3, 'notifications');
    }

    public function test_several_changes_are_saved_in_one_request_and_only_what_is_sent_changes(): void
    {
        $user = User::factory()->create(['motion_enabled' => true]);
        $token = $user->createToken('telefoon')->plainTextToken;

        $this->withToken($token)->patchJson(route('api.app.settings.update'), [
            'theme' => 'donker',
            'notifications' => ['chat_mention' => false, 'task_assigned' => false],
        ])->assertOk()
            ->assertJsonPath('theme', 'donker')
            ->assertJsonPath('motion', true)
            ->assertJsonPath('notifications.0.enabled', false)
            ->assertJsonPath('notifications.1.enabled', false)
            ->assertJsonPath('notifications.2.enabled', true);

        $user->refresh();
        $this->assertSame('donker', $user->app_theme);
        $this->assertSame(['chat_mention' => false, 'task_assigned' => false], $user->notification_preferences);

        // Weer aan = niet meer bewaard (nieuwe soorten staan vanzelf aan); thema blijft staan.
        $this->withToken($token)->patchJson(route('api.app.settings.update'), [
            'notifications' => ['chat_mention' => true],
            'motion' => false,
        ])->assertOk()->assertJsonPath('theme', 'donker');

        $user->refresh();
        $this->assertSame(['task_assigned' => false], $user->notification_preferences);
        $this->assertFalse($user->motion_enabled);
    }

    public function test_it_refuses_unknown_values_and_kinds_the_user_cannot_get(): void
    {
        $uitvoerder = User::factory()->uitvoerder()->create();
        $token = $uitvoerder->createToken('telefoon')->plainTextToken;

        $this->withToken($token)->patchJson(route('api.app.settings.update'), ['theme' => 'paars'])->assertUnprocessable();
        // Ondertekende offertes gaan niet naar uitvoerders, dus die schakelaar bestaat voor hem niet.
        $this->withToken($token)->patchJson(route('api.app.settings.update'), ['notifications' => ['quote_signed' => false]])->assertUnprocessable();

        $this->assertNull($uitvoerder->fresh()->notification_preferences);
    }

    public function test_a_test_notification_needs_a_linked_phone(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = $user->createToken('telefoon')->plainTextToken;

        $this->withToken($token)->postJson(route('api.app.settings.test'))->assertUnprocessable();
        Notification::assertNothingSent();

        $user->deviceTokens()->create(['token' => 'telefoon-1', 'platform' => 'android']);
        $this->withToken($token)->postJson(route('api.app.settings.test'))->assertOk();
        Notification::assertSentTo($user, TestPush::class);
    }

    public function test_only_an_app_that_knows_the_screen_opens_settings_in_the_app(): void
    {
        $this->app->instance('request', Request::create('/', 'GET', server: ['HTTP_X_APP_BUILD' => '5']));
        $this->assertSame('instellingen', AppMenu::screen('notification-settings.edit'));

        $this->app->instance('request', Request::create('/', 'GET', server: ['HTTP_X_APP_BUILD' => '4']));
        $this->assertNull(AppMenu::screen('notification-settings.edit'));
    }
}
