<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {

            // Client passe une commande
            'store' => [
                'modeLivraison'       => 'required|in:DOMICILE,POINT_RELAIS,RETRAIT_MAGASIN',
                // adresse_id obligatoire seulement si livraison à domicile
                'adresse_id'          => 'required_if:modeLivraison,DOMICILE|exists:adresses,id',
                'codePromo'           => 'nullable|string|exists:promotions,code',
                'lignes'              => 'required|array|min:1',
                'lignes.*.produit_id' => 'required|exists:produits,id',
                'lignes.*.quantite'   => 'required|numeric|min:1',
            ],

            // Admin change le statut d'une commande
            'changerStatut' => [
                'statut' => 'required|in:EN_PREPARATION,EN_LIVRAISON,LIVREE,ANNULEE',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'adresse_id.required_if'          => 'Une adresse est requise pour la livraison à domicile.',
            'lignes.required'                 => 'La commande doit contenir au moins un produit.',
            'lignes.*.produit_id.exists'      => 'Un produit sélectionné n\'existe pas.',
            'lignes.*.quantite.min'           => 'La quantité doit être au moins 1.',
            'codePromo.exists'                => 'Ce code promo n\'existe pas.',
        ];
    }
}
