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
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('description');
            $table->decimal('stock', 10, 2)->default(0);
            $table->decimal('prix', 10, 2);
            $table->decimal('prixPromo', 10, 2)->nullable();
            $table->integer('seuilAlerteStock')->default(5);
            $table->timestamp('dateAjout')->useCurrent();
            $table->enum('statut', ['DISPONIBLE', 'EN_RUPTURE'])->default('DISPONIBLE');            
            $table->foreignId('categorie_id')->constrained('categories')->onDelete('cascade');
            $table->string('marque')->nullable();
            $table->tinyInteger('note');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
