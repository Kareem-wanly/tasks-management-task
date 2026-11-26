<?php

declare(strict_types=1);

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;

/** A request with the trait, its route parameters and user set by hand. */
function requestWith(?User $user = null, array $parameters = []): FormRequest
{
    $request = new class extends FormRequest
    {
        use ResolvesActor;

        public function task(): Task
        {
            return $this->routeModel('task', Task::class);
        }

        public function optionalTask(): ?Task
        {
            return $this->optionalRouteModel('task', Task::class);
        }
    };

    $route = (new Route('GET', 'tasks/{task}', []))->bind($request);
    foreach ($parameters as $name => $value) {
        $route->setParameter($name, $value);
    }
    $request->setRouteResolver(fn (): Route => $route);
    $request->setUserResolver(fn (): ?User => $user);

    return $request;
}

it('gives the signed-in user', function (): void {
    $user = new User;

    expect(requestWith($user)->actor())->toBe($user);
});

it('refuses a request without a signed-in user', function (): void {
    expect(fn () => requestWith()->actor())->toThrow(AuthenticationException::class);
});

it('gives the model bound to the route', function (): void {
    $task = new Task;
    $request = requestWith(parameters: ['task' => $task]);

    expect($request->task())->toBe($task)
        ->and($request->optionalTask())->toBe($task);
});

it('treats an unbound parameter as missing', function (): void {
    $request = requestWith(parameters: ['task' => '42']);

    expect($request->optionalTask())->toBeNull()
        ->and(fn () => $request->task())->toThrow(LogicException::class, 'Route parameter [task] is not a bound Task.');
});
