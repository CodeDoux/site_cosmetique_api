<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\AvisController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\AdresseController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GammeController;
use App\Http\Controllers\UserController;

// ══════════════════════════════════════════════════════════════════════════════
//  ROUTES PUBLIQUES
// ══════════════════════════════════════════════════════════════════════════════

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

Route::get('produits',               [ProduitController::class,  'index']);
Route::get('produits/{produit}',     [ProduitController::class,  'show']);
Route::get('categories',             [CategorieController::class,'index']);
Route::get('categories/{categorie}', [CategorieController::class,'show']);
Route::get('produits/{produit}/images', [ImageController::class, 'index']);
Route::get('avis/{produitId}',       [AvisController::class,     'parProduit']);

Route::get('gammes',                    [GammeController::class, 'index']);
Route::get('gammes/{gamme}',            [GammeController::class, 'show']);

// Commande publique : accessible sans token (invité) ET avec token (client connecté)
Route::post('commandes', [CommandeController::class, 'store']);

Route::post('paiements/callback', [PaiementController::class, 'callback'])->name('paiements.callback');


// Webhooks paiement — SANS auth:sanctum
Route::prefix('paiements/webhook')->group(function () {
    Route::post('wave',     [PaiementController::class, 'webhookWave']);
    Route::post('paydunya', [PaiementController::class, 'webhookPayDunya']);
});

// ══════════════════════════════════════════════════════════════════════════════
//  ROUTES PROTÉGÉES
// ══════════════════════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->group(function () {

    Route::post  ('gammes',                              [GammeController::class, 'store']);
    Route::put   ('gammes/{gamme}',                      [GammeController::class, 'update']);
    Route::delete('gammes/{gamme}',                      [GammeController::class, 'destroy']);
    Route::post  ('gammes/{gamme}/produits',             [GammeController::class, 'ajouterProduit']);
    Route::delete('gammes/{gamme}/produits/{produitId}', [GammeController::class, 'retirerProduit']);
    // ── Profil
    Route::prefix('auth')->group(function () {
        Route::get('user',       [AuthController::class, 'user']);
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::put('profile',  [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });

     Route::get   ('users',              [UserController::class, 'index']);
    Route::post  ('users',              [UserController::class, 'store']);
    Route::get   ('users/{user}',       [UserController::class, 'show']);
    Route::put   ('users/{user}',       [UserController::class, 'update']);
    Route::delete('users/{user}',       [UserController::class, 'destroy']);
    Route::patch ('users/{user}/role',  [UserController::class, 'changerRole']);

    // ── Adresses
    Route::prefix('adresses')->group(function () {
        Route::get('/',             [AdresseController::class, 'index']);
        Route::get('/{adresse}',    [AdresseController::class, 'show']);
        Route::post('/',            [AdresseController::class, 'store']);
        Route::put('/{adresse}',    [AdresseController::class, 'update']);
        Route::delete('/{adresse}', [AdresseController::class, 'destroy']);
    });

    // ── Images produit (admin écriture)
    Route::post('produits/{produit}/images',  [ImageController::class, 'store']);
    Route::patch('images/{image}/primaire',   [ImageController::class, 'definirPrimaire']);
    Route::patch('images/{image}/alt-text',   [ImageController::class, 'updateAltText']);
    Route::delete('images/{image}',           [ImageController::class, 'destroy']);

    // ── Catégories (admin écriture)
    Route::post('categories',              [CategorieController::class, 'store']);
    Route::put('categories/{categorie}',   [CategorieController::class, 'update']);
    Route::delete('categories/{categorie}',[CategorieController::class, 'destroy']);

    // ── Produits (admin écriture)
    Route::get('produits/alerte-stock',    [ProduitController::class, 'alerteStock']);
    Route::post('produits',                [ProduitController::class, 'store']);
    Route::put('produits/{produit}',       [ProduitController::class, 'update']);
    Route::delete('produits/{produit}',    [ProduitController::class, 'destroy']);

    // ── Promotions
    Route::post('promotions/valider-code', [PromotionController::class, 'validerCode']);
    Route::get('promotions',               [PromotionController::class, 'index']);
    Route::post('promotions',              [PromotionController::class, 'store']);
    Route::put('promotions/{promotion}',   [PromotionController::class, 'update']);
    Route::delete('promotions/{promotion}',[PromotionController::class, 'destroy']);

    // ── Commandes
    Route::get('commandes/mes-commandes',         [CommandeController::class, 'mesCommandes']);
    Route::get('commandes',                       [CommandeController::class, 'index']);
    Route::get('commandes/{commande}',            [CommandeController::class, 'show']);
    Route::patch('commandes/{commande}/statut',   [CommandeController::class, 'changerStatut']);

    // ── Paiements
    Route::post('paiements/{commande}/initier',  [PaiementController::class, 'initierPaiementEnLigne']);
    // Liste tous les paiements (Admin)
    Route::get('paiements', [PaiementController::class, 'index']);
    Route::get('paiements/{token}/statut',       [PaiementController::class, 'verifierStatut']);


    // Détail d'un paiement (Admin)
    Route::get('paiements/{paiement}', [PaiementController::class, 'show']);

    // Rembourser un paiement (Admin)
    Route::patch('paiements/{paiement}/rembourser', [PaiementController::class, 'rembourser']);
    Route::patch('paiements/{paiement}/statut', [PaiementController::class, 'changerStatut']);


    // ── Livraisons
    Route::get('livraisons',                              [LivraisonController::class, 'index']);
    Route::get('livraisons/livreurs-disponibles',         [LivraisonController::class, 'livreursDisponibles']);
    Route::get('livraisons/mes-livraisons',               [LivraisonController::class, 'mesLivraisons']);
    Route::get('livraisons/{livraison}',                  [LivraisonController::class, 'show']);
    Route::post('livraisons/{livraison}/assigner',        [LivraisonController::class, 'assigner']);
    Route::post('livraisons/{livraison}/prendre-en-charge',[LivraisonController::class,'prendreEnCharge']);
    Route::patch('livraisons/{livraison}/expedier',       [LivraisonController::class, 'marquerExpediee']);
    Route::patch('livraisons/{livraison}/statut',         [LivraisonController::class, 'mettreAJourStatut']);

    // ── Avis
    Route::post('avis',                [AvisController::class, 'store']);
    Route::get('avis/en-attente',      [AvisController::class, 'enAttente']);
    Route::patch('avis/{avis}/moderer',[AvisController::class, 'moderer']);
    Route::delete('avis/{avis}',       [AvisController::class, 'destroy']);

    // ── Notifications
    Route::get('notifications',                      [NotificationController::class, 'index']);
    Route::patch('notifications/lire-tout',          [NotificationController::class, 'marquerToutLu']);
    Route::patch('notifications/{notification}/lire',[NotificationController::class, 'marquerLu']);

    // ── Dashboard Admin
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
});
