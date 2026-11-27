<?php

use App\Models\OsRow;
use App\Models\User;
use App\Os\Ai\Actions;
use App\Os\Ai\Agents;
use App\Os\Ai\AiUnavailable;
use App\Os\Ai\StateReader;
use App\Os\People;
use App\Os\StateStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

Artisan::command('os:install', function (StateStore $store) {
    $store->ensureInstalled();
    $this->info('Renovion Groep staat klaar (revisie '.$store->rev().').');
})->purpose('Zet de lege fabrieksstaat van Renovion Groep klaar als die er nog niet is');

Artisan::command('os:reset {--force : Ook op productie, zonder vragen}', function (StateStore $store) {
    if (app()->isProduction() && ! $this->option('force')) {
        $this->error('Op productie alleen met --force: dit wist alle gegevens van Renovion Groep.');

        return 1;
    }
    if (! $this->option('force') && ! $this->confirm('Alle projecten, klanten, taken en de rest van Renovion Groep worden gewist. Doorgaan?')) {
        return 1;
    }

    DB::table('os_rows')->truncate();
    DB::table('os_changes')->truncate();
    DB::table('os_meta')->truncate();
    DB::table('users')->update(['person_id' => null]);
    Cache::forget('os-lease:automations');
    $store->ensureInstalled();

    $this->info('Renovion Groep is leeg opnieuw ingericht (bestanden en accounts blijven staan).');
})->purpose('Wis de staat van Renovion Groep en begin opnieuw met de lege fabrieksstaat');

Artisan::command('os:tijdelijk-wachtwoord {email* : E-mailadressen van de accounts}', function () {
    foreach ($this->argument('email') as $email) {
        $account = User::query()->whereRaw('lower(email) = ?', [Str::lower($email)])->first();
        if (! $account) {
            $this->error("Geen account met {$email}.");

            continue;
        }
        $this->line(str_pad($account->email, 28).People::temporaryPassword($account));
    }
    $this->info('Bij de volgende keer inloggen kiest iedereen zelf een eigen wachtwoord. Open sessies zijn gesloten.');
})->purpose('Geef accounts een tijdelijk wachtwoord; de gebruiker kiest daarna zelf een eigen wachtwoord');

Artisan::command('os:agents {--max=6 : Hoeveel signalen deze ronde maximaal}', function (Agents $agents) {
    if (! config('services.anthropic.agents')) {
        $this->line('De agents staan uit (RENOVION_AGENTS=false).');

        return 0;
    }
    try {
        $done = $agents->round((int) $this->option('max'));
    } catch (AiUnavailable $e) {
        $this->error($e->getMessage());

        return 1;
    }
    foreach ($done as $run) {
        $this->line("{$run['agent']}: {$run['uitkomst']} ({$run['signaal']})");
    }
    $this->info(count($done).' uitvoering(en).');
})->purpose('Laat de agents van Renovion Groep een ronde werken op de echte gegevens');

// Elk half uur een ronde agents, overdag op werkdagen (de scheduler moet draaien: cron of schedule:work).
Schedule::command('os:agents')->everyThirtyMinutes()->weekdays()->between('7:00', '19:00')->withoutOverlapping();

/*
 * Eenmalig bij de overstap: klanten uit de vorige app (tabellen customers,
 * leads, timeline_events) overzetten naar Renovion Groep, met hun aanvraag en
 * logboek. Daarna kan os:oude-app-opruimen de oude tabellen weghalen.
 */
