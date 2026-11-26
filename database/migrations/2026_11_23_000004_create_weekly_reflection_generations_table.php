<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reflection_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('weekly_diary_id')->constrained('weekly_diaries')->cascadeOnDelete();
            $table->json('journal_ids');
            $table->json('result');
            $table->json('edited_result')->nullable();
            $table->string('model', 80)->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'weekly_diary_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reflection_generations');
    }
};
