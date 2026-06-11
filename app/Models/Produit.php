<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'stock',
        'prix',
        'prixPromo',
        'seuilAlerteStock',
        'dateAjout',
        'statut',
        'categorie_id',
        'note',
        'marque'
    ];

    protected $casts = [
        'dateAjout' => 'datetime',
        'stock'     => 'decimal:2',
        'prix'      => 'decimal:2',
        'prixPromo' => 'decimal:2',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    public function images()
    {
        return $this->hasMany(Image::class, 'produit_id');
    }

    public function imagePrimaire()
    {
        return $this->hasOne(Image::class, 'produit_id')->where('isPrimary', true);
    }

    public function avis()
    {
        return $this->hasMany(Avis::class, 'produit_id');
    }

    public function lignesCommande()
    {
        return $this->hasMany(LigneCommande::class, 'produit_id');
    }

    public function promotions(): BelongsToMany
        {
            return $this->belongsToMany(
                Promotion::class,
                'promotion_produits', // ← table pivot
                'produit_id',
                'promo_id'            // ← vérifiez le nom exact de la colonne
            );
        }

    // ── Relation ajoutée pour les gammes ──────────────────────────────────────
    public function gammes()
    {
        return $this->belongsToMany(Gamme::class, 'gamme_produits')
            ->using(GammeProduit::class)
            ->withPivot(['quantite', 'valeur_unitaire'])
            ->withTimestamps();
    }

    // ─── Accesseurs ──────────────────────────────────────────────────────────

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
