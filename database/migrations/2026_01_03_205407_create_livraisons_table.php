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
        Schema::create('livraisons', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('dateExpedition')->nullable();
            $table->date('dateLivraison')->nullable();
            $table->enum('statutLivraison', ['EN_COURS','LIVREE','NON_LIVREE']);
            $table->foreignId('commande_id')->constrained('commandes')->onDelete('cascade');
            $table->foreignId('adresseLivraison_id')->constrained('adresses')->onDelete('set null');
            $table->foreignId('livreur_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livraisons');
    }
};
