<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adresse extends Model
{
    protected $fillable = ['rue', 'ville', 'quartier', 'codePostal', 'user_id'];

    public function user()      { return $this->belongsTo(User::class, 'user_id'); }
    public function livraisons(){ return $this->hasMany(Livraison::class, 'adresseLivraison_id'); }
}