Artisan::command('os:importeer-oud {klant* : Id\'s uit de oude tabel customers}', function (StateStore $store) {
    if (! Schema::hasTable('customers')) {
        $this->error('De tabellen van de vorige app bestaan niet (meer).');

        return 1;
    }
    $store->ensureInstalled();
    $system = Agents::systemUser();
    $phase = ['nieuw' => 0, 'contact' => 0, 'intake' => 1, 'offerte' => 3, 'akkoord' => 4, 'project' => 5];

    foreach ($this->argument('klant') as $id) {
        $c = DB::table('customers')->find($id);
        if (! $c) {
            $this->warn("Klant {$id} bestaat niet.");

            continue;
        }
        $leads = DB::table('leads')->where('customer_id', $id)->orderBy('id')->get();
        $clientId = null;
        foreach ($leads->isEmpty() ? [null] : $leads as $lead) {
            $state = new StateReader($system);
            $actions = new Actions($state, now()->toDateString());
            $actions->execute($system, [[
                'type' => 'aanvraag', 'naam' => $c->name, 'klant_id' => $clientId, 'soort' => ($c->type ?? '') === 'zakelijk' ? 'Zakelijk' : 'Particulier',
                'contactpersoon' => $c->contact_name ?: $c->name, 'telefoon' => $c->phone ?? '', 'mailadres' => $c->email ?? '',
                'adres' => trim(($c->address ?? '').' '.($c->postal_code ?? '')), 'plaats' => $c->city ?? '',
                'soort_werk' => $lead->service ?? 'Renovatie', 'tekst' => (string) ($lead->description ?? ''), 'bron' => ucfirst((string) ($lead->source ?? 'overgezet')),
                'activiteit' => 'Overgezet uit de vorige app',
            ]]);
            $pid = $actions->lastProjectId;
            $project = json_decode(OsRow::query()->where('path', 'projects/'.$pid)->value('value'), false);
            if (! $clientId) {
                $clientId = $project->clientId;
            }

            // Logboek van de oude app, nieuwste bovenaan, en de fase van de aanvraag
            $events = DB::table('timeline_events')->where('customer_id', $id)
                ->when($lead, fn ($q) => $q->where(fn ($w) => $w->whereNull('subject_id')->orWhere('subject_id', $lead->id)))
                ->orderByDesc('happened_at')->get();
            foreach ($events as $e) {
                $project->activity[] = (object) ['d' => substr((string) $e->happened_at, 0, 10), 't' => $e->title.($e->body && $e->type !== 'aanvraag' ? ': '.$e->body : ''), 'k' => 'blue'];
            }
            $project->phase = $phase[$lead->status ?? 'nieuw'] ?? 0;
            $store->apply($system, [['projects/'.$pid, json_encode($project, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]);
            $this->info("{$c->name}: {$pid} ({$lead?->service}), ".count($events).' logboekregels.');
        }
    }
})->purpose('Zet klanten met hun aanvragen over uit de vorige app');

Artisan::command('os:oude-app-opruimen {--force : Echt weghalen}', function () {
    $keep = ['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'];
    $tables = collect(DB::select('SHOW TABLES'))->map(fn ($t) => array_values((array) $t)[0])
        ->reject(fn ($t) => in_array($t, $keep, true) || str_starts_with($t, 'os_'))->values();
    if ($tables->isEmpty()) {
        $this->info('Er staan geen tabellen van de vorige app meer.');

        return 0;
    }
    $this->line('Weg: '.$tables->implode(', '));
    if (! $this->option('force')) {
        $this->warn('Niets gedaan. Draai opnieuw met --force (maak eerst een back-up).');

        return 1;
    }

    // De koppeling van accounts naar oude klanten verdwijnt mee.
    foreach (DB::select("SELECT CONSTRAINT_NAME AS n FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND REFERENCED_TABLE_NAME IS NOT NULL") as $fk) {
        DB::statement("ALTER TABLE users DROP FOREIGN KEY `{$fk->n}`");
    }
    Schema::disableForeignKeyConstraints();
    foreach ($tables as $table) {
        Schema::drop($table);
    }
    Schema::enableForeignKeyConstraints();
    DB::table('migrations')->where('migration', 'not like', '0001_01_01_%')->where('migration', 'not like', '%renovion_os%')->where('migration', 'not like', '%os_agent_runs%')->delete();
    $this->info($tables->count().' tabellen van de vorige app verwijderd.');
})->purpose('Haal de tabellen van de vorige app weg (na het overzetten en een back-up)');
