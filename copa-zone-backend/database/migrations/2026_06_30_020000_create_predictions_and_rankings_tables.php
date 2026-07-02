<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignUuid('league_member_id')->constrained('league_members')->cascadeOnDelete();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('predicted_home_score');
            $table->unsignedTinyInteger('predicted_away_score');
            $table->string('status')->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->unsignedSmallInteger('points_awarded')->default(0);
            $table->string('score_reason')->nullable();
            $table->unsignedInteger('prediction_version')->default(1);
            $table->timestamps();

            $table->unique(['league_id', 'league_member_id', 'match_id']);
            $table->index(['league_id', 'status']);
            $table->index(['match_id', 'status']);
        });

        Schema::create('league_rankings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignUuid('league_member_id')->constrained('league_members')->cascadeOnDelete();
            $table->unsignedInteger('position')->nullable();
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedInteger('exact_scores')->default(0);
            $table->unsignedInteger('correct_goal_differences')->default(0);
            $table->unsignedInteger('correct_outcomes')->default(0);
            $table->unsignedInteger('wrong_predictions')->default(0);
            $table->unsignedInteger('settled_predictions')->default(0);
            $table->timestamp('last_points_at')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'league_member_id']);
            $table->index(['league_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_rankings');
        Schema::dropIfExists('predictions');
    }
};
