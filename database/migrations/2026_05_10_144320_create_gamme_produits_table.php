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
        Schema::create('gamme_produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gamme_id')
                ->constrained('gammes')
                ->onDelete('cascade');
            $table->foreignId('produit_id')
                ->constrained('produits')
                ->onDelete('cascade');
            $table->decimal('quantite', 8, 2)->default(1);
            $table->decimal('valeur_unitaire', 10, 2)->nullable();
            $table->timestamps();
            // Un produit ne peut pas apparaître deux fois dans la même gamme
            $table->unique(['gamme_id', 'produit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gamme_produits');
    }
};
