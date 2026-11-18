<?php

declare(strict_types=1);

namespace App\Enums;

/** The roles the app relies on by name (roles.name); they cannot be renamed or deleted. */
enum SystemRole: string
{
    case Administrator = 'Administrator';
    case ProjectManager = 'Project Manager';
    case Member = 'Member';

    /** @return list<string> */
    public static function names(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
