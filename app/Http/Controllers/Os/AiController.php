<?php

namespace App\Http\Controllers\Os;

use App\Http\Controllers\Controller;
use App\Os\Ai\ActionException;
use App\Os\Ai\Agents;
use App\Os\Ai\AiUnavailable;
use App\Os\Ai\Nova;
use App\Os\Ai\OfferWriter;
use App\Os\Ai\WorkOrderReader;
use App\Os\StateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nova (vragen en voorstellen bevestigen) en de agents (een ronde laten
 * werken, en beslissen over hun voorstellen).
 */
class AiController extends Controller
{
    public function ask(Request $request, Nova $nova): JsonResponse
    {
        abort_if($request->user()->isExternal(), 403);
        $data = $request->validate([
            'vraag' => ['required', 'string', 'max:4000'],
            'geschiedenis' => ['array', 'max:40'],
            'geschiedenis.*.r' => ['string'],
            'geschiedenis.*.t' => ['nullable', 'string', 'max:6000'],
            'project' => ['nullable', 'string', 'max:40'],
            'werkwijze' => ['nullable', 'in:advies,bevestigen,auto'],
            'scherm' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            return response()->json($nova->ask(
                $request->user(), $data['vraag'], $data['geschiedenis'] ?? [], $data['project'] ?? null,
                $data['werkwijze'] ?? 'bevestigen', $data['scherm'] ?? 'vandaag',
            ));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function confirm(Request $request, Nova $nova, string $proposal): JsonResponse
    {
        abort_if($request->user()->isExternal(), 403);

        try {
            $result = $nova->confirm($request->user(), $proposal);
        } catch (ActionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result + ['rows' => $this->rowsSince($request)]);
    }

    public function decide(Request $request, Agents $agents, string $approval): JsonResponse
    {
        abort_if($request->user()->isExternal(), 403);
        $ok = $request->validate(['besluit' => ['required', 'in:ok,nee']])['besluit'] === 'ok';

        try {
            $result = $agents->decide($request->user(), $approval, $ok);
        } catch (ActionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result + ['rows' => $this->rowsSince($request)]);
    }

    /** Directie laat de agents nu een ronde werken in plaats van op de volgende planning te wachten. */
    public function round(Request $request, Agents $agents): JsonResponse
    {
        abort_unless($request->user()->role->isManagement(), 403);

        try {
            $done = $agents->round();
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['uitvoeringen' => $done, 'rows' => $this->rowsSince($request)]);
    }

    /** Een foto of scan van een papieren werkbon uitlezen; de gebruiker controleert de velden. */
    public function readWorkOrder(Request $request, WorkOrderReader $reader): JsonResponse
    {
        abort_if($request->user()->isExternal(), 403);
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'data' => ['required', 'string', 'max:14000000'],
        ]);

        try {
            return response()->json($reader->read($request->user(), $data['type'], $data['data']));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    /** Nova schrijft mee aan een offerteblok; het resultaat is een voorstel. */
    public function writeOffer(Request $request, OfferWriter $writer): JsonResponse
    {
        abort_if($request->user()->isExternal(), 403);
        $data = $request->validate([
            'project' => ['required', 'string', 'max:40'],
            'modus' => ['required', 'in:'.implode(',', OfferWriter::MODES)],
            'opdracht' => ['nullable', 'string', 'max:2000'],
            'tekst' => ['nullable', 'string', 'max:20000'],
            'blok' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            return response()->json($writer->write($request->user(), $data['project'], $data['modus'], (string) ($data['opdracht'] ?? ''), (string) ($data['tekst'] ?? ''), (string) ($data['blok'] ?? 'tekstblok')));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    /** Wat er sinds de stand van de app veranderde, zodat het scherm meteen bijwerkt. */
    private function rowsSince(Request $request): array
    {
        return app(StateStore::class)->since($request->user(), max(0, (int) $request->input('since', 0)));
    }
}
