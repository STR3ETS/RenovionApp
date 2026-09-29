<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Briefing §9: de klant tekent bij mijlpalen digitaal "gezien en akkoord";
 * datum/tijd en ondertekenaar worden vastgelegd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_phases', function (Blueprint $table) {
            $table->timestamp('client_approved_at')->nullable()->after('gate_note');
            $table->foreignId('client_approved_by')->nullable()->after('client_approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_phases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_approved_by');
            $table->dropColumn('client_approved_at');
        });
    }
};
