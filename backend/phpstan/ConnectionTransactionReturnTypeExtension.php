<?php

declare(strict_types=1);

namespace PHPStanExtensions;

use Illuminate\Database\ConnectionInterface;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * ConnectionInterface::transaction() is documented as returning mixed, but every
 * connection implements it with ManagesTransactions: it returns what the callback
 * returns. Services inject the interface (ARC-6), so this gives them that type.
 * (Larastan already stubs the interface, so a second stub would clash.)
 */
final class ConnectionTransactionReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return ConnectionInterface::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'transaction';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $callback = $methodCall->getArgs()[0] ?? null;
        if ($callback === null) {
            return null;
        }

        $type = $scope->getType($callback->value);
        if (! $type->isCallable()->yes()) {
            return null;
        }

        $returnTypes = [];
        foreach ($type->getCallableParametersAcceptors($scope) as $acceptor) {
            $returnTypes[] = $acceptor->getReturnType();
        }

        return $returnTypes === [] ? null : TypeCombinator::union(...$returnTypes);
    }
}
