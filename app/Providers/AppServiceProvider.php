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
use App\Models\MailTemplate;
use App\Models\Meeting;
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
use App\Services\Transcription\AssemblyAiTranscriber;
use App\Services\Transcription\DemoTranscriber;
use App\Services\Transcription\Transcriber;
use App\Services\Transcription\WhisperTranscriber;
use App\Support\NativeApp;
use App\Support\Navigation;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Gespreksverslagen: Whisper (standaard), AssemblyAI (EU, met sprekers), of het vaste voorbeeldgesprek
        // om lokaal te testen. Dat laatste nooit live.
        $this->app->bind(Transcriber::class, fn () => match (config('renovion.transcription.driver')) {
            'assemblyai' => new AssemblyAiTranscriber,
            'demo' => $this->app->isProduction() ? new WhisperTranscriber : new DemoTranscriber,
            default => new WhisperTranscriber,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::if('nativeapp', fn () => NativeApp::active());

        // Nederlandse afkortingen zonder punt: "31 okt, Peter" in plaats van "31 okt., Peter", waar punt en
        // komma botsen. Zelfde schrijfwijze als de datums in de app (Intl) en het prototype.
        foreach ([Carbon::getTranslator(), CarbonImmutable::getTranslator()] as $translator) {
            $translator->setMessages('nl', [
                'months_short' => ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'],
                'weekdays_short' => ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'],
            ]);
        }

        // App-tokens (api/app): na 60 dagen niet gebruiken opnieuw inloggen. Sanctum's eigen 'expiration'
        // telt vanaf het aanmaken; dit telt vanaf het laatste gebruik, zodat dagelijks gebruik ingelogd blijft.
        Sanctum::authenticateAccessTokensUsing(fn (PersonalAccessToken $token, bool $isValid) => $isValid
            && ($token->last_used_at ?? $token->created_at)->gt(now()->subDays(60)));

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
            'mail_template' => MailTemplate::class,
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
            'meeting' => Meeting::class,
        ]);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip()
            ));
        });

        // Van de app naar een serverpagina (app/sessie). Een eigen teller: een naamloze limiet ("throttle:30,1")
        // deelt per gebruiker één teller met alle andere naamloze limieten, ook die van de app-API. Dan gaf een
        // paar schermen wisselen in een minuut al "Even rustig aan". De code zelf is eenmalig en niet te raden.
        RateLimiter::for('app-session', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('app-webcode', fn (Request $request) => Limit::perMinute(60)->by((string) $request->user()?->id ?: $request->ip()));

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
