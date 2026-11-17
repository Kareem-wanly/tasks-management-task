# Tooling configuration

> Part of the `laravel-service-pattern` skill. The rules (ARC/SEC/CODE/QUE/CFG) live in `SKILL.md`; the templates below are the canonical shape of each file. Create any file that is missing.

**`phpstan.neon`** (`larastan/larastan`)
```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
    level: 9
```
Use a baseline only for legacy code; new code is never added to the baseline. On legacy projects start lower (e.g. 5) and raise per PR.

**`pint.json`**
```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "strict_comparison": true,
        "void_return": true,
        "fully_qualified_strict_types": true,
        "global_namespace_import": {
            "import_classes": true,
            "import_constants": true,
            "import_functions": true
        },
        "ordered_class_elements": true,
        "no_unused_imports": true
    }
}
```

**`rector.php`** (`rector/rector`, `driftingly/rector-laravel`)
```php
<?php

declare(strict_types=1);

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
    ]);
```
`withPhpSets()` with no argument follows the PHP version in `composer.json`. The rest of the templates in this skill are already written the way Rector wants them: `final readonly class` (CODE-5), `event()` / `dispatch()` helpers, `to_route()`, `resolve()`.

**Apply the tooling to the untouched codebase first.** On a fresh or existing project, run `vendor/bin/pint` and `vendor/bin/rector` once, before adding any feature code, and commit the result alone (`chore: apply pint and rector baseline`). The skill's `pint.json` adds `declare(strict_types=1)` to config, routes and migrations too; doing it in its own commit keeps later diffs limited to real changes.

**`composer.json` scripts**
```json
"scripts": {
    "lint": "pint",
    "lint:check": "pint --test",
    "refactor:check": "rector --dry-run",
    "analyse": "phpstan analyse --memory-limit=1G",
    "test": "pest --parallel",
    "check": ["@lint:check", "@refactor:check", "@analyse", "@test"]
}
```
