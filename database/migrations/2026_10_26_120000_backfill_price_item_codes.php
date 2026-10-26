<?php

use App\Models\PriceItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Prijslijst: bestaande items zonder artikelnummer krijgen er een
     * (ARB-001, MAT-002, ...). Nieuwe items krijgen hun nummer automatisch
     * via PriceItem::booted.
     */
    public function up(): void
    {
        PriceItem::whereNull('code')
            ->orWhere('code', '')
            ->orderBy('id')
            ->get()
            ->each(function (PriceItem $item) {
                $item->forceFill(['code' => PriceItem::nextCode($item->type->value)])->save();
            });
    }

    public function down(): void
    {
        // Artikelnummers laten staan; terugdraaien zou verwijzingen breken.
    }
};
