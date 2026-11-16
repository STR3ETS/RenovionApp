<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\AcademyProgress;
use App\Support\Academy;
use App\Support\App\AppColours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * De Academy in de app (academy/index en academy/show): het leerpad voor jouw rol, de rondleidingen per
 * onderwerp, de afspraken en de veelgestelde vragen, en een rondleiding stap voor stap. Een stap openen
 * telt als voortgang, net als op de website; afronden gaat via de AcademyController van de website.
 */
class AcademyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $role = $request->user()->role;
        $tours = Academy::toursFor($role);
        $progress = AcademyProgress::forUser($request->user());
        $paths = Academy::pathsFor($role);
        $ownId = Academy::pathFor($role);
        $state = fn (string $id) => $progress[$id] ?? ['done' => false, 'started' => false, 'step' => 0];

        $own = null;
        if (isset($paths[$ownId])) {
            $path = $paths[$ownId];
            $relevant = array_values(array_filter($path['tours'], fn (string $id) => isset($tours[$id])));
            $finished = array_filter($relevant, fn (string $id) => $state($id)['done']);
            $own = [
                'title' => $path['title'],
                'for' => $path['for'],
                'icon' => $path['icon'],
                'classes' => AppColours::chip($path['colour']),
                'percentage' => $relevant === [] ? 0 : (int) round(count($finished) / count($relevant) * 100),
                'tours' => array_map(fn (string $id) => [
                    'id' => $id,
                    'title' => $tours[$id]['title'],
                    'done' => $state($id)['done'],
                    'started' => $state($id)['started'],
                    'path' => route('academy.show', $id, false),
                ], $relevant),
            ];
        }

        return response()->json([
            'done' => collect($progress)->where('done', true)->count(),
            'total' => count($tours),
            'own' => $own,
            'categories' => collect(Academy::CATEGORIES)
                ->map(fn (array $category, string $key) => [
                    'key' => $key,
                    'label' => $category['label'],
                    'icon' => $category['icon'],
                    'classes' => AppColours::chip($category['colour']),
                    'tours' => collect($tours)
                        ->filter(fn (array $tour) => $tour['category'] === $key)
                        ->map(fn (array $tour, string $id) => [
                            'id' => $id,
                            'title' => $tour['title'],
                            'summary' => $tour['summary'],
                            'minutes' => $tour['minutes'],
                            'steps' => count($tour['steps']),
                            'level' => $tour['level'],
                            'done' => $state($id)['done'],
                            'started' => $state($id)['started'],
                            'step' => $state($id)['step'] + 1,
                            'path' => route('academy.show', $id, false),
                        ])->values(),
                ])
                ->filter(fn (array $category) => $category['tours']->isNotEmpty())
                ->values(),
            'rules' => array_map(fn (array $rule) => ['text' => $rule[0], 'icon' => $rule[1]], Academy::RULES),
            'faq' => array_map(fn (array $item) => ['question' => $item[0], 'answer' => $item[1]], Academy::FAQ),
        ]);
    }

    public function show(Request $request, string $tour): JsonResponse
    {
        $tours = Academy::toursFor($request->user()->role);

        abort_unless(isset($tours[$tour]), 404);

        $definition = $tours[$tour];
        $step = min(count($definition['steps']) - 1, max(0, $request->integer('stap')));

        // Openen is beginnen: de voortgang volgt uit wat je doet (zoals AcademyController@show op de website).
        $progress = AcademyProgress::firstOrCreate(
            ['user_id' => $request->user()->id, 'tour' => $tour],
            ['started_at' => now()],
        );

        if ($step > $progress->step) {
            $progress->update(['step' => $step]);
        }

        $current = $definition['steps'][$step];

        return response()->json([
            'id' => $tour,
            'title' => $definition['title'],
            'summary' => $definition['summary'],
            'category' => Academy::CATEGORIES[$definition['category']]['label'],
            'step' => $step,
            'count' => count($definition['steps']),
            'last' => $step === count($definition['steps']) - 1,
            'current' => [
                'title' => $current['title'],
                'body' => $current['body'],
                'path' => isset($current['route']) && Route::has($current['route']) ? route($current['route'], [], false) : null,
            ],
            'steps' => array_map(fn (array $item) => ['title' => $item['title']], $definition['steps']),
            'completed_on' => $progress->isComplete() ? $progress->completed_at->translatedFormat('j M Y') : null,
        ]);
    }
}
