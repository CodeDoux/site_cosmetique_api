<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GammeProduit extends Pivot
{
    // Nom explicite de la table pivot
    protected $table = 'gamme_produits';

    public $timestamps = true;

    protected $fillable = [
        'gamme_id',
        'produit_id',
        'quantite',
        'valeur_unitaire',
    ];

    protected $casts = [
        'quantite'        => 'decimal:2',
        'valeur_unitaire' => 'decimal:2',
    ];

    public function gamme()
    {
        return $this->belongsTo(Gamme::class, 'gamme_id');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    public function getSousTotalAttribute(): float
    {
        $prix = $this->valeur_unitaire ?? $this->produit->prix;
        return $prix * $this->quantite;
    }
}
