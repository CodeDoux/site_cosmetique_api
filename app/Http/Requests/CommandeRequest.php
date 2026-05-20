<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tout le monde peut passer une commande (connecté ou invité)
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {

            'store' => [
                'modeLivraison' => 'required|in:DOMICILE,POINT_RELAIS,RETRAIT_MAGASIN',
                // ✅ adresse_id seulement si le client est connecté ET a une adresse
                 'adresse_id' => 'nullable|exists:adresses,id',
                'codePromo' => 'nullable|string|exists:promotions,code',
                'lignes' => 'required|array|min:1',
                'lignes.*.produit_id' => 'nullable|exists:produits,id',
                'lignes.*.gamme_id' => 'nullable|exists:gammes,id',
                'lignes.*.quantite' => 'required|numeric|min:1',

                // ── Champs invité (obligatoires si non connecté) ───────────
                'invite.nom' => 'required_without:client_id|string|max:255',
                'invite.email' => 'required_without:client_id|email|max:255',
                'invite.tel' => 'required_without:client_id|string|max:20',
                // Adresse directe pour l'invité (pas d'adresse_id en BDD)
                'invite.adresse' => 'required_if:modeLivraison,DOMICILE|nullable|string|max:500',
                'invite.ville' => 'required_if:modeLivraison,DOMICILE|nullable|string|max:255',
                'invite.quartier' => 'nullable|string|max:255',
                'paiement.modePaiement' => 'required|in:EN_LIGNE,EN_ESPECE',
                'paiement.operateur'    => 'required_if:paiement.modePaiement,EN_LIGNE|in:ORANGE_MONEY,WAVE,FREE_MONEY',
                'paiement.telephone'    => 'required_if:paiement.modePaiement,EN_LIGNE|string|max:20',
            ],

            'changerStatut' => [
                'statut' => 'required|in:EN_PREPARATION,EN_LIVRAISON,LIVREE,ANNULEE',
            ],

            default => [],
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->lignes ?? [] as $index => $ligne) {
                $aProduit = !empty($ligne['produit_id']);
                $aGamme   = !empty($ligne['gamme_id']);

                if (!$aProduit && !$aGamme) {
                    $validator->errors()->add(
                        "lignes.{$index}",
                        'Chaque ligne doit référencer un produit ou une gamme.'
                    );
                }

                if ($aProduit && $aGamme) {
                    $validator->errors()->add(
                        "lignes.{$index}",
                        'Une ligne ne peut pas référencer un produit ET une gamme en même temps.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'adresse_id.required_if'      => 'Une adresse est requise pour la livraison à domicile.',
            'lignes.required'             => 'La commande doit contenir au moins un produit ou une gamme.',
            'lignes.*.produit_id.exists'  => 'Un produit sélectionné n\'existe pas.',
            'lignes.*.gamme_id.exists'    => 'Une gamme sélectionnée n\'existe pas.',
            'lignes.*.quantite.min'       => 'La quantité doit être au moins 1.',
            'codePromo.exists'            => 'Ce code promo n\'existe pas.',
            'invite.nom.required_without' => 'Votre nom est requis.',
            'invite.email.required_without' => 'Votre email est requis.',
            'invite.tel.required_without' => 'Votre numéro de téléphone est requis.',
            'invite.adresse.required_if'  => 'L\'adresse de livraison est requise.',
            'invite.ville.required_if'    => 'La ville est requise pour la livraison à domicile.',
        ];
    }
}
