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
        Schema::table('livraisons', function (Blueprint $table) {
        // ── Correction 1 : adresseLivraison_id ────────────────────────
        // La migration originale utilisait $table->string('adresseLivraison_id')
        // sans nullable() — ce qui bloque pour RETRAIT_MAGASIN et POINT_RELAIS
        // qui n'ont pas d'adresse de livraison.
        // On la supprime et recrée correctement en foreignId nullable.
        $table->dropColumn('adresseLivraison_id');
    });

        Schema::table('livraisons', function (Blueprint $table) {
            $table->foreignId('adresseLivraison_id')
                ->nullable()
                ->after('commande_id')
                ->constrained('adresses')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropForeign(['adresseLivraison_id']);

        });

    }
};
