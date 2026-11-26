<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/** The signed-in user a resource is rendered for (its can_* flags depend on them). */
trait ResolvesViewer
{
    protected function viewer(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
