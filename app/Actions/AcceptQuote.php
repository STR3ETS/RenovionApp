<?php

namespace App\Actions;

use App\Enums\ActionSource;
use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

class AcceptQuote
{
    /**
     * Offerte akkoord: maak automatisch een project aan vanuit de lead/offerte.
     */
    public function handle(Quote $quote, ActionSource $source = ActionSource::Handmatig): Project
    {
        return DB::transaction(function () use ($quote, $source) {
            $oldStatus = $quote->status;

            $quote->forceFill([
                'status' => QuoteStatus::Akkoord,
                'accepted_at' => now(),
            ])->save();

            $customer = $quote->customer;
            $lead = $quote->lead;

            $project = Project::create([
                'customer_id' => $customer->id,
                'lead_id' => $lead?->id,
                'quote_id' => $quote->id,
                'name' => ($lead?->service ?? 'Renovatie').' '.$customer->name,
                'address' => $customer->address,
                'city' => $customer->city,
                'status' => ProjectStatus::Voorbereiding,
                'value' => $quote->total,
                'deposit_amount' => round((float) $quote->total * 0.30, 2),
            ]);

            $quote->forceFill(['project_id' => $project->id])->save();

            if ($lead !== null) {
                $lead->update(['status' => LeadStatus::Project]);
                AuditLog::record($lead, 'status_gewijzigd', [], ['status' => LeadStatus::Project->value], $source);
            }

            AuditLog::record($quote, 'akkoord', ['status' => $oldStatus->value], ['status' => QuoteStatus::Akkoord->value], $source);
            AuditLog::record($project, 'aangemaakt', [], ['value' => (string) $project->value], $source);

            $customer->recordEvent(TimelineEventType::Offerte, "Offerte {$quote->number} akkoord", null, $quote, $source);
            $customer->recordEvent(TimelineEventType::Projectupdate, "Project aangemaakt: {$project->name}", null, $project, $source);

            return $project;
        });
    }
}
