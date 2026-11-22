<?php

declare(strict_types=1);

use App\DTOs\Concerns\ReadsInput;

/** The trait's private helpers, called from outside for the test. */
function readsInput(string $helper, mixed $value): mixed
{
    $reader = new class
    {
        use ReadsInput;

        public function __call(string $method, array $arguments): mixed
        {
            return self::{$method}(...$arguments);
        }
    };

    return $reader->{$helper}($value);
}

it('reads text sent as text or as a number', function (mixed $value, ?string $expected): void {
    expect(readsInput('textOrNull', $value))->toBe($expected);
})->with([
    'text' => ['Report', 'Report'],
    'number' => [42, '42'],
    'null' => [null, null],
]);

it('treats an empty filter as not given', function (): void {
    expect(readsInput('filledTextOrNull', ''))->toBeNull()
        ->and(readsInput('filledTextOrNull', 'open'))->toBe('open');
});

it('requires text where a value must be there', function (): void {
    expect(fn (): mixed => readsInput('requiredText', null))->toThrow(InvalidArgumentException::class, 'Expected text, got null.');
});

it('reads whole numbers from JSON or a query string', function (mixed $value, ?int $expected): void {
    expect(readsInput('intOrNull', $value))->toBe($expected);
})->with([
    'int' => [7, 7],
    'digits' => ['7', 7],
    'negative' => ['-3', -3],
    'null' => [null, null],
]);

it('reads what the boolean rule accepts', function (mixed $value, ?bool $expected): void {
    expect(readsInput('boolOrNull', $value))->toBe($expected);
})->with([
    'true' => [true, true],
    'one' => [1, true],
    'zero text' => ['0', false],
    'null' => [null, null],
]);

it('refuses a value of the wrong type instead of casting it', function (string $helper, mixed $value): void {
    expect(fn (): mixed => readsInput($helper, $value))->toThrow(InvalidArgumentException::class);
})->with([
    'text from an array' => ['textOrNull', ['a']],
    'number from words' => ['intOrNull', 'abc'],
    'number from a decimal' => ['intOrNull', '1.5'],
    'boolean from words' => ['boolOrNull', 'maybe'],
]);
