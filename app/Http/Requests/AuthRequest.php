<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // ─── Règles selon l'action appelée ───────────────────────────────────────

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {

            'register' => [
                'nomComplet' => 'required|string|max:255',
                'email'      => 'required|email|unique:users,email',
                'password'   => 'required|string|min:8|confirmed',
                'tel'        => 'required|string|max:20',
                'image'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            ],

            'login' => [
                'email'    => 'required|email',
                'password' => 'required|string',
            ],

            'updateProfile' => [
                'nomComplet' => 'sometimes|string|max:255',
                'tel'        => 'sometimes|string|max:20',
                'image'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            ],

            'changePassword' => [
                'ancien_password'   => 'required|string',
                'password'          => 'required|string|min:8|confirmed',
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'email.unique'          => 'Cet email est déjà utilisé.',
            'password.confirmed'    => 'Les mots de passe ne correspondent pas.',
            'password.min'          => 'Le mot de passe doit contenir au moins 8 caractères.',
            'image.mimes'           => 'L\'image doit être au format jpeg, png, jpg ou webp.',
        ];
    }
}
