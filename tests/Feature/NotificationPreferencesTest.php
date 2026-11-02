<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\ChatChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $opensslConfig = tempnam(sys_get_temp_dir(), 'cnf');
        file_put_contents($opensslConfig, "[ req ]\ndistinguished_name = dn\n[ dn ]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $opensslConfig];
        openssl_pkey_export(openssl_pkey_new($options), $privateKey, null, $options);
        @unlink($opensslConfig);

        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->credentialsPath, json_encode([
            'project_id' => 'renovion-test',
            'client_email' => 'push@renovion-test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.firebase.credentials' => $this->credentialsPath]);
        Cache::forget('firebase-access-token');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);

        parent::tearDown();
    }

    public function test_everything_is_on_by_default(): void
    {
        $peter = User::factory()->create(['role' => UserRole::Uitvoerder]);

        foreach (NotificationType::cases() as $type) {
            $this->assertTrue($peter->wantsPush($type));
        }
    }

    public function test_user_can_switch_a_notification_type_off(): void
    {
        $imad = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($imad)->get(route('notification-settings.edit'))->assertOk()
            ->assertSee('Iemand noemt je in de chat')
            ->assertSee('Een klant tekent een offerte');

        // Elke schakelaar slaat direct op: taken en offertes uit, de chat blijft aan.
        $this->actingAs($imad)->patchJson(route('notification-settings.update'), ['type' => 'task_assigned', 'enabled' => false])
            ->assertOk()->assertJson(['type' => 'task_assigned', 'enabled' => false]);
        $this->actingAs($imad)->patchJson(route('notification-settings.update'), ['type' => 'quote_signed', 'enabled' => false])->assertOk();

        $imad->refresh();
        $this->assertTrue($imad->wantsPush(NotificationType::ChatMention));
        $this->assertFalse($imad->wantsPush(NotificationType::TaskAssigned));
        $this->assertFalse($imad->wantsPush(NotificationType::QuoteSigned));
        $this->assertSame(['task_assigned' => false, 'quote_signed' => false], $imad->notification_preferences);

        // Weer aanzetten haalt hem uit de lijst; alles aan = leeg.
        $this->actingAs($imad)->patchJson(route('notification-settings.update'), ['type' => 'task_assigned', 'enabled' => true])->assertOk();
        $this->actingAs($imad)->patchJson(route('notification-settings.update'), ['type' => 'quote_signed', 'enabled' => true])->assertOk();
        $this->assertNull($imad->fresh()->notification_preferences);
    }

    public function test_uitvoerder_cannot_set_the_quote_notification_or_unknown_types(): void
    {
        $peter = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($peter)->patchJson(route('notification-settings.update'), ['type' => 'quote_signed', 'enabled' => false])->assertUnprocessable();
        $this->actingAs($peter)->patchJson(route('notification-settings.update'), ['type' => 'bestaat_niet', 'enabled' => false])->assertUnprocessable();

        $this->assertNull($peter->fresh()->notification_preferences);
    }

    public function test_quote_setting_is_not_offered_to_uitvoerders(): void
    {
        $peter = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($peter)->get(route('notification-settings.edit'))->assertOk()
            ->assertSee('Iemand geeft je een taak')
            ->assertDontSee('Een klant tekent een offerte');
    }

    public function test_switched_off_type_is_not_pushed_but_still_recorded(): void
    {
        $imad = User::factory()->create(['name' => 'Imad', 'role' => UserRole::Admin]);
        $peter = User::factory()->create(['name' => 'Peter', 'role' => UserRole::Uitvoerder]);
        $peter->deviceTokens()->create(['token' => 'peter-telefoon', 'platform' => 'android']);
        $peter->forceFill(['notification_preferences' => ['chat_mention' => false]])->save();
        $kanaal = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        $this->actingAs($imad)->postJson(route('chat.messages.store', $kanaal), ['body' => '@Peter'])->assertOk();
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com'));

        // Een taak staat nog wel aan.
        $this->actingAs($imad)->post(route('tasks.store'), ['title' => 'Kitwerk', 'owner_id' => $peter->id]);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com')
            && $request['message']['notification']['title'] === 'Imad gaf je een taak');

        $this->assertCount(2, $peter->notifications);
    }

    public function test_test_notification_goes_to_own_phone(): void
    {
        $peter = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($peter)->post(route('notification-settings.test'))->assertSessionHas('error');
        Http::assertNothingSent();

        $peter->deviceTokens()->create(['token' => 'peter-telefoon', 'platform' => 'android']);
        $this->actingAs($peter)->post(route('notification-settings.test'))->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com')
            && $request['message']['token'] === 'peter-telefoon'
            && $request['message']['notification']['title'] === 'Testmelding van Renovion'
            && $request['message']['data']['test'] === '1');
        $this->assertCount(0, $peter->notifications);
    }
}
