<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welke signalen een agent al heeft opgepakt (zodat hetzelfde punt niet
 * steeds opnieuw geld kost) en wat elke uitvoering kostte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('os_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->string('signal_key', 191)->unique();
            $table->string('agent', 20);
            $table->string('skill', 60);
            $table->string('project_id', 40)->nullable();
            $table->string('run_id', 40)->nullable();
            $table->string('uitkomst', 40);
            $table->decimal('kosten', 8, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os_agent_runs');
    }
};
