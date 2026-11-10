<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De offertetool (begroting en offerte in één scherm) bewaart per offerte
     * zijn hele model als JSON, met een revisienummer tegen dubbel opslaan.
     */
    public function up(): void
    {
        Schema::create('tool_offers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            // Rauwe JSON zoals de tool hem opslaat. Bewust geen json-cast: lege
            // objecten moeten objecten blijven en geen lege lijsten worden.
            $table->longText('model')->nullable();
            $table->unsignedInteger('rev')->default(0);
            $table->timestamp('saved_at')->nullable();
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tool_offer_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_offer_id')->constrained()->cascadeOnDelete();
            // Wie er was ingelogd met een toegangscode.
            $table->string('person_id')->nullable();
            $table->string('person_name')->nullable();
            // Wat de ondertekenaar zelf invulde.
            $table->string('name');
            $table->string('function')->nullable();
            $table->string('place')->nullable();
            $table->date('signed_on');
            $table->string('offer_number')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->longText('signature');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_offer_signatures');
        Schema::dropIfExists('tool_offers');
    }
};
