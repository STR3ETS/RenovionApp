<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Een foto of document uit het systeem (klusfoto, bijlage, handtekening).
 * Het bestand staat in de privé-opslag; de staat bewaart alleen de link.
 */
class OsFile extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'name', 'mime', 'size', 'disk_path', 'uploaded_by'];

    public function url(): string
    {
        return '/os/bestanden/'.$this->id;
    }
}
