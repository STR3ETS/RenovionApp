<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Standaardweergave van de app wordt "telefoon" (volgt de donkere modus van de telefoon), in plaats van licht.
 * Bestaande "licht" wordt ook "telefoon": dat was alleen de standaard, want kiezen werkte op live nog niet
 * (deze kolom bestond daar niet). Wie daarna bewust licht kiest, houdt dat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('app_theme', 10)->default('telefoon')->change();
        });

        DB::table('users')->where('app_theme', 'licht')->update(['app_theme' => 'telefoon']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('app_theme', 10)->default('licht')->change();
        });
    }
};
