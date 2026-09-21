<?php

namespace App\Http\Controllers;

use Anthropic\Core\Exceptions\APIStatusException;
use App\Enums\ActionSource;
use App\Services\NovaAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NovaController extends Controller
{
    /**
     * Herken de intentie en geef een antwoord of een actievoorstel terug.
     */
    public function propose(Request $request, NovaAssistant $nova): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if (! $nova->isConfigured()) {
            return response()->json([
                'type' => 'answer',
                'text' => 'Nova is nog niet geconfigureerd: er ontbreekt een ANTHROPIC_API_KEY in de omgeving.',
            ]);
        }

        try {
            return response()->json($nova->propose($validated['message'], $request->user()));
        } catch (APIStatusException $exception) {
            report($exception);

            return response()->json([
                'type' => 'answer',
                'text' => 'Nova is even niet bereikbaar ('.($exception->type?->value ?? 'api-fout').'). Probeer het zo opnieuw.',
            ], 200);
        }
    }

    /**
     * De gecachete Nova-dagbriefing voor het Vandaag-scherm.
     */
    public function briefing(Request $request, NovaAssistant $nova): JsonResponse
    {
        if (! $nova->isConfigured()) {
            return response()->json(['text' => null]);
        }

        $cacheKey = 'nova-briefing.'.$request->user()->id.'.'.today()->toDateString();

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        try {
            $text = Cache::remember($cacheKey, now()->addHours(3), fn () => $nova->dailyBriefing($request->user()));
        } catch (APIStatusException $exception) {
            report($exception);

            return response()->json(['text' => null]);
        }

        return response()->json(['text' => $text]);
    }

    /**
     * Voer een door de gebruiker bevestigde actie uit.
     */
    public function execute(Request $request, NovaAssistant $nova): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'array'],
            'action.type' => ['required', Rule::in(NovaAssistant::ACTIONS)],
            'action.params' => ['required', 'array'],
            'via_voice' => ['nullable', 'boolean'],
        ]);

        $source = $request->boolean('via_voice') ? ActionSource::Voice : ActionSource::Nova;

        try {
            $result = $nova->execute($validated['action'], $request->user(), $source);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Deze actie kan niet worden uitgevoerd: '.$exception->validator->errors()->first(),
            ], 422);
        }

        return response()->json($result);
    }
}
