<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El producto es una billetera virtual: el identificador de cuenta correcto es el CVU
     * (Clave Virtual Uniforme), no el CBU (que es de cuentas bancarias tradicionales). Se
     * renombran las columnas ya creadas en vez de tocar las migraciones viejas, porque esas
     * ya corrieron en entornos existentes (dev, el servidor de despliegue).
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('cbu', 'cvu');
        });

        Schema::table('movements', function (Blueprint $table) {
            $table->renameColumn('counterpart_cbu', 'counterpart_cvu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('cvu', 'cbu');
        });

        Schema::table('movements', function (Blueprint $table) {
            $table->renameColumn('counterpart_cvu', 'counterpart_cbu');
        });
    }
};
