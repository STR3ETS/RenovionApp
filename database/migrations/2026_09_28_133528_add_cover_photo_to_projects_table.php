<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Omslagfoto per project: de fotokaarten uit de mockups (§18) tonen deze;
 * later kan foto-bewijs de omslag automatisch aanleveren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('cover_photo_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('cover_photo_path');
        });
    }
};
