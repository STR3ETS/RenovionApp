<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatisch opleverrapport (briefing §13): snapshot van projectdata,
 * foto-bewijs en restpunten, met digitale handtekening van klant en Renovion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('company_signed_at')->nullable();
            $table->string('client_signed_name')->nullable();
            $table->timestamp('client_signed_at')->nullable();
            $table->string('client_signed_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_reports');
    }
};
