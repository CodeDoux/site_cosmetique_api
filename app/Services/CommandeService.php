<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Livraison;
use App\Models\Produit;
use App\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommandeService
{
    public function __construct(private NotificationService $notifService) {}

    // ─── Lister les commandes (admin) ─────────────────────────────────────────

    public function lister(array $filtres = [])
    {
        return Commande::with(['client', 'lignesCommande', 'paiement', 'livraison'])
            ->when(
                isset($filtres['statut']),
                fn($q) => $q->where('statut', $filtres['statut'])
            )
            ->when(
                isset($filtres['client_id']),
                fn($q) => $q->where('client_id', $filtres['client_id'])
            )
            ->latest()
            ->paginate(15);
    }

    // ─── Mes commandes (client connecté) ──────────────────────────────────────

    public function mesCommandes(int $clientId)
    {
        return Commande::where('client_id', $clientId)
            ->with(['lignesCommande.produit.imagePrimaire', 'paiement', 'livraison'])
            ->latest()
            ->paginate(10);
    }

    // ─── Voir une commande ────────────────────────────────────────────────────

    public function show(Commande $commande): Commande
    {
        return $commande->load([
            'client',
            'lignesCommande.produit.images',
            'paiement',
            'livraison.adresse',
        ]);
    }

    // ─── Passer une commande ──────────────────────────────────────────────────

    public function store(array $data, int $clientId): Commande
    {
        return DB::transaction(function () use ($data, $clientId) {

            $montantTotal   = 0;
            $fraisLivraison = $this->calculerFraisLivraison($data['modeLivraison']);
            $lignesData     = [];

            // ── 1. Vérifier le stock et calculer les montants ──────────────
            foreach ($data['lignes'] as $ligne) {
                // lockForUpdate : évite les conflits de stock concurrents
                $produit = Produit::lockForUpdate()->findOrFail($ligne['produit_id']);

                if ($produit->stock < $ligne['quantite']) {
                    throw new \Exception("Stock insuffisant pour : {$produit->nom}.");
                }

                $prixUnitaire = $produit->prixPromo ?? $produit->prix;
                $montantLigne = $prixUnitaire * $ligne['quantite'];
                $reduction    = ($produit->prix - $prixUnitaire) * $ligne['quantite'];
                $montantTotal += $montantLigne;

                // Décrémente le stock
                $produit->decrement('stock', $ligne['quantite']);

                // Passe en rupture si stock épuisé
                if ($produit->fresh()->stock <= 0) {
                    $produit->update(['statut' => 'EN_RUPTURE']);
                }

                $lignesData[] = [
                    'produit_id'   => $produit->id,
                    'quantite'     => $ligne['quantite'],
                    'prix'         => $prixUnitaire,
                    'montantLigne' => $montantLigne,
                    'reduction'    => $reduction,
                ];
            }

            // ── 2. Appliquer le code promo ─────────────────────────────────
            if (!empty($data['codePromo'])) {
                $promo = Promotion::where('code', $data['codePromo'])->first();

                if ($promo && $promo->estValide() && $montantTotal >= $promo->montantMinCommande) {
                    $reductionPromo = $promo->calculerReduction($montantTotal);
                    $montantTotal  -= $reductionPromo;
                }
            }

            // ── 3. Créer la commande ───────────────────────────────────────
            $commande = Commande::create([
                'reference'      => 'CMD-' . strtoupper(Str::random(8)),
                'statut'         => 'EN_ATTENTE',
                'montantTotal'   => $montantTotal + $fraisLivraison,
                'fraisLivraison' => $fraisLivraison,
                'modeLivraison'  => $data['modeLivraison'],
                'client_id'      => $clientId,
            ]);

            // ── 4. Créer les lignes de commande ────────────────────────────
            foreach ($lignesData as $ligne) {
                $commande->lignesCommande()->create($ligne);
            }

            // ── 5. Créer la livraison ──────────────────────────────────────
            Livraison::create([
                'reference'           => 'LIV-' . strtoupper(Str::random(8)),
                'statutLivraison'     => 'EN_COURS',
                'commande_id'         => $commande->id,
                'adresseLivraison_id' => $data['adresse_id'] ?? null,
            ]);

            // ── 6. Notifier le client ──────────────────────────────────────
            $this->notifService->envoyer(
                $clientId,
                'Commande confirmée ✓',
                "Votre commande {$commande->reference} a bien été enregistrée.",
                'COMMANDE'
            );

            return $commande->load(['lignesCommande.produit', 'livraison']);
        });
    }

    // ─── Changer le statut (admin) ────────────────────────────────────────────

    public function changerStatut(Commande $commande, string $statut): Commande
    {
        $commande->update(['statut' => $statut]);

        // Message de notification selon le statut
        $messages = [
            'EN_PREPARATION' => 'Votre commande est en cours de préparation.',
            'EN_LIVRAISON'   => 'Votre commande est en route !',
            'LIVREE'         => 'Votre commande a été livrée. Merci pour votre achat !',
            'ANNULEE'        => 'Votre commande a été annulée.',
        ];

        if (isset($messages[$statut])) {
            $this->notifService->envoyer(
                $commande->client_id,
                'Mise à jour de votre commande',
                $messages[$statut],
                'COMMANDE'
            );
        }

        // Si livrée → mettre à jour la livraison
        if ($statut === 'LIVREE') {
            $commande->livraison?->update([
                'statutLivraison' => 'LIVREE',
                'dateLivraison'   => now(),
            ]);
        }

        return $commande->fresh();
    }

    // ─── Calcul des frais de livraison ────────────────────────────────────────

    private function calculerFraisLivraison(string $mode): float
    {
        return match ($mode) {
            'DOMICILE'        => 2000,  // 2 000 FCFA
            'POINT_RELAIS'    => 1000,  // 1 000 FCFA
            'RETRAIT_MAGASIN' => 0,     // Gratuit
            default           => 0,
        };
    }
}
