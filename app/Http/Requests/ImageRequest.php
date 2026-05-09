<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Seul l'admin peut gérer les images des produits
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {

            // Ajouter une ou plusieurs images à un produit
            'store' => [
                'images' => 'required|array|min:1|max:5',
                'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
                'imagePrimaire' => 'nullable|integer|min:0', // index du tableau d'images
            ],

            // Changer l'image primaire d'un produit
            'definirPrimaire' => [],  // pas de body, juste l'ID dans l'URL

            // Modifier le texte alternatif d'une image
            'updateAltText' => [
                'altText' => 'required|string|max:255',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'images.required'    => 'Vous devez fournir au moins une image.',
            'images.max'         => 'Maximum 5 images par upload.',
            'images.*.image'     => 'Chaque fichier doit être une image.',
            'images.*.mimes'     => 'Formats acceptés : jpeg, png, jpg, webp.',
            'images.*.max'       => 'Chaque image ne doit pas dépasser 2 Mo.',
            'altText.required'   => 'Le texte alternatif est obligatoire.',
        ];
    }

}
