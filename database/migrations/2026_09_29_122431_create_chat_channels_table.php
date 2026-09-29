<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teamchat (briefing §11): teamkanalen en projectkanalen in één interface,
 * met per gebruiker een leesmarkering voor de unread counters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_channels', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('team');
            $table->string('name');
            $table->foreignId('project_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('chat_channel_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_channel_id', 'user_id']);
        });

        // Vast teamkanaal + een kanaal per bestaand project.
        DB::table('chat_channels')->insert([
            'type' => 'team',
            'name' => 'Algemeen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->orderBy('id')->get(['id', 'name'])->each(function ($project) {
            DB::table('chat_channels')->insert([
                'type' => 'project',
                'name' => $project->name,
                'project_id' => $project->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_channel_user');
        Schema::dropIfExists('chat_channels');
    }
};
