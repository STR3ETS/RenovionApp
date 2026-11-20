<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uitbreidingen uit de briefing van ronde 28 tot en met 34: klanttype en
     * betaaltermijn, raamovereenkomsten, agenda, leadopvolging, marge per bod,
     * urenstaat, meerwerk als eigen opdracht, de aanvraagmail en de aanmelding
     * van de bouw.
     *
     * Waar de bestaande basis al een plek had, gebruiken we die: afspraken
     * blijven planningsblokken, de volgende stap blijft op de aanvraag staan en
     * het contract (aangenomen of regie) staat op het project zelf.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Particulier = klussen, zakelijk = partijen voor wie we werken.
            $table->string('type')->default('particulier')->after('name');
            $table->string('number', 20)->nullable()->unique()->after('id');
            $table->string('contact_name')->nullable()->after('type');
            $table->string('coc_number', 20)->nullable()->after('city');
            $table->string('vat_number', 30)->nullable()->after('coc_number');
            $table->unsignedSmallInteger('payment_days')->default(14)->after('vat_number');
            $table->boolean('reminders')->default(true)->after('payment_days');
            // Voor wie werken we namens deze klant, of via wie kwam hij binnen.
            $table->string('relation')->nullable()->after('reminders');
            // De persoonlijke link waarmee een zakelijke klant een opdracht inschiet.
            $table->string('portal_token', 64)->nullable()->unique()->after('relation');
        });

        // Bestaande klanten krijgen hun nummer op dezelfde manier als nieuwe: uit hun id.
        foreach (DB::table('customers')->orderBy('id')->pluck('id') as $id) {
            DB::table('customers')->where('id', $id)->update(['number' => sprintf('K-%04d', $id)]);
        }

        Schema::create('framework_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            // Aan: een nieuwe begroting voor deze klant start met de vaste prijzen.
            $table->boolean('preset_active')->default(true);
            // Vaste prijzen zijn verkoopprijzen, dus de opslagen staan standaard op nul.
            $table->decimal('overhead_pct', 5, 2)->default(0);
            $table->decimal('profit_pct', 5, 2)->default(0);
            $table->unsignedSmallInteger('payment_days')->default(30);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('framework_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('framework_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('unit', 20)->default('m2');
            $table->decimal('fixed_price', 10, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('framework_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('framework_agreement_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('price_items', function (Blueprint $table) {
            // Een groep binnen de prijslijst, zoals Wandwerk of Plafonds.
            $table->string('group')->nullable()->after('type');
        });

        // Een afspraak kan meer deelnemers hebben dan alleen de eigenaar van het blok.
        Schema::create('schedule_entry_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['schedule_entry_id', 'user_id']);
        });

        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->timestamp('done_at')->nullable()->after('notes');
        });

        Schema::table('tasks', function (Blueprint $table) {
            // Een taak met een tijd komt op het agendaraster te staan.
            $table->time('due_time')->nullable()->after('deadline');
        });

        Schema::table('leads', function (Blueprint $table) {
            // Wat de volgende stap is: bellen, terugbellen, afspraak, mail of offerte.
            $table->string('next_action_type')->nullable()->after('next_action');
            // Hoe vaak achter elkaar geen gehoor sinds het laatste gesprek.
            $table->unsignedSmallInteger('missed_calls')->default(0)->after('next_action_at');
        });

        Schema::table('projects', function (Blueprint $table) {
            // Aangenomen werk heeft een vaste prijs; bij regie rekenen we uren en materiaal af.
            $table->string('contract_form')->default('aangenomen')->after('type');
            $table->decimal('regie_hourly_rate', 8, 2)->nullable()->after('contract_form');
            $table->decimal('regie_day_rate', 8, 2)->nullable()->after('regie_hourly_rate');
            // Aftekenen legt het aantal uren vast. Wijzigt dat daarna, dan opnieuw aftekenen.
            $table->foreignId('hours_signed_by')->nullable()->after('regie_day_rate')->constrained('users')->nullOnDelete();
            $table->timestamp('hours_signed_at')->nullable()->after('hours_signed_by');
            $table->decimal('hours_signed_total', 8, 2)->nullable()->after('hours_signed_at');
            // Een opdracht die buiten het systeem om is gegeven, bijvoorbeeld op papier.
            $table->timestamp('order_confirmed_at')->nullable()->after('hours_signed_total');
        });

        // Wat al loopt, blijft lopen: bestaande projecten gelden als in opdracht.
        DB::table('projects')->update(['order_confirmed_at' => DB::raw('created_at')]);

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Komt de regel uit een goedgekeurde werkbon, dan hangt hij daaraan.
            $table->foreignId('work_order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->date('date');
            $table->decimal('hours', 6, 2);
            $table->string('description')->nullable();
            // Uren die al op een regiefactuur staan, rekenen we niet nog eens af.
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'date']);
        });

        Schema::create('regie_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('st');
            $table->decimal('price', 10, 2)->default(0);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Meerwerk als eigen opdracht onder het projectnummer, met eigen akkoord en factuur.
        Schema::create('extra_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40)->unique();
            $table->string('description');
            $table->text('details')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('concept');
            $table->string('public_token', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_name')->nullable();
            $table->string('approved_ip', 45)->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('rfq_bids', function (Blueprint $table) {
            // Waarvoor we dit werk zelf verkopen: daaruit volgt de marge van het bod.
            $table->decimal('sale_quantity', 10, 2)->nullable()->after('price');
            $table->string('sale_unit', 20)->nullable()->after('sale_quantity');
            $table->decimal('sale_price', 12, 2)->nullable()->after('sale_unit');
            $table->boolean('via_whatsapp')->default(false)->after('notes');
            // Wie een bod onder de bodem toch goedkeurde, en waarom.
            $table->json('margin_approval')->nullable()->after('via_whatsapp');
            $table->foreignId('purchase_order_id')->nullable()->after('margin_approval')->constrained()->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            // Een order aan een onderaannemer, ontstaan uit een gegunde prijsaanvraag.
            $table->foreignId('subcontractor_id')->nullable()->after('supplier')->constrained()->nullOnDelete();
            $table->foreignId('rfq_id')->nullable()->after('subcontractor_id')->constrained()->nullOnDelete();
            // Zonder foto's voor, tijdens en na keuren we de factuur niet goed.
            $table->boolean('photo_required')->default(false)->after('rfq_id');
        });

        Schema::create('inbound_mails', function (Blueprint $table) {
            $table->id();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status')->default('nieuw');
            // Via de mail of via de persoonlijke portaallink van een zakelijke klant.
            $table->string('source')->default('mail');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            // Wat Nova eruit las: werk, adres, deadline en een voorstel voor de klant.
            $table->json('extracted')->nullable();
            // Het bericht-id van de maildienst, zodat dezelfde mail niet twee keer binnenkomt.
            $table->string('message_id')->nullable()->unique();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('site_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            // De vooraanmelding bij de opdrachtgever (GPI), het kantoor en op de klus zelf.
            $table->date('gpi_registered_on')->nullable();
            $table->date('office_registered_on')->nullable();
            $table->date('site_registered_on')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamp('package_sent_at')->nullable();
            $table->foreignId('package_sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('project_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'subcontractor_id', 'rfq_id']);
        });

        // Doorlopend werk: een raamovereenkomst met een onderaannemer loopt zes maanden.
        Schema::create('subcontractor_frameworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Het logboek per onderaannemer: gegund, niet gegund, vastgelegd.
        Schema::create('subcontractor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('body');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('logged_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontractor_logs');
        Schema::dropIfExists('subcontractor_frameworks');
        Schema::dropIfExists('project_agreements');
        Schema::dropIfExists('site_registrations');
        Schema::dropIfExists('inbound_mails');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rfq_id');
            $table->dropConstrainedForeignId('subcontractor_id');
            $table->dropColumn('photo_required');
        });

        Schema::table('rfq_bids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
            $table->dropColumn(['sale_quantity', 'sale_unit', 'sale_price', 'via_whatsapp', 'margin_approval']);
        });

        Schema::dropIfExists('extra_works');
        Schema::dropIfExists('regie_materials');
        Schema::dropIfExists('time_entries');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hours_signed_by');
            $table->dropColumn([
                'contract_form', 'regie_hourly_rate', 'regie_day_rate',
                'hours_signed_at', 'hours_signed_total', 'order_confirmed_at',
            ]);
        });

        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['next_action_type', 'missed_calls']));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('due_time'));
        Schema::table('schedule_entries', fn (Blueprint $table) => $table->dropColumn('done_at'));
        Schema::dropIfExists('schedule_entry_user');
        Schema::table('price_items', fn (Blueprint $table) => $table->dropColumn('group'));

        Schema::dropIfExists('framework_terms');
        Schema::dropIfExists('framework_prices');
        Schema::dropIfExists('framework_agreements');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropUnique(['portal_token']);
            $table->dropColumn([
                'type', 'number', 'contact_name', 'coc_number', 'vat_number',
                'payment_days', 'reminders', 'relation', 'portal_token',
            ]);
        });
    }
};
