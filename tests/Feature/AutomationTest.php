<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\QuoteStatus;
use App\Enums\ScheduleEntryType;
use App\Mail\RequestPhoneNumberMail;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_follow_up_creates_task_and_marks_quote(): void
    {
        $quote = Quote::factory()->create([
            'status' => QuoteStatus::Verstuurd,
            'sent_at' => now()->subDays(4),
        ]);

        $this->artisan('automations:run')->assertSuccessful();

        $quote->refresh();
        $this->assertSame(QuoteStatus::Opvolgen, $quote->status);
        $this->assertDatabaseHas('tasks', [
            'customer_id' => $quote->customer_id,
            'source' => 'automation',
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'quote', 'source' => 'automation']);
    }

    public function test_automations_are_idempotent(): void
    {
        Quote::factory()->create([
            'status' => QuoteStatus::Verstuurd,
            'sent_at' => now()->subDays(4),
        ]);

        $this->artisan('automations:run')->assertSuccessful();
        $this->artisan('automations:run')->assertSuccessful();

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_missing_phone_sends_email_when_address_is_known(): void
    {
        Mail::fake();

        $customer = Customer::factory()->withoutPhone()->create(['email' => 'klant@example.nl']);
        $lead = Lead::factory()->create(['customer_id' => $customer->id]);

        $this->artisan('automations:run')->assertSuccessful();

        Mail::assertSent(RequestPhoneNumberMail::class, fn (RequestPhoneNumberMail $mail) => $mail->hasTo('klant@example.nl'));
        $this->assertNotNull($lead->refresh()->phone_requested_at);
        $this->assertDatabaseHas('timeline_events', [
            'customer_id' => $customer->id,
            'type' => 'email',
            'source' => 'automation',
        ]);
    }

    public function test_missing_phone_without_email_creates_task(): void
    {
        Mail::fake();

        $customer = Customer::factory()->withoutPhone()->create(['email' => null]);
        Lead::factory()->create(['customer_id' => $customer->id]);

        $this->artisan('automations:run')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('tasks', [
            'customer_id' => $customer->id,
            'priority' => 'hoog',
            'source' => 'automation',
        ]);
    }

    public function test_complete_new_lead_gets_a_sales_task(): void
    {
        $customer = Customer::factory()->create(['phone' => '06-12345678']);
        $lead = Lead::factory()->create(['customer_id' => $customer->id, 'status' => LeadStatus::Nieuw]);

        $this->artisan('automations:run')->assertSuccessful();

        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'source' => 'automation',
        ]);
    }

    public function test_deposit_warning_before_project_start(): void
    {
        $project = Project::factory()->create([
            'deposit_amount' => 5000,
            'deposit_received_at' => null,
            'start_date' => today()->addDays(2)->toDateString(),
        ]);

        $this->artisan('automations:run')->assertSuccessful();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'priority' => 'hoog',
            'source' => 'automation',
        ]);
        $this->assertDatabaseHas('timeline_events', [
            'customer_id' => $project->customer_id,
            'type' => 'betaling',
            'source' => 'automation',
        ]);
    }

    public function test_intake_reminder_a_day_ahead(): void
    {
        $user = User::factory()->create();
        ScheduleEntry::factory()->create([
            'user_id' => $user->id,
            'type' => ScheduleEntryType::Intake,
            'date' => today()->addDay()->toDateString(),
        ]);

        $this->artisan('automations:run')->assertSuccessful();

        $this->assertDatabaseHas('tasks', [
            'owner_id' => $user->id,
            'source' => 'automation',
        ]);
    }
}
