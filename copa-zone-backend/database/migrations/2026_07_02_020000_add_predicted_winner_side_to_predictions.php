<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->string('predicted_winner_side')->nullable()->after('predicted_away_score');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropColumn('predicted_winner_side');
        });
    }
};
