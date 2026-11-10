<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Twee takken voegden elk een animatie-voorkeur toe: "motion" (app, Instellingen) en "motion_enabled"
 * (website, de bliksemknop). Na het samenvoegen is er één: motion_enabled. Wie animaties al had
 * uitgezet, houdt ze uit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'motion')) {
            return;
        }

        DB::table('users')->where('motion', false)->update(['motion_enabled' => false]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('motion');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('motion')->default(true)->after('notification_preferences');
        });

        DB::table('users')->where('motion_enabled', false)->update(['motion' => false]);
    }
};
