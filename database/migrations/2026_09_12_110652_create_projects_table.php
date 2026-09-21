<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('status')->default('voorbereiding')->index();
            $table->decimal('value', 10, 2)->default(0);
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->dateTime('deposit_received_at')->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->date('next_payment_due_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date_expected')->nullable();
            $table->date('end_date_actual')->nullable();
            $table->foreignId('project_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::dropIfExists('projects');
    }
};
