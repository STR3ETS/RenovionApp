<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Services\QuoteBlockAi;
use App\Support\QuoteBlockRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * De offerte-builder (briefing §6, EasyDash-concept): blokbibliotheek,
 * live document met inline bewerken, templates en per-blok AI.
 */
class QuoteBuilderController extends Controller
{
    public function edit(Request $request, Quote $quote): View|RedirectResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return redirect()->route('quotes.show', $quote)
                ->with('error', 'Alleen conceptoffertes kunnen worden bewerkt — start eerst een nieuwe versie.');
        }

        $quote->load(['customer', 'lead', 'lines']);

        // Oud tekstformaat eenmalig omzetten naar getypeerde blokken.
        $typed = QuoteBlockRegistry::ensureTyped($quote->blocks, $quote);
        if ($typed !== $quote->blocks) {
            $quote->update(['blocks' => $typed]);
        }

        return view('quotes.builder', [
            'quote' => $quote,
            'templates' => QuoteTemplate::orderBy('sort_order')->orderBy('name')->get(),
            'registry' => collect(QuoteBlockRegistry::registry())
                ->map(fn (array $meta, string $type) => [...$meta, 'type' => $type])
                ->values()
                ->all(),
        ]);
    }

    public function update(Request $request, Quote $quote): JsonResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return response()->json(['ok' => false, 'error' => 'Offerte is niet meer in concept.'], 422);
        }

        $validated = $request->validate([
            'blocks' => ['required', 'array', 'max:40'],
        ]);

        $quote->update(['blocks' => QuoteBlockRegistry::normalize($validated['blocks'])]);

        return response()->json(['ok' => true]);
    }

    public function applyTemplate(Request $request, Quote $quote): JsonResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return response()->json(['ok' => false, 'error' => 'Offerte is niet meer in concept.'], 422);
        }

        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:quote_templates,id'],
        ]);

        $template = QuoteTemplate::findOrFail($validated['template_id']);

        $blocks = QuoteBlockRegistry::substitute(
            array_map(fn (array $block) => [
                'id' => QuoteBlockRegistry::newId(),
                'type' => $block['type'],
                'data' => $block['data'] ?? [],
            ], $template->blocks),
            $quote,
        );

        $quote->update(['blocks' => $blocks]);
        AuditLog::record($quote, 'template_toegepast', [], ['template' => $template->name]);

        return response()->json(['ok' => true, 'blocks' => $quote->blocks]);
    }

    public function saveAsTemplate(Request $request, Quote $quote): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $blocks = array_map(
            fn (array $block) => ['type' => $block['type'], 'data' => $block['data']],
            QuoteBlockRegistry::normalize($quote->blocks ?? []),
        );

        if ($blocks === []) {
            return response()->json(['ok' => false, 'error' => 'Geen blokken om op te slaan.'], 422);
        }

        $base = Str::slug($validated['name']) ?: 'template';
        $slug = $base;
        $i = 2;
        while (QuoteTemplate::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        $template = QuoteTemplate::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'blocks' => $blocks,
            'is_default' => false,
            'sort_order' => 100,
        ]);

        return response()->json(['ok' => true, 'template' => [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'is_default' => false,
        ]]);
    }

    public function deleteTemplate(QuoteTemplate $template): JsonResponse
    {
        if ($template->is_default) {
            return response()->json(['ok' => false, 'error' => 'Standaardtemplates kunnen niet worden verwijderd.'], 422);
        }

        $template->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Afbeelding voor een foto-blok; wordt via de klantlink-route geserveerd.
     */
    public function upload(Request $request, Quote $quote): JsonResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return response()->json(['ok' => false, 'error' => 'Offerte is niet meer in concept.'], 422);
        }

        $request->validate([
            'file' => ['required', 'image', 'max:10240'],
        ]);

        $name = Str::random(20).'.'.strtolower($request->file('file')->getClientOriginalExtension() ?: 'jpg');
        $request->file('file')->storeAs('quote-media/'.$quote->id, $name);

        return response()->json([
            'ok' => true,
            'url' => route('quotes.public.media', ['quote' => $quote->public_token, 'file' => $name]),
        ]);
    }

    public function blockAi(Request $request, Quote $quote, QuoteBlockAi $ai): JsonResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return response()->json(['ok' => false, 'error' => 'Offerte is niet meer in concept.'], 422);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'data' => ['nullable', 'array'],
            'instruction' => ['nullable', 'string', 'max:4000'],
        ]);

        if (! $ai->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'De AI-assistent is niet geconfigureerd.'], 422);
        }

        $result = $ai->rewrite(
            $quote,
            $validated['type'],
            $validated['data'] ?? [],
            trim((string) ($validated['instruction'] ?? '')),
        );

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    /**
     * Media voor de klantview: token-gescoped, zonder inlog (net als de offerte zelf).
     */
    public function media(Quote $quote, string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9]+\.[a-z0-9]{2,5}$/', $file) === 1, 404);

        $path = 'quote-media/'.$quote->id.'/'.$file;
        abort_unless(Storage::exists($path), 404);

        return response()->file(Storage::path($path), [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
