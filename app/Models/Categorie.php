<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'description'];

    public function produits()    { return $this->hasMany(Produit::class, 'categorie_id'); }
    //public function promotions()  { return $this->hasMany(Promotion::class, 'categorie_id'); }
}
