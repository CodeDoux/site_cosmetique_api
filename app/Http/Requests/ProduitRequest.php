<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Seul l'admin peut créer/modifier/supprimer un produit
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {

            'store' => [
                'nom'              => 'required|string|max:255',
                'description'      => 'required|string',
                'marque'           => 'nullable|string',
                'stock'            => 'required|numeric|min:0',
                'prix'             => 'required|numeric|min:0',
                'prixPromo'        => 'nullable|numeric|min:0|lt:prix',
                'seuilAlerteStock' => 'nullable|integer|min:0',
                'categorie_id'     => 'required|exists:categories,id',
                'note'             => 'nullable|integer|between:1,5',
                // Images : tableau de fichiers (max 5 images)
                'images'           => 'nullable|array|max:5',
                'images.*'         => 'image|mimes:jpeg,png,jpg,webp|max:2048',
                'imagePrimaire'    => 'nullable|integer|min:0', // index de l'image principale
            ],

            'update' => [
                'nom'              => 'sometimes|string|max:255',
                'description'      => 'sometimes|string',
                'stock'            => 'sometimes|numeric|min:0',
                'prix'             => 'sometimes|numeric|min:0',
                'prixPromo'        => 'nullable|numeric|min:0',
                'seuilAlerteStock' => 'sometimes|integer|min:0',
                'categorie_id'     => 'sometimes|exists:categories,id',
                'note'             => 'nullable|integer|between:1,5',
                'statut'           => 'sometimes|in:DISPONIBLE,EN_RUPTURE',
                'marque'           => 'nullable|string',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'prixPromo.lt'         => 'Le prix promotionnel doit être inférieur au prix normal.',
            'categorie_id.exists'  => 'La catégorie sélectionnée n\'existe pas.',
            'images.max'           => 'Vous ne pouvez pas uploader plus de 5 images.',
            'images.*.mimes'       => 'Chaque image doit être au format jpeg, png, jpg ou webp.',
            'note.between'         => 'La note doit être entre 1 et 5.',
        ];
    }
}
