<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'initial_credits',
                'minimum_stake',
                'maximum_stake',
                'reward_multiplier',
            ]);
        });

        Schema::table('league_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('points_correct_outcome')->default(3)->after('league_id');
            $table->unsignedSmallInteger('points_wrong_outcome')->default(0)->after('points_correct_outcome');
        });
    }

    public function down(): void
    {
        Schema::table('league_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'points_correct_outcome',
                'points_wrong_outcome',
            ]);
        });

        Schema::table('league_settings', function (Blueprint $table): void {
            $table->unsignedBigInteger('initial_credits')->default(1000);
            $table->unsignedBigInteger('minimum_stake')->default(10);
            $table->unsignedBigInteger('maximum_stake')->default(100);
            $table->decimal('reward_multiplier', 8, 2)->default(2);
        });
    }
};
