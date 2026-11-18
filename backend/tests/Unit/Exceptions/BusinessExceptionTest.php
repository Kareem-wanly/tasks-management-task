<?php

declare(strict_types=1);

use App\Exceptions\Auth\AccountDeactivatedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\Bitrix\BitrixInstallRefusedException;
use App\Exceptions\Bitrix\BitrixLoginCodeExpiredException;
use App\Exceptions\Bitrix\BitrixSignInException;
use App\Exceptions\BusinessException;
use App\Exceptions\Project\ProjectHasUnfinishedTasksException;
use App\Exceptions\Project\ProjectMemberAlreadyAddedException;
use App\Exceptions\Project\ProjectMemberNotFoundException;
use App\Exceptions\Role\PermissionGrantDeniedException;
use App\Exceptions\Role\ReservedRoleNameException;
use App\Exceptions\Role\RolePermissionsChangeDeniedException;
use App\Exceptions\Role\SystemRoleCannotBeDeletedException;
use App\Exceptions\Role\SystemRoleCannotBeRenamedException;
use App\Exceptions\Task\ChecklistListNotMovableException;
use App\Exceptions\Task\TimerRunningOnAnotherTaskException;
use App\Exceptions\Task\TimerStartedElsewhereException;
use App\Exceptions\TaskLookup\LookupFromBitrixException;
use App\Exceptions\TaskLookup\LookupInUseException;
use App\Exceptions\User\AdministratorChangeDeniedException;
use App\Exceptions\User\AdministratorRoleChangeDeniedException;
use App\Exceptions\User\CannotDeactivateOwnAccountException;
use App\Exceptions\User\LastAdministratorException;
use App\Exceptions\User\RoleAssignmentDeniedException;

/*
 * The status and JSON body of every business exception, exactly as the API
 * answered them before PR6 (the frontend reads message, errors and reason).
 */
it('answers with its status and body', function (BusinessException $exception, int $status, array $body): void {
    expect($exception->status())->toBe($status)
        ->and($exception->body())->toEqual($body);
})->with([
    'invalid credentials' => [new InvalidCredentialsException, 401, ['message' => 'Invalid login credentials']],
    'account deactivated' => [new AccountDeactivatedException, 403, ['message' => 'This account is deactivated']],
    'project with open tasks' => [new ProjectHasUnfinishedTasksException, 422, [
        'message' => 'Cannot complete project with unfinished tasks.',
        'errors' => ['status' => ['Project has pending or in-progress tasks that must be completed first.']],
    ]],
    'member already added' => [new ProjectMemberAlreadyAddedException, 422, [
        'success' => false,
        'message' => 'User is already a member of this project.',
        'errors' => ['user_id' => ['The specified user is already attached to this project.']],
    ]],
    'member not found' => [new ProjectMemberNotFoundException, 404, [
        'success' => false,
        'message' => 'User is not a member of this project.',
        'errors' => ['user_id' => ['The specified user is not attached to this project.']],
    ]],
    'system role renamed' => [new SystemRoleCannotBeRenamedException, 403, ['message' => 'System roles cannot be renamed.']],
    'reserved role name' => [new ReservedRoleNameException, 403, ['message' => 'Cannot rename a role to a reserved system role name.']],
    'system role deleted' => [SystemRoleCannotBeDeletedException::forRole('Administrator'), 403, ['message' => 'Protected system role [Administrator] cannot be deleted']],
    'role permissions by non-admin' => [new RolePermissionsChangeDeniedException, 403, ['message' => 'Only an Administrator can modify permissions for this role.']],
    'permission not possessed' => [new PermissionGrantDeniedException, 403, ['message' => 'You cannot grant permissions that you do not possess.']],
    'role not possessed' => [new RoleAssignmentDeniedException, 403, ['message' => 'You cannot assign roles with permissions that you do not possess.']],
    'administrator role by non-admin' => [new AdministratorRoleChangeDeniedException, 403, ['message' => 'Only an Administrator can assign or revoke the Administrator role.']],
    'last administrator' => [new LastAdministratorException, 422, ['message' => 'Cannot revoke Administrator role from the final active administrator.']],
    'own account deactivated' => [new CannotDeactivateOwnAccountException, 403, ['message' => 'You cannot deactivate your own account.']],
    'administrator changed by non-admin' => [new AdministratorChangeDeniedException, 403, ['message' => 'Only an Administrator can change another Administrator.']],
    'list moved' => [new ChecklistListNotMovableException, 422, ['message' => 'Lists cannot be moved.']],
    'timer on another task' => [new TimerRunningOnAnotherTaskException, 422, ['message' => 'Your timer is running on another task. Stop it there.']],
    'timer started elsewhere' => [new TimerStartedElsewhereException, 409, ['message' => 'Your timer was just started from another tab. Refresh to see it.']],
    'bitrix lookup deleted' => [new LookupFromBitrixException, 422, ['message' => 'Values that come from Bitrix24 cannot be deleted.']],
    'lookup in use' => [new LookupInUseException, 422, ['message' => 'This value is used by tasks and cannot be deleted.']],
    'login code expired' => [new BitrixLoginCodeExpiredException, 422, ['message' => 'The sign-in link expired or was already used. Please sign in again.']],
    'sign-in refused' => [new BitrixSignInException('inactive'), 422, ['message' => 'Sign-in with Bitrix24 was refused.', 'reason' => 'inactive']],
    'install: not an admin' => [BitrixInstallRefusedException::notAnAdministrator(), 403, ['message' => 'Only a Bitrix24 administrator can install this app. Ask your portal administrator to install it.']],
    'install: missing parameters' => [BitrixInstallRefusedException::missingParameters(), 422, ['message' => 'Missing install parameters.']],
    'bitrix: no token' => [BitrixException::missingToken(), 403, ['message' => 'Sign in with Bitrix24 to change data that is synced with Bitrix24.']],
    'bitrix: unreachable' => [BitrixException::unreachable('timeout'), 503, ['message' => 'Bitrix24 is not responding. Your change was not saved; try again in a moment.']],
    'bitrix: cannot sync' => [BitrixException::cannotSync('Mona is not a Bitrix24 user.'), 422, ['message' => 'Mona is not a Bitrix24 user.']],
    'bitrix: access denied' => [BitrixException::fromResponse(['error' => 'ACCESS_DENIED'], 403), 403, ['message' => 'Bitrix24 does not allow you to do this.']],
    'bitrix: rejected' => [BitrixException::fromResponse(['error' => 'ERROR_CORE', 'error_description' => 'Title is empty'], 400), 422, ['message' => 'Bitrix24 rejected the change: Title is empty']],
]);

it('keeps the detail for the log out of the answer', function (): void {
    $exception = BitrixException::missingToken();

    expect($exception->getMessage())->toBe('No Bitrix24 token is stored for this account.')
        ->and($exception->errorCode)->toBe(BitrixException::NO_TOKEN);
});

it('still redacts secrets from an unreachable Bitrix', function (): void {
    $exception = BitrixException::unreachable('cURL error 28 for https://portal.example/rest/x.json?auth=secret-token');

    expect($exception->getMessage())->not->toContain('secret-token')
        ->and($exception->getMessage())->toContain('?[redacted]');
});
