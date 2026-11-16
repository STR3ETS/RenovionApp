<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\App\Badges;
use App\Support\Navigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Het team in de app (team/index): de teamleden met rol, open taken en projecten, en de rechtenmatrix (welke
 * rol welk scherm ziet). Aanmaken en bewerken openen de formulieren team.create en team.edit.
 */
class TeamOverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $sections = Navigation::sections();
        $roles = array_values(array_filter(UserRole::cases(), fn (UserRole $role) => $role !== UserRole::Klant));

        $users = User::withCount(['projects', 'tasks' => fn ($query) => $query->open()])
            ->where('role', '!=', UserRole::Klant)
            ->orderBy('name')
            ->get();

        return response()->json([
            'count' => $users->count(),
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'initial' => mb_strtoupper(mb_substr($user->name, 0, 1)),
                'me' => $user->is($request->user()),
                'email' => $user->email,
                'sub' => collect([
                    $user->phone,
                    $user->role === UserRole::Uitvoerder ? $user->projects_count.' '.($user->projects_count === 1 ? 'project' : 'projecten') : null,
                    $user->tasks_count.' open '.($user->tasks_count === 1 ? 'taak' : 'taken'),
                ])->filter()->join(', '),
                'role' => ['label' => $user->role->label(), 'classes' => Badges::role($user->role)],
                'edit' => route('team.edit', $user, false),
            ]),
            'create' => route('team.create', [], false),
            'matrix' => [
                'roles' => array_map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()], $roles),
                'screens' => collect($sections)->sum(fn (array $section) => count($section['items'])),
                'sections' => collect($sections)->map(fn (array $section) => [
                    'label' => $section['group'].', '.$section['label'],
                    'items' => collect($section['items'])->map(fn (array $item, string $module) => [
                        'module' => $module,
                        'label' => $item[0],
                        'management_only' => in_array($module, Navigation::MANAGEMENT_ONLY, true),
                        'roles' => collect($roles)->filter(fn (UserRole $role) => Navigation::allows($role, $module))->map->value->values(),
                    ])->values(),
                ])->values(),
            ],
        ]);
    }
}
