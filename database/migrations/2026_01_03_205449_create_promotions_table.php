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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('code')->unique()->nullable(); // code promo optionnel
            $table->enum('type', ['POURCENTAGE', 'MONTANT_FIXE']); // ex: -20% ou -5000 FCFA
            $table->decimal('valeur', 10, 2); // valeur de la réduction
            $table->decimal('montantMinCommande', 10, 2)->default(0); // montant min pour appliquer
            $table->boolean('estActif')->default(true);
            $table->timestamp('dateDebut');
            $table->timestamp('dateFin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
