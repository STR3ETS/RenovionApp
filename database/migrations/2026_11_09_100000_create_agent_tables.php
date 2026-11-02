<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nova en de agents: uitvoeringen, goedkeuringen, audit en de instellingen per
 * skill.
 *
 * Het principe uit de briefing is dat een agent voorstelt en een mens beslist.
 * Daarom is een goedkeuring geen vinkje maar een vastgelegd besluit met een
 * hash van waar het over ging. Verandert het voorstel daarna, dan vervalt de
 * goedkeuring: je hebt dan iets anders goedgekeurd dan er nu ligt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('agent');
            $table->string('skill');
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status')->default('gepland');
            $table->string('trigger')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // Wat de uitvoering heeft gekost, zodat je weet wat het model doet.
            $table->decimal('cost', 10, 4)->default(0);
            $table->json('steps')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'agent']);
            $table->index(['project_id', 'skill']);
        });

        Schema::create('agent_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('agent_run_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('agent');
            $table->string('skill');
            $table->string('action');
            $table->text('summary');
            $table->json('payload')->nullable();
            // De vingerafdruk van het voorstel op het moment van vragen.
            $table->string('fingerprint', 64);
            $table->string('status')->default('open');
            $table->string('approver_role')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'agent']);
        });

        Schema::create('agent_events', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            // Wie het deed: een agent of een mens.
            $table->string('actor');
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['type', 'occurred_at']);
            $table->index(['project_id', 'occurred_at']);
        });

        Schema::create('agent_skill_settings', function (Blueprint $table) {
            $table->id();
            $table->string('skill')->unique();
            // Het niveau waarop deze skill mag werken; overschrijft de standaard.
            $table->string('level')->nullable();
            $table->boolean('paused')->default(false);
            // Een bedragsgrens: boven dit bedrag beslist altijd een mens.
            $table->decimal('limit_amount', 12, 2)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_skill_settings');
        Schema::dropIfExists('agent_events');
        Schema::dropIfExists('agent_approvals');
        Schema::dropIfExists('agent_runs');
    }
};
