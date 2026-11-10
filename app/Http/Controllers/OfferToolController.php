<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ToolOffer;
use App\Support\OfferToolPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * De offertetool voor het team: begroting en offerte in één scherm. De tool
 * zelf is een kant-en-klaar HTML-bestand; deze controller levert hem uit,
 * bewaart wat hij opslaat en neemt bijlagen aan.
 */
class OfferToolController extends Controller
{
    /** Ruim boven een volle begroting, ver onder wat de server aankan. */
    private const MAX_BODY_BYTES = 8 * 1024 * 1024;

    public function index(): View
    {
        $offers = ToolOffer::with(['savedBy', 'signatures'])->orderByDesc('updated_at')->get();

        return view('offer-tool.index', [
            'offers' => $offers,
            // Zie config/renovion.php: de tool bevat nog de gegevens van één project.
            'canCreate' => $offers->isEmpty() || config('renovion.offer_tool.multi_project'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            ToolOffer::query()->doesntExist() || config('renovion.offer_tool.multi_project'),
            403,
            'De offertetool staat nog op één offerte.',
        );

        $validated = $request->validate([
            'number' => ['required', 'string', 'regex:/^'.ToolOffer::NUMBER_PATTERN.'$/', Rule::unique('tool_offers', 'number')],
        ], [
            'number.regex' => 'Gebruik alleen letters, cijfers en streepjes.',
            'number.unique' => 'Dit nummer is al in gebruik.',
        ]);

        $offer = ToolOffer::create(['number' => $validated['number']]);
        AuditLog::record($offer, 'aangemaakt', [], ['number' => $offer->number]);

        return redirect()->route('offer-tool.show', $offer);
    }

    /** De tool zelf, in teammodus. */
    public function show(ToolOffer $offer): Response
    {
        $config = [
            'mode' => 'team',
            'rev' => $offer->rev,
            'at' => $offer->saved_at?->toIso8601String(),
            'saveUrl' => route('offer-tool.save', $offer, false),
            'uploadUrl' => route('offer-tool.upload', $offer, false),
            'clientUrl' => route('offer-tool.client', $offer),
            'csrf' => csrf_token(),
        ];

        // Nog nooit opgeslagen: dan start de tool met zijn eigen standaardbegroting.
        if ($offer->data() !== null) {
            $config['model'] = $offer->data();
        }

        return response(OfferToolPage::tool($offer->number, $config))
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Opslaan vanuit de tool. Alleen als de tool op de laatste revisie werkte:
     * anders heeft een collega intussen opgeslagen en zou zijn werk verdwijnen.
     */
    public function save(Request $request, ToolOffer $offer): JsonResponse
    {
        if (strlen($request->getContent()) > self::MAX_BODY_BYTES) {
            return response()->json(['error' => 'te_groot'], 413);
        }

        // Als objecten lezen: een leeg object moet een object blijven.
        $payload = json_decode($request->getContent());
        $model = is_object($payload) ? ($payload->model ?? null) : null;

        if (! is_object($model) || ! is_array($model->chapters ?? null)) {
            return response()->json(['error' => 'ongeldig'], 422);
        }

        $rev = (int) ($payload->rev ?? 0);

        $saved = DB::transaction(function () use ($offer, $model, $rev, $request) {
            $current = ToolOffer::whereKey($offer->getKey())->lockForUpdate()->firstOrFail();

            if ($current->rev !== $rev) {
                return null;
            }

            $current->putData($model);
            $current->rev = $rev + 1;
            $current->saved_at = now();
            $current->saved_by = $request->user()->id;
            $current->save();

            return $current;
        });

        if ($saved === null) {
            return response()->json(['error' => 'conflict', 'rev' => $offer->fresh()->rev], 409);
        }

        return response()->json(['rev' => $saved->rev, 'at' => $saved->saved_at->toIso8601String()]);
    }

    /**
     * Een bijlage bij de offerte. Komt in de privé-opslag onder een willekeurige
     * naam; de tool onthoudt zelf de titel en het bestandstype.
     */
    public function upload(Request $request, ToolOffer $offer): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('renovion.offer_tool.max_upload_kb')],
            'id' => ['nullable', 'string', 'max:100'],
        ]);

        $name = (string) Str::uuid();
        $request->file('file')->storeAs('offer-tool/'.$offer->getKey(), $name);

        return response()->json([
            'url' => route('offer-tool.file', ['offer' => $offer, 'file' => $name], false),
        ]);
    }
}
