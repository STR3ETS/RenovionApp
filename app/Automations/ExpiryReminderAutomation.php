<?php

namespace App\Automations;

use App\Enums\ActionSource;
use App\Enums\TaskPriority;
use App\Mail\NoticeMail;
use App\Models\AutomationRun;
use App\Models\Certificate;
use App\Models\FrameworkAgreement;
use App\Models\SubcontractorDocument;
use App\Models\SubcontractorFramework;
use App\Models\Task;
use App\Models\User;
use App\Services\AttentionExtras;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Certificaten, dossierstukken en overeenkomsten die binnen 31 dagen verlopen:
 * één herinneringsmail naar wie het stuk moet vernieuwen. Eén keer per stuk en
 * per verloopdatum, dus een vernieuwd stuk krijgt volgend jaar gewoon weer een
 * herinnering, maar niemand krijgt elke dag dezelfde mail.
 */
class ExpiryReminderAutomation extends BaseAutomation
{
    public function key(): string
    {
        return 'verloop-herinnering';
    }

    public function name(): string
    {
        return 'Verloopherinnering';
    }

    public function description(): string
    {
        return 'Certificaat, dossierstuk of overeenkomst verloopt binnen 31 dagen → een herinneringsmail, een keer per stuk.';
    }

    public function run(): int
    {
        $limit = today()->addDays(AttentionExtras::EXPIRY_DAYS);
        $count = 0;

        $certificates = Certificate::query()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', today())
            ->whereDate('valid_until', '<=', $limit)
            ->with(['user', 'subcontractor'])
            ->get();

        foreach ($certificates as $certificate) {
            $owner = $certificate->user ?? $certificate->subcontractor;

            if ($owner === null || ($certificate->user !== null && ! $certificate->user->active)) {
                continue;
            }

            $count += $this->remind(
                $certificate,
                $certificate->valid_until,
                'Certificaat '.($certificate->meta()['short'] ?? $certificate->type).' van '.$owner->name,
                $owner->email,
                $owner->name,
                'uw certificaat '.($certificate->meta()['short'] ?? $certificate->type),
                $certificate->subcontractor_id !== null ? route('subcontractors.show', $certificate->subcontractor_id) : route('workforce.index'),
            );
        }

        $documents = SubcontractorDocument::query()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', today())
            ->whereDate('valid_until', '<=', $limit)
            ->with('subcontractor')
            ->get();

        foreach ($documents as $document) {
            if ($document->subcontractor === null || $document->subcontractor->blocked) {
                continue;
            }

            $count += $this->remind(
                $document,
                $document->valid_until,
                strtoupper($document->type).' van '.$document->subcontractor->name,
                $document->subcontractor->email,
                $document->subcontractor->contact_name ?: $document->subcontractor->name,
                'het document '.strtoupper($document->type).' in uw dossier',
                route('subcontractors.show', $document->subcontractor_id),
            );
        }

        $frameworks = SubcontractorFramework::query()
            ->whereDate('ends_on', '>=', today())
            ->whereDate('ends_on', '<=', $limit)
            ->with('subcontractor.frameworks')
            ->get();

        foreach ($frameworks as $framework) {
            // Is er al een volgende periode vastgelegd, dan verloopt er niets.
            if ($framework->subcontractor === null
                || $framework->subcontractor->frameworks->contains(fn ($other) => $other->ends_on->gt($framework->ends_on))) {
                continue;
            }

            $count += $this->remind(
                $framework,
                $framework->ends_on,
                'Raamovereenkomst met '.$framework->subcontractor->name,
                $framework->subcontractor->email,
                $framework->subcontractor->contact_name ?: $framework->subcontractor->name,
                'onze raamovereenkomst voor doorlopend werk',
                route('subcontractors.show', $framework->subcontractor_id),
            );
        }

        // Een raamovereenkomst met een klant verlengen we zelf: dat is een taak, geen mail.
        $agreements = FrameworkAgreement::query()
            ->whereNotNull('ends_on')
            ->whereDate('ends_on', '>=', today())
            ->whereDate('ends_on', '<=', $limit)
            ->with('customer.frameworkAgreements')
            ->get();

        foreach ($agreements as $agreement) {
            if ($agreement->customer->frameworkAgreements->contains(fn ($other) => $other->ends_on?->gt($agreement->ends_on))) {
                continue;
            }

            $count += $this->remind(
                $agreement,
                $agreement->ends_on,
                'Raamovereenkomst '.$agreement->number.' met '.$agreement->customer->name,
                null,
                null,
                null,
                route('customers.show', $agreement->customer_id),
            );
        }

        return $count;
    }

    /**
     * Eén herinnering per stuk en per verloopdatum. Met een mailadres gaat er
     * een mail naar wie het moet vernieuwen; anders, of bij een stuk dat we
     * zelf moeten regelen, komt er een taak.
     */
    private function remind(Model $subject, Carbon $date, string $what, ?string $email, ?string $name, ?string $theirs, string $url): int
    {
        if (! AutomationRun::claim($this->key().':'.$date->toDateString(), $subject)) {
            return 0;
        }

        $days = (int) today()->diffInDays($date, false);
        $when = $date->translatedFormat('j F Y');

        $this->act(
            "{$what} verloopt op {$when}, over {$days} ".($days === 1 ? 'dag' : 'dagen').'.',
            function () use ($what, $when, $email, $name, $theirs) {
                if (filled($email) && $theirs !== null) {
                    Mail::to($email)->send(new NoticeMail(
                        'Verloopt binnenkort: '.$what,
                        'Beste '.$name.',',
                        [
                            ucfirst($theirs)." verloopt op {$when}.",
                            'Wilt u ons de vernieuwde versie toesturen voordat het zover is? Zonder geldig stuk kunnen we u niet inplannen op onze projecten.',
                        ],
                    ));

                    return;
                }

                Task::create([
                    'title' => 'Vernieuwen: '.$what,
                    'note' => "Verloopt op {$when}.".(blank($email) && $theirs !== null ? ' Geen mailadres bekend voor een herinnering.' : ''),
                    'owner_id' => User::where('active', true)->get()->first(fn (User $user) => $user->role->isManagement())?->id,
                    'deadline' => today()->addWeek(),
                    'priority' => TaskPriority::Normaal,
                    'source' => ActionSource::Automation,
                ]);
            },
            null,
            $url,
        );

        return 1;
    }
}
