<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MailTemplate;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mailbuilder: de teksten die naar klanten gaan, op één plek in plaats van in de
 * hoofden van vier mensen.
 *
 * De voorbeeldweergave vult een echt project in. Zo zie je of alle variabelen
 * gevuld worden voordat de mail de deur uit gaat; wat niet gevuld kan worden
 * blijft als ontbrekend staan.
 */
class MailTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $this->seedDefaults($request);

        $templates = MailTemplate::orderBy('id')->get();
        $preview = $request->integer('voorbeeld')
            ? $templates->firstWhere('id', $request->integer('voorbeeld'))
            : $templates->first();

        $project = $request->integer('project')
            ? Project::with('customer', 'projectLeader')->find($request->integer('project'))
            : Project::with('customer', 'projectLeader')->latest('id')->first();

        return view('mail-templates.index', [
            'templates' => $templates,
            'preview' => $preview,
            'values' => $this->valuesFor($project),
            'project' => $project,
            'projects' => Project::with('customer')->orderBy('name')->get(),
            'variables' => MailTemplate::VARIABLES,
            'blockTypes' => MailTemplate::BLOCK_TYPES,
        ]);
    }

    public function update(Request $request, MailTemplate $mailTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'subject' => ['sometimes', 'string', 'max:200'],
            'moment' => ['nullable', 'string', 'max:200'],
            'active' => ['nullable', 'boolean'],
            'blocks' => ['nullable', 'array'],
            'blocks.*.type' => ['required_with:blocks', 'string', 'in:'.implode(',', array_keys(MailTemplate::BLOCK_TYPES))],
            'blocks.*.body' => ['nullable', 'string', 'max:5000'],
        ]);

        $old = $mailTemplate->only(['subject', 'name']);

        $mailTemplate->update([
            ...collect($validated)->except('blocks')->all(),
            'active' => $request->boolean('active'),
            'blocks' => collect($validated['blocks'] ?? [])
                ->filter(fn (array $block) => filled($block['body'] ?? null))
                ->values()
                ->all(),
            'updated_by' => $request->user()->id,
        ]);

        AuditLog::record($mailTemplate, 'mailtemplate bijgewerkt', $old, [
            'onderwerp' => $mailTemplate->subject,
            'blokken' => count($mailTemplate->blocks ?? []),
        ]);

        $unknown = $mailTemplate->unknownVariables();

        return back()->with(
            $unknown === [] ? 'success' : 'error',
            $unknown === []
                ? 'Template opgeslagen.'
                : 'Opgeslagen, maar deze variabelen kent het systeem niet: '.implode(', ', $unknown).'.',
        );
    }

    /**
     * De waarden waarmee de voorbeeldweergave gevuld wordt. Een echt project,
     * zodat je ziet wat de klant straks leest.
     *
     * @return array<string, string|null>
     */
    private function valuesFor(?Project $project): array
    {
        $customer = $project?->customer;
        $quote = $project?->quote;

        return [
            'klant_naam' => $customer?->name,
            'contactpersoon' => $customer?->name,
            'project_naam' => $project?->name,
            'project_adres' => collect([$project?->address, $project?->city])->filter()->join(', ') ?: null,
            'offertenummer' => $quote?->number,
            'offertebedrag' => $quote !== null ? 'EUR '.number_format((float) $quote->total, 2, ',', '.') : null,
            'offertelink' => $quote !== null ? route('quotes.public', $quote->public_token) : null,
            'aanneemsom' => $project !== null ? 'EUR '.number_format((float) $project->value, 2, ',', '.') : null,
            'startdatum' => $project?->start_date?->translatedFormat('j F Y'),
            'opleverdatum' => $project?->end_date_expected?->translatedFormat('j F Y'),
            'projectleider' => $project?->projectLeader?->name,
            'factuurnummer' => $project?->invoices()->latest('id')->value('number'),
            'factuurbedrag' => null,
            'vervaldatum' => $project?->invoices()->latest('id')->value('due_date'),
            'bedrijf_naam' => Setting::get('bedrijf.naam'),
            'bedrijf_telefoon' => Setting::get('bedrijf.telefoon'),
        ];
    }

    /** De zeven momenten waarop er een mail naar een klant gaat, als startpunt. */
    private function seedDefaults(Request $request): void
    {
        if (MailTemplate::exists()) {
            return;
        }

        foreach (MailTemplate::defaults() as $template) {
            MailTemplate::create([...$template, 'updated_by' => $request->user()->id]);
        }
    }
}
