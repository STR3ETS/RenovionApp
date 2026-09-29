<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Calculation;
use App\Models\ChecklistItem;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Photo;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkPackage;
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
            'calculation' => Calculation::class,
            'checklist_item' => ChecklistItem::class,
            'customer' => Customer::class,
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
        ]);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip()
            ));
        });

        // Briefing §15: uitvoerders zien alleen hun eigen projecten, taken en planning.
        Gate::define('manage-crm', fn (User $user) => ! in_array($user->role, [UserRole::Uitvoerder, UserRole::Klant], true));

        // De interne omgeving is niet voor klanten — die krijgen het portaal (§10).
        Gate::define('internal', fn (User $user) => $user->role !== UserRole::Klant);

        // Teambeheer (gebruikers aanmaken/bewerken) is alleen voor admins.
        Gate::define('manage-team', fn (User $user) => $user->role === UserRole::Admin);
    }
}
