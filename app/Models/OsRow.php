<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eén stuk van de staat van Renovion Groep. value is JSON, of null als het stuk
 * is verwijderd (dan weten de andere schermen bij de volgende ronde dat het
 * weg moet). rev is het nummer van de opslag die het laatst iets veranderde.
 */
class OsRow extends Model
{
    protected $fillable = ['path', 'collection', 'value', 'rev', 'updated_by'];

    protected function casts(): array
    {
        return ['rev' => 'integer'];
    }
}
