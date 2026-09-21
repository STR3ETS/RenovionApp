<?php

namespace App\Models;

use App\Enums\ActionSource;
use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id', 'service', 'description', 'source', 'status', 'value',
    'assigned_to', 'last_contact_at', 'next_action', 'next_action_at',
    'phone_requested_at', 'lost_reason', 'position',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => ActionSource::class,
            'value' => 'decimal:2',
            'last_contact_at' => 'datetime',
            'next_action_at' => 'datetime',
            'phone_requested_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereNotIn('status', [LeadStatus::Project, LeadStatus::Verloren]);
    }

    public function missingPhone(): bool
    {
        return blank($this->customer->phone);
    }

    public function needsFollowUpToday(): bool
    {
        return $this->next_action_at !== null && $this->next_action_at->isToday();
    }
}
