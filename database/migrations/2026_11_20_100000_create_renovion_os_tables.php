<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renovion OS: het systeem uit het prototype draait op één gedeelde staat.
 * Elke regel in os_rows is één stuk van die staat (een project, een taak, de
 * instellingen), zodat collega's tegelijk aan verschillende dingen werken
 * zonder elkaars werk te overschrijven. os_changes nummert elke opslag en is
 * meteen het logboek van wie wat wanneer veranderde.
 *
 * De gebruikerskolommen worden alleen toegevoegd als ze er nog niet zijn: op
 * de live database bestaan ze al uit de vorige opzet.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Eén teller voor de hele staat. Wie opslaat, zet hem vast tot zijn
        // opslag binnen is, zodat de nummers op volgorde binnenkomen en geen
        // scherm een wijziging overslaat.
        Schema::create('os_meta', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('rev')->default(0);
        });

        Schema::create('os_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rev')->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->json('paths');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('os_rows', function (Blueprint $table) {
            $table->id();
            $table->string('path', 191)->unique();
            $table->string('collection', 64)->index();
            $table->longText('value')->nullable();
            $table->unsignedBigInteger('rev')->index();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('os_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->string('disk_path');
            $table->foreignId('uploaded_by')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('uitvoerder');
            }
            if (! Schema::hasColumn('users', 'active')) {
                $table->boolean('active')->default(true);
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (! Schema::hasColumn('users', 'person_id')) {
                $table->string('person_id', 64)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os_files');
        Schema::dropIfExists('os_rows');
        Schema::dropIfExists('os_changes');
        Schema::dropIfExists('os_meta');

        if (Schema::hasColumn('users', 'person_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('person_id'));
        }
    }
};
