<?php

namespace App\Support;

use App\Models\User;

final class SellerUserResource
{
    /**
     * @return array{id: int, name: string, email: string|null, phone: null, role: string}
     */
    public static function toArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => null,
            'role' => 'seller',
        ];
    }
}
