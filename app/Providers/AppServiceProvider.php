<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AgentApproval;
use App\Models\AgentEvent;
use App\Models\AgentRun;
use App\Models\AgentSkillSetting;
use App\Models\AutomationSetting;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetOption;
use App\Models\Calculation;
use App\Models\Certificate;
use App\Models\ChecklistItem;
use App\Models\Customer;
use App\Models\DeliveryReport;
use App\Models\Document;
use App\Models\Intake;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JobExecution;
use App\Models\JobReport;
use App\Models\JobStep;
use App\Models\Lead;
use App\Models\Photo;
use App\Models\Project;
use App\Models\ProjectDeduction;
use App\Models\ProjectPhase;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\QualityCheck;
use App\Models\QualityReview;
use App\Models\Quote;
use App\Models\QuoteQuestion;
use App\Models\QuoteView;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Models\ScheduleEntry;
use App\Models\ServiceTicket;
use App\Models\Setting;
use App\Models\SnagItem;
use App\Models\Subcontractor;
use App\Models\SubcontractorDocument;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkPackage;
use App\Support\Navigation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'absence' => Absence::class,
            'agent_approval' => AgentApproval::class,
            'agent_event' => AgentEvent::class,
            'agent_run' => AgentRun::class,
            'agent_skill_setting' => AgentSkillSetting::class,
            'automation_setting' => AutomationSetting::class,
            'budget' => Budget::class,
            'budget_line' => BudgetLine::class,
            'budget_option' => BudgetOption::class,
            'calculation' => Calculation::class,
            'certificate' => Certificate::class,
            'checklist_item' => ChecklistItem::class,
            'customer' => Customer::class,
            'delivery_report' => DeliveryReport::class,
            'document' => Document::class,
            'intake' => Intake::class,
            'invoice' => Invoice::class,
            'invoice_line' => InvoiceLine::class,
            'job_execution' => JobExecution::class,
            'job_report' => JobReport::class,
            'job_step' => JobStep::class,
            'lead' => Lead::class,
            'photo' => Photo::class,
            'project' => Project::class,
            'project_deduction' => ProjectDeduction::class,
            'project_phase' => ProjectPhase::class,
            'purchase_invoice' => PurchaseInvoice::class,
            'purchase_order' => PurchaseOrder::class,
            'purchase_order_line' => PurchaseOrderLine::class,
            'quality_check' => QualityCheck::class,
            'quality_review' => QualityReview::class,
            'quote' => Quote::class,
            'quote_question' => QuoteQuestion::class,
            'quote_view' => QuoteView::class,
            'rfq' => Rfq::class,
            'rfq_bid' => RfqBid::class,
            'schedule_entry' => ScheduleEntry::class,
            'service_ticket' => ServiceTicket::class,
            'setting' => Setting::class,
            'snag_item' => SnagItem::class,
            'subcontractor' => Subcontractor::class,
            'subcontractor_document' => SubcontractorDocument::class,
            'task' => Task::class,
            'user' => User::class,
            'work_order' => WorkOrder::class,
            'work_package' => WorkPackage::class,
        ]);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip()
            ));
        });

        // Briefing §15: uitvoerders zien alleen hun eigen projecten, taken en planning.
        Gate::define('manage-crm', fn (User $user) => ! in_array($user->role, [UserRole::Uitvoerder, UserRole::Klant, UserRole::Onderaannemer], true));

        // De interne omgeving is niet voor klanten en onderaannemers - die krijgen een eigen portaal.
        Gate::define('internal', fn (User $user) => ! $user->role->isExternal());

        // Inrichting van het systeem (team, rechten, instellingen) is voor directie en superadmin.
        Gate::define('manage-team', fn (User $user) => $user->role->isManagement());

        // Per module: de rechtenmatrix uit Navigation bepaalt wie welk scherm ziet.
        Gate::define('module', fn (User $user, string $module) => Navigation::allows($user->role, $module));
    }
}
