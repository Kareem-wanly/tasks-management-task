<?php

namespace App\Data;

/**
 * A task's change history as the service hands it to the controller.
 */
final readonly class TaskHistory
{
    /** The task isn't in Bitrix24, so it has no history there. */
    public const NOT_IN_BITRIX = 'not_in_bitrix';

    /** Bitrix24 didn't answer. */
    public const UNREACHABLE = 'unreachable';

    /** Bitrix24 answered but won't show it (e.g. the task was deleted there). */
    public const UNAVAILABLE = 'unavailable';

    /**
     * @param  list<array{id: int, field: string, from: ?string, to: ?string, user: array{id: ?int, name: string}|null, created_at: ?string}>  $entries  Newest first.
     * @param  string|null  $unavailableBecause  One of the constants when there is no history to show.
     */
    public function __construct(
        public array $entries,
        public ?string $unavailableBecause = null,
    ) {}
}
