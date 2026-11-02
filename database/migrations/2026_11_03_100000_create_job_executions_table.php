<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mijn klussen: de werkweergave voor op de bouwplaats. Een klus hangt aan een
 * planningsblok (schedule_entries) en voegt daar de uitvoering aan toe: status,
 * veiligheidscheck, stappen met foto-bewijs en meldingen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('gepland');
            // De LMRA: pas na vijf keer ja mag het werk beginnen.
            $table->timestamp('lmra_confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // Som van de pauzes in minuten, zodat de urenschatting klopt.
            $table->unsignedInteger('paused_minutes')->default(0);
            $table->timestamp('paused_at')->nullable();
            $table->decimal('hours', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('job_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_execution_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title');
            $table->string('hint')->nullable();
            // Verplichte foto: de stap kan niet af zonder bewijs.
            $table->boolean('photo_required')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['job_execution_id', 'position']);
        });

        Schema::create('job_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_execution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->text('body');
            // Spoed betekent dat het werk stilligt.
            $table->boolean('urgent')->default(false);
            $table->string('status')->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index(['status', 'urgent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_reports');
        Schema::dropIfExists('job_steps');
        Schema::dropIfExists('job_executions');
    }
};
