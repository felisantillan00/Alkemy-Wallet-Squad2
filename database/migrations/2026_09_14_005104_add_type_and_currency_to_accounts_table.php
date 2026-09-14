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
        Schema::table('accounts', function (Blueprint $table) {
            # Las cuentas existentes reciben el valor por defecto de la columna (savings / ARS).
            $table->enum('type', ['savings', 'checking'])->default('savings')->after('cbu');
            $table->enum('currency', ['ARS', 'USD'])->default('ARS')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['type', 'currency']);
        });
    }
};
