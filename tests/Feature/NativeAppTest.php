<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ChatChannel;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NativeAppTest extends TestCase
{
    use RefreshDatabase;

    private const APP = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/131 Mobile Safari/537.36 RenovionApp';

    private const PHONE_BROWSER = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/131 Mobile Safari/537.36';

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_the_app_gets_the_renovion_os_shell(): void
    {
        $response = $this->actingAs($this->admin())
            ->withHeader('User-Agent', self::APP)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('class="native-app"', false)
            ->assertSee('app-bnav', false)
            ->assertSee('outfit', false)
            ->assertSee('Inbox')
            ->assertDontSee('Bekijk alle inzichten');
    }

    public function test_a_phone_browser_keeps_the_website(): void
    {
        $response = $this->actingAs($this->admin())
            ->withHeader('User-Agent', self::PHONE_BROWSER)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertDontSee('native-app', false)
            ->assertDontSee('app-bnav', false)
            ->assertDontSee('outfit', false)
            ->assertSee('inter-tight', false);
    }

    public function test_an_executor_gets_chat_instead_of_inbox_in_the_app(): void
    {
        $executor = User::factory()->create(['role' => UserRole::Uitvoerder]);

        $this->actingAs($executor)
            ->withHeader('User-Agent', self::APP)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Teamchat')
            ->assertDontSee(route('attention.index'), false);
    }

    public function test_opening_the_notification_list_marks_everything_as_read(): void
    {
        $user = $this->admin();
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => TaskAssigned::class,
            'notifiable_type' => 'user',
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Nieuwe taak', 'body' => 'Tegels bestellen', 'url' => '/taken', 'icon' => 'clipboard-check'],
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::APP)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nieuwe taak');

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_every_section_of_the_app_opens(): void
    {
        $this->actingAs($this->admin())->withHeader('User-Agent', self::APP);

        foreach (['attention.index', 'leads.index', 'customers.index', 'quotes.index', 'calculations.index', 'price-items.index', 'projects.index', 'planning.index', 'tasks.index', 'chat.index', 'automations.index', 'team.index', 'notification-settings.edit'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_a_chat_in_the_app_fits_a_narrow_phone_and_keeps_send_free(): void
    {
        $channel = ChatChannel::create(['type' => 'team', 'name' => 'Algemeen']);
        $this->actingAs($this->admin())->withHeader('User-Agent', self::APP);

        // Zonder vaste kolom werd de kolom zo breed als de invoerbalk minimaal is (427 px op een 384 px-scherm).
        $this->get(route('chat.show', $channel))->assertOk()
            ->assertSee('class="grid grid-cols-1 gap-4 lg:grid-cols-3"', false)
            ->assertSee('class="min-w-0 lg:col-span-2"', false)
            ->assertDontSee('class="app-nova', false);

        $this->get(route('dashboard'))->assertOk()->assertSee('class="app-nova', false);
    }
}
