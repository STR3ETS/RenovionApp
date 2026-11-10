<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twee velden die het prototype overal gebruikt en die wij nog niet hadden:
 *
 * - planningsblokken lopen vaak meerdere dagen ("Voorzetwanden, ma t/m wo"),
 *   dus krijgen ze een einddatum naast de startdatum;
 * - een project heeft een soort (badkamerrenovatie, aanbouw, stucwerk). Daarop
 *   kiezen we checklists, kwaliteitstemplates en vergelijken we in rapportages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('date');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
