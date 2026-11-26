<?php

declare(strict_types=1);

use App\Exceptions\Bitrix\BitrixRequestFailedException;
use App\Exceptions\Bitrix\BitrixUnexpectedResponseException;
use App\Integrations\Bitrix24\BitrixPayload;

function answer(array $data): BitrixPayload
{
    return BitrixPayload::of($data, 'tasks.task.get');
}

it('reads numbers Bitrix sends as JSON numbers or as text', function (): void {
    $task = answer(['result' => ['task' => ['id' => '15', 'status' => 3]]]);

    expect($task->int('result.task.id'))->toBe(15)
        ->and($task->int('result.task.status'))->toBe(3);
});

it('treats missing, null and false as not there', function (mixed $value): void {
    $task = answer(['result' => ['task' => ['deadline' => $value]]]);

    expect($task->intOrNull('result.task.deadline'))->toBeNull()
        ->and($task->stringOrNull('result.task.deadline'))->toBeNull()
        ->and($task->has('result.task.deadline'))->toBeFalse()
        ->and($task->payload('result.task.deadline')->isEmpty())->toBeTrue();
})->with(['null' => [null], 'false' => [false]]);

it('fails on a required field that is missing, naming the method and the field', function (): void {
    expect(fn (): int => answer(['result' => []])->int('result.task.id'))
        ->toThrow(BitrixUnexpectedResponseException::class, 'Unexpected Bitrix24 answer from tasks.task.get: "result.task.id" is missing.');
});

it('fails on a field of the wrong type, without quoting its value', function (): void {
    $secret = 'abc-access-token';

    try {
        answer(['result' => ['id' => $secret]])->int('result.id');
        $this->fail('Expected an exception.');
    } catch (BitrixUnexpectedResponseException $e) {
        expect($e->getMessage())->toBe('Unexpected Bitrix24 answer from tasks.task.get: "result.id" should be a whole number, got string.')
            ->not->toContain($secret)
            ->and($e->status())->toBe(502);
    }
});

it('reads lists of rows and of IDs', function (): void {
    $answer = answer(['result' => [
        'list' => [7 => ['ID' => '1', 'TITLE' => 'A'], 9 => ['ID' => '2', 'TITLE' => 'B']],
        'files' => ['3', 4],
    ]]);

    expect($answer->rows('result.list'))->toBe([['ID' => '1', 'TITLE' => 'A'], ['ID' => '2', 'TITLE' => 'B']])
        ->and($answer->ints('result.files'))->toBe([3, 4])
        ->and($answer->rows('result.missing'))->toBe([]);
});

it('refuses a row that is a list instead of an object', function (): void {
    expect(fn (): array => answer(['result' => [['a', 'b']]])->rows('result'))
        ->toThrow(BitrixUnexpectedResponseException::class);
});

it('reads one command of a batch, and null for a refused one', function (): void {
    $batch = answer([
        'result' => ['task_1' => [['ID' => '5']]],
        'result_error' => ['task_2' => ['error' => 'ACCESS_DENIED']],
    ]);

    expect($batch->batchRows('task_1'))->toBe([['ID' => '5']])
        ->and($batch->batchRows('task_2'))->toBeNull()
        ->and($batch->batchRows('task_3'))->toBeNull();
});

it('fails a batch on the first command Bitrix refused', function (): void {
    expect(fn () => answer(['result_error' => ['page_50' => ['error' => 'ACCESS_DENIED', 'error_description' => 'No access']]])->failOnBatchError())
        ->toThrow(BitrixRequestFailedException::class, 'No access');

    answer(['result_error' => []])->failOnBatchError();
});

it('reads ISO dates, and an empty one as not there', function (): void {
    $task = answer(['deadline' => '2026-11-22T12:00:00+03:00', 'closedDate' => '', 'createdDate' => false]);

    expect($task->dateOrNull('deadline')?->utc()->toIso8601String())->toBe('2026-11-22T09:00:00+00:00')
        ->and($task->dateOrNull('closedDate'))->toBeNull()
        ->and($task->dateOrNull('createdDate'))->toBeNull();
});

it('gives dates in the app timezone for our columns', function (): void {
    config(['app.timezone' => 'Asia/Damascus']);

    expect(answer(['date' => '2026-11-22T09:00:00+00:00'])->localDateOrNull('date')?->format('Y-m-d H:i P'))
        ->toBe('2026-11-22 12:00 +03:00');
});

it('compares a flag without failing on another type', function (): void {
    $item = answer(['IS_COMPLETE' => 'Y', 'IS_DELETED' => true]);

    expect($item->equals('IS_COMPLETE', 'Y'))->toBeTrue()
        ->and($item->equals('IS_DELETED', 'Y'))->toBeFalse()
        ->and($item->equals('MISSING', 'Y'))->toBeFalse();
});

it('reads text values with their keys', function (): void {
    expect(answer(['values' => ['1' => 'Low', 2 => 'Normal']])->strings('values'))->toBe([1 => 'Low', 2 => 'Normal'])
        ->and(fn (): array => answer(['values' => [['nested']]])->strings('values'))->toThrow(BitrixUnexpectedResponseException::class);
});
