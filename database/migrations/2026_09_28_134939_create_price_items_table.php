<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kostendatabase (briefing §5): beheerde prijsbibliotheek met bron,
 * editie/jaar, eenheid, indexfactor, opslag en datum laatste controle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_items', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('edition');
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('type');
            $table->string('unit', 20);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('surcharge_pct', 5, 2)->default(0);
            $table->decimal('index_factor', 6, 3)->default(1);
            $table->boolean('active')->default(true);
            $table->date('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_items');
    }
};
