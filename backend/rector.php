<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\FuncCall\FunctionFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/tests'])
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSets([LaravelSetList::LARAVEL_CODE_QUALITY])
    ->withSkip([
        // Arch tests must reference namespaces as strings, not ::class.
        StringClassNameToClassConstantRector::class => [__DIR__.'/tests/Arch'],
        // Policy methods keep `User $user` even when unused — Laravel's Gate passes it positionally.
        // Same for notification channels: via() / toMail() receive $notifiable from the framework.
        RemoveUnusedPublicMethodParameterRector::class => [__DIR__.'/app/Policies', __DIR__.'/app/Notifications'],
        // array_map(intval(...), ...) segfaults PHP 8.2.12 (the project's CLI) on Windows; keep 'intval' strings.
        FunctionFirstClassCallableRector::class,
    ]);
