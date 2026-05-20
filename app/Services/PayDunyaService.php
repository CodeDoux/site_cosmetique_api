<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PayDunyaService
{
    private string $baseUrl;
    private array  $headers;

    public function __construct()
    {
        $mode          = config('paydunya.mode', 'test');
        $this->baseUrl = $mode === 'live'
            ? config('paydunya.base_url_live')
            : config('paydunya.base_url_test');

        $this->headers = [
            'PAYDUNYA-MASTER-KEY'  => config('paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => config('paydunya.private_key'),
            'PAYDUNYA-PUBLIC-KEY'  => config('paydunya.public_key'),
            'PAYDUNYA-TOKEN'       => config('paydunya.token'),
            'Content-Type'         => 'application/json',
        ];
    }

    /**
     * Créer une facture de paiement
     */
    public function creerFacture(array $data): array
    {
        $payload = [
            'invoice' => [
                'items' => [
                    'item_0' => [
                        'name'        => $data['description'],
                        'quantity'    => 1,
                        'unit_price'  => $data['montant'],
                        'total_price' => $data['montant'],
                        'description' => $data['description'],
                    ]
                ],
                'total_amount' => $data['montant'],
                'description'  => $data['description'],
            ],
            'store' => [
                'name'    => 'CoBeauty',
                'website' => config('app.frontend_url'),
            ],
            'actions' => [
                'cancel_url'   => config('app.frontend_url') . '/commande/annulee',
                'return_url'   => config('app.frontend_url') . '/commande/succes?commande_id=' . $data['commande_id'],
                'callback_url' => route('paiements.callback'),
            ],
            'custom_data' => [
                'commande_id' => $data['commande_id'],
            ],
        ];

        $response = Http::withHeaders($this->headers)
            ->post("{$this->baseUrl}/checkout-invoice/create", $payload);

        return $response->json();
    }

    /**
     * Vérifier le statut d'une facture
     */
    public function verifierFacture(string $token): array
    {
        $response = Http::withHeaders($this->headers)
            ->get("{$this->baseUrl}/checkout-invoice/confirm/{$token}");

        return $response->json();
    }
}