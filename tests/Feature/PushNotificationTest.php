<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ChatChannel;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Nep-serviceaccount met een echte RSA-sleutel, zodat het ondertekenen echt gebeurt.
        // Minimale openssl.cnf meegeven: PHP op Windows heeft er anders geen.
        $opensslConfig = tempnam(sys_get_temp_dir(), 'cnf');
        file_put_contents($opensslConfig, "[ req ]\ndistinguished_name = dn\n[ dn ]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $opensslConfig];
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $privateKey, null, $options);
        @unlink($opensslConfig);
        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'renovion-test',
            'client_email' => 'push@renovion-test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.firebase.credentials' => $this->credentialsPath]);
        Cache::forget('firebase-access-token');
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);

        parent::tearDown();
    }

    private function mentionPeter(): array
    {
        $imad = User::factory()->create(['name' => 'Imad', 'role' => UserRole::Admin]);
        $peter = User::factory()->create(['name' => 'Peter', 'role' => UserRole::Uitvoerder]);
        $kanaal = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);

        return [$imad, $peter, $kanaal];
    }

    public function test_app_registers_its_token_for_the_logged_in_user(): void
    {
        [$imad, $peter] = $this->mentionPeter();

        $this->actingAs($peter)->postJson(route('devices.store'), ['token' => 'tok-1', 'platform' => 'android'])->assertOk();
        // Zelfde telefoon, andere gebruiker: het token verhuist mee.
        $this->actingAs($imad)->postJson(route('devices.store'), ['token' => 'tok-1', 'platform' => 'android'])->assertOk();
        $this->actingAs($imad)->postJson(route('devices.store'), ['token' => 'tok-2', 'platform' => 'windows'])->assertUnprocessable();

        $this->assertSame(1, DeviceToken::count());
        $this->assertSame($imad->id, DeviceToken::first()->user_id);
    }

    public function test_mention_is_pushed_to_the_mentioned_users_phone(): void
    {
        [$imad, $peter, $kanaal] = $this->mentionPeter();
        $peter->deviceTokens()->create(['token' => 'peter-telefoon', 'platform' => 'android']);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/renovion-test/messages/1']),
        ]);

        $this->actingAs($imad)->postJson(route('chat.messages.store', $kanaal), ['body' => '@Peter kitranden?'])->assertOk();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/renovion-test/messages:send'
            && $request->hasHeader('Authorization', 'Bearer google-token')
            && $request['message']['token'] === 'peter-telefoon'
            && $request['message']['notification']['title'] === 'Imad noemde je in Algemeen'
            && $request['message']['data']['url'] === '/chat/'.$kanaal->id
            && ! isset($request['message']['data']['test']));
        $this->assertSame(1, $peter->deviceTokens()->count());
    }

    public function test_dead_tokens_are_removed(): void
    {
        [$imad, $peter, $kanaal] = $this->mentionPeter();
        $peter->deviceTokens()->create(['token' => 'oude-telefoon', 'platform' => 'android']);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED', 'message' => 'Requested entity was not found.']], 404),
        ]);

        $this->actingAs($imad)->postJson(route('chat.messages.store', $kanaal), ['body' => '@Peter'])->assertOk();

        $this->assertSame(0, $peter->deviceTokens()->count());
    }

    public function test_tokens_from_another_firebase_project_are_removed(): void
    {
        [$imad, $peter, $kanaal] = $this->mentionPeter();
        $peter->deviceTokens()->create(['token' => 'token-van-testproject', 'platform' => 'android']);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token']),
            'fcm.googleapis.com/*' => Http::response(['error' => [
                'code' => 403,
                'message' => 'SenderId mismatch',
                'status' => 'PERMISSION_DENIED',
                'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'SENDER_ID_MISMATCH']],
            ]], 403),
        ]);

        $this->actingAs($imad)->postJson(route('chat.messages.store', $kanaal), ['body' => '@Peter'])->assertOk();

        $this->assertSame(0, $peter->deviceTokens()->count());
    }

    public function test_firebase_outage_never_breaks_the_chat(): void
    {
        [$imad, $peter, $kanaal] = $this->mentionPeter();
        $peter->deviceTokens()->create(['token' => 'peter-telefoon', 'platform' => 'android']);
        Http::fake(['*' => Http::response('storing', 503)]);

        $this->actingAs($imad)->postJson(route('chat.messages.store', $kanaal), ['body' => '@Peter'])->assertOk();

        $this->assertCount(1, $peter->notifications);
    }

    public function test_without_firebase_credentials_nothing_is_sent(): void
    {
        config(['services.firebase.credentials' => null]);
        [$imad, $peter, $kanaal] = $this->mentionPeter();
        $peter->deviceTokens()->create(['token' => 'peter-telefoon', 'platform' => 'android']);
        Http::fake();

        $this->actingAs($imad)->postJson(route('chat.messages.store', $kanaal), ['body' => '@Peter'])->assertOk();

        Http::assertNothingSent();
        $this->assertCount(1, $peter->notifications);
    }

    public function test_logging_out_in_the_app_removes_that_phones_token(): void
    {
        [, $peter] = $this->mentionPeter();
        $peter->deviceTokens()->create(['token' => 'peter-telefoon', 'platform' => 'android']);
        $peter->deviceTokens()->create(['token' => 'peter-tablet', 'platform' => 'android']);

        $this->actingAs($peter)->post(route('logout'), ['push_token' => 'peter-telefoon'])->assertRedirect(route('login'));

        $this->assertSame(['peter-tablet'], $peter->deviceTokens()->pluck('token')->all());
    }
}
