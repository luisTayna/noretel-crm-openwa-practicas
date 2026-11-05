<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Asegúrate de usar el mismo tipo de columna que el ID de la tabla roles
            $table->unsignedBigInteger('role_id')->nullable()->after('id');
            // $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade'); // Opcional
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('role_id');
        });
    }
};
