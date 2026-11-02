<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensen en partners: certificaten en afwezigheid van eigen mensen,
 * onderaannemers met hun dossier, en prijsaanvragen met biedingen.
 *
 * Een certificaat of dossierstuk dat verloopt is geen administratief detail: wie
 * zonder geldige VCA de bouwplaats op gaat, is een risico. Daarom kent elk stuk
 * een geldigheidsdatum en waarschuwt het systeem dertig dagen vooruit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->nullable();
            $table->string('type');
            $table->string('number')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('path')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'valid_until']);
        });

        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('vakantie');
            $table->date('from');
            $table->date('until');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'from']);
        });

        Schema::create('subcontractors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('coc_number')->nullable();
            $table->string('vat_number')->nullable();
            // De vakken waarvoor we deze partij uitnodigen bij een prijsaanvraag.
            $table->json('trades')->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->string('region')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedSmallInteger('assignments')->default(0);
            // Geblokkeerd betekent: niet uitnodigen, niet inplannen.
            $table->boolean('blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->boolean('id_checked')->default(false);
            // Een eigen inlog voor het partnerportaal, optioneel.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('subcontractor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('path')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_by')->nullable();
            $table->timestamps();

            $table->unique(['subcontractor_id', 'type']);
        });

        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('chapter_code', 8)->nullable();
            $table->text('scope')->nullable();
            // Wat ervoor begroot staat: daartegen beoordeel je de biedingen.
            $table->decimal('budget', 12, 2)->default(0);
            $table->string('status')->default('concept');
            $table->date('deadline')->nullable();
            $table->date('starts_on')->nullable();
            $table->foreignId('awarded_to')->nullable();
            $table->string('order_number')->nullable();
            $table->date('awarded_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('rfq_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            $table->unique(['rfq_id', 'subcontractor_id']);
        });

        Schema::create('rfq_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->date('can_start_on')->nullable();
            $table->unsignedSmallInteger('days')->nullable();
            $table->text('conditions')->nullable();
            // Hoeveel procent van de gevraagde posten geprijsd is: een bieding die
            // niet compleet is, is niet vergelijkbaar.
            $table->unsignedTinyInteger('completeness')->default(100);
            $table->text('notes')->nullable();
            $table->date('received_on')->nullable();
            $table->timestamps();

            $table->unique(['rfq_id', 'subcontractor_id']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('subcontractor_id')->references('id')->on('subcontractors')->cascadeOnDelete();
        });

        Schema::table('rfqs', function (Blueprint $table) {
            $table->foreign('awarded_to')->references('id')->on('subcontractors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_bids');
        Schema::dropIfExists('rfq_invitations');
        Schema::dropIfExists('rfqs');
        Schema::dropIfExists('subcontractor_documents');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('subcontractors');
        Schema::dropIfExists('absences');
    }
};
