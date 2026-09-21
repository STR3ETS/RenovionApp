<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('service')->nullable();
            $table->text('description')->nullable();
            $table->string('source')->default('website');
            $table->string('status')->default('nieuw');
            $table->decimal('value', 10, 2)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('last_contact_at')->nullable();
            $table->string('next_action')->nullable();
            $table->dateTime('next_action_at')->nullable();
            $table->dateTime('phone_requested_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
