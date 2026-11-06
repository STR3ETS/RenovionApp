<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gespreksverslagen: Nova neemt een overleg, bouwvergadering of klantgesprek op, laat het transcriberen
 * en maakt er een verslag van (samenvatting, besluiten, acties, vragen) dat de gebruiker controleert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('kind');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('opname');
            $table->timestamp('consent_at');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);

            // Opname: losse stukken van de telefoon worden op volgorde achter elkaar gezet.
            $table->string('audio_path')->nullable();
            $table->string('audio_mime')->nullable();
            $table->unsignedBigInteger('audio_bytes')->default(0);
            $table->unsignedInteger('audio_chunks')->default(0);
            $table->timestamp('audio_deleted_at')->nullable();

            // Transcriptie en verslag
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->json('transcript')->nullable();
            $table->json('speakers')->nullable();
            $table->text('summary')->nullable();
            $table->json('decisions')->nullable();
            $table->json('actions')->nullable();
            $table->json('questions')->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
