<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    protected $fillable = [
        'reference', 'montant', 'statutPaiement', 'operateur',
        'modePaiement', 'telephone', 'commande_id',
    ];
    protected $attributes = [
    'datePaiement' => null,
     ];

    protected $casts = ['datePaiement' => 'date'];

    public function commande() { return $this->belongsTo(Commande::class, 'commande_id'); }
    protected static function boot()
{
    parent::boot();

    static::creating(function ($paiement) {
        $paiement->datePaiement = $paiement->datePaiement ?? now();
    });
}
}
