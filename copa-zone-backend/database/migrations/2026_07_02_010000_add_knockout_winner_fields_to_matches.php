<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->foreignUuid('winner_team_id')->nullable()->after('away_team_id')->constrained('teams')->nullOnDelete();
            $table->integer('home_penalty_score')->nullable()->after('away_score');
            $table->integer('away_penalty_score')->nullable()->after('home_penalty_score');
            $table->string('winner_source')->nullable()->after('away_penalty_score');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('winner_team_id');
            $table->dropColumn(['home_penalty_score', 'away_penalty_score', 'winner_source']);
        });
    }
};
