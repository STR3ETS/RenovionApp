<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Briefing v2 §4: een aanvraag wordt eerst gekwalificeerd voordat de
 * opname/het gesprek volgt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('qualification')->default('onbeoordeeld')->after('status');
            $table->string('desired_start')->nullable()->after('qualification');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['qualification', 'desired_start']);
        });
    }
};
