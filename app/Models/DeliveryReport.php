<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id', 'snapshot', 'generated_at', 'generated_by',
    'company_signed_by', 'company_signed_at',
    'client_signed_name', 'client_signed_at', 'client_signed_ip',
])]
class DeliveryReport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'generated_at' => 'datetime',
            'company_signed_at' => 'datetime',
            'client_signed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function companySigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_signed_by');
    }

    public function isSignedByClient(): bool
    {
        return $this->client_signed_at !== null;
    }

    public function isFullySigned(): bool
    {
        return $this->isSignedByClient() && $this->company_signed_at !== null;
    }
}
