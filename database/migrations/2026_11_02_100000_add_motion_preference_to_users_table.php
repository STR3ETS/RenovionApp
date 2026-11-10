<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Animaties zijn per gebruiker uit te zetten (briefing hoofdstuk 6).
     * Het besturingssysteem (prefers-reduced-motion) wint altijd.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('motion_enabled')->default(true)->after('onboarding_dismissed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('motion_enabled');
        });
    }
};
