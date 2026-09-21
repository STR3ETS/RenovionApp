<?php

namespace App\Automations;

use App\Enums\ActionSource;
use App\Enums\TaskPriority;
use App\Enums\TimelineEventType;
use App\Mail\RequestPhoneNumberMail;
use App\Models\AutomationRun;
use App\Models\Lead;
use App\Models\Task;
use Illuminate\Support\Facades\Mail;

/**
 * Briefing §9: nieuwe lead zonder telefoonnummer → automatisch e-mail om het
 * nummer op te vragen. Zonder e-mailadres wordt een taak aangemaakt.
 */
class MissingPhoneAutomation implements Automation
{
    public function key(): string
    {
        return 'telefoonnummer-opvragen';
    }

    public function name(): string
    {
        return 'Ontbrekend telefoonnummer opvragen';
    }

    public function description(): string
    {
        return 'Nieuwe lead + telefoonnummer ontbreekt → e-mail naar de klant om het nummer op te vragen (of een taak als er geen e-mailadres is).';
    }

    public function run(): int
    {
        $count = 0;

        $leads = Lead::open()
            ->whereNull('phone_requested_at')
            ->whereHas('customer', fn ($query) => $query->whereNull('phone'))
            ->with(['customer', 'assignee'])
            ->get();

        foreach ($leads as $lead) {
            if (! AutomationRun::claim($this->key(), $lead)) {
                continue;
            }

            if (filled($lead->customer->email)) {
                Mail::to($lead->customer->email)->send(new RequestPhoneNumberMail($lead));

                $lead->forceFill(['phone_requested_at' => now()])->save();

                $lead->customer->recordEvent(
                    TimelineEventType::Email,
                    'E-mail verstuurd: telefoonnummer opgevraagd',
                    'Automatische e-mail met het verzoek om een telefoonnummer te delen.',
                    $lead,
                    ActionSource::Automation,
                );
            } else {
                Task::create([
                    'title' => 'Telefoonnummer achterhalen: '.$lead->customer->name,
                    'customer_id' => $lead->customer_id,
                    'lead_id' => $lead->id,
                    'owner_id' => $lead->assigned_to,
                    'deadline' => today()->toDateString(),
                    'priority' => TaskPriority::Hoog,
                    'source' => ActionSource::Automation,
                ]);
            }

            $count++;
        }

        return $count;
    }
}
