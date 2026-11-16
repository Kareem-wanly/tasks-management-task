<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A request that is valid but breaks a business rule (completing a project
 * with open tasks, deleting a protected role...). Services throw it; it is
 * rendered as JSON in bootstrap/app.php with its status and body, so the
 * API answers exactly as the controllers used to.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors  Field errors, as a validation error would carry.
     * @param  array<string, mixed>  $extra  Further keys of the JSON body (e.g. 'success' => false).
     */
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly array $errors = [],
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return [...$this->extra, 'message' => $this->getMessage(), ...($this->errors ? ['errors' => $this->errors] : [])];
    }
}
