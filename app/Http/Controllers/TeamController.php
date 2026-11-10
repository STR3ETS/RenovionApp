<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\JobExecution;
use App\Models\JobReport;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        // De rechtenmatrix erbij: zo is te zien waarom een collega iets niet ziet,
        // in plaats van dat het op een fout lijkt.
        $sections = Navigation::sections();
        $roles = array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role) => $role !== UserRole::Klant,
        ));

        return view('team.index', [
            'users' => User::withCount(['projects', 'tasks' => fn ($query) => $query->open()])
                ->where('role', '!=', UserRole::Klant)
                ->orderBy('name')
                ->get(),
            'roles' => $roles,
            'sections' => $sections,
            'matrix' => collect($sections)
                ->mapWithKeys(fn (array $section, string $id) => [$id => collect($section['items'])
                    ->mapWithKeys(fn (array $item, string $module) => [$module => collect($roles)
                        ->mapWithKeys(fn (UserRole $role) => [
                            $role->value => Navigation::allows($role, $module),
                        ])->all()])
                    ->all()])
                ->all(),
            'managementOnly' => Navigation::MANAGEMENT_ONLY,
        ]);
    }

    public function create(): View
    {
        return view('team.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        AuditLog::record($user, 'aangemaakt', [], ['role' => $user->role->value]);

        return redirect()->route('team.index')->with('success', $user->name.' is toegevoegd aan het team.');
    }

    public function edit(User $user): View
    {
        return view('team.edit', ['user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        // Voorkom dat een admin zichzelf buitensluit door de eigen rol te verlagen.
        if ($user->is($request->user())) {
            unset($validated['role']);
        }

        $oldRole = $user->role->value;
        $user->update($validated);

        if ($user->wasChanged('role')) {
            AuditLog::record($user, 'rol_gewijzigd', ['role' => $oldRole], ['role' => $user->role->value]);
        }

        return redirect()->route('team.index')->with('success', $user->name.' is bijgewerkt.');
    }

    /**
     * Iemand uit het team halen. Wie nog geen klussen of meldingen op zijn naam
     * heeft, verdwijnt echt. Wie die wel heeft, wordt gedeactiveerd: het account
     * kan niet meer inloggen en valt uit de planning, maar het werk blijft op
     * naam staan. Echt verwijderen zou die klussen met foto-bewijs meenemen.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role === UserRole::Klant, 404);

        if ($user->is($request->user())) {
            return back()->with('error', 'Je kunt je eigen account niet verwijderen.');
        }

        $this->endSessions($user);

        if ($this->hasWorkHistory($user)) {
            $user->forceFill(['active' => false, 'remember_token' => null])->save();
            AuditLog::record($user, 'gedeactiveerd', ['active' => true], ['active' => false]);

            return redirect()->route('team.index')->with(
                'success',
                $user->name.' heeft klussen of meldingen op zijn naam staan, dus het account is gedeactiveerd in plaats van verwijderd. Inloggen kan niet meer, het werk blijft bewaard.',
            );
        }

        $name = $user->name;
        AuditLog::record($user, 'verwijderd', ['name' => $name, 'email' => $user->email, 'role' => $user->role->value], []);
        $user->delete();

        return redirect()->route('team.index')->with('success', $name.' is verwijderd.');
    }

    public function activate(User $user): RedirectResponse
    {
        abort_if($user->role === UserRole::Klant, 404);

        $user->forceFill(['active' => true])->save();
        AuditLog::record($user, 'geactiveerd', ['active' => false], ['active' => true]);

        return redirect()->route('team.index')->with('success', $user->name.' kan weer inloggen.');
    }

    private function hasWorkHistory(User $user): bool
    {
        return JobExecution::where('user_id', $user->id)->exists()
            || JobReport::where('reported_by', $user->id)->exists();
    }

    /** Wie uit het team gaat, moet ook direct uitgelogd zijn. */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
