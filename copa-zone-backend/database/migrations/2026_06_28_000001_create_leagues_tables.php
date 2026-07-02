<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('visibility')->default('public');
            $table->string('join_policy')->default('open');
            $table->string('invite_code')->nullable()->unique();
            $table->unsignedInteger('max_members')->default(32);
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index(['visibility', 'status']);
        });

        Schema::create('league_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('participant');
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_members');
        Schema::dropIfExists('leagues');
    }
};
