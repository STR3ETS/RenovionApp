<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calculatieregels met snapshot van prijs en bron (briefing §5: een offerte
 * moet later exact kunnen aantonen met welke prijsset hij is gemaakt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('type');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('surcharge_pct', 5, 2)->default(0);
            $table->foreignId('price_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('price_source')->nullable();
            $table->string('price_edition')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_lines');
    }
};
