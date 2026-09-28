<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offerte-editor (briefing §6): blokken, klantlink met digitaal ondertekenen
 * en versiebeheer met wijzigingslog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('calculation_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
            $table->json('blocks')->nullable()->after('notes');
            $table->unsignedTinyInteger('version')->default(1)->after('number');
            $table->string('public_token', 64)->nullable()->unique()->after('blocks');
            $table->string('signed_name')->nullable()->after('accepted_at');
            $table->timestamp('signed_at')->nullable()->after('signed_name');
            $table->string('signed_ip', 45)->nullable()->after('signed_at');
            $table->text('change_request')->nullable()->after('signed_ip');
        });

        Schema::table('quote_lines', function (Blueprint $table) {
            $table->boolean('is_estimate')->default(false)->after('total');
        });

        Schema::create('quote_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('version');
            $table->string('note')->nullable();
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['quote_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_versions');

        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropColumn('is_estimate');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calculation_id');
            $table->dropColumn(['blocks', 'version', 'public_token', 'signed_name', 'signed_at', 'signed_ip', 'change_request']);
        });
    }
};
