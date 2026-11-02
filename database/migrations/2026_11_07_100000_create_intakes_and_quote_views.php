<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intake in vijf stappen, en het bijhouden van wanneer een klant zijn offerte
 * opent.
 *
 * De intake is het verschil tussen een calculatie die klopt en een die later
 * misgaat. Daarom is het een formulier met vaste velden en een score: welke
 * antwoorden heb je, en welke mis je nog.
 *
 * Elke keer dat de klantlink van een offerte geopend wordt, leggen we dat vast.
 * Of en hoe vaak iemand kijkt, zegt iets over de kans dat hij tekent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // Welke stap van de vijf nu aan de beurt is.
            $table->unsignedTinyInteger('step')->default(0);
            // De vijftien antwoorden, als veld => tekst.
            $table->json('answers')->nullable();
            // Wie de opname doet en wanneer.
            $table->foreignId('surveyor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('survey_on')->nullable();
            $table->time('survey_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'completed_at']);
        });

        Schema::create('quote_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['quote_id', 'viewed_at']);
        });

        // Vragen die de klant via de klantlink stelt: een sterk signaal dat hij
        // serieus overweegt, dus weegt het mee in de scoringskans.
        Schema::create('quote_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('asked_by')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->text('answer')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            // Waar deze aanvraag vandaan komt, voor het marketingrendement.
            $table->string('campaign')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('campaign');
        });

        Schema::dropIfExists('quote_questions');
        Schema::dropIfExists('quote_views');
        Schema::dropIfExists('intakes');
    }
};
