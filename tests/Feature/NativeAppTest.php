<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
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
            ->assertSee('inter-tight', false)
            ->assertSee('Bekijk alle inzichten');
    }

    public function test_only_the_app_opens_cards_by_tapping_the_card(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['customer_id' => Customer::factory()->create()->id]);

        $this->actingAs($admin)->withHeader('User-Agent', self::APP)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('fasen-wisselen', false);

        $this->actingAs($admin)->withHeader('User-Agent', self::PHONE_BROWSER)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('fasen-wisselen', false);
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
}
