<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Http\RedirectResponse;
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
}
