<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_editions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedSmallInteger('season');
            $table->string('provider')->default('openligadb');
            $table->string('provider_league_id');
            $table->string('status')->default('configured');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_league_id', 'season']);
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('country')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('provider')->default('openligadb');
            $table->string('provider_team_id');
            $table->timestamps();

            $table->unique(['provider', 'provider_team_id']);
            $table->index(['name']);
        });

        Schema::create('tournament_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_edition_id')->constrained('tournament_editions')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['tournament_edition_id', 'name']);
        });

        Schema::create('matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_edition_id')->constrained('tournament_editions')->cascadeOnDelete();
            $table->foreignUuid('tournament_group_id')->nullable()->constrained('tournament_groups')->nullOnDelete();
            $table->foreignUuid('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignUuid('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('provider')->default('openligadb');
            $table->string('provider_fixture_id');
            $table->timestamp('starts_at')->nullable();
            $table->string('timezone')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('round')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('status_short')->nullable();
            $table->unsignedSmallInteger('elapsed')->nullable();
            $table->integer('home_score')->nullable();
            $table->integer('away_score')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_fixture_id']);
            $table->index(['tournament_edition_id', 'starts_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('tournament_groups');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('tournament_editions');
    }
};
