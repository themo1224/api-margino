<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SellerUserResource;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'shop_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($validated, $ensureUserShop): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $ensureUserShop->handle($user, $validated['shop_name'] ?? null);

            return $user;
        });

        event(new Registered($user));

        $token = $user->createToken('panel')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => SellerUserResource::toArray($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['اطلاعات ورود نادرست است.'],
            ]);
        }

        $token = $user->createToken('panel')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => SellerUserResource::toArray($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(SellerUserResource::toArray($request->user()));
    }
}
