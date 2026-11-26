<?php

declare(strict_types=1);

use App\Repositories\Concerns\ReadsColumns;

/** The trait's private helpers, called from outside for the test. */
function readsColumns(string $helper, mixed $value): mixed
{
    $reader = new class
    {
        use ReadsColumns;

        public function __call(string $method, array $arguments): mixed
        {
            return self::{$method}(...$arguments);
        }
    };

    return $reader->{$helper}($value);
}

it('reads integer columns as MySQL or other drivers return them', function (mixed $value, ?int $expected): void {
    expect(readsColumns('toIntOrNull', $value))->toBe($expected);
})->with([
    'int' => [12, 12],
    'numeric string' => ['12', 12],
    'no row' => [null, null],
]);

it('reads a column of IDs as a list', function (): void {
    expect(readsColumns('toIntList', collect([3 => 5, 9 => '6'])))->toBe([5, 6]);
});

it('reads text columns', function (): void {
    expect(readsColumns('toStringOrNull', 'todo'))->toBe('todo')
        ->and(readsColumns('toStringOrNull', null))->toBeNull();
});

it('refuses a value that is not the column type', function (string $helper, mixed $value): void {
    expect(fn (): mixed => readsColumns($helper, $value))->toThrow(UnexpectedValueException::class);
})->with([
    'int from words' => ['toInt', 'abc'],
    'int from null' => ['toInt', null],
    'text from a number' => ['toStringOrNull', 5],
]);
