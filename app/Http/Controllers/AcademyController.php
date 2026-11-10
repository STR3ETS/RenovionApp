<?php

namespace App\Http\Controllers;

use App\Models\AcademyProgress;
use App\Support\Academy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * De Academy: rondleidingen per onderwerp, een leerpad per rol, de afspraken van
 * het bedrijf en antwoorden op de vragen die mensen echt stellen.
 */
class AcademyController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->user()->role;
        $tours = Academy::toursFor($role);
        $progress = AcademyProgress::forUser($request->user());
        $paths = Academy::pathsFor($role);
        $own = Academy::pathFor($role);

        return view('academy.index', [
            'tours' => $tours,
            'progress' => $progress,
            'paths' => $paths,
            'ownPath' => $own,
            'categories' => Academy::CATEGORIES,
            'faq' => Academy::FAQ,
            'rules' => Academy::RULES,
            'done' => collect($progress)->where('done', true)->count(),
            'total' => count($tours),
            // De voortgang per leerpad: alleen de rondleidingen die bij deze rol passen.
            'pathProgress' => collect($paths)->map(function (array $path) use ($tours, $progress) {
                $relevant = array_values(array_filter($path['tours'], fn (string $id) => isset($tours[$id])));
                $finished = array_filter($relevant, fn (string $id) => $progress[$id]['done'] ?? false);

                return [
                    'tours' => $relevant,
                    'done' => count($finished),
                    'percentage' => $relevant === [] ? 0 : (int) round(count($finished) / count($relevant) * 100),
                ];
            })->all(),
        ]);
    }

    public function show(Request $request, string $tour): View
    {
        $tours = Academy::toursFor($request->user()->role);

        abort_unless(isset($tours[$tour]), 404);

        $definition = $tours[$tour];
        $step = min(count($definition['steps']) - 1, max(0, $request->integer('stap')));

        // Openen is beginnen: de voortgang volgt uit wat je doet.
        $progress = AcademyProgress::firstOrCreate(
            ['user_id' => $request->user()->id, 'tour' => $tour],
            ['started_at' => now()],
        );

        if ($step > $progress->step) {
            $progress->update(['step' => $step]);
        }

        return view('academy.show', [
            'id' => $tour,
            'tour' => $definition,
            'step' => $step,
            'progress' => $progress,
            'categories' => Academy::CATEGORIES,
        ]);
    }

    public function complete(Request $request, string $tour): RedirectResponse
    {
        $tours = Academy::toursFor($request->user()->role);

        abort_unless(isset($tours[$tour]), 404);

        AcademyProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'tour' => $tour],
            [
                'step' => count($tours[$tour]['steps']) - 1,
                'started_at' => now(),
                'completed_at' => now(),
            ],
        );

        return redirect()
            ->route('academy.index')
            ->with('success', 'Rondleiding "'.$tours[$tour]['title'].'" afgerond.');
    }
}
