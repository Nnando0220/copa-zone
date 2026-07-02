<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_cup_sync_states', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider')->default('openligadb');
            $table->string('scope');
            $table->string('shortcut');
            $table->unsignedSmallInteger('season');
            $table->string('status')->default('idle');
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'scope', 'shortcut', 'season']);
            $table->index(['status']);
        });

        Schema::create('api_sync_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider')->default('openligadb');
            $table->string('operation');
            $table->string('scope')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('calls_count')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'started_at']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_logs');
        Schema::dropIfExists('world_cup_sync_states');
    }
};
