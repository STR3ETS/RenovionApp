<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personeelsgegevens die de module Mensen en de werkbonnen nodig hebben: het
 * uurtarief waarmee uren omgerekend worden naar kosten, de weekcapaciteit voor
 * de capaciteitsweergave, en het vak waarop we werk toewijzen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('role');
            // Het vak waarop deze persoon inzetbaar is (tegelwerk, elektra, stucwerk).
            $table->json('trades')->nullable()->after('job_title');
            $table->decimal('hourly_rate', 8, 2)->nullable()->after('trades');
            // Inzetbare uren per week; 0 betekent kantoor en dus niet planbaar.
            $table->unsignedSmallInteger('weekly_capacity')->default(40)->after('hourly_rate');
            $table->boolean('plannable')->default(true)->after('weekly_capacity');
            $table->date('started_on')->nullable()->after('plannable');
            $table->boolean('active')->default(true)->after('started_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'job_title', 'trades', 'hourly_rate', 'weekly_capacity',
                'plannable', 'started_on', 'active',
            ]);
        });
    }
};
