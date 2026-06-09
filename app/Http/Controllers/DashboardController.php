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
        'produits_en_alerte' => Produit::whereRaw('stock <= "seuilAlerteStock"')->count(),

        // Répartition des commandes par statut
        'commandes_par_statut' => Commande::select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->get(),

        // Revenus par mois (année en cours)
        'revenus_mensuels' => Paiement::where('statutPaiement', 'PAYEE')
            ->selectRaw('EXTRACT(MONTH FROM "datePaiement") as mois, SUM(montant) as total')
            ->whereRaw('EXTRACT(YEAR FROM "datePaiement") = ?', [now()->year])
            ->groupBy('mois')
            ->orderBy('mois')
            ->get(),

         'top_produits' => DB::table('ligne_commandes')
            ->join('produits', 'ligne_commandes.produit_id', '=', 'produits.id')
            ->join('commandes', 'ligne_commandes.commande_id', '=', 'commandes.id')
            ->whereNotNull('ligne_commandes.produit_id')
           // ->where('commandes.statut', '!=', 'ANNULEE') // ← exclure annulées
            ->select(
                'produits.id',
                'produits.nom',
                'produits.prix',
                'produits.stock',
                'produits.statut',
                DB::raw('produits."prixPromo"'),
                DB::raw('(SELECT chemin FROM images WHERE images.produit_id = produits.id AND images."isPrimary" = true LIMIT 1) as image_primaire'),
                DB::raw('SUM(ligne_commandes.quantite) as total_vendu')
            )
            ->groupBy(
                'produits.id',
                'produits.nom',
                'produits.prix',
                'produits.stock',
                'produits.statut',
                DB::raw('produits."prixPromo"'),
            )
            ->orderByDesc('total_vendu')
            ->limit(5)
            ->get(),

        // ← Ventes des 7 derniers jours (uniquement paiements PAYEE)
        'ventes_7_jours' => Paiement::where('statutPaiement', 'PAYEE')
            ->whereBetween('datePaiement', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->selectRaw('CAST("datePaiement" AS DATE) as date, SUM(montant) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get(),

        // Dans stats()
        'commandes_recentes' => Commande::with(['client', 'paiement'])
            ->latest('dateCommande')
            ->limit(5)
            ->get(),    

             // Total produits catalogue
        'total_produits' => Produit::count(),

        // Clients récents
        'clients_recents' => User::where('role', 'CLIENT')
            ->latest()
            ->limit(5)
            ->get(['id', 'nomComplet', 'email', 'tel', 'created_at']),    
        
            ]);
     }

     public function ventes(Request $request): JsonResponse
{
    abort_if(!$request->user()->isAdmin(), 403);

    $period    = $request->period ?? 'week';
    $dateDebut = match($period) {
        'today' => now()->startOfDay(),
        'week'  => now()->subDays(6)->startOfDay(),
        'month' => now()->startOfMonth(),
        'year'  => now()->startOfYear(),
        default => now()->subDays(6)->startOfDay(),
    };

    if ($period === 'today') {
        $ventes = Paiement::where('statutPaiement', 'PAYEE')
            ->where('datePaiement', '>=', $dateDebut)
            ->selectRaw('EXTRACT(HOUR FROM "datePaiement") as heure, SUM(montant) as total')
            ->groupBy('heure')
            ->orderBy('heure')
            ->get();
    } elseif ($period === 'year') {
    // Par mois
    $ventes = Paiement::where('statutPaiement', 'PAYEE')
        ->where('datePaiement', '>=', $dateDebut)
        ->selectRaw('EXTRACT(MONTH FROM "datePaiement") as mois, SUM(montant) as total')
        ->groupBy('mois')
        ->orderBy('mois')
        ->get();

    } else {
        $ventes = Paiement::where('statutPaiement', 'PAYEE')
            ->where('datePaiement', '>=', $dateDebut)
            ->selectRaw('CAST("datePaiement" AS DATE) as date, SUM(montant) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    return response()->json(['ventes' => $ventes]);
}

       
}
