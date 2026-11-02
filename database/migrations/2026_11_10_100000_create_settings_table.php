<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instellingen die je zonder deploy wilt kunnen wijzigen: bedrijfsgegevens,
 * standaardpercentages en betaaltermijnen.
 *
 * Wat in .env hoort (sleutels, tokens, mailserver) staat hier bewust niet in:
 * dat zijn geheimen, en die horen niet in een database die iedereen met
 * beheerrechten kan openen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('bedrijf');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
