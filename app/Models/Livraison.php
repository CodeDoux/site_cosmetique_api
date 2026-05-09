<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Livraison extends Model
{
    protected $fillable = [
        'reference',
        'dateExpedition',
        'dateLivraison',
        'statutLivraison',
        'commande_id',
        'adresseLivraison_id',
        'livreur_id',        // ← ajouté
    ];

    protected $casts = [
        'dateExpedition' => 'date',
        'dateLivraison'  => 'date',
    ];

    public function commande() { return $this->belongsTo(Commande::class, 'commande_id'); }
    public function adresse()  { return $this->belongsTo(Adresse::class, 'adresseLivraison_id'); }
    public function livreur()  { return $this->belongsTo(User::class, 'livreur_id'); }
}
