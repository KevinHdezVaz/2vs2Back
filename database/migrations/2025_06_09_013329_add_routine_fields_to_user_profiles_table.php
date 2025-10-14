<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Añadimos las columnas que faltaban
            $table->string('sport')->nullable()->after('sex');
            $table->string('training_frequency')->nullable()->after('sport');
            $table->string('meal_count')->nullable()->after('training_frequency');
            $table->string('breakfast_time')->nullable()->after('meal_count');
            $table->string('lunch_time')->nullable()->after('breakfast_time');
            $table->string('dinner_time')->nullable()->after('lunch_time');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'sport',
                'training_frequency',
                'meal_count',
                'breakfast_time',
                'lunch_time',
                'dinner_time'
            ]);
        });
    }
};