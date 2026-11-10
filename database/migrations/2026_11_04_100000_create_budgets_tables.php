<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Begrotingen met hoofdstukken, zoals de begrotingstool van Renovion.
 *
 * De opbouw is hoofdstuk (nummer en naam) -> paragraaf -> regel. Een regel
 * rekent met de formule q * (n * tarief + m + e + o) + v: hoeveelheid maal de
 * som van normuren tegen tarief, materiaal, materieel en onderaanneming, plus
 * een vast bedrag voor overige kosten.
 *
 * Daarboven komt de staart: algemene kosten, winst en risico en verzekering.
 * Die rekenen cumulatief door, elk over het bedrag inclusief de vorige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('concept');
            $table->unsignedSmallInteger('version')->default(1);
            // Het uurtarief waarmee normuren naar arbeidskosten gaan.
            $table->decimal('hourly_rate', 8, 2)->default(55);
            $table->decimal('vat_pct', 5, 2)->default(21);
            // Bruto vloeroppervlak, voor de prijs per vierkante meter.
            $table->decimal('floor_area', 10, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('author')->nullable();
            $table->date('dated_on')->nullable();
            // Wat er wel en niet in zit, en op welke documenten de begroting rust.
            $table->json('scope')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('finalised_at')->nullable();
            $table->foreignId('finalised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('budget_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            // Het hoofdstuknummer uit de standaard, bijvoorbeeld 21 voor binnenwanden.
            $table->string('code', 8);
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['budget_id', 'position']);
        });

        Schema::create('budget_paragraphs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_chapter_id')->constrained()->cascadeOnDelete();
            $table->string('code', 8)->default('');
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['budget_chapter_id', 'position']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_paragraph_id')->constrained()->cascadeOnDelete();
            // Een tekstregel rekent niet mee: die staat er voor de leesbaarheid.
            $table->string('type')->default('regel');
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('unit', 12)->default('st');
            // Normuren per eenheid, en de vier kostensoorten per eenheid.
            $table->decimal('norm_hours', 10, 4)->default(0);
            $table->decimal('material', 12, 2)->default(0);
            $table->decimal('equipment', 12, 2)->default(0);
            $table->decimal('subcontracting', 12, 2)->default(0);
            // Overige kosten: een vast bedrag per eenheid.
            $table->decimal('other', 12, 2)->default(0);
            // Een eigen tarief voor deze regel, bijvoorbeeld voor speciaal werk.
            $table->decimal('hourly_rate', 8, 2)->nullable();
            // Waar de prijs vandaan komt: prijslijst, eigen norm, aanname.
            $table->string('source')->nullable();
            $table->foreignId('price_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['budget_paragraph_id', 'position']);
        });

        // De staart: cumulatieve opslagen over de directe kosten.
        Schema::create('budget_markups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('percentage', 6, 3)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Opties en meerwerk: staan buiten het totaal tot de klant akkoord geeft.
        Schema::create('budget_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->text('note')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 12)->default('pst');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('status')->default('optie');
            $table->timestamp('decided_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Termijnschema: percentages van de aanneemsom, gekoppeld aan mijlpalen.
        Schema::create('budget_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('percentage', 6, 3)->default(0);
            // Een vaste termijn rekent met een bedrag in plaats van een percentage.
            $table->boolean('fixed')->default(false);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('gepland');
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_terms');
        Schema::dropIfExists('budget_options');
        Schema::dropIfExists('budget_markups');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budget_paragraphs');
        Schema::dropIfExists('budget_chapters');
        Schema::dropIfExists('budgets');
    }
};
