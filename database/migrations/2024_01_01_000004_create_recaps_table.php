<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('weekly_diary_id')->constrained('weekly_diaries')->cascadeOnDelete();
            $table->text('achievement')->nullable();
            $table->text('reflection')->nullable();
            $table->text('lesson_learned')->nullable();
            $table->text('next_week_plan')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'weekly_diary_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recaps');
    }
};
