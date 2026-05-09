<?php

use App\Http\Controllers\LivraisonController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\AvisController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DashboardController;

// ══════════════════════════════════════════════════════════════════════════════
//  ROUTES PUBLIQUES (sans authentification)
// ══════════════════════════════════════════════════════════════════════════════

// ── Authentification ──────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);  // Inscription
    Route::post('login',    [AuthController::class, 'login']);     // Connexion
});

// ── Catalogue produits (lecture seule) ────────────────────────────────────────
Route::prefix('produits')->group(function () {
    Route::get('/',          [ProduitController::class, 'index']);  // Liste + filtres
    Route::get('/{produit}', [ProduitController::class, 'show']);   // Détail produit
});

// ── Catégories (lecture seule) ────────────────────────────────────────────────
Route::prefix('categories')->group(function () {
    Route::get('/',             [CategorieController::class, 'index']); // Liste
    Route::get('/{categorie}',  [CategorieController::class, 'show']);  // Détail + produits
});

// ── Avis produit (lecture seule) ──────────────────────────────────────────────
Route::get('avis/{produitId}', [AvisController::class, 'parProduit']); // Avis approuvés

// ── Webhooks paiement (appelés par Wave et PayDunya, pas d'auth) ──────────────
Route::prefix('paiements/webhook')->group(function () {
    Route::post('wave',     [PaiementController::class, 'webhookWave']);     // Webhook Wave
    Route::post('paydunya', [PaiementController::class, 'webhookPayDunya']); // Webhook PayDunya
});


// ══════════════════════════════════════════════════════════════════════════════
//  ROUTES PROTÉGÉES (auth:sanctum requis)
// ══════════════════════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->group(function () {

    // ── Profil utilisateur ────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::get('me',               [AuthController::class, 'me']);              // Mon profil
        Route::post('logout',          [AuthController::class, 'logout']);          // Déconnexion
        Route::put('profile',          [AuthController::class, 'updateProfile']);   // Modifier profil
        Route::put('password',         [AuthController::class, 'changePassword']);  // Changer mdp
    });

    // ── Commandes ─────────────────────────────────────────────────────────────
    Route::prefix('commandes')->group(function () {
        Route::get('mes-commandes',           [CommandeController::class, 'mesCommandes']); // Mes commandes (client)
        Route::post('/',                      [CommandeController::class, 'store']);        // Passer une commande (client)
        Route::get('/{commande}',             [CommandeController::class, 'show']);         // Détail commande
        Route::get('/',                       [CommandeController::class, 'index']);        // Toutes les commandes (admin)
        Route::patch('/{commande}/statut',    [CommandeController::class, 'changerStatut']); // Changer statut (admin)
    });

    // ── Paiements ─────────────────────────────────────────────────────────────
    Route::prefix('paiements')->group(function () {
        Route::post('/', [PaiementController::class, 'initier']); // Initier un paiement
    });

    // ── Avis ──────────────────────────────────────────────────────────────────
    Route::prefix('avis')->group(function () {
        Route::post('/',                    [AvisController::class, 'store']);    // Laisser un avis (client)
        Route::get('en-attente',            [AvisController::class, 'enAttente']); // Avis à modérer (admin)
        Route::patch('/{avis}/moderer',     [AvisController::class, 'moderer']);  // Modérer (admin)
        Route::delete('/{avis}',            [AvisController::class, 'destroy']);  // Supprimer (admin)
    });

    // ── Promotions ────────────────────────────────────────────────────────────
    Route::prefix('promotions')->group(function () {
        Route::post('valider-code',         [PromotionController::class, 'validerCode']); // Valider un code (client)
        Route::get('/',                     [PromotionController::class, 'index']);       // Liste (admin)
        Route::post('/',                    [PromotionController::class, 'store']);       // Créer (admin)
        Route::put('/{promotion}',          [PromotionController::class, 'update']);     // Modifier (admin)
        Route::delete('/{promotion}',       [PromotionController::class, 'destroy']);    // Supprimer (admin)
    });

    // ── Produits (admin) ──────────────────────────────────────────────────────
    Route::prefix('produits')->group(function () {
        Route::get('alerte-stock',              [ProduitController::class, 'alerteStock']);    // Stock faible
        Route::post('/',                        [ProduitController::class, 'store']);          // Créer
        Route::put('/{produit}',                [ProduitController::class, 'update']);         // Modifier
        Route::delete('/{produit}',             [ProduitController::class, 'destroy']);        // Supprimer
        Route::post('/{produit}/images',        [ProduitController::class, 'ajouterImages']);  // Ajouter images
        Route::delete('/images/{image}',        [ProduitController::class, 'supprimerImage']); // Supprimer image
    });

    // ── Catégories (admin) ────────────────────────────────────────────────────
    Route::prefix('categories')->group(function () {
        Route::post('/',            [CategorieController::class, 'store']);   // Créer
        Route::put('/{categorie}',  [CategorieController::class, 'update']);  // Modifier
        Route::delete('/{categorie}',[CategorieController::class, 'destroy']); // Supprimer
    });

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::prefix('notifications')->group(function () {
        Route::get('/',                             [NotificationController::class, 'index']);         // Mes notifs
        Route::patch('/{notification}/lire',        [NotificationController::class, 'marquerLu']);     // Marquer une lue
        Route::patch('/lire-tout',                  [NotificationController::class, 'marquerToutLu']); // Toutes lues
    });

    Route::prefix('livraisons')->group(function () {

        // ── Admin ──────────────────────────────────────────────────────────────────
        Route::get('/',                      [LivraisonController::class, 'index']);               // Toutes les livraisons
        Route::get('livreurs-disponibles',   [LivraisonController::class, 'livreursDisponibles']); // Livreurs dispo
        Route::post('/{livraison}/assigner', [LivraisonController::class, 'assigner']);            // Assigner un livreur

        // ── Livreur ────────────────────────────────────────────────────────────────
        Route::get('mes-livraisons',                     [LivraisonController::class, 'mesLivraisons']);   // Mes livraisons
        Route::post('/{livraison}/prendre-en-charge',    [LivraisonController::class, 'prendreEnCharge']); // Prendre en charge
        Route::patch('/{livraison}/expedier',            [LivraisonController::class, 'marquerExpediee']); // Marquer en route
        Route::patch('/{livraison}/statut',              [LivraisonController::class, 'mettreAJourStatut']); // LIVREE/NON_LIVREE

        // ── Admin + Livreur assigné ────────────────────────────────────────────────
        Route::get('/{livraison}',           [LivraisonController::class, 'show']);                // Détail
    });

    // ── Dashboard (admin) ─────────────────────────────────────────────────────
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
});
