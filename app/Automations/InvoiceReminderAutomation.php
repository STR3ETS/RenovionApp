<?php

namespace App\Automations;

use App\Enums\ActionSource;
use App\Enums\AutomationMode;
use App\Enums\InvoiceStatus;
use App\Enums\TaskPriority;
use App\Enums\TimelineEventType;
use App\Mail\NoticeMail;
use App\Models\AutomationRun;
use App\Models\Invoice;
use App\Models\Task;
use Illuminate\Support\Facades\Mail;

/**
 * Factuur over de vervaldatum: een herinnering naar de opdrachtgever, maar
 * alleen als die dat heeft aanstaan. Wie geen herinnering wil, krijgt er geen:
 * die factuur blijft in de inbox staan om zelf na te bellen.
 *
 * Een mail aan een klant gaat de deur uit, dus standaard signaleert Nova
 * alleen. Zet de regel op automatisch om de herinnering echt te versturen.
 */
class InvoiceReminderAutomation extends BaseAutomation
{
    public function key(): string
    {
        return 'factuur-herinnering';
    }

    public function name(): string
    {
        return 'Betalingsherinnering';
    }

    public function description(): string
    {
        return 'Factuur over de vervaldatum + opdrachtgever heeft herinneringen aanstaan → herinneringsmail, een keer per factuur.';
    }

    public function defaultMode(): AutomationMode
    {
        return AutomationMode::Signaleren;
    }

    public function run(): int
    {
        $count = 0;

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Verzonden)
            ->whereDate('due_date', '<', today())
            ->whereHas('customer', fn ($customer) => $customer->where('reminders', true))
            ->with(['customer', 'project'])
            ->get();

        foreach ($invoices as $invoice) {
            if (! AutomationRun::claim($this->key(), $invoice)) {
                continue;
            }

            $amount = '€ '.number_format($invoice->openAmount(), 2, ',', '.');

            $this->act(
                "Factuur {$invoice->number} van {$invoice->customer->name} ({$amount}) is {$invoice->daysOverdue()} dagen over de vervaldatum.",
                function () use ($invoice, $amount) {
                    if (filled($invoice->customer->email)) {
                        Mail::to($invoice->customer->email)->send(new NoticeMail(
                            'Herinnering factuur '.$invoice->number,
                            'Beste '.($invoice->customer->contact_name ?: $invoice->customer->name).',',
                            [
                                "Volgens onze administratie staat factuur {$invoice->number} van {$amount} nog open. De vervaldatum was {$invoice->due_date->translatedFormat('j F Y')}.",
                                'Heeft u de betaling net gedaan, dan kunt u dit bericht als niet verzonden beschouwen. Heeft u een vraag over de factuur, laat het ons dan weten.',
                            ],
                        ));

                        $invoice->customer->recordEvent(
                            TimelineEventType::Email,
                            "Betalingsherinnering verstuurd voor factuur {$invoice->number}",
                            $amount.', '.$invoice->daysOverdue().' dagen over de vervaldatum',
                            $invoice->project,
                            ActionSource::Automation,
                        );

                        return;
                    }

                    // Zonder mailadres kan er geen herinnering weg: dan belt iemand.
                    Task::create([
                        'title' => "Bellen over factuur {$invoice->number}: {$invoice->customer->name}",
                        'note' => $amount.', geen mailadres bekend voor een herinnering',
                        'customer_id' => $invoice->customer_id,
                        'project_id' => $invoice->project_id,
                        'deadline' => today(),
                        'priority' => TaskPriority::Hoog,
                        'source' => ActionSource::Automation,
                    ]);
                },
                null,
                route('invoices.show', $invoice),
            );

            $count++;
        }

        return $count;
    }
}
