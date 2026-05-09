<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'code',
        'type',
        'valeur',
        'montantMinCommande',
        'estActif',
        'dateDebut',
        'dateFin',
        'produit_id',
    ];

    protected $casts = [
        'dateDebut' => 'datetime',
        'dateFin'   => 'datetime',
        'estActif'  => 'boolean',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    // ─── Méthodes métier ─────────────────────────────────────────────────────

    /**
     * Vérifie si la promotion est actuellement valide
     */
    public function estValide(): bool
    {
        if (!$this->estActif) return false;

        $now = Carbon::now();

        if ($now->lt($this->dateDebut)) return false;

        if ($this->dateFin && $now->gt($this->dateFin)) return false;

        return true;
    }

    /**
     * Calcule la réduction sur un montant donné
     */
    public function calculerReduction(float $montant): float
    {
        if ($this->type === 'POURCENTAGE') {
            return round($montant * $this->valeur / 100, 2);
        }

        // MONTANT_FIXE : ne pas dépasser le montant total
        return min($this->valeur, $montant);
    }
}
