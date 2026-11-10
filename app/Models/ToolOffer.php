<?php

namespace App\Models;

use App\Support\OfferToolAccess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eén offerte uit de offertetool: de begroting en de offerte die de klant
 * tekent, als één JSON-model dat de tool zelf beheert.
 */
#[Fillable(['number', 'model', 'rev', 'saved_at', 'saved_by'])]
class ToolOffer extends Model
{
    public const NUMBER_PATTERN = '[A-Za-z0-9][A-Za-z0-9-]{0,39}';

    private ?object $decoded = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rev' => 'integer',
            'saved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ToolOfferSignature::class)->latest();
    }

    /**
     * Het model als objecten. Bewust geen arrays: een leeg object ({}) zou
     * anders als lege lijst ([]) teruggaan en dat breekt de tool.
     */
    public function data(): ?object
    {
        if ($this->decoded === null && filled($this->model)) {
            $decoded = json_decode($this->model);
            $this->decoded = is_object($decoded) ? $decoded : null;
        }

        return $this->decoded;
    }

    public function putData(object $model): void
    {
        $this->model = json_encode($model, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->decoded = null;
    }

    /** Het model voor de klant: zonder de personen en hun code-hashes. */
    public function dataForClient(): ?object
    {
        $model = filled($this->model) ? json_decode($this->model) : null;

        if (! is_object($model)) {
            return null;
        }

        if (isset($model->access) && is_object($model->access)) {
            $model->access->persons = [];
        }

        return $model;
    }

    public function project(): string
    {
        return (string) ($this->data()?->meta?->project ?? '');
    }

    public function client(): string
    {
        return (string) ($this->data()?->meta?->opdrachtgever ?? '');
    }

    /** concept, preview, verzonden of getekend; null zolang er geen offerte van is gemaakt. */
    public function status(): ?string
    {
        return $this->data()?->offerte?->status ?? null;
    }

    public function version(): ?int
    {
        $version = $this->data()?->offerte?->versie ?? null;

        return $version === null ? null : (int) $version;
    }

    /**
     * Hoeveel mensen er nu met een code in kunnen.
     */
    public function openAccessCount(): int
    {
        $model = $this->data();

        return count(array_filter(
            OfferToolAccess::persons($model),
            fn (object $person) => OfferToolAccess::state($model, $person) === OfferToolAccess::OPEN,
        ));
    }
}
