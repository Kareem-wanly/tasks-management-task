<?php

declare(strict_types=1);

use App\DTOs\Role\UpdateRoleDTO;
use App\DTOs\Task\CreateTaskDTO;
use App\DTOs\Task\TaskFiltersDTO;
use App\DTOs\Task\UpdateTaskDTO;
use App\DTOs\TaskLookup\SaveTaskLookupDTO;

it('keeps only the task fields the client sent', function (): void {
    $dto = UpdateTaskDTO::fromArray(['title' => 'New', 'due_date' => null]);

    expect($dto->toArray())->toBe(['title' => 'New', 'due_date' => null])
        ->and($dto->has('due_date'))->toBeTrue()
        ->and($dto->has('description'))->toBeFalse();
});

it('casts form strings to the attribute types', function (): void {
    $dto = UpdateTaskDTO::fromArray(['assigned_to' => '7', 'allow_time_tracking' => '0', 'time_estimate' => '3600']);

    expect($dto->toArray())->toBe(['assigned_to' => 7, 'allow_time_tracking' => false, 'time_estimate' => 3600]);
});

it('leaves unsent task fields to the database defaults on create', function (): void {
    expect(CreateTaskDTO::fromArray(['title' => 'Write the report'])->toArray())->toBe(['title' => 'Write the report']);
});

it('replaces role permissions only when they are sent', function (): void {
    $renamed = UpdateRoleDTO::fromArray(['display_name' => 'Editors']);
    $cleared = UpdateRoleDTO::fromArray(['permissions' => null]);

    expect($renamed->has('permissions'))->toBeFalse()
        ->and($renamed->toArray())->toBe(['display_name' => 'Editors'])
        ->and($cleared->has('permissions'))->toBeTrue()
        ->and($cleared->permissionIds)->toBe([])
        ->and($cleared->toArray())->toBe([]);
});

it('never turns an unknown input key into an attribute', function (): void {
    expect(SaveTaskLookupDTO::fromArray(['name' => 'Urgent', 'is_system' => true])->toArray())->toBe(['name' => 'Urgent']);
});

it('treats empty task filters as not filtered', function (): void {
    $filters = TaskFiltersDTO::fromArray(['search' => '', 'status' => '', 'assigned_to' => null, 'overdue' => false]);

    expect($filters->search)->toBeNull()
        ->and($filters->status)->toBeNull()
        ->and($filters->assignedTo)->toBeNull()
        ->and($filters->overdue)->toBeFalse();
});
