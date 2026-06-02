<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'nom',
        'description',
        'code',
        'type',
        'valeur',
        'montantMinCommande',
        'estActif',
        'dateDebut',
        'dateFin',
    ];

    protected $casts = [
        'estActif'  => 'boolean',
        'dateDebut' => 'datetime',
        'dateFin'   => 'datetime',
        'valeur'    => 'decimal:2',
        'montantMinCommande' => 'decimal:2',
    ];

    // ─── Relation many-to-many avec Produit via promotion_produits ───
    public function produits()
    {
        return $this->belongsToMany(
            Produit::class,
            'promotion_produits', // table pivot
            'promo_id',           // FK vers promotions
            'produit_id'          // FK vers produits
        )
        ->withPivot('montant_reduction')
        ->withTimestamps();
    }

    // Dans app/Models/Promotion.php

public function estValide(): bool
{
    if (!$this->estActif) return false;

    $now = now();

    // Vérifier les dates si définies
    if ($this->dateDebut && $now->lt($this->dateDebut)) return false;
    if ($this->dateFin   && $now->gt($this->dateFin))   return false;

    return true;
}

    // ─── Helpers ───

    public function estEnCours(): bool
    {
        $now = now();
        return $this->estActif
            && $this->dateDebut <= $now
            && ($this->dateFin === null || $this->dateFin >= $now);
    }

    public function calculerReduction(float $montant): float
    {
        return $this->type === 'POURCENTAGE'
            ? $montant * ($this->valeur / 100)
            : min($this->valeur, $montant);
    }
}