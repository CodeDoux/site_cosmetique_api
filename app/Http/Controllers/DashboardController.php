<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // ─── GET /api/dashboard/stats ─────────────────────────────────────────────
    // Admin uniquement
    public function stats(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        return response()->json([
            // KPIs principaux
            'total_clients'      => User::where('role', 'CLIENT')->count(),
            'total_commandes'    => Commande::count(),
            'commandes_du_jour'  => Commande::whereDate('dateCommande', today())->count(),
            'chiffre_affaires'   => Paiement::where('statutPaiement', 'PAYEE')->sum('montant'),
            'produits_en_alerte' => Produit::whereRaw('stock <= seuilAlerteStock')->count(),

            // Répartition des commandes par statut
            'commandes_par_statut' => Commande::select('statut', DB::raw('count(*) as total'))
                ->groupBy('statut')
                ->get(),

            // Revenus par mois (année en cours)
            'revenus_mensuels' => Paiement::where('statutPaiement', 'PAYEE')
                ->whereYear('datePaiement', now()->year)
                ->selectRaw('MONTH(datePaiement) as mois, SUM(montant) as total')
                ->groupBy('mois')
                ->orderBy('mois')
                ->get(),

            // Top 5 produits les plus vendus
            'top_produits' => DB::table('ligne_commandes')
                ->join('produits', 'ligne_commandes.produit_id', '=', 'produits.id')
                ->select('produits.nom', DB::raw('SUM(ligne_commandes.quantite) as total_vendu'))
                ->groupBy('produits.id', 'produits.nom')
                ->orderByDesc('total_vendu')
                ->limit(5)
                ->get(),
        ]);
    }
}
