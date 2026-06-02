<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commande extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'dateCommande',
        'statut',
        'montantTotal',
        'fraisLivraison',
        'modeLivraison',
        'client_id',
        'codePromo',
        'reduction',
    ];

    protected $casts = [
        'montantTotal'   => 'float',
        'fraisLivraison' => 'float',
        'reduction'      => 'float',  // ← ajouter
        'dateCommande'   => 'date',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function lignesCommande()
    {
        return $this->hasMany(LigneCommande::class, 'commande_id');
    }

    public function paiement()
    {
        return $this->hasOne(Paiement::class, 'commande_id');
    }

    public function livraison()
    {
        return $this->hasOne(Livraison::class, 'commande_id');
    }
}

