<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\User;
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
            'customer' => Customer::class,
            'document' => Document::class,
            'lead' => Lead::class,
            'project' => Project::class,
            'quote' => Quote::class,
            'schedule_entry' => ScheduleEntry::class,
            'task' => Task::class,
            'user' => User::class,
        ]);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip()
            ));
        });

        // Briefing §25: vakmensen zien alleen hun eigen projecten, taken en planning.
        Gate::define('manage-crm', fn (User $user) => $user->role !== UserRole::Vakman);

        // Teambeheer (gebruikers aanmaken/bewerken) is alleen voor admins.
        Gate::define('manage-team', fn (User $user) => $user->role === UserRole::Admin);
    }
}
