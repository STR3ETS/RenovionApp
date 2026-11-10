<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kwaliteit, oplevering en nazorg.
 *
 * De oplevering is een keten van acht stappen en elke stap heeft een harde
 * voorwaarde. Je komt niet bij de klantinspectie zolang er restpunten open
 * staan, en niet bij ondertekening zolang de controlepunten niet af zijn.
 *
 * Restpunten zijn het verschil tussen een oplevering die klaar is en een die
 * "bijna" klaar is. Ze krijgen daarom een eigen plek met locatie, foto en wie
 * het oplost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            // Het template dat bij dit soort werk hoort.
            $table->string('template')->default('renovatie');
            // Valt dit project onder de Wet kwaliteitsborging? Dan komen er vier
            // stukken bij die de gemeente wil zien.
            $table->boolean('wkb')->default(false);
            $table->unsignedTinyInteger('step')->default(0);
            $table->string('signed_name')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quality_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_review_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // Of dit een Wkb-punt is, want die hoeven alleen bij zulke projecten.
            $table->boolean('wkb')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('snag_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quality_review_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            // Wie het oplost: eigen mens of de partij die het werk deed.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subcontractor_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            // Komt het van de klant of van onze eigen controle?
            $table->string('source')->default('intern');
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('nieuw');
            $table->string('priority')->default('normaal');
            // Valt dit onder garantie? Dan is het voor onze rekening.
            $table->boolean('warranty')->default(true);
            $table->string('warranty_note')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('planned_on')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reported_via')->default('telefoon');
            $table->timestamps();

            $table->index(['status', 'priority']);
        });

        // Foto's kunnen nu ook bij een restpunt of een servicemelding horen.
        Schema::table('photos', function (Blueprint $table) {
            $table->foreignId('snag_item_id')->nullable()->after('job_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_ticket_id')->nullable()->after('snag_item_id')->constrained()->cascadeOnDelete();
        });

        // De garantietermijn per project, zodat nazorg weet wat nog onder garantie valt.
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('warranty_months')->default(12)->after('retention_released_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('warranty_months');
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['snag_item_id']);
            $table->dropForeign(['service_ticket_id']);
            $table->dropColumn(['snag_item_id', 'service_ticket_id']);
        });

        Schema::dropIfExists('service_tickets');
        Schema::dropIfExists('snag_items');
        Schema::dropIfExists('quality_checks');
        Schema::dropIfExists('quality_reviews');
    }
};
