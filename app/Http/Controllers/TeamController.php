<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('team.index', [
            'users' => User::withCount(['projects', 'tasks' => fn ($query) => $query->open()])
                ->orderBy('name')
                ->get(),
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
