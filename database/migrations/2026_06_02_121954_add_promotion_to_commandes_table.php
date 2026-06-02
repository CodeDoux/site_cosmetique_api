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
        Schema::table('commandes', function (Blueprint $table) {
             $table->string('codePromo')->nullable()->after('fraisLivraison');
             $table->double('reduction', 10, 2)->default(0)->after('codePromo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
             $table->dropColumn(['codePromo', 'reduction']);
        });
    }
};
