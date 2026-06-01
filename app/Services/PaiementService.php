<?php
// ══════════════════════════════════════════════════════════════
// app/Services/PaiementService.php
//
// Intégrations :
//   • Wave      → Wave Business API (checkout sessions)
//   • Orange Money / Free Money → PayDunya
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

        // Vérifier que la commande n'est pas déjà payée
        if ($commande->paiement?->statutPaiement === 'PAYEE') {
            throw new \Exception('Cette commande est déjà payée.');
        }

        return match ($data['modePaiement']) {
            'EN_ESPECE' => $this->paiementEspece($commande, $data),
            'EN_LIGNE'  => $this->paiementEnLigne($commande, $data),
        };
    }

    // ─── Paiement en espèce ───────────────────────────────────────────────────

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

    // ─── Paiement en ligne ────────────────────────────────────────────────────

    private function paiementEnLigne(Commande $commande, array $data): array
    {
        return match ($data['operateur']) {
            'WAVE'         => $this->initierWave($commande, $data),
            'ORANGE_MONEY' => $this->initierPayDunya($commande, $data),
            'FREE_MONEY'   => $this->initierPayDunya($commande, $data),
            default        => throw new \Exception('Opérateur non supporté.'),
        };
    }

    // ══════════════════════════════════════════════════════════
    // WAVE BUSINESS API
    // Doc officielle : https://wave.com/en/business/
    //
    // .env requis :
    //   WAVE_API_KEY=votre_clé_wave
    //   WAVE_WEBHOOK_SECRET=votre_secret_webhook
    //
    // config/services.php :
    //   'wave' => [
    //       'api_key'        => env('WAVE_API_KEY'),
    //       'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
    //   ],
    // ══════════════════════════════════════════════════════════

    private function initierWave(Commande $commande, array $data): array
    {
        $reference = 'PAY-' . strtoupper(Str::random(8));

        // 1. Enregistrer le paiement en BDD (NON_PAYEE en attendant la confirmation)
        $paiement = Paiement::updateOrCreate(
            ['commande_id' => $commande->id],
            [
                'reference'      => $reference,
                'montant'        => $commande->montantTotal,
                'statutPaiement' => 'NON_PAYEE',
                'modePaiement'   => 'EN_LIGNE',
                'operateur'      => 'WAVE',
                'telephone'      => $data['telephone'],
            ]
        );

        // 2. Créer une session de paiement Wave
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.wave.api_key'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.wave.com/v1/checkout/sessions', [
            'currency'         => 'XOF',
            'amount'           => (string)(int)$commande->montantTotal,
            'error_url'        => config('app.url') . '/paiement/echec',
            'success_url'      => config('app.url') . '/paiement/succes?ref=' . $reference,
            'client_reference' => $reference, // notre référence pour retrouver le paiement dans le webhook
        ]);

        if ($response->failed()) {
            Log::error('Wave - Erreur création session', [
                'response' => $response->json(),
                'commande' => $commande->reference,
            ]);
            throw new \Exception('Erreur lors de l\'initiation du paiement Wave.');
        }

        $waveData = $response->json();

        return [
            'message'      => 'Session Wave créée. Redirigez l\'utilisateur vers l\'URL de paiement.',
            'paiement'     => $paiement,
            'checkout_url' => $waveData['wave_launch_url'], // URL vers laquelle rediriger le client
            'session_id'   => $waveData['id'],              // à sauvegarder pour vérification
        ];
    }

    // ══════════════════════════════════════════════════════════
    // PAYDUNYA (Orange Money + Free Money)
    // Doc officielle : https://paydunya.com/developers
    //
    // .env requis :
    //   PAYDUNYA_MASTER_KEY=votre_master_key
    //   PAYDUNYA_PRIVATE_KEY=votre_private_key
    //   PAYDUNYA_TOKEN=votre_token
    //   PAYDUNYA_MODE=test  (ou live en production)
    //
    // config/services.php :
    //   'paydunya' => [
    //       'master_key'  => env('PAYDUNYA_MASTER_KEY'),
    //       'private_key' => env('PAYDUNYA_PRIVATE_KEY'),
    //       'token'       => env('PAYDUNYA_TOKEN'),
    //       'mode'        => env('PAYDUNYA_MODE', 'test'),
    //   ],
    // ══════════════════════════════════════════════════════════

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
                'operateur'      => $data['operateur'],
                'telephone'      => $data['telephone'],
            ]
        );

        // 2. Créer une facture PayDunya
        $response = Http::withHeaders([
            'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
            'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
            'Content-Type'         => 'application/json',
        ])->post($baseUrl . '/checkout-invoice/create', [
            'invoice' => [
                'total_amount' => (int)$commande->montantTotal,
                'description'  => 'Commande ' . $commande->reference,
            ],
            'store' => [
                'name' => config('app.name'),
            ],
            'actions' => [
                'cancel_url'   => config('app.url') . '/paiement/echec',
                'return_url'   => config('app.url') . '/paiement/succes?ref=' . $reference,
                'callback_url' => config('app.url') . '/api/paiements/webhook/paydunya',
            ],
            // Données personnalisées récupérées dans le webhook
            'custom_data' => [
                'reference'   => $reference,
                'commande_id' => $commande->id,
            ],
        ]);

        $responseData = $response->json();

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
            'checkout_url' => $responseData['response_text'], // URL de paiement PayDunya
            'token'        => $responseData['token'],
        ];
    }

    // ══════════════════════════════════════════════════════════
    // WEBHOOKS (appelés automatiquement après paiement)
    // Ces routes NE doivent PAS être protégées par auth:sanctum
    // ══════════════════════════════════════════════════════════

    /**
     * Webhook Wave
     * Wave envoie une requête POST sur /api/paiements/webhook/wave
     * après chaque tentative de paiement (réussie ou échouée)
     */
    public function traiterWebhookWave(array $payload): void
    {
        // client_reference = notre référence envoyée lors de la création de session
        $reference = $payload['client_reference'] ?? null;
        $statut    = $payload['payment_status']   ?? null; // 'succeeded' | 'failed'

        if (!$reference) return;

        $paiement = Paiement::where('reference', $reference)->first();
        if (!$paiement) return;

        if ($statut === 'succeeded') {
            $paiement->update(['statutPaiement' => 'PAYEE']);
            $paiement->commande->update(['statut' => 'EN_PREPARATION']);

            $this->notifService->envoyer(
                $paiement->commande->client_id,
                'Paiement confirmé ✓',
                'Votre paiement Wave a été confirmé. Commande en préparation !',
                'PAIEMENT'
            );

            Log::info('Wave paiement confirmé', ['reference' => $reference]);
        } else {
            Log::warning('Wave paiement échoué', ['reference' => $reference, 'statut' => $statut]);
        }
    }

    /**
     * Webhook PayDunya (Orange Money / Free Money)
     * PayDunya envoie une requête POST sur /api/paiements/webhook/paydunya
     */
    public function traiterWebhookPayDunya(array $payload): void
    {
        $customData = $payload['custom_data']  ?? [];
        $reference  = $customData['reference'] ?? null;
        $statut     = $payload['status']       ?? null; // 'completed' | 'failed' | 'pending'

        if (!$reference) return;

        $paiement = Paiement::where('reference', $reference)->first();
        if (!$paiement) return;

        if ($statut === 'completed') {
            $paiement->update(['statutPaiement' => 'PAYEE']);
            $paiement->commande->update(['statut' => 'EN_PREPARATION']);

            $this->notifService->envoyer(
                $paiement->commande->client_id,
                'Paiement confirmé ✓',
                'Votre paiement ' . $paiement->operateur . ' a été confirmé !',
                'PAIEMENT'
            );
        }
    }

    // ─── Vérifier manuellement le statut d'une session Wave ──────────────────

    public function verifierStatutWave(string $sessionId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.wave.api_key'),
        ])->get("https://api.wave.com/v1/checkout/sessions/{$sessionId}");

        return $response->json();
    }
}
