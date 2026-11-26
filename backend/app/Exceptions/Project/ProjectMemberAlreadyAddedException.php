<?php

declare(strict_types=1);

namespace App\Exceptions\Project;

use App\Exceptions\BusinessException;

final class ProjectMemberAlreadyAddedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('User is already a member of this project.');
    }

    public function errors(): array
    {
        return ['user_id' => ['The specified user is already attached to this project.']];
    }

    public function extra(): array
    {
        return ['success' => false];
    }
}
