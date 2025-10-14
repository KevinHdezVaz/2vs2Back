<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Le decimos que vamos a modificar la tabla 'user_profiles'
        Schema::table('user_profiles', function (Blueprint $table) {
            // Añadimos las nuevas columnas después de 'user_id'
            $table->decimal('height', 5, 2)->nullable()->after('user_id'); // Ej: 175.50
            $table->decimal('weight', 5, 2)->nullable()->after('height'); // Ej: 70.50
            $table->integer('age')->nullable()->after('weight');
            $table->string('sex')->nullable()->after('age');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Esto es por si necesitas revertir la migración.
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['height', 'weight', 'age', 'sex']);
        });
    }
};