<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    // ─── POST /api/auth/register ──────────────────────────────────────────────
    public function register(AuthRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            $request->validated(),
            $request->file('image')
        );

        return response()->json([
            'message' => 'Inscription réussie.',
            'user'    => $result['user'],
            'token'   => $result['token'],
        ], 201);
    }

    // ─── POST /api/auth/login ─────────────────────────────────────────────────
    public function login(AuthRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password
        );

        return response()->json([
            'message' => 'Connexion réussie.',
            'user'    => $result['user'],
            'token'   => $result['token'],
        ]);
    }

    // ─── POST /api/auth/logout ────────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    // ─── GET /api/auth/me ─────────────────────────────────────────────────────
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    // ─── PUT /api/auth/profile ────────────────────────────────────────────────
    public function updateProfile(AuthRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile(
            $request->user(),
            $request->validated(),
            $request->file('image')
        );

        return response()->json([
            'message' => 'Profil mis à jour.',
            'user'    => $user,
        ]);
    }

    // ─── PUT /api/auth/password ───────────────────────────────────────────────
    public function changePassword(AuthRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->ancien_password,
            $request->password
        );

        return response()->json(['message' => 'Mot de passe modifié avec succès.']);
    }
}

