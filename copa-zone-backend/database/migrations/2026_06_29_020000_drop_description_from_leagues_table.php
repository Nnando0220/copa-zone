<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leagues', 'description')) {
            return;
        }

        Schema::table('leagues', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('leagues', 'description')) {
            return;
        }

        Schema::table('leagues', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });
    }
};
