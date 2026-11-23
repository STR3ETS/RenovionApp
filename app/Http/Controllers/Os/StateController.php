<?php

namespace App\Http\Controllers\Os;

use App\Http\Controllers\Controller;
use App\Os\Factory;
use App\Os\Layout;
use App\Os\StateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * De drie ingangen van de gedeelde staat: alles ophalen bij het openen,
 * wijzigingen opslaan, en bijwerken met wat collega's intussen deden.
 */
class StateController extends Controller
{
    public function __construct(private readonly StateStore $store) {}

    public function show(Request $request): JsonResponse
    {
        $this->store->ensureInstalled();
        $snapshot = $this->store->snapshot($request->user());

        return response()->json([
            'rev' => $snapshot['rev'],
            'rows' => $snapshot['rows'],
            'skeleton' => Layout::skeleton(Factory::state()),
        ])->header('Cache-Control', 'no-store');
    }

    public function pull(Request $request): JsonResponse
    {
        $since = max(0, (int) $request->query('since', 0));

        return response()->json($this->store->since($request->user(), $since))->header('Cache-Control', 'no-store');
    }

    /**
     * De automations rekenen in de browser. Als twee collega's tegelijk
     * werken, zou elke regel dubbel vuren; daarom wijst de server één scherm
     * aan dat ze draait. Dat scherm houdt de beurt zolang het zich elke
     * minuut meldt.
     */
    public function lease(Request $request): JsonResponse
    {
        if ($request->user()->isExternal()) {
            return response()->json(['leader' => false]);
        }

        $key = 'os-lease:automations';
        $me = $request->session()->getId();

        if (Cache::add($key, $me, 90) || Cache::get($key) === $me) {
            Cache::put($key, $me, 90);

            return response()->json(['leader' => true]);
        }

        return response()->json(['leader' => false]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['required', 'integer', 'min:0'],
            'changes' => ['present', 'array', 'max:2000'],
            'changes.*' => ['array', 'size:2'],
            'changes.*.0' => ['required', 'string', 'max:191'],
            'changes.*.1' => ['nullable', 'string'],
        ]);

        $result = $this->store->apply($request->user(), $data['changes']);
        $pulled = $this->store->since($request->user(), (int) $data['since']);

        return response()->json([
            'rev' => $pulled['rev'],
            'rows' => $pulled['rows'],
            'rejected' => $result['rejected'],
            'restore' => $this->store->current($request->user(), $result['rejected']),
            'accounts' => $result['accounts'],
        ])->header('Cache-Control', 'no-store');
    }
}
