<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Gamme;
use App\Models\Livraison;
use App\Models\Produit;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\CompteInviteCreeMail;

class CommandeService
{
    public function __construct(
        private NotificationService $notifService,
        private GammeService        $gammeService,
    ) {}

    // ─── Lister les commandes (admin) ─────────────────────────────────────────

    public function lister(array $filtres = [])
    {
        return Commande::with(['client', 'lignesCommande.produit','lignesCommande.gamme', 'paiement', 'livraison'])
            ->when(isset($filtres['statut']),    fn($q) => $q->where('statut', $filtres['statut']))
            ->when(isset($filtres['client_id']), fn($q) => $q->where('client_id', $filtres['client_id']))
            ->latest()
            ->paginate(15);
    }

    // ─── Mes commandes (client connecté) ──────────────────────────────────────

    public function mesCommandes(int $clientId)
    {
        return Commande::where('client_id', $clientId)
            ->with([
                'lignesCommande.produit.imagePrimaire',
                'lignesCommande.gamme',
                'paiement',
                'livraison',
            ])
            ->latest()
            ->paginate(10);
    }

    // ─── Voir une commande ────────────────────────────────────────────────────

   public function show(Commande $commande): JsonResponse
    {
        $commande->load([
            'client',
            'invite',                          // ← infos client invité
            'lignesCommande.produit.images',
            'lignesCommande.gamme.produits',
            'paiement',
            'livraison.adresse',
        ]);

        return response()->json($commande);
    }

    // ─── Passer une commande (connecté OU invité) ─────────────────────────────

    public function store(array $data, ?int $clientId): Commande
    {
        return DB::transaction(function () use ($data, $clientId) {

            $montantTotal   = 0;
            $fraisLivraison = $this->calculerFraisLivraison($data['modeLivraison']);
            $lignesData     = [];

            // ── Calculer les lignes ────────────────────────────────────────
            foreach ($data['lignes'] as $ligne) {

                if (!empty($ligne['gamme_id'])) {

                    /** @var Gamme $gamme */
                    $gamme    = Gamme::with('produits')->lockForUpdate()->findOrFail($ligne['gamme_id']);
                    $quantite = $ligne['quantite'];

                    $this->gammeService->verifierEtDecrementerStock($gamme, $quantite);

                    $prixUnitaire = $gamme->prix_effectif;
                    $montantLigne = $prixUnitaire * $quantite;
                    $montantTotal += $montantLigne;

                    $lignesData[] = [
                        'type'         => 'GAMME',
                        'gamme_id'     => $gamme->id,
                        'produit_id'   => null,
                        'quantite'     => $quantite,
                        'prix'         => $prixUnitaire,
                        'montantLigne' => $montantLigne,
                        'reduction'    => max(0, $gamme->valeur_totale - $prixUnitaire) * $quantite,
                    ];

                } else {

                    /** @var Produit $produit */
                    $produit  = Produit::lockForUpdate()->findOrFail($ligne['produit_id']);
                    $quantite = $ligne['quantite'];

                    if ($produit->stock < $quantite) {
                        throw new \Exception("Stock insuffisant pour : {$produit->nom}.");
                    }

                    $prixUnitaire = $produit->prixPromo ?? $produit->prix;
                    $montantLigne = $prixUnitaire * $quantite;
                    $montantTotal += $montantLigne;

                    $produit->decrement('stock', $quantite);
                    if ($produit->fresh()->stock <= 0) {
                        $produit->update(['statut' => 'EN_RUPTURE']);
                    }

                    $lignesData[] = [
                        'type'         => 'PRODUIT',
                        'produit_id'   => $produit->id,
                        'gamme_id'     => null,
                        'quantite'     => $quantite,
                        'prix'         => $prixUnitaire,
                        'montantLigne' => $montantLigne,
                        'reduction'    => ($produit->prix - $prixUnitaire) * $quantite,
                    ];
                }
            }
            $reductionPromo    = 0;
            $codePromoApplique = null;

            // ── Appliquer le code promo ────────────────────────────────────
            if (!empty($data['codePromo'])) {
                $promo = Promotion::where('code', $data['codePromo'])->first();
                if ($promo && $promo->estValide() && $montantTotal >= $promo->montantMinCommande) {
                    $reductionPromo    = $promo->calculerReduction($montantTotal);
                    $montantTotal     -= $reductionPromo;
                    $codePromoApplique = $data['codePromo'];
                }
            }

            // ── Résoudre le client_id final ────────────────────────────────
            // Connecté → on utilise son ID directement
            // Invité   → on crée un compte CLIENT automatiquement
            $finalClientId = $clientId ?? $this->creerCompteInvite($data['invite']);

            // ── Créer la commande ──────────────────────────────────────────
            $commande = Commande::create([
                'reference'      => 'CMD-' . strtoupper(Str::random(8)),
                'dateCommande'   => now(),
                'statut'         => 'EN_ATTENTE',
                'montantTotal'   => $montantTotal,
                'fraisLivraison' => $fraisLivraison,
                'modeLivraison'  => $data['modeLivraison'],
                'client_id'      => $finalClientId,
                'codePromo'      => $codePromoApplique, // ← ajouter
                'reduction'      => $reductionPromo,    // ← ajouter
            ]);

            // ── Créer les lignes ───────────────────────────────────────────
            foreach ($lignesData as $ligne) {
                $commande->lignesCommande()->create($ligne);
            }

            // ── Créer la livraison ─────────────────────────────────────────
            // Pour les invités avec livraison DOMICILE :
            // on crée une adresse en BDD avec les infos fournies
            $adresseLivraisonId = $this->resoudreAdresse($data, $finalClientId);

            Livraison::create([
                'reference'           => 'LIV-' . strtoupper(Str::random(8)),
                'statutLivraison'     => 'EN_COURS',
                'commande_id'         => $commande->id,
                'adresseLivraison_id' => $adresseLivraisonId,
            ]);

            // ── Notifier ───────────────────────────────────────────────────
            $this->notifService->envoyer(
            'Nouvelle commande 🛍️',
            "Nouvelle commande {$commande->reference} de " . 
            ($commande->client->nomComplet ?? $commande->client->email) . 
            " — " . number_format($commande->montantTotal, 0, ',', ' ') . ' Fr',
            'COMMANDE'
        );

            return $commande->load([
                'lignesCommande.produit',
                'lignesCommande.gamme',
                'livraison',
                'client',
            ]);
        });
    }

