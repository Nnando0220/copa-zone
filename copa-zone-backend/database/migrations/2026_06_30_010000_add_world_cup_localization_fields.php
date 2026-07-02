<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('external_name')->nullable()->after('name');
            $table->string('official_name')->nullable()->after('external_name');
            $table->string('display_name_pt_br')->nullable()->after('official_name');
            $table->string('country_code')->nullable()->after('display_name_pt_br');
        });

        Schema::table('tournament_groups', function (Blueprint $table): void {
            $table->string('external_name')->nullable()->after('name');
            $table->string('internal_code')->nullable()->after('external_name');
            $table->string('display_name')->nullable()->after('internal_code');
            $table->string('locale')->default('pt-BR')->after('display_name');
            $table->string('translation_status')->default('automatic')->after('locale');
        });

        Schema::table('league_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('points_exact_score')->default(5)->after('points_wrong_outcome');
            $table->unsignedSmallInteger('points_correct_goal_difference')->default(3)->after('points_exact_score');
            $table->unsignedSmallInteger('points_correct_outcome_scoreline')->default(2)->after('points_correct_goal_difference');
            $table->unsignedSmallInteger('prediction_lock_minutes_before_start')->default(0)->after('points_correct_outcome_scoreline');
        });
    }

    public function down(): void
    {
        Schema::table('league_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'points_exact_score',
                'points_correct_goal_difference',
                'points_correct_outcome_scoreline',
                'prediction_lock_minutes_before_start',
            ]);
        });

        Schema::table('tournament_groups', function (Blueprint $table): void {
            $table->dropColumn([
                'external_name',
                'internal_code',
                'display_name',
                'locale',
                'translation_status',
            ]);
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn([
                'external_name',
                'official_name',
                'display_name_pt_br',
                'country_code',
            ]);
        });
    }
};
