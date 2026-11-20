<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De aanmelding van een bouw: de vooraanmelding bij de opdrachtgever (GPI),
 * het kantoor en het aanmelden op de klus zelf, plus het mailpakket met alle
 * documenten dat naar de WKA-mailbox gaat.
 */
#[Fillable([
    'project_id', 'gpi_registered_on', 'office_registered_on', 'site_registered_on',
    'contact_name', 'contact_phone', 'package_sent_at', 'package_sent_by', 'notes',
])]
class SiteRegistration extends Model
{
    /** De drie aanmeldingen, in de volgorde waarin ze gebeuren. */
    public const STEPS = [
        'gpi_registered_on' => ['GPI vooraanmelding', 'Mensen en bedrijven vooraf aangemeld bij de opdrachtgever'],
        'office_registered_on' => ['Aangemeld bij kantoor', 'Het kantoor weet wie er wanneer op de bouw is'],
        'site_registered_on' => ['Aangemeld op de klus', 'Gemeld bij de uitvoerder of de poort op de eerste dag'],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gpi_registered_on' => 'date',
            'office_registered_on' => 'date',
            'site_registered_on' => 'date',
            'package_sent_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'package_sent_by');
    }

    public function doneSteps(): int
    {
        return collect(array_keys(self::STEPS))->filter(fn (string $field) => $this->{$field} !== null)->count();
    }

    public function isComplete(): bool
    {
        return $this->doneSteps() === count(self::STEPS) && $this->package_sent_at !== null;
    }
}
