<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Briefing v2 (§15) hernoemt de rol "vakman" naar "uitvoerder".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'vakman')->update(['role' => 'uitvoerder']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('uitvoerder')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('vakman')->change();
        });

        DB::table('users')->where('role', 'uitvoerder')->update(['role' => 'vakman']);
    }
};
