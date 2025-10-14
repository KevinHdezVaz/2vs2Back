<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Añadimos la nueva columna booleana después de 'things_to_avoid'
            $table->boolean('plan_setup_complete')->default(false)->after('things_to_avoid');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('plan_setup_complete');
        });
    }
};