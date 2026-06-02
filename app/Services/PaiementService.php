<?php
// ══════════════════════════════════════════════════════════════
// app/Services/PaiementService.php
//
// Tous les paiements en ligne passent par PayDunya
// (Orange Money, Free Money, Wave)
// ══════════════════════════════════════════════════════════════
namespace App\Services;

use App\Models\Paiement;
use App\Models\Commande;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaiementService
{
    public function __construct(private NotificationService $notifService) {}

    // ══════════════════════════════════════════════════════════
    // INITIER UN PAIEMENT
    // ══════════════════════════════════════════════════════════

    public function initier(array $data): array
    {
        $commande = Commande::with('client')->findOrFail($data['commande_id']);

        if ($commande->paiement?->statutPaiement === 'PAYEE') {
            throw new \Exception('Cette commande est déjà payée.');
        }

        return match ($data['modePaiement']) {
            'EN_ESPECE' => $this->paiementEspece($commande, $data),
            'EN_LIGNE'  => $this->initierPayDunya($commande, $data),
            default     => throw new \Exception('Mode de paiement non supporté.'),
        };
    }

    // ──────────────────────────────────────────────────────────
    // PAIEMENT EN ESPÈCES
    // ──────────────────────────────────────────────────────────

    private function paiementEspece(Commande $commande, array $data): array
    {
        $paiement = Paiement::updateOrCreate(
            ['commande_id' => $commande->id],
            [
                'reference'      => 'PAY-' . strtoupper(Str::random(8)),
                'montant'        => $commande->montantTotal,
                'statutPaiement' => 'NON_PAYEE',
                'modePaiement'   => 'EN_ESPECE',
                'operateur'      => null,
                'telephone'      => null,
                'datePaiement'   => now(),
            ]
        );

        return [
            'message'  => 'Paiement en espèce enregistré. Vous paierez à la livraison.',
            'paiement' => $paiement,
        ];
    }

    // ──────────────────────────────────────────────────────────
    // PAIEMENT EN LIGNE VIA PAYDUNYA
    // Supporte : Orange Money, Free Money, Wave
    //
    // .env requis :
    //   PAYDUNYA_MASTER_KEY=votre_master_key
    //   PAYDUNYA_PRIVATE_KEY=votre_private_key
    //   PAYDUNYA_PUBLIC_KEY=votre_public_key
    //   PAYDUNYA_TOKEN=votre_token
    //   PAYDUNYA_MODE=test  (ou live en production)
    //
    // config/paydunya.php :
    //   'master_key'  => env('PAYDUNYA_MASTER_KEY'),
    //   'private_key' => env('PAYDUNYA_PRIVATE_KEY'),
    //   'public_key'  => env('PAYDUNYA_PUBLIC_KEY'),
    //   'token'       => env('PAYDUNYA_TOKEN'),
    //   'mode'        => env('PAYDUNYA_MODE', 'test'),
    // ──────────────────────────────────────────────────────────

    private function initierPayDunya(Commande $commande, array $data): array
    {
        $reference  = 'PAY-' . strtoupper(Str::random(8));
        $isTestMode = config('paydunya.mode') === 'test';
        $baseUrl    = $isTestMode
            ? 'https://app.paydunya.com/sandbox-api/v1'
            : 'https://app.paydunya.com/api/v1';

        // 1. Enregistrer en BDD
        $paiement = Paiement::updateOrCreate(
            ['commande_id' => $commande->id],
            [
                'reference'      => $reference,
                'montant'        => $commande->montantTotal,
                'statutPaiement' => 'NON_PAYEE',
                'modePaiement'   => 'EN_LIGNE',
                'operateur'      => $data['operateur'] ?? null,
                'telephone'      => $data['telephone'] ?? null,
                'datePaiement'   => now(),
            ]
        );

        // 2. Créer la facture PayDunya
        $response = Http::withHeaders([
            'PAYDUNYA-MASTER-KEY'  => config('paydunya.master_key'),  // ← config/paydunya.php
            'PAYDUNYA-PRIVATE-KEY' => config('paydunya.private_key'),
            'PAYDUNYA-PUBLIC-KEY'  => config('paydunya.public_key'),
            'PAYDUNYA-TOKEN'       => config('paydunya.token'),
            'Content-Type'         => 'application/json',
        ])->post($baseUrl . '/checkout-invoice/create', [
            'invoice' => [
                'total_amount' => (int) $commande->montantTotal,
                'description'  => 'Commande ' . $commande->reference,
            ],
            'store' => [
                'name'    => config('app.name'),
                'website' => config('app.frontend_url'),
            ],
            'actions' => [
                'cancel_url'   => config('app.frontend_url') . '/commande/annulee',
                'return_url'   => config('app.frontend_url') . '/commande/succes?ref=' . $reference,
                'callback_url' => config('app.url') . '/api/paiements/webhook/paydunya',
            ],
            'custom_data' => [
                'reference'   => $reference,
                'commande_id' => $commande->id,
            ],
        ]);

        $responseData = $response->json();

        Log::info('PayDunya response', [
            'response_code' => $responseData['response_code'] ?? null,
            'response_text' => $responseData['response_text'] ?? null,
        ]);

        if (($responseData['response_code'] ?? null) !== '00') {
            Log::error('PayDunya - Erreur création facture', [
                'response' => $responseData,
                'commande' => $commande->reference,
            ]);
            throw new \Exception('Erreur PayDunya : ' . ($responseData['response_text'] ?? 'Inconnue'));
        }

        return [
            'message'      => 'Facture créée. Redirigez l\'utilisateur vers l\'URL de paiement.',
            'paiement'     => $paiement,
            'checkout_url' => $responseData['response_text'],
            'token'        => $responseData['token'],
        ];
    }

    // ══════════════════════════════════════════════════════════
    // WEBHOOK PAYDUNYA
    // PayDunya envoie une requête POST après chaque paiement
    // Route : /api/paiements/webhook/paydunya (sans auth:sanctum)
    // ══════════════════════════════════════════════════════════

    public function traiterWebhookPayDunya(array $payload): void
    {
        Log::info('PayDunya webhook reçu', $payload);

        $customData = $payload['custom_data']  ?? [];
        $reference  = $customData['reference'] ?? null;
        $statut     = $payload['status']       ?? null; // 'completed' | 'failed' | 'pending'

        if (!$reference) {
            Log::warning('PayDunya webhook - référence manquante');
            return;
        }

        $paiement = Paiement::where('reference', $reference)->first();

        if (!$paiement) {
            Log::warning('PayDunya webhook - paiement introuvable', ['reference' => $reference]);
            return;
        }

        if ($statut === 'completed') {
            $paiement->update(['statutPaiement' => 'PAYEE']);
            $paiement->commande->update(['statut' => 'EN_PREPARATION']);

            // Notifier le client si connecté
           /* if ($paiement->commande->client_id) {
                $this->notifService->envoyer(
                    'Paiement confirmé ✓',
                    'Votre paiement ' . ($paiement->operateur ?? 'en ligne') . ' a été confirmé. Commande en préparation !',
                    'PAIEMENT'
                );
            }*/

            Log::info('PayDunya paiement confirmé', ['reference' => $reference]);
        } else {
            Log::warning('PayDunya paiement non complété', [
                'reference' => $reference,
                'statut'    => $statut,
            ]);
        }
    }
}