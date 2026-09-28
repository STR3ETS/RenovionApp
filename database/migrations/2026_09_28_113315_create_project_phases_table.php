<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projectfasering volgens briefing v2 §7: elk project doorloopt de vaste
 * fasen 0–8, elk met eigen status, planning en verantwoordelijke.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('name');
            $table->string('status')->default('niet_gestart');
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'position']);
        });

        // Backfill: bestaande projecten krijgen de standaardfasen, fase 0 start direct.
        $names = [
            'Opdracht & overdracht', 'Voortraject', 'Werkvoorbereiding', 'Inkoop & planning',
            'Uitvoering', 'Controle & klantacties', 'Vooroplevering', 'Oplevering', 'Nazorg',
        ];

        DB::table('projects')->orderBy('id')->pluck('id')->each(function (int $projectId) use ($names) {
            DB::table('project_phases')->insert(collect($names)->map(fn (string $name, int $position) => [
                'project_id' => $projectId,
                'position' => $position,
                'name' => $name,
                'status' => $position === 0 ? 'bezig' : 'niet_gestart',
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phases');
    }
};
