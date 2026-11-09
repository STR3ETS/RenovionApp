<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\AutomationSetting;
use App\Models\Calculation;
use App\Models\ChecklistItem;
use App\Models\Customer;
use App\Models\DeliveryReport;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Photo;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkPackage;
use App\Services\Transcription\AssemblyAiTranscriber;
use App\Services\Transcription\DemoTranscriber;
use App\Services\Transcription\Transcriber;
use App\Services\Transcription\WhisperTranscriber;
use App\Support\NativeApp;
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
            'automation_setting' => AutomationSetting::class,
            'calculation' => Calculation::class,
            'checklist_item' => ChecklistItem::class,
            'customer' => Customer::class,
            'delivery_report' => DeliveryReport::class,
            'document' => Document::class,
            'lead' => Lead::class,
            'photo' => Photo::class,
            'project' => Project::class,
            'project_phase' => ProjectPhase::class,
            'quote' => Quote::class,
            'schedule_entry' => ScheduleEntry::class,
            'task' => Task::class,
            'user' => User::class,
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
        Gate::define('manage-crm', fn (User $user) => ! in_array($user->role, [UserRole::Uitvoerder, UserRole::Klant], true));

        // De interne omgeving is niet voor klanten - die krijgen het portaal (§10).
        Gate::define('internal', fn (User $user) => $user->role !== UserRole::Klant);

        // Teambeheer (gebruikers aanmaken/bewerken) is alleen voor admins.
        Gate::define('manage-team', fn (User $user) => $user->role === UserRole::Admin);
    }
}
