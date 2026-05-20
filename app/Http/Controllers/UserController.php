<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    // ─── GET /api/users ───────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        return response()->json(
            $this->userService->lister($request->all())
        );
    }

    // ─── POST /api/users ──────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        $data = $request->validate([
            'nomComplet' => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'tel'        => 'nullable|string|max:20',
            'role'       => 'required|in:ADMIN,CLIENT,LIVREUR',
            'password'   => 'required|string|min:8|confirmed',
        ], [
            'email.unique'      => 'Cet e-mail est déjà utilisé.',
            'password.min'      => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'=> 'Les mots de passe ne correspondent pas.',
            'role.in'           => 'Le rôle doit être ADMIN, CLIENT ou LIVREUR.',
        ]);

        $user = $this->userService->store($data);

        return response()->json($user, 201);
    }

    // ─── GET /api/users/{user} ────────────────────────────────────────────────

    public function show(Request $request, User $user): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        return response()->json($this->userService->show($user));
    }

    // ─── PUT /api/users/{user} ────────────────────────────────────────────────

    public function update(Request $request, User $user): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        $data = $request->validate([
            'nomComplet' => 'sometimes|string|max:255',
            'email'      => [
                'sometimes', 'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'tel'        => 'nullable|string|max:20',
            'role'       => 'sometimes|in:ADMIN,CLIENT,LIVREUR',
            'password'   => 'nullable|string|min:8|confirmed',
        ], [
            'email.unique'      => 'Cet e-mail est déjà utilisé.',
            'password.min'      => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'=> 'Les mots de passe ne correspondent pas.',
        ]);

        $user = $this->userService->update($user, $data);

        return response()->json($user);
    }

    // ─── PATCH /api/users/{user}/role ─────────────────────────────────────────

    public function changerRole(Request $request, User $user): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        $data = $request->validate([
            'role' => 'required|in:ADMIN,CLIENT,LIVREUR',
        ], [
            'role.in' => 'Le rôle doit être ADMIN, CLIENT ou LIVREUR.',
        ]);

        $user = $this->userService->changerRole($user, $data['role']);

        return response()->json([
            'message' => 'Rôle mis à jour.',
            'user'    => $user,
        ]);
    }

    // ─── DELETE /api/users/{user} ─────────────────────────────────────────────

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if(!$request->user()->isAdmin(), 403);

        // Empêcher l'admin de se supprimer lui-même
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.'
            ], 403);
        }

        $this->userService->destroy($user);

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}