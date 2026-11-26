<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Exceptions\Bitrix\BitrixException;

/**
 * A decoded Bitrix24 answer, read with its types checked.
 *
 * Paths are dot separated ("result.task.id"; "" is the whole answer). Bitrix
 * sends numbers as JSON numbers or as numeric text ("15"), so both read as
 * int. A field that is missing, null or false (Bitrix's "empty") is "not
 * there": the *OrNull readers give null, the others fail. A field that is there with the wrong type always
 * fails, with BitrixUnexpectedResponseException naming the method and the
 * field (never the value: it could be a token).
 */
final readonly class BitrixPayload
{
    /** @param  array<mixed>  $data */
    private function __construct(private array $data, private string $source) {}

    /**
     * @param  array<mixed>  $data
     * @param  string  $source  The Bitrix method, for error messages.
     */
    public static function of(array $data, string $source): self
    {
        return new self($data, $source);
    }

    public function has(string $path): bool
    {
        return $this->value($path) !== null;
    }

    public function int(string $path): int
    {
        return $this->intOrNull($path) ?? throw $this->missing($path);
    }

    public function intOrNull(string $path): ?int
    {
        $value = $this->value($path);

        return match (true) {
            $value === null => null,
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw $this->wrongType($path, 'a whole number', $value),
        };
    }

    public function string(string $path): string
    {
        return $this->stringOrNull($path) ?? throw $this->missing($path);
    }

    public function stringOrNull(string $path): ?string
    {
        $value = $this->value($path);

        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => throw $this->wrongType($path, 'text', $value),
        };
    }

    /** A nested object or list; when it is not there, an empty one. */
    public function payload(string $path): self
    {
        $value = $this->value($path);

        if ($value !== null && ! is_array($value)) {
            throw $this->wrongType($path, 'an object', $value);
        }

        return new self($value ?? [], $this->source);
    }

    /**
     * Each element of a list or keyed object, as a payload (keys kept).
     * Missing or null is empty; an element that isn't an object fails.
     *
     * @return array<array-key, self>
     */
    public function payloads(string $path): array
    {
        $payloads = [];
        foreach ($this->payload($path)->data as $key => $value) {
            $payloads[$key] = $this->payload($this->join($path, (string) $key));
        }

        return $payloads;
    }

    /**
     * Rows (objects) of a list, as plain arrays for the code that maps them.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $path): array
    {
        return array_values(array_map(fn (self $row): array => $row->toRow(), $this->payloads($path)));
    }

    /**
     * Whole numbers of a list (IDs); missing or null is empty.
     *
     * @return list<int>
     */
    public function ints(string $path): array
    {
        $ints = [];
        foreach (array_keys($this->payload($path)->data) as $key) {
            $ints[] = $this->int($this->join($path, (string) $key));
        }

        return $ints;
    }

    /**
     * This object as an array with field names as keys, for the code that maps it.
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        $row = [];
        foreach ($this->data as $key => $value) {
            if (! is_string($key)) {
                throw BitrixException::unexpectedResponse($this->source, 'expected an object with named fields, got a list');
            }
            $row[$key] = $value;
        }

        return $row;
    }

    /** A batch answer: fail with the first command Bitrix refused, if any. */
    public function failOnBatchError(): void
    {
        foreach ($this->payload('result_error')->data as $error) {
            throw BitrixException::fromResponse(is_array($error) ? $error : null, 200);
        }
    }

    /**
     * A batch answer: one command's rows; null when Bitrix refused that command
     * or answered nothing for it.
     *
     * @return list<array<string, mixed>>|null
     */
    public function batchRows(string $key): ?array
    {
        if ($this->has("result_error.{$key}") || ! $this->has("result.{$key}")) {
            return null;
        }

        return $this->rows("result.{$key}");
    }

    /** @return array<mixed> The content as Bitrix sent it. */
    public function toArray(): array
    {
        return $this->data;
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    private function value(string $path): mixed
    {
        $value = $this->data;
        foreach ($path === '' ? [] : explode('.', $path) as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        // Bitrix sends an empty field (no deadline, no attached files) as false.
        return $value === false ? null : $value;
    }

    private function join(string $path, string $key): string
    {
        return $path === '' ? $key : "{$path}.{$key}";
    }

    private function missing(string $path): \App\Exceptions\Bitrix\BitrixUnexpectedResponseException
    {
        return BitrixException::unexpectedResponse($this->source, "{$this->describe($path)} is missing");
    }

    private function wrongType(string $path, string $expected, mixed $value): \App\Exceptions\Bitrix\BitrixUnexpectedResponseException
    {
        return BitrixException::unexpectedResponse($this->source, "{$this->describe($path)} should be {$expected}, got ".get_debug_type($value));
    }

    private function describe(string $path): string
    {
        return $path === '' ? 'the answer' : "\"{$path}\"";
    }
}
