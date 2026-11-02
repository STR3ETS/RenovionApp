<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De geldkant: uitgaande facturen (termijnen, meerwerk, eindafrekening),
 * inkooporders en inkoopfacturen.
 *
 * Een termijnfactuur verwijst naar de termijn in de begroting, zodat je altijd
 * kunt zien welk deel van de aanneemsom al gefactureerd is en welk deel niet.
 * De eindafrekening verrekent meer- en minderwerk en houdt eventueel vijf
 * procent achter volgens het opschortingsrecht van de klant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('termijn');
            $table->string('description');
            $table->date('date');
            $table->date('due_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('vat_pct', 5, 2)->default(21);
            // Vijf procent dat de klant mag achterhouden tot na de onderhoudstermijn.
            $table->decimal('retention', 12, 2)->default(0);
            $table->string('status')->default('concept');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 12)->default('pst');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('supplier');
            $table->string('reference')->nullable();
            $table->string('status')->default('concept');
            $table->date('ordered_on')->nullable();
            $table->date('expected_on')->nullable();
            $table->date('delivered_on')->nullable();
            // Welk hoofdstuk van de begroting dit verbruikt, voor de nacalculatie.
            $table->string('chapter_code', 8)->nullable();
            $table->string('category')->default('materiaal');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 12)->default('st');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->string('supplier');
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->default('materiaal');
            $table->date('date');
            $table->date('due_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('vat_pct', 5, 2)->default(21);
            $table->string('status')->default('nieuw');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->unique(['supplier', 'number']);
        });

        // Minderwerk: werk dat vervalt en van de aanneemsom af gaat.
        Schema::create('project_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('voorstel');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            // Het opschortingsrecht: vijf procent in depot of via bankgarantie,
            // drie maanden na oplevering vrij te geven.
            $table->string('retention_type')->default('nvt')->after('paid_amount');
            $table->decimal('retention_amount', 12, 2)->default(0)->after('retention_type');
            $table->date('retention_release_on')->nullable()->after('retention_amount');
            $table->timestamp('retention_released_at')->nullable()->after('retention_release_on');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['retention_type', 'retention_amount', 'retention_release_on', 'retention_released_at']);
        });

        Schema::dropIfExists('project_deductions');
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
