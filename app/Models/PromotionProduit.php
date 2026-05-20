<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionProduit extends Model
{
    use HasFactory;


    protected $fillable = [
        'produit_id',
        'promo_id',
        'montant_reduction'
    ];

    protected $casts = [
        'montant_reduction' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function promotion()
    {
        return $this->belongsTo(Promotion::class, 'promo_id');
    }

    public function produit()
    {
        return $this->belongsTo(Produits::class, 'produit_id');
    }
}
