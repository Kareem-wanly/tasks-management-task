<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Typed access to the signed-in user and the route's models, for requests behind auth:sanctum.
 * user() and route() are untyped (User|null, mixed); these say what the request really has.
 */
trait ResolvesActor
{
    /** The signed-in user. The auth middleware runs first, so a missing user is never expected. */
    public function actor(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * The model bound to a route parameter.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    protected function routeModel(string $parameter, string $model): Model
    {
        return $this->optionalRouteModel($parameter, $model)
            ?? throw new LogicException("Route parameter [{$parameter}] is not a bound ".class_basename($model).'.');
    }

    /**
     * The model bound to a route parameter that only some routes of this request have.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel|null
     */
    protected function optionalRouteModel(string $parameter, string $model): ?Model
    {
        $value = $this->route($parameter);

        return $value instanceof $model ? $value : null;
    }
}
