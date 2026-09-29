<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per Nova-rule instelbare modus (briefing §12): uit / signaleren /
 * bevestigen / automatisch. Zonder rij geldt de default van de automation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('mode', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_settings');
    }
};
