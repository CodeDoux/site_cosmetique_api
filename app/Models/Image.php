<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = ['chemin', 'isPrimary', 'altText', 'produit_id'];

    protected $casts = ['isPrimary' => 'boolean', 'dateCreation' => 'date'];

    public function produit() { return $this->belongsTo(Produit::class, 'produit_id'); }
}
