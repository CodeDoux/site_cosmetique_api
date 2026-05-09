<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthService
{
    // ─── Inscription ─────────────────────────────────────────────────────────

    public function register(array $data, $imageFile = null): array
    {
        $imagePath = null;

        if ($imageFile) {
            $imagePath = $imageFile->store('users', 'public');
        }

        $user = User::create([
            'nomComplet' => $data['nomComplet'],
            'email'      => $data['email'],
            'password'   => $data['password'], // hashé automatiquement par le cast
            'tel'        => $data['tel'],
            'image'      => $imagePath,
            'role'       => 'CLIENT',          // rôle par défaut
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    // ─── Connexion ───────────────────────────────────────────────────────────

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email ou mot de passe incorrect.'],
            ]);
        }

        // Révoque tous les anciens tokens (une seule session active)
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    // ─── Déconnexion ─────────────────────────────────────────────────────────

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    // ─── Mise à jour du profil ───────────────────────────────────────────────

    public function updateProfile(User $user, array $data, $imageFile = null): User
    {
        if ($imageFile) {
            // Supprimer l'ancienne image si elle existe
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            $data['image'] = $imageFile->store('users', 'public');
        }

        $user->update($data);

        return $user->fresh();
    }

    // ─── Changement de mot de passe ──────────────────────────────────────────

    public function changePassword(User $user, string $ancienPassword, string $nouveauPassword): void
    {
        if (!Hash::check($ancienPassword, $user->password)) {
            throw ValidationException::withMessages([
                'ancien_password' => ['L\'ancien mot de passe est incorrect.'],
            ]);
        }

        $user->update(['password' => $nouveauPassword]);
    }
}

