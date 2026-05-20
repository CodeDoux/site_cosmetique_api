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
        Schema::create('ligne_commandes', function (Blueprint $table) {
            $table->id();
            $table->decimal('prix',10,2);
            $table->decimal('quantite',8,2);
            $table->decimal('montantLigne',10,2);
            $table->decimal('reduction',10,2);
            $table->foreignId('produit_id')->constrained('produits')->onDelete('set null');
            $table->foreignId('gamme_id')->nullable()->constrained('gammes')->onDelete('set null');
            $table->foreignId('commande_id')->constrained('commandes')->onDelete('cascade');
            $table->enum('type', ['PRODUIT', 'GAMME'])->default('PRODUIT')->after('gamme_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ligne_commandes');
    }
};
