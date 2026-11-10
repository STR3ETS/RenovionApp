<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Werkbonnen: het bewijsstuk van een dag werk. De uitvoerder dient hem in vanaf
 * de bouwplaats (uren, materiaal, checklist, handtekening van de klant), de
 * projectleider keurt hem goed. Pas na goedkeuring tellen de uren mee in de
 * kosten, de voortgang en later de nacalculatie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_execution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('concept');
            // Geboekte uren tegenover de uren uit de begroting voor dit werk.
            $table->decimal('hours', 6, 2)->default(0);
            $table->decimal('budget_hours', 6, 2)->default(0);
            // Afgesproken uurtarief op het moment van goedkeuren, zodat een latere
            // tariefwijziging de historie niet verandert.
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->json('materials')->nullable();
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->string('signed_name')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['user_id', 'date']);
        });

        // Foto-bewijs hangt nooit los: een foto kan nu ook bij een klusstap of een
        // melding horen, naast fase, werkpakket en checklistitem.
        Schema::table('photos', function (Blueprint $table) {
            $table->foreignId('job_step_id')->nullable()->after('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_report_id')->nullable()->after('job_step_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['job_step_id']);
            $table->dropForeign(['job_report_id']);
            $table->dropColumn(['job_step_id', 'job_report_id']);
        });

        Schema::dropIfExists('work_orders');
    }
};
