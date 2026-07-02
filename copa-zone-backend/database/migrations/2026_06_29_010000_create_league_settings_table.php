<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->unique()->constrained('leagues')->cascadeOnDelete();
            $table->unsignedBigInteger('initial_credits')->default(1000);
            $table->unsignedBigInteger('minimum_stake')->default(10);
            $table->unsignedBigInteger('maximum_stake')->default(100);
            $table->decimal('reward_multiplier', 8, 2)->default(2);
            $table->boolean('allow_prediction_cancellation')->default(true);
            $table->boolean('late_join_enabled')->default(true);
            $table->string('ranking_visibility')->default('members');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_settings');
    }
};
