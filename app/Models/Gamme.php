<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Gamme extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'prix_fixe',
        'prixPromo',
        'image',
        'statut',
        'dateDebut',
        'dateFin',
        'gamme'
    ];

    protected $casts = [
        'prix_fixe'  => 'decimal:2',
        'prixPromo'  => 'decimal:2',
        'dateDebut'  => 'datetime',
        'dateFin'    => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    /**
     * Produits composant cette gamme via la table pivot gamme_produit
     */
    public function produits()
    {
        return $this->belongsToMany(Produit::class, 'gamme_produits')
            ->using(GammeProduit::class)
            ->withPivot(['quantite', 'valeur_unitaire'])
            ->withTimestamps();
    }

    /**
     * Accès direct aux lignes pivot (pour l'admin)
     */
    public function lignesPivot()
    {
        return $this->hasMany(GammeProduit::class, 'gamme_id');
    }

    /**
     * Lignes de commandes qui référencent cette gamme
     */
    public function lignesCommande()
    {
        return $this->hasMany(LigneCommande::class, 'gamme_id');
    }

    // ─── Accesseurs ──────────────────────────────────────────────────────────

    /**
     * Prix effectif : promo si disponible, sinon prix_fixe
     */
    public function getPrixEffectifAttribute(): float
    {
        return $this->prixPromo ?? $this->prix_fixe;
    }

    /**
     * Valeur totale réelle des produits (prix séparés)
     * Affichée pour montrer l'économie au client
     */
    public function getValeurTotaleAttribute(): float
    {
        return $this->produits->sum(function ($produit) {
            $valeurUnitaire = $produit->pivot->valeur_unitaire ?? $produit->prix;
            return $valeurUnitaire * $produit->pivot->quantite;
        });
    }

    /**
     * Économie réalisée = valeur totale - prix effectif
     */
    public function getEconomieAttribute(): float
    {
        return max(0, $this->valeur_totale - $this->prix_effectif);
    }

    // ─── Méthodes métier ─────────────────────────────────────────────────────

    /**
     * Vérifie si la gamme est actuellement disponible
     */
    public function estDisponible(): bool
    {
        if ($this->statut !== 'DISPONIBLE') return false;

        $now = Carbon::now();
        if ($this->dateDebut && $now->lt($this->dateDebut)) return false;
        if ($this->dateFin   && $now->gt($this->dateFin))   return false;

        return true;
    }

    /**
     * Vérifie si tous les produits ont assez de stock
     */
    public function aAssezDeStock(): bool
    {
        foreach ($this->produits as $produit) {
            if ($produit->stock < $produit->pivot->quantite) {
                return false;
            }
        }
        return true;
    }
}
