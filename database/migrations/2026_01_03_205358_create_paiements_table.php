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
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->nullable();
            $table->date('datePaiement')->useCurrent();
            $table->decimal('montant', 10, 2);
            $table->enum('statutPaiement', ['PAYEE','NON_PAYEE','REMBOURSE']);
            $table->enum('operateur', ['ORANGE_MONEY','WAVE','FREE_MONEY'])->nullable();
            $table->enum('modePaiement', ['EN_LIGNE','EN_ESPECE']);
            $table->string('telephone')->nullable();
            $table->foreignId('commande_id')->nullable()->constrained('commandes')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
