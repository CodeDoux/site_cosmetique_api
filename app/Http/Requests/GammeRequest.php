<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {

            'store' => [
                'nom'                            => 'required|string|max:255|unique:gammes,nom',
                'description'                    => 'nullable|string',
                'prix_fixe'                      => 'required|numeric|min:0',
                'prixPromo'                      => 'nullable|numeric|min:0|lt:prix_fixe',
                'image'                          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'statut'                         => 'nullable|in:DISPONIBLE,EPUISEE,A_VENIR',
                'marque'                         => 'nullable|string',
                'dateDebut'                      => 'nullable|date',
                'dateFin'                        => 'nullable|date|after:dateDebut',
                'produits'                       => 'nullable|array',
                'produits.*.produit_id'          => 'required|exists:produits,id',
                'produits.*.quantite'            => 'required|numeric|min:1',
                'produits.*.valeur_unitaire'     => 'nullable|numeric|min:0',
            ],

            'update' => [
                'nom'                            => 'sometimes|string|max:255|unique:gammes,nom,' . $this->route('gamme')?->id,
                'description'                    => 'nullable|string',
                'marque'                         => 'nullable|string',
                'prix_fixe'                      => 'sometimes|numeric|min:0',
                'prixPromo'                      => 'nullable|numeric|min:0',
                'image'                          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'statut'                         => 'sometimes|in:DISPONIBLE,EPUISEE,A_VENIR',
                'dateDebut'                      => 'nullable|date',
                'dateFin'                        => 'nullable|date|after:dateDebut',
            ],

            'ajouterProduit' => [
                'produit_id'                     => 'required|exists:produits,id',
                'quantite'                       => 'required|numeric|min:1',
                'valeur_unitaire'                => 'nullable|numeric|min:0',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'nom.unique'                         => 'Une gamme avec ce nom existe déjà.',
            'prixPromo.lt'                       => 'Le prix promo doit être inférieur au prix fixe.',
            'dateFin.after'                      => 'La date de fin doit être après la date de début.',
            'produits.*.produit_id.exists'       => 'Un produit sélectionné n\'existe pas.',
            'produits.*.quantite.min'            => 'La quantité doit être au moins 1.',
        ];
    }
}
