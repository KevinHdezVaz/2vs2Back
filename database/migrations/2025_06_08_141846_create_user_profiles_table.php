<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('users', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            // Relación con la tabla de usuarios
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Cuestionario
            $table->string('goal')->nullable(); // Bajar grasa, Subir músculo, etc.
            $table->string('activity_level')->nullable(); // Sedentario, Activo, etc.
            $table->string('dietary_style')->nullable(); // Omnívoro, Vegano, etc.
            $table->string('budget')->nullable(); // Menos de S/50, etc.
            $table->string('cooking_habit')->nullable(); // Cocino yo, etc.
            $table->string('eats_out')->nullable(); // Si, No
            $table->text('disliked_foods')->nullable();
            $table->text('allergies')->nullable();
            $table->string('medical_condition')->nullable();

            // Personalización de la experiencia
            $table->string('communication_style')->nullable(); // Motivadora, Relajada, etc.
            $table->string('motivation_style')->nullable(); // Mensajes de ánimo, Datos duros, etc.
            $table->string('preferred_name')->nullable(); // Apodo
            $table->text('things_to_avoid')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};