<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LigneCommande extends Model
{
    protected $fillable = [
        'type',          // 'PRODUIT' ou 'GAMME'
        'produit_id',    // nullable si type = GAMME
        'gamme_id',      // nullable si type = PRODUIT
        'prix',
        'quantite',
        'montantLigne',
        'reduction',
        'commande_id',
    ];

    protected $casts = [
        'prix'         => 'decimal:2',
        'quantite'     => 'decimal:2',
        'montantLigne' => 'decimal:2',
        'reduction'    => 'decimal:2',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function commande()
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    // set null → le produit peut être supprimé, la ligne reste avec son historique
    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    // set null → la gamme peut être supprimée, la ligne reste avec son historique
    public function gamme()
    {
        return $this->belongsTo(Gamme::class, 'gamme_id');
    }

    // ─── Accesseur : label affiché même si produit/gamme supprimé ────────────

    public function getLabelAttribute(): string
    {
        if ($this->type === 'GAMME') {
            return $this->gamme?->nom ?? 'Gamme supprimée';
        }
        return $this->produit?->nom ?? 'Produit supprimé';
    }
}
