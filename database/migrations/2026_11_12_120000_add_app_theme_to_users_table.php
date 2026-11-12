<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weergave in de iOS/Android-app: licht, donker of volgens de telefoon. Per gebruiker, zodat de schermen
 * in de app en de serverpagina's in de app (andere origin, dus geen gedeelde opslag) hetzelfde tonen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('app_theme', 10)->default('licht')->after('motion_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('app_theme');
        });
    }
};
