<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LigneCommande extends Model
{
    protected $fillable = [
        'prix', 'quantite', 'montantLigne', 'reduction', 'produit_id', 'commande_id',
    ];

    public function produit()  { return $this->belongsTo(Produit::class, 'produit_id'); }
    public function commande() { return $this->belongsTo(Commande::class, 'commande_id'); }
}
