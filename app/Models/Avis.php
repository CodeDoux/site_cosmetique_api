<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Avis extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'produit_id', 'note', 'commentaire', 'statut', 'estVerifie',
    ];

    protected $casts = [
        'dateAvis'   => 'datetime',
        'estVerifie' => 'boolean',
    ];

    public function client()  { return $this->belongsTo(User::class, 'client_id'); }
    public function produit() { return $this->belongsTo(Produit::class, 'produit_id'); }
}

