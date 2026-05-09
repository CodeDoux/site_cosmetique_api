<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom', 'description', 'stock', 'prix', 'prixPromo',
        'seuilAlerteStock', 'statut', 'categorie_id', 'note',
    ];

    protected $casts = [
        'dateAjout' => 'datetime',
        'stock'     => 'decimal:2',
        'prix'      => 'decimal:2',
        'prixPromo' => 'decimal:2',
    ];

    public function categorie()      { return $this->belongsTo(Categorie::class, 'categorie_id'); }
    public function images()         { return $this->hasMany(Image::class, 'produit_id'); }
    public function imagePrimaire()  { return $this->hasOne(Image::class, 'produit_id')->where('isPrimary', true); }
    public function avis()           { return $this->hasMany(Avis::class, 'produit_id'); }
    public function lignesCommande() { return $this->hasMany(LigneCommande::class, 'produit_id'); }
    public function promotions()     { return $this->hasMany(Promotion::class, 'produit_id'); }

    // Prix effectif (promo ou normal)
    public function getPrixEffectifAttribute(): float
    {
        return $this->prixPromo ?? $this->prix;
    }

    public function estEnRupture(): bool
    {
        return $this->stock <= 0;
    }

    public function estSousSeuilAlerte(): bool
    {
        return $this->stock <= $this->seuilAlerteStock;
    }
}