    // ─── Créer un compte invité automatiquement ───────────────────────────────
    // L'invité reçoit un mot de passe temporaire par email
    // Il pourra le modifier plus tard depuis son profil

    private function creerCompteInvite(array $invite): int
    {
        // Si l'email existe déjà → on rattache la commande au compte existant
        $existant = User::where('email', $invite['email'])->first();
        if ($existant) {
            return $existant->id;
        }

        $motDePasseTemp = Str::random(10);

        $user = User::create([
            'nomComplet' => $invite['nom'],
            'email'      => $invite['email'],
            'tel'        => $invite['tel'],
            'password'   => $motDePasseTemp, // hashé automatiquement par le cast
            'role'       => 'CLIENT',
        ]);

        // TODO: envoyer le mot de passe temporaire par email
        // Mail::to($user->email)->send(new CompteInviteCreeMail($user, $motDePasseTemp));
        //Mail::to($user->email)
          //  ->send(new CompteInviteCreeMail($user, $motDePasseTemp));

        return $user->id;
    }

    // ─── Résoudre l'adresse de livraison ──────────────────────────────────────

    private function resoudreAdresse(array $data, int $clientId): ?int
    {
        // Client connecté avec adresse_id existante
        if (!empty($data['adresse_id'])) {
            return $data['adresse_id'];
        }

        // Invité avec livraison DOMICILE → créer l'adresse en BDD
        if ($data['modeLivraison'] === 'DOMICILE' && !empty($data['invite'])) {
            $adresse = \App\Models\Adresse::create([
                'rue'      => $data['invite']['adresse'] ?? null,
                'ville'    => $data['invite']['ville']   ?? null,
                'quartier' => $data['invite']['quartier'] ?? null,
                'user_id'  => $clientId,
            ]);
            return $adresse->id;
        }

        // RETRAIT_MAGASIN ou POINT_RELAIS → pas d'adresse nécessaire
        return null;
    }

    // ─── Changer le statut (admin) ────────────────────────────────────────────

    public function changerStatut(Commande $commande, string $statut): Commande
    {
        $commande->update(['statut' => $statut]);

        $messages = [
            'EN_PREPARATION' => 'Votre commande est en cours de préparation.',
            'EN_LIVRAISON'   => 'Votre commande est en route !',
            'LIVREE'         => 'Votre commande a été livrée. Merci pour votre achat !',
            'ANNULEE'        => 'Votre commande a été annulée.',
        ];

        if (isset($messages[$statut])) {
            $this->notifService->envoyer(
                'Mise à jour de votre commande',
                $messages[$statut],
                'COMMANDE'
            );
        }

        if ($statut === 'LIVREE') {
            $commande->livraison?->update([
                'statutLivraison' => 'LIVREE',
                'dateLivraison'   => now(),
            ]);
        }

        return $commande->fresh();
    }

    private function calculerFraisLivraison(string $mode): float
    {
        return match ($mode) {
            'DOMICILE'        => 2000,
            'POINT_RELAIS'    => 1000,
            'RETRAIT_MAGASIN' => 0,
            default           => 0,
        };
    }
}
