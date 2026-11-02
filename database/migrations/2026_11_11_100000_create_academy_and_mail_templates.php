<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De Academy houdt per gebruiker bij welke rondleiding af is, en de mailbuilder
 * bewaart de teksten die naar klanten gaan.
 *
 * Een mailtemplate bestaat uit blokken met variabelen erin. Zo staat de tekst op
 * één plek in plaats van in de hoofden van vier mensen, en kan er niemand een
 * bedrag of een datum vergeten in te vullen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour');
            $table->unsignedTinyInteger('step')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour']);
        });

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            // Wanneer je deze mail stuurt, zodat niemand hoeft te gokken.
            $table->string('moment')->nullable();
            $table->json('blocks')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
        Schema::dropIfExists('academy_progress');
    }
};
