<?php

declare(strict_types=1);

namespace App\Exceptions\Project;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class ProjectMemberNotFoundException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('User is not a member of this project.');
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function errors(): array
    {
        return ['user_id' => ['The specified user is not attached to this project.']];
    }

    public function extra(): array
    {
        return ['success' => false];
    }
}
